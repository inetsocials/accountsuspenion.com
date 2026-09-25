<?php
declare(strict_types=1);

namespace DR\Core;

final class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $cfg = $GLOBALS['DR_CONFIG'] ?? [];
        foreach (explode('.', $key) as $part) {
            if (!is_array($cfg) || !array_key_exists($part, $cfg)) {
                return $default;
            }
            $cfg = $cfg[$part];
        }
        return $cfg;
    }

    public static function installed(): bool
    {
        return is_file(DR_ROOT . '/config/config.php') && is_file(DR_ROOT . '/config/installed.lock');
    }
}
