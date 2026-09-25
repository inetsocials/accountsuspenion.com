<?php
declare(strict_types=1);

namespace DR\Core;

final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function str(string $key, int $max = 500): string
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? '';
        if (!is_string($v)) {
            return '';
        }
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
        return mb_substr(trim($v), 0, $max);
    }

    /** Multi-line text: keeps newlines. */
    public static function text(string $key, int $max = 20000): string
    {
        $v = $_POST[$key] ?? '';
        if (!is_string($v)) {
            return '';
        }
        $v = str_replace("\r\n", "\n", $v);
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
        return mb_substr(trim($v), 0, $max);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? null;
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function arr(string $key): array
    {
        $v = $_POST[$key] ?? [];
        return is_array($v) ? array_values(array_filter($v, 'is_string')) : [];
    }

    public static function oneOf(string $key, array $allowed, string $default = ''): string
    {
        $v = self::str($key, 60);
        return array_key_exists($v, $allowed) ? $v : $default;
    }

    public static function date(string $key): ?string
    {
        $v = self::str($key, 20);
        if ($v === '') {
            return null;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        return ($d && $d->format('Y-m-d') === $v) ? $v : null;
    }

    public static function datetime(string $key): ?string
    {
        $v = self::str($key, 25);
        if ($v === '') {
            return null;
        }
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d\TH:i:s'] as $f) {
            $d = \DateTime::createFromFormat($f, $v);
            if ($d) {
                return $d->format('Y-m-d H:i:s');
            }
        }
        return null;
    }

    public static function ip(): string
    {
        $trustCf = (bool) Config::get('trust_cloudflare', false);
        $ip = ($trustCf && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        return (bool) Config::get('trust_proxy_https', false) && (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    public static function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch');
    }
}
