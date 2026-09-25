<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\App;
use DR\Core\Audit;
use DR\Core\Auth;
use DR\Core\Crypto;
use DR\Core\Db;
use DR\Core\HttpError;
use DR\Core\Mailer;
use DR\Core\Qr;
use DR\Core\RateLimit;
use DR\Core\Request;
use DR\Core\Session;
use DR\Core\Settings;
use DR\Core\Totp;

final class AuthController extends Controller
{
    private function safeNext(?string $next): ?string
    {
        if (!is_string($next) || $next === '') {
            return null;
        }
        $base = App::basePath();
        if (!str_starts_with($next, $base . '/') || str_contains($next, '//') || str_contains($next, '\\')
            || preg_match('#/(login|logout|invite|reset|sudo)#', substr($next, strlen($base)))) {
            return null;
        }
        return $next;
    }

    private function home(array $u): never
    {
        $next = $this->safeNext(Session::pull('next'));
        if ($next) {
            Session::save();
            header('Location: ' . $next, true, 303);
            exit;
        }
        App::redirect(Auth::isStaff($u) ? '/' : '/client');
    }

    // ---------------------------------------------------------------- step one: password

    public function loginForm(array $p, ?array $u): void
    {
        $this->view('auth/login', ['title' => 'Sign in', 'error' => null, 'email' => '', 'next' => Request::str('next', 300)], 'auth');
    }

    public function login(array $p, ?array $u): void
    {
        $email = Request::str('email', 190);
        [$ok, $msg] = Auth::attempt($email, (string) ($_POST['password'] ?? ''));
        if (!$ok) {
            http_response_code(422);
            $this->view('auth/login', ['title' => 'Sign in', 'error' => $msg, 'email' => $email, 'next' => Request::str('next', 300)], 'auth');
            return;
        }
        if ($n = $this->safeNext(Request::str('next', 300))) {
            Session::put('next', $n);
        }
        App::redirect('/login/verify');
    }

    // ---------------------------------------------------------------- step two: authenticator code

    public function verifyForm(array $p, ?array $pending): void
    {
        if ((int) $pending['totp_enabled'] !== 1) {
            App::redirect('/login/setup');
        }
        $this->view('auth/verify', ['title' => 'Two-step verification', 'error' => null, 'pending' => $pending], 'auth');
    }

    public function verify(array $p, ?array $pending): void
    {
        if ((int) $pending['totp_enabled'] !== 1) {
            App::redirect('/login/setup');
        }
        if (!RateLimit::attempt('mfa:' . $pending['id'], 10, 900)) {
            Audit::log('mfa_locked', 'user', (int) $pending['id'], [], $pending);
            Session::destroy();
            http_response_code(429);
            $this->view('auth/login', ['title' => 'Sign in', 'error' => 'Too many incorrect codes. Please sign in again in fifteen minutes.', 'email' => '', 'next' => ''], 'auth');
            return;
        }
        if (!Auth::verifySecondFactor($pending, Request::str('code', 20))) {
            Audit::log('mfa_failed', 'user', (int) $pending['id'], [], $pending);
            http_response_code(422);
            $this->view('auth/verify', ['title' => 'Two-step verification', 'error' => 'That code was not accepted. Codes change every 30 seconds and each can be used once.', 'pending' => $pending], 'auth');
            return;
        }
        RateLimit::clear('mfa:' . $pending['id']);
        Auth::completeMfa($pending);
        $remaining = count(json_decode((string) Db::value('SELECT recovery_hashes FROM users WHERE id = ?', [(int) $pending['id']]), true) ?: []);
        if ($remaining <= 3) {
            Session::flash('warn', 'You have ' . $remaining . ' recovery codes left. Generate a new set from your account page.');
        }
        $this->home($pending);
    }

    // ---------------------------------------------------------------- authenticator enrollment (compulsory for every role)

    public function setupForm(array $p, ?array $pending): void
    {
        if ((int) $pending['totp_enabled'] === 1) {
            App::redirect('/login/verify');
        }
        $sealed = Session::data()['totp_pending'] ?? null;
        $secret = $sealed ? (string) Crypto::fromSystem('totp', $sealed) : '';
        if ($secret === '') {
            $secret = Totp::newSecret();
            Session::put('totp_pending', Crypto::forSystem('totp', $secret));
        }
        $uri = Totp::uri($secret, $pending['email'], Settings::get('portal_name'));
        $this->view('auth/setup', [
            'title' => 'Set up two-step verification', 'wide' => true, 'error' => null, 'pending' => $pending,
            'qr' => Qr::svg($uri, 5), 'secret' => trim(chunk_split($secret, 4, ' ')), 'uri' => $uri,
        ], 'auth');
    }

