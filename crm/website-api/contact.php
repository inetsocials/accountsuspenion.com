<?php
/**
 * AccountSuspension.com case intake (Hostinger, PHP 8.1+).
 * Five-step website form: platform, issue, appeal history, urgency, details.
 *
 * Every request becomes an encrypted lead in AS Case Vault:
 *  - the full submission is sealed with libsodium before it touches the database
 *  - the email is stored only as a keyed hash so the enquirer can check status later
 *  - the team receives a content-free alert and reviews the lead inside the portal
 * No phone numbers, passwords or one-time codes are collected.
 */
declare(strict_types=1);

require __DIR__ . '/config.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function respond(bool $ok, string $error = '', int $code = 200, string $ref = ''): never
{
    global $wantsJson;
    http_response_code($code);
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'error' => $error, 'ref' => $ref], JSON_UNESCAPED_UNICODE);
    } else {
        $target = $ok ? '/thank-you/' . ($ref !== '' ? '?ref=' . rawurlencode($ref) : '') : '/contact-us/?error=1';
        header('Location: ' . $target, true, 303);
    }
    exit;
}

function field(string $key, int $max = 200): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '');
    return mb_substr($v, 0, $max);
}

function one_of(string $value, array $allowed, string $fallback = ''): string
{
    return in_array($value, $allowed, true) ? $value : $fallback;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Method not allowed.';
    exit;
}

/* Connect to AS Case Vault (outside public_html) */
$crm = ASCRM_PATH !== '' ? rtrim(ASCRM_PATH, '/') : dirname(__DIR__, 2) . '/ascrm';
if (!is_file($crm . '/bootstrap.php')) {
    error_log('AccountSuspension intake: AS Case Vault not found at ' . $crm);
    respond(false, 'Our secure intake is briefly unavailable. Please try again in a few minutes.', 503);
}
require $crm . '/bootstrap.php';
restore_exception_handler();

use DR\Core\Config;
use DR\Core\Labels;
use DR\Core\RateLimit;
use DR\Core\Request;
use DR\Service\Cases;

try {
    if (!Config::installed()) {
        respond(false, 'Our secure intake is briefly unavailable. Please try again in a few minutes.', 503);
    }
    $intakeCfg = (array) Config::get('intake', []);

    /* Same-origin check (Origin header when present) */
    $origin = rtrim($_SERVER['HTTP_ORIGIN'] ?? '', '/');
    $allowed = array_map(static fn($o) => rtrim((string) $o, '/'), (array) ($intakeCfg['allowed_origins'] ?? []));
    if ($origin !== '' && $allowed && !in_array($origin, $allowed, true)) {
        respond(false, 'Request not allowed.', 403);
    }

    /* Honeypot and timing */
    if (field('website') !== '') {
        respond(true);
    }
    $ts = (int) field('form_ts', 20);
    if ($ts > 0 && (time() - $ts) < (int) ($intakeCfg['min_fill_seconds'] ?? 3)) {
        respond(false, 'Please take a moment to complete the form.', 429);
    }

    /* Rate limit per IP (stored as a keyed hash bucket in the CRM) */
    $ip = Request::ip();
    $bucket = 'intake:' . substr(hash_hmac('sha256', $ip, 'ascv-intake'), 0, 32);
    if (!RateLimit::attempt($bucket, max(1, (int) ($intakeCfg['rate_limit_per_hour'] ?? 5)), 3600)) {
        respond(false, 'Too many submissions from this connection. Please wait an hour and try again.', 429);
    }

    /* Optional Cloudflare Turnstile */
    $turnstile = (string) ($intakeCfg['turnstile_secret'] ?? '');
    if ($turnstile !== '') {
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
            CURLOPT_POSTFIELDS => http_build_query(['secret' => $turnstile, 'response' => field('cf-turnstile-response', 4096), 'remoteip' => $ip]),
        ]);
        $out = curl_exec($ch);
        curl_close($ch);
        $verify = is_string($out) ? json_decode($out, true) : null;
        if (!is_array($verify) || empty($verify['success'])) {
            respond(false, 'Verification failed. Please try again.', 400);
        }
    }

    /* Validate (allow-lists come from the CRM so the website and CRM never drift apart) */
    $platform = one_of(field('platform', 40), array_keys(Labels::PLATFORMS), '');
    $platformOther = field('platform_other', 80);
    $issue = one_of(field('issue', 20), array_keys(Labels::ISSUES), '');
    $history = one_of(field('history', 20), array_keys(Labels::HISTORY), '');
    $urgency = one_of(field('urgency', 20), array_keys(Labels::URGENCY_IN), '');
    $source = one_of(field('source', 30), ['contact', 'readiness', 'decoder', 'platform', 'service', 'guide', 'priority'], 'contact');
    $name = field('name', 120);
    $email = mb_strtolower(field('email', 190));
    $details = field('details', 4000);
    $score = field('score', 3);
    $consent = field('consent', 5) !== '';

    if (!$consent) {
        respond(false, 'Please confirm you agree to the privacy policy.', 422);
    }
    if ($platform === '' || $issue === '' || $history === '' || $urgency === '') {
        respond(false, 'Please complete every step of the form.', 422);
    }
    if ($name === '') {
        respond(false, 'Please enter your name.', 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(false, 'Please enter a valid email address for our written reply.', 422);
    }
    if (mb_strlen($details) < 10) {
        respond(false, 'Please tell us what the notice says in a line or two.', 422);
    }
    if ($score !== '' && (!ctype_digit($score) || (int) $score > 100)) {
        $score = '';
    }
    $page = (string) (parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH) ?? '');

    $case = Cases::fromIntake([
        'form_type' => 'intake', 'source' => $source, 'platform' => $platform, 'platform_other' => $platformOther,
        'issue' => $issue, 'history' => $history, 'urgency' => $urgency,
        'name' => $name, 'email' => $email, 'details' => $details, 'score' => $score,
        'page' => mb_substr($page, 0, 200), 'received' => date('c'),
    ]);
    respond(true, '', 200, $case['ref']);
} catch (Throwable $e) {
    DR\Core\Log::error($e);
    respond(false, 'Temporary problem. Please try again in a few minutes.', 500);
}
