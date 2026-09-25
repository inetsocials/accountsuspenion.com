<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\App;
use DR\Core\Audit;
use DR\Core\Auth;
use DR\Core\Crypto;
use DR\Core\Db;
use DR\Core\Request;
use DR\Core\Schema;

/**
 * One-time web installer. Gated by a random setup key written to ascrm/config/setup.key,
 * which proves the person installing has file access to the server.
 */
final class InstallController extends Controller
{
    private function setupKeyPath(): string
    {
        return DR_ROOT . '/config/setup.key';
    }

    private function ensureSetupKey(): void
    {
        $p = $this->setupKeyPath();
        if (!is_file($p)) {
            if (!is_dir(dirname($p))) {
                @mkdir(dirname($p), 0700, true);
            }
            @file_put_contents($p, bin2hex(random_bytes(16)) . "\n", LOCK_EX);
            @chmod($p, 0600);
        }
    }

    public static function requirements(): array
    {
        $w = static fn(string $d): bool => (is_dir($d) || @mkdir($d, 0700, true)) && is_writable($d);
        return [
            ['PHP 8.1 or newer', PHP_VERSION_ID >= 80100, PHP_VERSION, true],
            ['libsodium (encryption)', function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push'), extension_loaded('sodium') ? 'loaded' : 'missing', true],
            ['PDO MySQL or SQLite driver', extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'), implode(', ', \PDO::getAvailableDrivers()), true],
            ['mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'loaded' : 'missing', true],
            ['fileinfo (upload type checks)', class_exists('finfo'), class_exists('finfo') ? 'loaded' : 'missing', true],
            ['config/ writable', $w(DR_ROOT . '/config'), DR_ROOT . '/config', true],
            ['keys/ writable', $w(DR_ROOT . '/keys'), DR_ROOT . '/keys', true],
            ['storage/ writable', $w(DR_STORAGE), DR_STORAGE, true],
            ['Argon2id password hashing', defined('PASSWORD_ARGON2ID'), defined('PASSWORD_ARGON2ID') ? 'available' : 'bcrypt fallback', false],
            ['HTTPS', Request::isHttps(), Request::isHttps() ? 'on' : 'off (required in production)', false],
        ];
    }

    public function form(array $p, ?array $u): void
    {
        $this->ensureSetupKey();
        $scheme = Request::isHttps() ? 'https' : 'http';
        $this->view('install/form', [
            'reqs' => self::requirements(),
            'error' => null,
            'in' => ['app_url' => $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . App::basePath(), 'driver' => extension_loaded('pdo_mysql') ? 'mysql' : 'sqlite', 'db_host' => 'localhost', 'db_port' => '3306'],
            'title' => 'Install', 'wide' => true,
        ], 'auth');
    }

    public function run(array $p, ?array $u): void
    {
        $this->ensureSetupKey();
        $in = [
            'setup_key' => Request::str('setup_key', 64), 'app_url' => rtrim(Request::str('app_url', 190), '/'),
            'driver' => Request::oneOf('driver', ['mysql' => 1, 'sqlite' => 1], 'mysql'),
            'db_host' => Request::str('db_host', 120), 'db_port' => Request::str('db_port', 6), 'db_name' => Request::str('db_name', 64),
            'db_user' => Request::str('db_user', 64), 'db_pass' => (string) ($_POST['db_pass'] ?? ''),
            'name' => Request::str('name', 120), 'email' => mb_strtolower(Request::str('email', 190)), 'password' => (string) ($_POST['password'] ?? ''),
        ];
        $fail = function (string $msg) use ($in): never {
            unset($in['db_pass'], $in['password'], $in['setup_key']);
            http_response_code(422);
            $this->view('install/form', ['reqs' => self::requirements(), 'error' => $msg, 'in' => $in, 'title' => 'Install', 'wide' => true], 'auth');
            exit;
        };
        $key = trim((string) @file_get_contents($this->setupKeyPath()));
        if ($key === '' || !hash_equals($key, $in['setup_key'])) {
            $fail('The setup key does not match ascrm/config/setup.key.');
        }
        foreach (self::requirements() as [$label, $ok, , $required]) {
            if ($required && !$ok) {
                $fail('Requirement not met: ' . $label . '.');
            }
        }
        if (!filter_var($in['app_url'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#', $in['app_url'])) {
            $fail('Enter the full portal address, for example https://accountsuspension.com/portal');
        }
        if ($in['name'] === '' || !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
            $fail('Enter the master admin name and a valid email address.');
        }
        if ($problem = Auth::passwordProblem($in['password'], $in['email'], $in['name'])) {
            $fail('Master admin password: ' . $problem);
        }
        $db = $in['driver'] === 'sqlite'
            ? ['driver' => 'sqlite', 'path' => DR_STORAGE . '/db/ascrm.sqlite']
            : ['driver' => 'mysql', 'host' => $in['db_host'] ?: 'localhost', 'port' => (int) ($in['db_port'] ?: 3306), 'name' => $in['db_name'], 'user' => $in['db_user'], 'pass' => $in['db_pass']];
        if ($db['driver'] === 'mysql' && ($db['name'] === '' || $db['user'] === '')) {
            $fail('Enter the MySQL database name and user from hPanel.');
        }
        try {
            Db::reset();
            Db::connect($db);
        } catch (\Throwable $e) {
            $fail('Could not connect to the database. Check the details from hPanel > Databases.');
        }

        $urlPath = rtrim((string) (parse_url($in['app_url'], PHP_URL_PATH) ?? ''), '/');
        $config = [
            'app_url' => preg_replace('#^(https?://[^/]+).*$#', '$1', $in['app_url']),
            'base_path' => $urlPath,
            'timezone' => 'America/New_York',
            'db' => $db,
            'master_key_path' => DR_ROOT . '/keys/master.key',
            'vault_path' => DR_STORAGE . '/vault',
            'cookie_name' => 'ascv_sess',
            'force_secure_cookies' => str_starts_with($in['app_url'], 'https://'),
            'trust_proxy_https' => false,
            'trust_cloudflare' => false,
            'session_absolute_hours' => 12,
            'cron_token' => bin2hex(random_bytes(24)),
            'debug' => false,
            'intake' => [
                'allowed_origins' => ['https://accountsuspension.com', 'https://www.accountsuspension.com'],
                'min_fill_seconds' => 3,
                'rate_limit_per_hour' => 5,
                'turnstile_secret' => '',
            ],
        ];
        $GLOBALS['DR_CONFIG'] = $config;

        if (!is_file(Crypto::masterKeyPath())) {
            Crypto::generateMasterKey(Crypto::masterKeyPath());
        }
        Crypto::master();
        Schema::migrate();

        $master = Db::one("SELECT id FROM users WHERE role = 'master' LIMIT 1");
        if (!$master) {
            $existing = Db::one('SELECT id FROM users WHERE email = ?', [$in['email']]);
            if ($existing) {
                $fail('That email address is already used by another account in this database.');
            }
            $id = Db::insert('users', [
                'role' => 'master', 'email' => $in['email'], 'name' => $in['name'], 'password_hash' => Auth::hashPassword($in['password']),
                'status' => 'active', 'client_id' => null, 'totp_secret_enc' => null, 'totp_enabled' => 0, 'recovery_hashes' => null,
                'failed_logins' => 0, 'locked_until' => null, 'last_login_at' => null, 'last_login_ip' => null, 'known_devices' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            Audit::log('installed', 'system', null, ['version' => DR_VERSION, 'driver' => $db['driver']], ['id' => $id, 'role' => 'master']);
        }

        $php = "<?php\n// AS Case Vault configuration. Generated by the installer on " . date('m/d/Y H:i') . ".\n// Keep this file private. It is outside public_html.\nreturn " . var_export($config, true) . ";\n";
        if (file_put_contents(DR_ROOT . '/config/config.php', $php, LOCK_EX) === false) {
            $fail('Could not write config/config.php. Check folder permissions.');
        }
        @chmod(DR_ROOT . '/config/config.php', 0600);
        file_put_contents(DR_ROOT . '/config/installed.lock', date('c') . "\n");
        @unlink($this->setupKeyPath());
        foreach (['vault', 'logs', 'backups', 'mail', 'db'] as $d) {
            if (!is_dir(DR_STORAGE . '/' . $d)) {
                @mkdir(DR_STORAGE . '/' . $d, 0700, true);
            }
        }
        $this->view('install/done', ['title' => 'Installed', 'cron' => $config['cron_token'], 'app_url' => $in['app_url']], 'auth');
    }
}
