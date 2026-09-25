<?php
/**
 * accountsuspension.com secure case intake.
 *
 * - POST only, Origin/Referer host check, honeypot, minimum fill time
 * - Hashed-IP rate limit (default 5 per hour)
 * - Optional Cloudflare Turnstile
 * - Strict allow-list validation
 * - Stores each case as a libsodium-encrypted file OUTSIDE public_html
 * - Staff notification contains the reference and urgency only, never case content
 * - Returns JSON {ok, error, ref} for fetch(), or 303 redirects for no-JS posts
 *
 * Settings: copy config.sample.php to config.php (same folder) and fill it in.
 */
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$cfg = [
    'ALLOWED_HOSTS'     => ['accountsuspension.com', 'www.accountsuspension.com'],
    'DATA_DIR'          => dirname(__DIR__, 2) . '/as_data',   // one level above public_html
    'APP_KEY'           => '',     // base64 of 32 random bytes; required for storage
    'IP_SALT'           => '',     // random string; used to hash IPs for rate limiting
    'NOTIFY_TO'         => '',     // staff inbox for content-free alerts
    'NOTIFY_FROM'       => '',     // a sender on your domain
    'TURNSTILE_SECRET'  => '',     // optional
    'MIN_FILL_SECONDS'  => 4,
    'RATE_LIMIT'        => 5,
    'RATE_WINDOW'       => 3600,
];
if (is_file(__DIR__ . '/config.php')) {
    $local = require __DIR__ . '/config.php';
    if (is_array($local)) {
        $cfg = array_merge($cfg, $local);
    }
}

const ISSUES   = ['suspended', 'funds', 'limited', 'verification', 'ip', 'strikes', 'rejected', 'prevent', 'other'];
const HISTORY  = ['none', 'one', 'many', 'final'];
const URGENCY  = ['deadline', 'revenue', 'standard'];

$wantsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function respond(bool $ok, string $error = '', string $ref = '', int $code = 200): never
{
    global $wantsJson;
    if ($wantsJson) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'error' => $error, 'ref' => $ref], JSON_UNESCAPED_SLASHES);
        exit;
    }
    $to = $ok ? '/thank-you/?ref=' . rawurlencode($ref) : '/contact-us/?error=1';
    header('Location: ' . $to, true, 303);
    exit;
}

function fail_log(string $msg): void
{
    error_log('[as-intake] ' . $msg);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(false, 'Method not allowed.', '', 405);
}

// Origin check
$origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
$originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
if ($originHost === '' || !in_array($originHost, array_map('strtolower', $cfg['ALLOWED_HOSTS']), true)) {
    fail_log('origin rejected: ' . $originHost);
    respond(false, 'This form must be sent from our website.', '', 403);
}

$ref = 'AS-' . strtoupper(bin2hex(random_bytes(4)));

// Honeypot: silently accept, store nothing
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    respond(true, '', $ref);
}

// Minimum fill time
$ts = (int) ($_POST['form_ts'] ?? 0);
if ($ts <= 0 || (time() - $ts) < (int) $cfg['MIN_FILL_SECONDS'] || (time() - $ts) > 86400) {
    respond(false, 'Please take a moment to complete the form, then send it again.', '', 400);
}

// Storage prerequisites
$dataDir = rtrim((string) $cfg['DATA_DIR'], '/');
$key = base64_decode((string) $cfg['APP_KEY'], true);
if (!function_exists('sodium_crypto_secretbox') || $key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
    fail_log('APP_KEY missing or invalid, or libsodium unavailable');
    respond(false, 'Our intake is temporarily unavailable. Please try again later.', '', 503);
}
foreach ([$dataDir, $dataDir . '/cases', $dataDir . '/rl'] as $d) {
    if (!is_dir($d) && !@mkdir($d, 0700, true)) {
        fail_log('cannot create ' . $d);
        respond(false, 'Our intake is temporarily unavailable. Please try again later.', '', 503);
    }
}
if (!is_file($dataDir . '/.htaccess')) {
    @file_put_contents($dataDir . '/.htaccess', "Require all denied\n");
}

