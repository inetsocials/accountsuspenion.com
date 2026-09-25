<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\App;
use DR\Core\Db;
use DR\Core\HttpError;
use DR\Core\Request;
use DR\Core\Session;
use DR\Core\View;

abstract class Controller
{
    protected function view(string $template, array $data = [], string $layout = 'app'): void
    {
        View::render($template, $data, $layout);
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    protected function id(array $params, string $key = 'id'): int
    {
        $v = $params[$key] ?? '';
        if (!ctype_digit((string) $v) || (int) $v < 1) {
            throw new HttpError('Not found.', 404);
        }
        return (int) $v;
    }

    /** Sensitive actions need a fresh password + code confirmation within the last ten minutes. */
    protected function requireSudo(string $return): void
    {
        if (!Session::sudoActive()) {
            if (Request::wantsJson()) {
                throw new HttpError('Please confirm your identity again.', 401);
            }
            App::redirect('/sudo', ['next' => $return]);
        }
    }

    /**
     * Simple page-number pagination.
     * @return array{page:int,per:int,offset:int,total:int,pages:int}
     */
    protected function paginate(string $countSql, array $params, int $per = 25): array
    {
        $total = (int) Db::value($countSql, $params);
        $pages = max(1, (int) ceil($total / $per));
        $page = min($pages, max(1, Request::int('page', 1)));
        return ['page' => $page, 'per' => $per, 'offset' => ($page - 1) * $per, 'total' => $total, 'pages' => $pages];
    }

    /** Validation failure: flash, keep input for the next render, go back. */
    protected function fail(string $message, string $fallback): never
    {
        if (Request::wantsJson()) {
            App::json(['ok' => false, 'error' => $message], 422);
        }
        Session::flash('error', $message);
        Session::put('_old', array_filter($_POST, static fn($k) => !in_array($k, ['_csrf', 'password', 'password2', 'current_password', 'code'], true), ARRAY_FILTER_USE_KEY));
        App::back($fallback);
    }
}
