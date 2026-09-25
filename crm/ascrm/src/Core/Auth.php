<?php
declare(strict_types=1);

namespace DR\Core;

final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    public const MAX_FAILS = 8;
    public const LOCK_MINUTES = 15;

    public static function hashPassword(string $plain): string
    {
        return defined('PASSWORD_ARGON2ID')
            ? password_hash($plain, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 1])
            : password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /** Returns an error message, or null when the password is acceptable. */
    public static function passwordProblem(string $pw, string $email = '', string $name = ''): ?string
    {
        if (mb_strlen($pw) < 12) {
            return 'Use at least 12 characters.';
        }
        if (mb_strlen($pw) > 200) {
            return 'Use at most 200 characters.';
        }
        $lower = mb_strtolower($pw);
        foreach (['password', '123456', 'qwerty', 'letmein', 'welcome', 'accountsuspension', 'suspension', 'admin'] as $bad) {
            if (str_contains($lower, $bad)) {
                return 'Avoid common words such as "' . $bad . '".';
            }
        }
        $local = mb_strtolower(strstr($email, '@', true) ?: '');
        if ($local !== '' && mb_strlen($local) >= 4 && str_contains($lower, $local)) {
            return 'Do not include your email address.';
        }
        if (count(array_unique(mb_str_split($pw))) < 6) {
            return 'Use a wider mix of characters.';
        }
        return null;
    }

    /**
     * Step one of login. Returns [ok, message]. On success a pre-MFA session exists.
     */
    public static function attempt(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        $ip = Request::ip();
        if (!RateLimit::attempt('login_ip:' . $ip, 30, 900) || !RateLimit::attempt('login_email:' . $email, 10, 900)) {
            return [false, 'Too many attempts. Please wait fifteen minutes and try again.'];
        }
        $u = Db::one('SELECT * FROM users WHERE email = ?', [$email]);
        $generic = 'Email or password not recognized.';
        if (!$u || !$u['password_hash']) {
            password_verify($password, self::hashPassword('timing-equaliser'));
            Audit::log('login_failed', 'user', null, ['reason' => 'unknown_email'], ['id' => null, 'role' => null]);
            return [false, $generic];
        }
        if ($u['locked_until'] && strtotime($u['locked_until']) > time()) {
            return [false, 'This account is temporarily locked after repeated failed attempts. Try again later.'];
        }
        if (!password_verify($password, $u['password_hash'])) {
            $fails = (int) $u['failed_logins'] + 1;
            $lock = $fails >= self::MAX_FAILS ? date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60) : null;
            Db::update('users', ['failed_logins' => $lock ? 0 : $fails, 'locked_until' => $lock, 'updated_at' => now()], 'id = :id', ['id' => (int) $u['id']]);
            Audit::log($lock ? 'account_locked' : 'login_failed', 'user', (int) $u['id'], [], $u);
            return [false, $generic];
        }
        if ($u['status'] !== 'active') {
            Audit::log('login_blocked', 'user', (int) $u['id'], ['status' => $u['status']], $u);
            return [false, $u['status'] === 'suspended' ? 'This account is suspended.' : 'This account is not yet activated. Use the link in your invitation.'];
        }
        if (password_needs_rehash($u['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
            Db::update('users', ['password_hash' => self::hashPassword($password)], 'id = :id', ['id' => (int) $u['id']]);
        }
        Db::update('users', ['failed_logins' => 0, 'locked_until' => null], 'id = :id', ['id' => (int) $u['id']]);
        Session::create((int) $u['id'], false);
        return [true, ''];
    }

    /** Session exists but MFA not yet passed. */
    public static function pendingUser(): ?array
    {
        $s = Session::start();
        if (!$s) {
            return null;
        }
        return Db::one('SELECT * FROM users WHERE id = ? AND status = ?', [(int) $s['user_id'], 'active']);
    }

    public static function completeMfa(array $user): void
    {
        Session::markMfaPassed();
        $ip = Request::ip();
        $devices = json_decode((string) ($user['known_devices'] ?? ''), true) ?: [];
        $fp = hash('sha256', Request::userAgent() . '|' . implode('.', array_slice(explode('.', $ip), 0, 2)));
        $newDevice = !in_array($fp, $devices, true);
        if ($newDevice) {
            $devices[] = $fp;
            $devices = array_slice($devices, -10);
        }
        Db::update('users', [
            'last_login_at' => now(), 'last_login_ip' => $ip, 'known_devices' => json_encode($devices), 'updated_at' => now(),
        ], 'id = :id', ['id' => (int) $user['id']]);
        Audit::log('login', 'user', (int) $user['id'], ['new_device' => $newDevice], $user);
        if ($newDevice && $user['last_login_at']) {
            Mailer::notice($user['email'], 'New sign-in to your ' . Settings::get('portal_name') . ' account',
                "A new sign-in to your account was completed on " . fdate(now(), true) . ".\n\nIf this was you, no action is needed. If it was not, sign in and change your password immediately, then start a case on our website.");
        }
        self::$resolved = false;
    }

    public static function userOrNull(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;
        $s = Session::start();
        if (!$s || (int) $s['mfa_passed'] !== 1) {
            return self::$user = null;
        }
        $u = Db::one('SELECT * FROM users WHERE id = ?', [(int) $s['user_id']]);
        if (!$u || $u['status'] !== 'active') {
            Session::destroy();
            return self::$user = null;
        }
        return self::$user = $u;
    }

    public static function isStaff(?array $u): bool
    {
        return $u !== null && in_array($u['role'], Labels::STAFF_ROLES, true);
    }

    public static function atLeast(?array $u, string $role): bool
    {
        if (!$u) {
            return false;
        }
        $rank = ['staff' => 1, 'lead' => 2, 'admin' => 3, 'master' => 4];
        return ($rank[$u['role']] ?? 0) >= ($rank[$role] ?? 99);
    }

    public static function logout(): void
    {
        $u = self::userOrNull();
        if ($u) {
            Audit::log('logout', 'user', (int) $u['id'], [], $u);
        }
        Session::destroy();
        self::$user = null;
        self::$resolved = true;
    }

    /** Verify a TOTP or a recovery code for a user. */
    public static function verifySecondFactor(array $u, string $code): bool
    {
        $code = trim($code);
        if ($u['totp_secret_enc'] && preg_match('/^\d{6}$/', preg_replace('/\s/', '', $code) ?? '')) {
            $secret = (string) Crypto::fromSystem('totp', $u['totp_secret_enc']);
            return Totp::verify($secret, $code, (int) $u['id']);
        }
        $hashes = json_decode((string) ($u['recovery_hashes'] ?? ''), true) ?: [];
        $norm = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        if (strlen($norm) !== 10) {
            return false;
        }
        $norm = substr($norm, 0, 5) . '-' . substr($norm, 5);
        foreach ($hashes as $i => $hsh) {
            if (password_verify($norm, $hsh)) {
                unset($hashes[$i]);
                Db::update('users', ['recovery_hashes' => json_encode(array_values($hashes))], 'id = :id', ['id' => (int) $u['id']]);
                Audit::log('recovery_code_used', 'user', (int) $u['id'], ['remaining' => count($hashes)], $u);
                return true;
            }
        }
        return false;
    }

    // ---------------------------------------------------------------- tokens (invites, resets, magic links)

    public static function issueToken(?int $userId, string $purpose, int $ttlMinutes, array $meta = []): string
    {
        $token = bin2hex(random_bytes(32));
        Db::insert('tokens', [
            'user_id' => $userId, 'purpose' => $purpose, 'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlMinutes * 60), 'used_at' => null,
            'created_at' => now(), 'meta' => $meta ? json_encode($meta) : null,
        ]);
        return $token;
    }

    public static function findToken(string $token, string $purpose): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $t = Db::one('SELECT * FROM tokens WHERE token_hash = ? AND purpose = ?', [hash('sha256', $token), $purpose]);
        if (!$t || $t['used_at'] || strtotime($t['expires_at']) < time()) {
            return null;
        }
        return $t;
    }

    public static function consumeToken(int $id): void
    {
        Db::update('tokens', ['used_at' => now()], 'id = :id', ['id' => $id]);
    }
}