// Rate limit by salted IP hash
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ipHash = hash('sha256', $cfg['IP_SALT'] . '|' . $ip);
$rlFile = $dataDir . '/rl/' . substr($ipHash, 0, 32) . '.json';
$now = time();
$hits = [];
$fh = @fopen($rlFile, 'c+');
if ($fh !== false) {
    flock($fh, LOCK_EX);
    $raw = stream_get_contents($fh);
    $hits = array_values(array_filter(json_decode($raw ?: '[]', true) ?: [], static fn($t) => is_int($t) && $t > $now - (int) $cfg['RATE_WINDOW']));
    if (count($hits) >= (int) $cfg['RATE_LIMIT']) {
        flock($fh, LOCK_UN);
        fclose($fh);
        respond(false, 'Too many submissions from this connection. Please try again later.', '', 429);
    }
    $hits[] = $now;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($hits));
    flock($fh, LOCK_UN);
    fclose($fh);
}

// Optional Turnstile
if ($cfg['TURNSTILE_SECRET'] !== '') {
    $token = (string) ($_POST['cf-turnstile-response'] ?? '');
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'timeout' => 5,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query(['secret' => $cfg['TURNSTILE_SECRET'], 'response' => $token, 'remoteip' => $ip]),
    ]]);
    $res = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
    $ok = $res !== false && (json_decode($res, true)['success'] ?? false) === true;
    if (!$ok) {
        respond(false, 'Verification failed. Please reload the page and try again.', '', 400);
    }
}

// Validation
function field(string $k, int $max): string
{
    $v = (string) ($_POST[$k] ?? '');
    $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '');
    return mb_substr($v, 0, $max);
}

$data = [
    'form_type'      => field('form_type', 20),
    'source'         => field('source', 40),
    'score'          => field('score', 3),
    'platform'       => field('platform', 40),
    'platform_other' => field('platform_other', 80),
    'issue'          => field('issue', 20),
    'history'        => field('history', 20),
    'urgency'        => field('urgency', 20),
    'name'           => field('name', 100),
    'email'          => field('email', 190),
    'details'        => field('details', 4000),
    'consent'        => field('consent', 1),
];

$errors = [];
if (!preg_match('/^[a-z0-9-]{2,40}$/', $data['platform'])) $errors[] = 'platform';
if (!in_array($data['issue'], ISSUES, true)) $errors[] = 'issue';
if (!in_array($data['history'], HISTORY, true)) $errors[] = 'history';
if (!in_array($data['urgency'], URGENCY, true)) $errors[] = 'urgency';
if (mb_strlen($data['name']) < 1) $errors[] = 'name';
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'email';
if (mb_strlen($data['details']) < 10) $errors[] = 'details';
if ($data['consent'] !== '1') $errors[] = 'consent';
if ($data['source'] !== '' && !preg_match('/^[a-z0-9_-]{1,40}$/i', $data['source'])) $data['source'] = 'other';
if ($data['score'] !== '' && (!ctype_digit($data['score']) || (int) $data['score'] > 100)) $data['score'] = '';
if ($data['form_type'] !== 'intake') $data['form_type'] = 'intake';

if ($errors) {
    respond(false, 'Please check these fields: ' . implode(', ', $errors) . '.', '', 422);
}

// Encrypt and store
$record = $data + [
    'ref'        => $ref,
    'received'   => gmdate('c'),
    'ip_hash'    => substr($ipHash, 0, 16),
    'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
];
$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
$cipher = sodium_crypto_secretbox(json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $nonce, $key);
$file = $dataDir . '/cases/' . gmdate('Ymd-His') . '-' . $ref . '.enc';
if (@file_put_contents($file, base64_encode($nonce . $cipher), LOCK_EX) === false) {
    fail_log('write failed ' . $file);
    respond(false, 'Our intake is temporarily unavailable. Please try again later.', '', 503);
}
@chmod($file, 0600);
sodium_memzero($key);

// Content-free staff alert
if ($cfg['NOTIFY_TO'] !== '' && $cfg['NOTIFY_FROM'] !== '') {
    $subject = 'New case ' . $ref . ($data['urgency'] === 'standard' ? '' : ' [' . strtoupper($data['urgency']) . ']');
    $body = "A new case has been received.\n\nReference: {$ref}\nUrgency: {$data['urgency']}\n\n"
          . "No case details are included in this email. Read the case with server-tools/read_cases.php.\n";
    $headers = 'From: ' . $cfg['NOTIFY_FROM'] . "\r\nContent-Type: text/plain; charset=utf-8";
    @mail($cfg['NOTIFY_TO'], $subject, $body, $headers);
}

respond(true, '', $ref);
