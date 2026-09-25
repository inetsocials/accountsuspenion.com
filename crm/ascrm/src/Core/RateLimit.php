<?php
declare(strict_types=1);

namespace DR\Core;

/** Sliding-window counters stored in the database. */
final class RateLimit
{
    public static function hit(string $bucket): void
    {
        Db::insert('rate_limits', ['bucket' => mb_substr($bucket, 0, 120), 'hit_at' => time()]);
    }

    public static function count(string $bucket, int $windowSeconds): int
    {
        return (int) Db::value(
            'SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND hit_at > ?',
            [mb_substr($bucket, 0, 120), time() - $windowSeconds]
        );
    }

    /** Returns true if the action is allowed (and records the hit). */
    public static function attempt(string $bucket, int $max, int $windowSeconds): bool
    {
        if (self::count($bucket, $windowSeconds) >= $max) {
            return false;
        }
        self::hit($bucket);
        return true;
    }

    public static function clear(string $bucket): void
    {
        Db::run('DELETE FROM rate_limits WHERE bucket = ?', [mb_substr($bucket, 0, 120)]);
    }

    public static function prune(): void
    {
        Db::run('DELETE FROM rate_limits WHERE hit_at < ?', [time() - 86400]);
    }
}
