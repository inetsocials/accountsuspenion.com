<?php
declare(strict_types=1);

namespace DR\Core;

/** Front controller: routing, guards, CSRF, security headers. */
final class App
{
    private static string $publicDir = '';
    private static ?string $base = null;

    /** @var array<int,array{0:string,1:string,2:string,3:string}> */
    private static array $routes = [];

    public static function publicDir(): string
    {
        return self::$publicDir !== '' ? self::$publicDir : (string) Config::get('public_dir', '');
    }

    public static function basePath(): string
    {
        if (self::$base === null) {
            $cfg = Config::get('base_path');
            if (is_string($cfg)) {
                self::$base = rtrim($cfg, '/');
            } else {
                $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
                self::$base = rtrim($dir === '.' ? '' : $dir, '/');
            }
        }
        return self::$base;
    }

    public static function absoluteUrl(string $path, array $query = []): string
    {
        $origin = rtrim((string) Config::get('app_url', ''), '/');
        if ($origin === '') {
            $scheme = Request::isHttps() ? 'https' : 'http';
            $origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        return $origin . url($path, $query);
    }

    public static function route(string $method, string $pattern, string $handler, string $guard): void
    {
        self::$routes[] = [$method, $pattern, $handler, $guard];
    }

    public static function run(string $publicDir): void
    {
        self::$publicDir = $publicDir;
        self::securityHeaders();
        require DR_ROOT . '/src/routes.php';

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = self::basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim($path, '/');

        try {
            if (!Config::installed()) {
                if (!str_starts_with($path, '/install')) {
                    self::redirect('/install');
                }
            } elseif (str_starts_with($path, '/install')) {
                throw new HttpError('Page not found', 404);
            }
            self::dispatch(Request::method(), $path);
        } catch (HttpError $e) {
            self::errorPage($e->getCode() ?: 500, $e->getMessage());
        }
        Session::save();
    }

    private static function dispatch(string $method, string $path): void
    {
        $allowed = false;
        foreach (self::$routes as [$m, $pattern, $handler, $guard]) {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[A-Za-z0-9_-]+)', $pattern) . '$#';
            if (!preg_match($regex, $path, $mt)) {
                continue;
            }
            $allowed = true;
            if ($m !== $method && !($m === 'GET' && $method === 'HEAD')) {
                continue;
            }
            $params = array_filter($mt, 'is_string', ARRAY_FILTER_USE_KEY);
            if ($method === 'POST' && !Session::verifyCsrf()) {
                throw new HttpError('Your session expired or the form was already submitted. Please go back, refresh and try again.', 419);
            }
            $user = self::guard($guard);
            if (Config::installed() && Settings::get('maintenance') === '1' && $user && $user['role'] !== 'master'
                && !in_array($guard, ['guest', 'pending', 'public'], true)) {
                throw new HttpError('The portal is in maintenance mode. Please try again shortly.', 503);
            }
            [$class, $fn] = explode('@', $handler);
            $fq = 'DR\\Http\\' . $class;
            (new $fq())->$fn($params, $user);
            return;
        }
        throw new HttpError($allowed ? 'Method not allowed' : 'Page not found', $allowed ? 405 : 404);
    }

    private static function guard(string $guard): ?array
    {
        switch ($guard) {
            case 'public':
                return Auth::userOrNull();
            case 'guest':
                $u = Auth::userOrNull();
                if ($u) {
                    self::redirect(Auth::isStaff($u) ? '/' : '/client');
                }
                return null;
            case 'pending':
                $p = Auth::pendingUser();
                if (!$p) {
                    self::redirect('/login');
                }
                if ((int) (Session::row()['mfa_passed'] ?? 0) === 1) {
                    self::redirect(Auth::isStaff($p) ? '/' : '/client');
                }
                return $p;
        }
        $u = Auth::userOrNull();
        if (!$u) {
            if (Auth::pendingUser()) {
                self::redirect('/login/verify');
            }
            if (Request::wantsJson()) {
                throw new HttpError('Please sign in again.', 401);
            }
            self::redirect('/login', ['next' => $_SERVER['REQUEST_URI'] ?? '']);
        }
        $ok = match ($guard) {
            'auth' => true,
            'client' => in_array($u['role'], ['client', 'adviser'], true),
            'staff' => Auth::isStaff($u),
            'lead' => Auth::atLeast($u, 'lead'),
            'admin' => Auth::atLeast($u, 'admin'),
            'master' => $u['role'] === 'master',
            default => false,
        };
        if (!$ok) {
            Audit::log('access_denied', 'route', null, ['path' => mb_substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 200)], $u);
            throw new HttpError('You do not have access to this page.', 403);
        }
        return $u;
    }

    private static function securityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
        header('Cache-Control: no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
        if (Request::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function redirect(string $path, array $query = []): never
    {
        Session::save();
        header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path, $query)), true, 303);
        exit;
    }

    public static function back(string $fallback = '/'): never
    {
        $ref = $_POST['_back'] ?? '';
        if (is_string($ref) && str_starts_with($ref, self::basePath() . '/') && !str_contains($ref, '//')) {
            Session::save();
            header('Location: ' . $ref, true, 303);
            exit;
        }
        self::redirect($fallback);
    }

    public static function json(array $data, int $code = 200): never
    {
        Session::save();
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function errorPage(int $code, string $message): void
    {
        if (!in_array($code, [400, 401, 403, 404, 405, 413, 419, 422, 429, 503], true)) {
            $code = 500;
        }
        http_response_code($code);
        if (Request::wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $message]);
            return;
        }
        View::render('errors/error', ['code' => $code, 'message' => $message], 'auth');
    }
}
