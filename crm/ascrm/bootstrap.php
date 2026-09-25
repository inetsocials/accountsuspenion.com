<?php
/**
 * AS Case Vault bootstrap.
 * This folder (ascrm/) must live OUTSIDE public_html. Only portal/ and api/contact.php are web-facing.
 */
declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('AS Case Vault requires PHP 8.1 or newer.');
}

define('DR_ROOT', __DIR__);
define('DR_STORAGE', DR_ROOT . '/storage');
define('DR_VERSION', '1.0.0');

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'DR\\', 3) !== 0) {
        return;
    }
    $file = DR_ROOT . '/src/' . str_replace('\\', '/', substr($class, 3)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require DR_ROOT . '/src/helpers.php';

$configFile = DR_ROOT . '/config/config.php';
$GLOBALS['DR_CONFIG'] = is_file($configFile) ? require $configFile : [];

date_default_timezone_set($GLOBALS['DR_CONFIG']['timezone'] ?? 'America/New_York');
mb_internal_encoding('UTF-8');

set_exception_handler(static function (Throwable $e): void {
    DR\Core\Log::error($e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $debug = (bool) (DR\Core\Config::get('debug', false));
    echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>'
        . '<body style="font-family:system-ui;padding:40px;max-width:640px;margin:auto">'
        . '<h1>Something went wrong</h1><p>The error has been logged. Please try again or go back.</p>'
        . ($debug ? '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES) . '</pre>' : '') . '</body>';
    exit;
});

set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    throw new ErrorException($str, 0, $no, $file, $line);
});
