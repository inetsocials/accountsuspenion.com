<?php
declare(strict_types=1);

use DR\Core\App;
use DR\Core\Session;

/** HTML-escape. */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Absolute path inside the portal, e.g. url('/cases/4'). */
function url(string $path = '/', array $query = []): string
{
    $base = App::basePath();
    $u = $base . '/' . ltrim($path, '/');
    if ($u === '') {
        $u = '/';
    }
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

function asset(string $path): string
{
    $file = App::publicDir() . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : DR_VERSION;
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

/** US display format MM/DD/YYYY (optionally with time). */
function fdate(?string $dt, bool $withTime = false): string
{
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return '';
    }
    return date($withTime ? 'm/d/Y H:i' : 'm/d/Y', $ts);
}

/** Relative time for activity feeds. */
function ago(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $d = time() - (int) strtotime($dt);
    if ($d < 60) {
        return 'just now';
    }
    if ($d < 3600) {
        $m = intdiv($d, 60);
        return $m . ' min ago';
    }
    if ($d < 86400) {
        $hr = intdiv($d, 3600);
        return $hr . ($hr === 1 ? ' hour ago' : ' hours ago');
    }
    if ($d < 86400 * 7) {
        $dy = intdiv($d, 86400);
        return $dy . ($dy === 1 ? ' day ago' : ' days ago');
    }
    return fdate($dt);
}

/** Money in minor units to display string. */
function money(int $minor, string $currency = 'USD'): string
{
    $sym = ['USD' => '$', 'CAD' => 'CA$', 'GBP' => "\u{00A3}", 'EUR' => "\u{20AC}"][$currency] ?? ($currency . ' ');
    $neg = $minor < 0;
    $v = number_format(abs($minor) / 100, 2, '.', ',');
    return ($neg ? '-' : '') . $sym . $v;
}

/** Parse "12.50" or "1,234" into minor units. */
function to_minor(string $s): int
{
    $s = preg_replace('/[^0-9.\-]/', '', $s) ?? '0';
    if ($s === '' || $s === '-' || $s === '.') {
        return 0;
    }
    return (int) round(((float) $s) * 100);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out !== '' ? $out : '?';
}

function str_limit(string $s, int $n): string
{
    return mb_strlen($s) > $n ? rtrim(mb_substr($s, 0, $n - 1)) . "\u{2026}" : $s;
}

function human_size(int $bytes): string
{
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $v = (float) $bytes;
    while ($v >= 1024 && $i < count($u) - 1) {
        $v /= 1024;
        $i++;
    }
    return ($i === 0 ? (string) $bytes : number_format($v, 1)) . ' ' . $u[$i];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(Session::csrf()) . '">';
}

function label(string $key): string
{
    return \DR\Core\Labels::get($key);
}

function selected(bool $cond): string
{
    return $cond ? ' selected' : '';
}

function checked(bool $cond): string
{
    return $cond ? ' checked' : '';
}

function icon(string $name, string $cls = 'ic'): string
{
    return '<svg class="' . h($cls) . '" aria-hidden="true" focusable="false"><use href="#i-' . h($name) . '"></use></svg>';
}

/** Previous input after a failed submission (one request only). */
function old(string $key, string $default = ''): string
{
    static $old = null;
    if ($old === null) {
        $old = (\DR\Core\Config::installed() && Session::row()) ? (Session::pull('_old', []) ?: []) : [];
    }
    $v = $old[$key] ?? $default;
    return is_string($v) ? $v : $default;
}

/** Options for a <select> from a label map. */
function options(array $map, ?string $current, bool $blank = false, string $blankLabel = 'Choose'): string
{
    $out = $blank ? '<option value="">' . h($blankLabel) . '</option>' : '';
    foreach ($map as $k => $v) {
        $out .= '<option value="' . h((string) $k) . '"' . selected((string) $current === (string) $k) . '>' . h((string) $v) . '</option>';
    }
    return $out;
}

/** Percentage class for CSP-safe width bars (w-0 .. w-100 in steps of 5). */
function wclass(float $pct): string
{
    $p = (int) (round(max(0, min(100, $pct)) / 5) * 5);
    return 'w-' . $p;
}

/** Page links for paginated lists. */
function pager(array $pg, string $path, array $query = []): string
{
    if ($pg['pages'] <= 1) {
        return '';
    }
    $out = '<nav class="pager" aria-label="Pagination">';
    if ($pg['page'] > 1) {
        $out .= '<a class="btn btn-ghost btn-sm" href="' . h(url($path, $query + ['page' => $pg['page'] - 1])) . '">Previous</a>';
    }
    $out .= '<span class="pager-info">Page ' . $pg['page'] . ' of ' . $pg['pages'] . ' (' . $pg['total'] . ')</span>';
    if ($pg['page'] < $pg['pages']) {
        $out .= '<a class="btn btn-ghost btn-sm" href="' . h(url($path, $query + ['page' => $pg['page'] + 1])) . '">Next</a>';
    }
    return $out . '</nav>';
}
