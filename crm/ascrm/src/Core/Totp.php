<?php
declare(strict_types=1);

namespace DR\Core;

/** RFC 6238 TOTP (SHA1, 6 digits, 30 s) with replay protection. */
final class Totp
{
    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(): string
    {
        return self::b32encode(random_bytes(20));
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    public static function code(string $secret, int $step): string
    {
        $key = self::b32decode($secret);
        $bin = pack('N2', 0, $step);
        $h = hash_hmac('sha1', $bin, $key, true);
        $o = ord($h[19]) & 0x0F;
        $num = ((ord($h[$o]) & 0x7F) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
        return str_pad((string) ($num % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Verify within +/- 1 step; rejects a code already used by this user. */
    public static function verify(string $secret, string $code, int $userId): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return false;
        }
        $now = intdiv(time(), 30);
        for ($d = -1; $d <= 1; $d++) {
            $step = $now + $d;
            if (hash_equals(self::code($secret, $step), $code)) {
                $bucket = 'totp:' . $userId . ':' . $step;
                if (RateLimit::count($bucket, 120) > 0) {
                    return false;
                }
                RateLimit::hit($bucket);
                return true;
            }
        }
        return false;
    }

    public static function b32encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::B32[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function b32decode(string $s): string
    {
        $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s) ?? '');
        $bits = '';
        foreach (str_split($s) as $c) {
            $bits .= str_pad(decbin(strpos(self::B32, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }

    /** Ten one-time recovery codes: returns [plain list, hashed json]. */
    public static function recoveryCodes(): array
    {
        $plain = [];
        $hashed = [];
        for ($i = 0; $i < 10; $i++) {
            $c = strtoupper(bin2hex(random_bytes(5)));
            $c = substr($c, 0, 5) . '-' . substr($c, 5);
            $plain[] = $c;
            $hashed[] = password_hash($c, PASSWORD_DEFAULT);
        }
        return [$plain, json_encode($hashed)];
    }
}