    public function setup(array $p, ?array $pending): void
    {
        if ((int) $pending['totp_enabled'] === 1) {
            App::redirect('/login/verify');
        }
        $sealed = Session::data()['totp_pending'] ?? null;
        if (!$sealed) {
            App::redirect('/login/setup');
        }
        if (!RateLimit::attempt('mfa:' . $pending['id'], 10, 900)) {
            Session::destroy();
            throw new HttpError('Too many incorrect codes. Please sign in again in fifteen minutes.', 429);
        }
        $secret = (string) Crypto::fromSystem('totp', $sealed);
        if (!Totp::verify($secret, Request::str('code', 20), (int) $pending['id'])) {
            http_response_code(422);
            $uri = Totp::uri($secret, $pending['email'], Settings::get('portal_name'));
            $this->view('auth/setup', [
                'title' => 'Set up two-step verification', 'wide' => true, 'error' => 'That code did not match. Check the time on your phone is set automatically, then try the next code.',
                'pending' => $pending, 'qr' => Qr::svg($uri, 5), 'secret' => trim(chunk_split($secret, 4, ' ')), 'uri' => $uri,
            ], 'auth');
            return;
        }
        [$plain, $hashed] = Totp::recoveryCodes();
        Db::update('users', [
            'totp_secret_enc' => Crypto::forSystem('totp', $secret), 'totp_enabled' => 1, 'recovery_hashes' => $hashed, 'updated_at' => now(),
        ], 'id = :id', ['id' => (int) $pending['id']]);
        RateLimit::clear('mfa:' . $pending['id']);
        Audit::log('mfa_enrolled', 'user', (int) $pending['id'], [], $pending);
        Session::pull('totp_pending');
        Session::put('recovery_codes', $plain);
        $fresh = Db::one('SELECT * FROM users WHERE id = ?', [(int) $pending['id']]);
        Auth::completeMfa($fresh);
        App::redirect('/account/recovery-codes');
    }

    public function cancel(array $p, ?array $u): void
    {
        Session::destroy();
        App::redirect('/login');
    }

    public function logout(array $p, ?array $u): void
    {
        Auth::logout();
        App::redirect('/login');
    }

    // ---------------------------------------------------------------- invitations

    public function inviteForm(array $p, ?array $u): void
    {
        [$token, $user] = $this->inviteToken($p['token'] ?? '');
        $this->view('auth/invite', ['title' => 'Accept invitation', 'error' => null, 'invitee' => $user, 'token' => $p['token']], 'auth');
    }

    public function invite(array $p, ?array $u): void
    {
        [$token, $user] = $this->inviteToken($p['token'] ?? '');
        $pw = (string) ($_POST['password'] ?? '');
        $name = Request::str('name', 120) ?: $user['name'];
        $err = Auth::passwordProblem($pw, $user['email'], $name);
        if (!$err && !hash_equals($pw, (string) ($_POST['password2'] ?? ''))) {
            $err = 'The two passwords do not match.';
        }
        if (empty($_POST['accept'])) {
            $err = $err ?? 'Please confirm you accept the portal terms of use.';
        }
        if ($err) {
            http_response_code(422);
            $this->view('auth/invite', ['title' => 'Accept invitation', 'error' => $err, 'invitee' => $user, 'token' => $p['token']], 'auth');
            return;
        }
        Db::update('users', [
            'password_hash' => Auth::hashPassword($pw), 'status' => 'active', 'name' => $name, 'failed_logins' => 0,
            'locked_until' => null, 'updated_at' => now(),
        ], 'id = :id', ['id' => (int) $user['id']]);
        Auth::consumeToken((int) $token['id']);
        Audit::log('invite_accepted', 'user', (int) $user['id'], [], $user);
        Session::create((int) $user['id'], false);
        App::redirect('/login/setup');
    }

    private function inviteToken(string $raw): array
    {
        $t = Auth::findToken($raw, 'invite');
        $user = $t ? Db::one("SELECT * FROM users WHERE id = ? AND status = 'invited'", [(int) $t['user_id']]) : null;
        if (!$t || !$user) {
            throw new HttpError('This invitation link has expired or was already used. Ask your case lead to send a new one.', 404);
        }
        return [$t, $user];
    }

    // ---------------------------------------------------------------- password reset

