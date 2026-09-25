<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\App;
use DR\Core\Audit;
use DR\Core\Auth;
use DR\Core\Db;
use DR\Core\Mailer;
use DR\Core\Request;
use DR\Core\Session;
use DR\Core\Settings;
use DR\Core\Totp;

final class AccountController extends Controller
{
    private function layout(array $u): string
    {
        return Auth::isStaff($u) ? 'app' : 'portal';
    }

    public function show(array $p, ?array $u): void
    {
        $sessions = Db::all('SELECT id, ip, user_agent, created_at, last_seen_at FROM sessions WHERE user_id = ? AND revoked = 0 AND expires_at > ? ORDER BY last_seen_at DESC', [(int) $u['id'], now()]);
        $codes = count(json_decode((string) $u['recovery_hashes'], true) ?: []);
        $this->view('shared/account', ['title' => 'My account', 'sessions' => $sessions, 'current' => Session::row()['id'] ?? '', 'codesLeft' => $codes], $this->layout($u));
    }

    public function profile(array $p, ?array $u): void
    {
        $name = Request::str('name', 120);
        if ($name === '') {
            $this->fail('Enter your name.', '/account');
        }
        Db::update('users', ['name' => $name, 'updated_at' => now()], 'id = :id', ['id' => (int) $u['id']]);
        $this->flash('ok', 'Profile updated.');
        App::redirect('/account');
    }

    public function password(array $p, ?array $u): void
    {
        if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $u['password_hash'])) {
            Audit::log('password_change_failed', 'user', (int) $u['id'], [], $u);
            $this->fail('Your current password was not accepted.', '/account');
        }
        $pw = (string) ($_POST['password'] ?? '');
        if ($err = Auth::passwordProblem($pw, $u['email'], $u['name'])) {
            $this->fail($err, '/account');
        }
        if (!hash_equals($pw, (string) ($_POST['password2'] ?? ''))) {
            $this->fail('The two new passwords do not match.', '/account');
        }
        Db::update('users', ['password_hash' => Auth::hashPassword($pw), 'updated_at' => now()], 'id = :id', ['id' => (int) $u['id']]);
        Session::revokeAllFor((int) $u['id'], Session::row()['id'] ?? null);
        Audit::log('password_changed', 'user', (int) $u['id'], [], $u);
        Mailer::notice($u['email'], 'Your password was changed', 'The password for your ' . Settings::get('portal_name') . ' account was changed and other devices were signed out. If this was not you, start a case on our website immediately.');
        $this->flash('ok', 'Password changed. Other devices were signed out.');
        App::redirect('/account');
    }

    public function regenerateCodes(array $p, ?array $u): void
    {
        $this->requireSudo(url('/account'));
        [$plain, $hashed] = Totp::recoveryCodes();
        Db::update('users', ['recovery_hashes' => $hashed, 'updated_at' => now()], 'id = :id', ['id' => (int) $u['id']]);
        Audit::log('recovery_codes_regenerated', 'user', (int) $u['id'], [], $u);
        Session::put('recovery_codes', $plain);
        App::redirect('/account/recovery-codes');
    }

    public function showCodes(array $p, ?array $u): void
    {
        $codes = Session::pull('recovery_codes');
        if (!is_array($codes) || !$codes) {
            App::redirect('/account');
        }
        $this->view('shared/recovery_codes', ['title' => 'Recovery codes', 'codes' => $codes], $this->layout($u));
    }

    public function revokeSessions(array $p, ?array $u): void
    {
        Session::revokeAllFor((int) $u['id'], Session::row()['id'] ?? null);
        Audit::log('sessions_revoked', 'user', (int) $u['id'], ['scope' => 'others'], $u);
        $this->flash('ok', 'All other devices were signed out.');
        App::redirect('/account');
    }

    public function resetOwn2fa(array $p, ?array $u): void
    {
        $this->requireSudo(url('/account'));
        Db::update('users', ['totp_secret_enc' => null, 'totp_enabled' => 0, 'recovery_hashes' => null, 'updated_at' => now()], 'id = :id', ['id' => (int) $u['id']]);
        Audit::log('mfa_reset', 'user', (int) $u['id'], ['by' => 'self'], $u);
        Session::revokeAllFor((int) $u['id']);
        Session::destroy();
        App::redirect('/login');
    }
}
