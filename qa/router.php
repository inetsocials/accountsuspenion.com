<?php
/**
 * PHP built-in server router that mirrors the Hostinger layout for local tests.
 *   php -S 127.0.0.1:8090 -t ENV/public_html qa/router.php
 * /portal/* -> portal/index.php, /api/contact.php and /cms.php run as PHP, everything else is static.
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$root = $_SERVER['DOCUMENT_ROOT'];
if ($uri === '/portal' || str_starts_with($uri, '/portal/')) {
    $file = $root . $uri;
    if ($uri !== '/portal/' && is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
    $_SERVER['SCRIPT_NAME'] = '/portal/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $root . '/portal/index.php';
    chdir($root . '/portal');
    require $root . '/portal/index.php';
    return true;
}
if ($uri === '/blog-sitemap.xml') {
    $_GET['sitemap'] = '1';
    $uri = '/cms.php';
}
if (str_starts_with($uri, '/_theme/')) {
    http_response_code(403);
    return true;
}
if ($uri === '/api/contact.php' || $uri === '/cms.php') {
    $_SERVER['SCRIPT_NAME'] = $uri;
    chdir(dirname($root . $uri));
    require $root . $uri;
    return true;
}
$path = $root . $uri;
if (is_dir($path) && is_file(rtrim($path, '/') . '/index.html')) {
    if (!str_ends_with($uri, '/')) {
        header('Location: ' . $uri . '/', true, 301);
        return true;
    }
    header('Content-Type: text/html; charset=utf-8');
    readfile(rtrim($path, '/') . '/index.html');
    return true;
}
if (is_file($path)) {
    return false;
}
// Unknown path: let the CMS handle redirects and posts, like .htaccess does in production.
if (is_file($root . '/cms.php')) {
    $_GET['path'] = $uri;
    $_SERVER['SCRIPT_NAME'] = '/cms.php';
    require $root . '/cms.php';
    return true;
}
http_response_code(404);
readfile($root . '/404.html');
return true;
