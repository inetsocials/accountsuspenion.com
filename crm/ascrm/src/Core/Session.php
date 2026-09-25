<?php
declare(strict_types=1);

namespace DR\Core;

/**
 * Database-backed sessions. The cookie holds a random 256-bit token; the database stores
 * only its SHA-256, so a database leak does not expose live sessions.
 */
final class Session
{
    private static ?array $row = null;
    private static bool $loaded = false;
    private static ?array $data = null;
    private static bool $dirty = false;

    public static function cookieName(): string
    {
        return (string) Config::get('cookie_name', 'ascv_sess');
    }

    public static function start(): ?array
    {
        if (self::$loaded) {
            return self::$row;
        }
        self::$loaded = true;
        if (!Config::installed()) {
            return null;
        }
        $token = $_COOKIE[self::cookieName()] ?? '';
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $row = Db::one('SELECT * FROM sessions WHERE id = ?', [hash('sha256', $token)]);
        if (!$row || (int) $row['revoked'] === 1 || strtotime($row['expires_at']) < time()) {
            self::clearCookie();
            return null;
        }
        $idle = max(5, Settings::int('session_idle_minutes')) * 60;
        if (strtotime($row['last_seen_at']) + $idle < time()) {
            Db::update('sessions', ['revoked' => 1], 'id = :id', ['id' => $row['id']]);
            self::clearCookie();
            return null;
        }
        if (strtotime($row['last_seen_at']) < time() - 60) {
            Db::update('sessions', ['last_seen_at' => now()], 'id = :id', ['id' => $row['id']]);
        }
        self::$row = $row;
        return $row;
    }

    public static function create(int $userId, bool $mfaPassed): void
    {
        self::destroy();
        $token = bin2hex(random_bytes(32));
        $hours = (int) Config::get('session_absolute_hours', 12);
        $row = [
            'id' => hash('sha256', $token),
            'user_id' => $userId,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'mfa_passed' => $mfaPassed ? 1 : 0,
            'sudo_until' => null,
            'data' => null,
            'csrf' => bin2hex(random_bytes(32)),
            'created_at' => now(),
            'last_seen_at' => now(),
            'expires_at' => date('Y-m-d H:i:s', time() + $hours * 3600),
            'revoked' => 0,
        ];
        Db::insert('sessions', $row);
        self::setCookie($token, time() + $hours * 3600);
        self::$row = $row;
        self::$loaded = true;
        self::$data = [];
    }

    public static function markMfaPassed(): void
    {
        if (!self::$row) {
            return;
        }
        // Rotate the token at privilege change to defeat session fixation.
        $userId = (int) self::$row['user_id'];
        $data = self::data();
        self::create($userId, true);
        self::$data = $data;
        self::$dirty = true;
        self::save();
    }

    public static function row(): ?array
    {
        return self::start();
    }

    public static function destroy(): void
    {
        $row = self::start();
        if ($row) {
            Db::update('sessions', ['revoked' => 1], 'id = :id', ['id' => $row['id']]);
        }
        self::$row = null;
        self::$data = null;
        self::clearCookie();
    }

    public static function revokeAllFor(int $userId, ?string $exceptId = null): void
    {
        if ($exceptId) {
            Db::run('UPDATE sessions SET revoked = 1 WHERE user_id = ? AND id <> ?', [$userId, $exceptId]);
        } else {
            Db::run('UPDATE sessions SET revoked = 1 WHERE user_id = ?', [$userId]);
        }
    }

    // ---------------------------------------------------------------- CSRF

    public static function csrf(): string
    {
        $row = self::start();
        if ($row) {
            return $row['csrf'];
        }
        $pre = $_COOKIE['ascv_pre'] ?? '';
        if (!is_string($pre) || !preg_match('/^[a-f0-9]{64}$/', $pre)) {
            $pre = bin2hex(random_bytes(32));
            if (!headers_sent()) {
                setcookie('ascv_pre', $pre, self::cookieOpts(0));
            }
            $_COOKIE['ascv_pre'] = $pre;
        }
        return $pre;
    }

    public static function verifyCsrf(): bool
    {
        $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || $sent === '') {
            return false;
        }
        return hash_equals(self::csrf(), $sent);
    }

    // ---------------------------------------------------------------- data (flash, sudo)

    public static function data(): array
    {
        if (self::$data === null) {
            $row = self::start();
            self::$data = $row && $row['data'] ? (json_decode($row['data'], true) ?: []) : [];
        }
        return self::$data;
    }

    public static function put(string $k, mixed $v): void
    {
        $d = self::data();
        $d[$k] = $v;
        self::$data = $d;
        self::$dirty = true;
    }

    public static function pull(string $k, mixed $default = null): mixed
    {
        $d = self::data();
        $v = $d[$k] ?? $default;
        if (array_key_exists($k, $d)) {
            unset($d[$k]);
            self::$data = $d;
            self::$dirty = true;
        }
        return $v;
    }

    public static function flash(string $type, string $msg): void
    {
        $f = self::data()['_flash'] ?? [];
        $f[] = [$type, $msg];
        self::put('_flash', $f);
    }

    public static function save(): void
    {
        if (self::$dirty && self::$row) {
            Db::update('sessions', ['data' => json_encode(self::$data)], 'id = :id', ['id' => self::$row['id']]);
            self::$dirty = false;
        }
    }

    public static function sudoActive(): bool
    {
        $row = self::start();
        return $row && $row['sudo_until'] && strtotime($row['sudo_until']) > time();
    }

    public static function grantSudo(int $minutes = 10): void
    {
        $row = self::start();
        if ($row) {
            $until = date('Y-m-d H:i:s', time() + $minutes * 60);
            Db::update('sessions', ['sudo_until' => $until], 'id = :id', ['id' => $row['id']]);
            self::$row['sudo_until'] = $until;
        }
    }

    // ---------------------------------------------------------------- cookies

    private static function cookieOpts(int $expires): array
    {
        return [
            'expires' => $expires,
            'path' => App::basePath() === '' ? '/' : App::basePath() . '/',
            'secure' => Request::isHttps() || (bool) Config::get('force_secure_cookies', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    private static function setCookie(string $token, int $expires): void
    {
        if (!headers_sent()) {
            setcookie(self::cookieName(), $token, self::cookieOpts($expires));
        }
        $_COOKIE[self::cookieName()] = $token;
    }

    private static function clearCookie(): void
    {
        if (!headers_sent()) {
            setcookie(self::cookieName(), '', self::cookieOpts(time() - 3600));
        }
        unset($_COOKIE[self::cookieName()]);
    }
}
