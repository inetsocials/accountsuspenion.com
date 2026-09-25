<?php
declare(strict_types=1);

namespace DR\Service;

use DR\Core\Audit;
use DR\Core\Auth;
use DR\Core\Crypto;
use DR\Core\Db;
use DR\Core\Mailer;
use DR\Core\Numbers;
use DR\Core\Settings;
use DR\Core\App;
use DR\Core\Vault;

final class Clients
{
    /** Create a client with its own data key and a random 64-hex vault folder. */
    public static function create(string $displayName, string $type, bool $codename, string $risk, ?int $createdBy): array
    {
        [$dek, $wrapped] = Crypto::newClientKey();
        sodium_memzero($dek);
        do {
            $dir = Crypto::randomHex(32);
        } while ((int) Db::value('SELECT COUNT(*) FROM clients WHERE vault_dir = ?', [$dir]) > 0);
        $id = Db::insert('clients', [
            'number' => Numbers::clientNumber(), 'type' => $type, 'display_name' => mb_substr($displayName, 0, 160),
            'is_codename' => $codename ? 1 : 0, 'status' => 'active', 'vault_dir' => $dir, 'dek_wrapped' => $wrapped,
            'risk_level' => $risk, 'notes_enc' => null, 'screening' => null, 'created_by' => $createdBy,
            'created_at' => now(), 'updated_at' => now(), 'erased_at' => null,
        ]);
        $client = Db::one('SELECT * FROM clients WHERE id = ?', [$id]);
        Vault::clientDir($client);
        return $client;
    }