    public function forgotForm(array $p, ?array $u): void
    {
        $this->view('auth/forgot', ['title' => 'Reset password', 'sent' => false], 'auth');
    }

    public function forgot(array $p, ?array $u): void
    {
        $email = mb_strtolower(Request::str('email', 190));
        if (RateLimit::attempt('forgot_ip:' . Request::ip(), 5, 3600) && RateLimit::attempt('forgot_email:' . $email, 3, 3600)) {
            $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? Db::one("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]) : null;
            if ($user) {
                $token = Auth::issueToken((int) $user['id'], 'reset', 60);
                Mailer::notice($user['email'], 'Reset your ' . Settings::get('portal_name') . ' password',
                    'A password reset was requested for your account. The link below works once and expires in one hour. Your authenticator app is still needed to sign in. If you did not ask for this, ignore this email.',
                    App::absoluteUrl('/reset/' . $token), 'Choose a new password');
                Audit::log('password_reset_requested', 'user', (int) $user['id'], [], $user);
            }
        }
        $this->view('auth/forgot', ['title' => 'Reset password', 'sent' => true], 'auth');
    }

    public function resetForm(array $p, ?array $u): void
    {
        $this->resetToken($p['token'] ?? '');
        $this->view('auth/reset', ['title' => 'Choose a new password', 'error' => null, 'token' => $p['token'], 'done' => false], 'auth');
    }

    public function reset(array $p, ?array $u): void
    {
        [$t, $user] = $this->resetToken($p['token'] ?? '');
        $pw = (string) ($_POST['password'] ?? '');
        $err = Auth::passwordProblem($pw, $user['email'], $user['name']);
        if (!$err && !hash_equals($pw, (string) ($_POST['password2'] ?? ''))) {
            $err = 'The two passwords do not match.';
        }
        if ($err) {
            http_response_code(422);
            $this->view('auth/reset', ['title' => 'Choose a new password', 'error' => $err, 'token' => $p['token'], 'done' => false], 'auth');
            return;
        }
        Db::update('users', ['password_hash' => Auth::hashPassword($pw), 'failed_logins' => 0, 'locked_until' => null, 'updated_at' => now()], 'id = :id', ['id' => (int) $user['id']]);
        Auth::consumeToken((int) $t['id']);
        Session::revokeAllFor((int) $user['id']);
        Audit::log('password_reset', 'user', (int) $user['id'], [], $user);
        Mailer::notice($user['email'], 'Your password was changed', 'The password for your ' . Settings::get('portal_name') . ' account was just changed and every signed-in device was signed out. If this was not you, start a case on our website immediately.');
        $this->view('auth/reset', ['title' => 'Password changed', 'error' => null, 'token' => '', 'done' => true], 'auth');
    }

    private function resetToken(string $raw): array
    {
        $t = Auth::findToken($raw, 'reset');
        $user = $t ? Db::one("SELECT * FROM users WHERE id = ? AND status = 'active'", [(int) $t['user_id']]) : null;
        if (!$t || !$user) {
            throw new HttpError('This reset link has expired or was already used. Request a new one.', 404);
        }
        return [$t, $user];
    }

    // ---------------------------------------------------------------- sudo (re-confirm identity for sensitive actions)

    public function sudoForm(array $p, ?array $u): void
    {
        $this->view('auth/sudo', ['title' => 'Confirm it is you', 'error' => null, 'next' => Request::str('next', 300)], 'auth');
    }

    public function sudo(array $p, ?array $u): void
    {
        $next = $this->safeNext(Request::str('next', 300));
        if (!RateLimit::attempt('sudo:' . $u['id'], 8, 900)) {
            throw new HttpError('Too many attempts. Please wait fifteen minutes.', 429);
        }
        $ok = password_verify((string) ($_POST['password'] ?? ''), (string) $u['password_hash'])
            && Auth::verifySecondFactor($u, Request::str('code', 20));
        if (!$ok) {
            Audit::log('sudo_failed', 'user', (int) $u['id'], [], $u);
            http_response_code(422);
            $this->view('auth/sudo', ['title' => 'Confirm it is you', 'error' => 'Password or code not accepted.', 'next' => $next ?? ''], 'auth');
            return;
        }
        Session::grantSudo(10);
        Audit::log('sudo_granted', 'user', (int) $u['id'], [], $u);
        if ($next) {
            Session::save();
            header('Location: ' . $next, true, 303);
            exit;
        }
        App::redirect(Auth::isStaff($u) ? '/' : '/client');
    }
}
