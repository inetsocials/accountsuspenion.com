<?php
/**
 * AS Case Vault front controller (the only PHP file served from public_html/portal).
 * The application itself lives outside the web root, by default in the folder "ascrm"
 * next to public_html. To use another location, create ascrm-path.php here returning its path.
 */
declare(strict_types=1);

$root = null;
if (is_file(__DIR__ . '/ascrm-path.php')) {
    $root = require __DIR__ . '/ascrm-path.php';
}
if (!is_string($root) || !is_file($root . '/bootstrap.php')) {
    $root = dirname(__DIR__, 2) . '/ascrm';
}
if (!is_file($root . '/bootstrap.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("AS Case Vault: application folder not found. Upload the ascrm folder next to public_html, or set its path in portal/ascrm-path.php.\n");
}
require $root . '/bootstrap.php';
DR\Core\App::run(__DIR__);
