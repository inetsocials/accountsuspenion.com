<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\App;
use DR\Core\Audit;
use DR\Core\Db;
use DR\Core\HttpError;
use DR\Core\Labels;
use DR\Core\Request;
use DR\Core\Session;
use DR\Service\Clients;

/** Staff user administration. Clients and advisers are managed from their client file. */
final class UserController extends Controller
{
    /** Roles the acting user may assign. */
    private function assignable(array $u): array
    {
        return $u['role'] === 'master' ? ['master', 'admin', 'lead', 'staff'] : ['lead', 'staff'];
    }

    private function editable(array $actor, array $target): bool
    {
        if ((int) $actor['id'] === (int) $target['id']) {
            return false;
        }
        if ($actor['role'] === 'master') {
            return true;
        }
        return in_array($target['role'], ['lead', 'staff', 'client', 'adviser'], true);
    }

    public function index(array $p, ?array $u): void
    {
        $role = Request::oneOf('role', Labels::ROLES, '');
        $status = Request::oneOf('status', ['active' => 1, 'invited' => 1, 'suspended' => 1], '');
        $q = Request::str('q', 80);
        $where = ['1=1'];
        $params = [];
        if ($role) {
            $where[] = 'u.role = :r';
            $params['r'] = $role;
        } else {
            $where[] = "u.role IN ('master','admin','lead','staff')";
        }
        if ($status) {
            $where[] = 'u.status = :s';
            $params['s'] = $status;
        }
        if ($q !== '') {
            $where[] = '(u.name LIKE :q1 OR u.email LIKE :q2)';
            $params += ['q1' => "%$q%", 'q2' => "%$q%"];
        }
        $w = implode(' AND ', $where);
        $pg = $this->paginate("SELECT COUNT(*) FROM users u WHERE $w", $params, 40);
        $rows = Db::all("SELECT u.*, cl.display_name AS client_name FROM users u LEFT JOIN clients cl ON cl.id = u.client_id WHERE $w ORDER BY u.status, u.role, u.name LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
        $this->view('admin/users', ['title' => 'Users and roles', 'rows' => $rows, 'pg' => $pg, 'assignable' => $this->assignable($u), 'filters' => compact('role', 'status', 'q')], 'app');
    }

    public function create(array $p, ?array $u): void
    {
        $email = mb_strtolower(Request::str('email', 190));
        $name = Request::str('name', 120);
        $role = Request::str('role', 20);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || !in_array($role, $this->assignable($u), true)) {
            $this->fail('Enter a name, a valid email and a role you are allowed to assign.', '/admin/users');
        }
        if (Db::value('SELECT id FROM users WHERE email = ?', [$email])) {
            $this->fail('That email address already has an account.', '/admin/users');
        }
        $id = Db::insert('users', [
            'role' => $role, 'email' => $email, 'name' => $name, 'password_hash' => null, 'status' => 'invited', 'client_id' => null,
            'totp_secret_enc' => null, 'totp_enabled' => 0, 'recovery_hashes' => null, 'failed_logins' => 0, 'locked_until' => null,
            'last_login_at' => null, 'last_login_ip' => null, 'known_devices' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Clients::sendInvite(Db::one('SELECT * FROM users WHERE id = ?', [$id]));
        Audit::log('user_invited', 'user', $id, ['role' => $role], $u);
        $this->flash('ok', $name . ' was invited as ' . Labels::ROLES[$role] . '.');
        App::redirect('/admin/users');
    }

    private function load(array $p, array $u): array
    {
        $t = Db::one('SELECT * FROM users WHERE id = ?', [$this->id($p)]);
        if (!$t) {
            throw new HttpError('User not found.', 404);
        }
        return $t;
    }

    public function show(array $p, ?array $u): void
    {
        $t = $this->load($p, $u);
        $this->view('admin/user', [
            'title' => $t['name'], 't' => $t, 'editable' => $this->editable($u, $t), 'assignable' => $this->assignable($u),
            'sessions' => Db::all('SELECT ip, user_agent, created_at, last_seen_at FROM sessions WHERE user_id = ? AND revoked = 0 AND expires_at > ? ORDER BY last_seen_at DESC', [(int) $t['id'], now()]),
            'audit' => Db::all('SELECT * FROM audit_log WHERE user_id = ? ORDER BY id DESC LIMIT 40', [(int) $t['id']]),
            'client' => $t['client_id'] ? Db::one('SELECT id, number, display_name FROM clients WHERE id = ?', [(int) $t['client_id']]) : null,
            'advises' => $t['role'] === 'adviser' ? Db::all('SELECT cl.id, cl.number, cl.display_name FROM client_advisers ca JOIN clients cl ON cl.id = ca.client_id WHERE ca.user_id = ?', [(int) $t['id']]) : [],
        ], 'app');
    }

    public function update(array $p, ?array $u): void
    {
        $t = $this->load($p, $u);
        if (!$this->editable($u, $t)) {
            throw new HttpError('You cannot change this account.', 403);
        }
        $upd = ['name' => Request::str('name', 120) ?: $t['name'], 'updated_at' => now()];
        if (in_array($t['role'], ['master', 'admin', 'lead', 'staff'], true)) {
            $role = Request::str('role', 20);
            if (in_array($role, $this->assignable($u), true)) {
                $upd['role'] = $role;
            }
        }
        $status = Request::oneOf('status', ['active' => 1, 'suspended' => 1, 'invited' => 1], $t['status']);
        if ($status === 'active' && !$t['password_hash']) {
            $status = 'invited';
        }
        $upd['status'] = $status;
        if (!empty($_POST['unlock'])) {
            $upd['locked_until'] = null;
            $upd['failed_logins'] = 0;
        }
        if ($t['role'] === 'master' && (($upd['role'] ?? 'master') !== 'master' || $status !== 'active')) {
            $masters = (int) Db::value("SELECT COUNT(*) FROM users WHERE role = 'master' AND status = 'active' AND id <> ?", [(int) $t['id']]);
            if ($masters < 1) {
                $this->fail('There must always be at least one active master admin.', '/admin/users/' . $t['id']);
            }
        }
        Db::update('users', $upd, 'id = :id', ['id' => (int) $t['id']]);
        if ($status === 'suspended' || (isset($upd['role']) && $upd['role'] !== $t['role'])) {
            Session::revokeAllFor((int) $t['id']);
        }
        Audit::log('user_updated', 'user', (int) $t['id'], array_intersect_key($upd, ['role' => 1, 'status' => 1]), $u);
        $this->flash('ok', 'Account updated.');
        App::redirect('/admin/users/' . $t['id']);
    }

    public function reset2fa(array $p, ?array $u): void
    {
        $t = $this->load($p, $u);
        if (!$this->editable($u, $t)) {
            throw new HttpError('You cannot change this account.', 403);
        }
        $this->requireSudo(url('/admin/users/' . $t['id']));
        Db::update('users', ['totp_secret_enc' => null, 'totp_enabled' => 0, 'recovery_hashes' => null, 'updated_at' => now()], 'id = :id', ['id' => (int) $t['id']]);
        Session::revokeAllFor((int) $t['id']);
        Audit::log('mfa_reset', 'user', (int) $t['id'], ['by' => (int) $u['id']], $u);
        $this->flash('ok', 'Two-step verification reset. The user will set up a new authenticator at next sign-in. Verify their identity in writing before telling them.');
        App::redirect('/admin/users/' . $t['id']);
    }

    public function resend(array $p, ?array $u): void
    {
        $t = $this->load($p, $u);
        if ($t['status'] !== 'invited' || !$this->editable($u, $t)) {
            throw new HttpError('This account is not awaiting an invitation.', 422);
        }
        Clients::sendInvite($t);
        Audit::log('invite_resent', 'user', (int) $t['id'], [], $u);
        $this->flash('ok', 'Invitation re-sent.');
        App::redirect('/admin/users/' . $t['id']);
    }

    public function revokeSessions(array $p, ?array $u): void
    {
        $t = $this->load($p, $u);
        if (!$this->editable($u, $t)) {
            throw new HttpError('You cannot change this account.', 403);
        }
        Session::revokeAllFor((int) $t['id']);
        Audit::log('sessions_revoked', 'user', (int) $t['id'], ['by' => (int) $u['id']], $u);
        $this->flash('ok', 'All sessions for this user were signed out.');
        App::redirect('/admin/users/' . $t['id']);
    }
}
