<?php
declare(strict_types=1);

namespace DR\Core;

/** File log for errors. Never logs request bodies or decrypted data. */
final class Log
{
    public static function error(\Throwable $e): void
    {
        self::write('ERROR', get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }

    public static function info(string $msg): void
    {
        self::write('INFO', $msg);
    }

    private static function write(string $level, string $msg): void
    {
        $dir = DR_STORAGE . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $line = '[' . date('c') . "] {$level} " . str_replace(["\r", "\n"], ' ', $msg) . PHP_EOL;
        @file_put_contents($dir . '/app-' . date('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