    /**
     * Invite (or re-invite) a portal user for a client. Returns the user row.
     * Existing active accounts are linked rather than duplicated.
     */
    public static function inviteUser(array $client, string $email, string $name, string $role, array $by): array
    {
        $email = mb_strtolower(trim($email));
        $existing = Db::one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($existing) {
            if ($role === 'adviser' && $existing['role'] === 'adviser') {
                if (!(int) Db::value('SELECT COUNT(*) FROM client_advisers WHERE client_id = ? AND user_id = ?', [(int) $client['id'], (int) $existing['id']])) {
                    Db::insert('client_advisers', ['client_id' => (int) $client['id'], 'user_id' => (int) $existing['id'], 'created_at' => now()]);
                }
                Audit::log('adviser_linked', 'client', (int) $client['id'], ['user_id' => (int) $existing['id']], $by);
                if ($existing['status'] === 'active') {
                    Notify::user((int) $existing['id'], 'access_granted', 'You have been given access to a new client file', '/client');
                }
                return $existing;
            }
            if ($existing['role'] === 'client' && (int) $existing['client_id'] === (int) $client['id']) {
                if ($existing['status'] === 'invited') {
                    self::sendInvite($existing);
                }
                return $existing;
            }
            throw new \DR\Core\HttpError('That email address already belongs to another account.', 422);
        }
        $uid = Db::insert('users', [
            'role' => $role, 'email' => $email, 'name' => mb_substr($name !== '' ? $name : $client['display_name'], 0, 120),
            'password_hash' => null, 'status' => 'invited', 'client_id' => $role === 'client' ? (int) $client['id'] : null,
            'totp_secret_enc' => null, 'totp_enabled' => 0, 'recovery_hashes' => null, 'failed_logins' => 0,
            'locked_until' => null, 'last_login_at' => null, 'last_login_ip' => null, 'known_devices' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($role === 'adviser') {
            Db::insert('client_advisers', ['client_id' => (int) $client['id'], 'user_id' => $uid, 'created_at' => now()]);
        }
        $user = Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
        self::sendInvite($user);
        Audit::log('user_invited', 'user', $uid, ['role' => $role, 'client_id' => (int) $client['id']], $by);
        return $user;
    }

    public static function sendInvite(array $user): void
    {
        Db::run("UPDATE tokens SET used_at = ? WHERE user_id = ? AND purpose = 'invite' AND used_at IS NULL", [now(), (int) $user['id']]);
        $token = Auth::issueToken((int) $user['id'], 'invite', 60 * 24 * 7);
        $staff = in_array($user['role'], ['master', 'admin', 'lead', 'staff'], true);
        Mailer::notice(
            $user['email'],
            'Your invitation to ' . Settings::get('portal_name'),
            $staff
                ? 'You have been invited to the ' . Settings::get('org_name') . ' case workspace. The link below is valid for seven days. You will set a password and connect an authenticator app.'
                : 'A secure client portal has been opened for you by ' . Settings::get('org_name') . '. The link below is valid for seven days. You will set a password and connect an authenticator app, then you can message your case team and share documents in an encrypted vault.',
            App::absoluteUrl('/invite/' . $token),
            'Accept the invitation'
        );
    }

    /**
     * Right to erasure: crypto-shred the vault and wipe client content.
     * Invoices and payment records are kept (statutory accounting retention) but de-identified.
     */
    public static function erase(array $client, array $by): array
    {
        $cid = (int) $client['id'];
        $files = Vault::shredClient($client);
        $caseIds = array_map('intval', array_column(Db::all('SELECT id FROM cases WHERE client_id = ?', [$cid]), 'id'));
        Db::tx(function () use ($cid, $caseIds, $client): void {
            if ($caseIds) {
                $in = implode(',', $caseIds);
                Db::run("DELETE FROM messages WHERE case_id IN ($in)");
                Db::run("DELETE FROM case_reads WHERE case_id IN ($in)");
                Db::run("DELETE FROM targets WHERE case_id IN ($in)");
                Db::run("DELETE FROM funds WHERE case_id IN ($in)");
                Db::run("DELETE FROM nda_signatures WHERE case_id IN ($in)");
                Db::run("DELETE FROM tasks WHERE case_id IN ($in)");
                Db::run("DELETE FROM deadlines WHERE case_id IN ($in)");
                Db::run("UPDATE cases SET title = 'Erased matter', intake_enc = NULL, intake_email_hash = NULL, decline_reason = NULL, services = NULL, status = 'closed', closed_at = COALESCE(closed_at, ?) WHERE id IN ($in)", [now()]);
            }
            Db::run('DELETE FROM documents WHERE client_id = ?', [$cid]);
            Db::run('DELETE FROM uploads WHERE client_id = ?', [$cid]);
            Db::run('DELETE FROM folders WHERE client_id = ?', [$cid]);
            Db::run('DELETE FROM contacts WHERE client_id = ?', [$cid]);
            foreach (Db::all("SELECT id FROM users WHERE role = 'client' AND client_id = ?", [$cid]) as $u) {
                Db::update('users', [
                    'email' => 'erased-' . $u['id'] . '-' . bin2hex(random_bytes(4)) . '.invalid', 'name' => 'Erased user', 'status' => 'suspended',
                    'password_hash' => null, 'totp_secret_enc' => null, 'totp_enabled' => 0, 'recovery_hashes' => null, 'known_devices' => null,
                    'last_login_ip' => null, 'updated_at' => now(),
                ], 'id = :id', ['id' => (int) $u['id']]);
                Db::run('UPDATE sessions SET revoked = 1 WHERE user_id = ?', [(int) $u['id']]);
            }
            Db::run('DELETE FROM client_advisers WHERE client_id = ?', [$cid]);
            Db::update('clients', [
                'display_name' => 'Erased client ' . $client['number'], 'is_codename' => 1, 'status' => 'erased', 'dek_wrapped' => null,
                'notes_enc' => null, 'screening' => null, 'erased_at' => now(), 'updated_at' => now(),
            ], 'id = :id', ['id' => $cid]);
        });
        Audit::log('client_erased', 'client', $cid, ['files_shredded' => $files, 'cases' => count($caseIds)], $by);
        return ['files' => $files, 'cases' => count($caseIds)];
    }
}
