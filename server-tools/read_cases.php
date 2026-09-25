<?php
/**
 * CLI only. Decrypts and lists stored intake cases.
 * Usage (on the server, outside public_html):
 *   php read_cases.php /path/to/public_html/api/config.php [REF]
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$cfgFile = $argv[1] ?? '';
if (!is_file($cfgFile)) {
    fwrite(STDERR, "Usage: php read_cases.php /path/to/api/config.php [REF]\n");
    exit(1);
}
$cfg = require $cfgFile;
$key = base64_decode((string) ($cfg['APP_KEY'] ?? ''), true);
if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
    fwrite(STDERR, "APP_KEY missing or invalid\n");
    exit(1);
}
$dir = rtrim($cfg['DATA_DIR'] ?? dirname(dirname($cfgFile), 2) . '/as_data', '/') . '/cases';
$filter = strtoupper($argv[2] ?? '');
foreach (glob($dir . '/*.enc') ?: [] as $f) {
    if ($filter !== '' && !str_contains($f, $filter)) {
        continue;
    }
    $raw = base64_decode((string) file_get_contents($f), true);
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
    if ($plain === false) {
        fwrite(STDERR, "Cannot decrypt " . basename($f) . "\n");
        continue;
    }
    $r = json_decode($plain, true);
    echo str_repeat('=', 60), "\n";
    foreach ($r as $k => $v) {
        echo str_pad($k, 15), ': ', str_replace("\n", "\n" . str_repeat(' ', 17), (string) $v), "\n";
    }
}
