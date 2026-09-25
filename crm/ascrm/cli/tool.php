<?php
/**
 * AS Case Vault command-line tool.
 *
 *   php cli/tool.php selftest                     verify encryption, keys and database
 *   php cli/tool.php backup:create                write an encrypted backup to storage/backups
 *   php cli/tool.php backup:restore FILE [KEYFILE] restore a backup into the configured (empty) database
 *   php cli/tool.php key:rotate                    rotate the master key
 *   php cli/tool.php user:unlock EMAIL             clear a sign-in lock
 *   php cli/tool.php user:reset-2fa EMAIL          force authenticator re-enrollment
 *   php cli/tool.php migrate                       apply schema changes
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/bootstrap.php';

use DR\Core\Audit;
use DR\Core\Config;
use DR\Core\Crypto;
use DR\Core\Db;
use DR\Core\Schema;
use DR\Core\Session;
use DR\Service\Maintenance;

$cmd = $argv[1] ?? 'help';
$cli = ['id' => null, 'role' => 'cli'];
if ($cmd !== 'help' && !Config::installed()) {
    fwrite(STDERR, "Not installed.\n");
    exit(1);
}
switch ($cmd) {
    case 'selftest':
        $k = sodium_crypto_secretbox_keygen();
        $ok = Crypto::open(Crypto::seal('probe', $k), $k) === 'probe';
        $tmp = tempnam(sys_get_temp_dir(), 'drv');
        file_put_contents($tmp, random_bytes(200000));
        $enc = $tmp . '.enc';
        $h1 = Crypto::encryptFile($tmp, $enc, $k);
        $out = fopen('php://memory', 'w+b');
        Crypto::decryptFileTo($enc, $out, $k);
        rewind($out);
        $ok = $ok && hash('sha256', (string) stream_get_contents($out)) === $h1;
        @unlink($tmp);
        @unlink($enc);
        Crypto::master();
        $clients = (int) Db::value('SELECT COUNT(*) FROM clients WHERE dek_wrapped IS NOT NULL');
        $bad = 0;
        foreach (Db::all('SELECT id FROM clients WHERE dek_wrapped IS NOT NULL') as $c) {
            try {
                Crypto::clientKey((int) $c['id']);
            } catch (Throwable $e) {
                $bad++;
            }
        }
        echo ($ok && !$bad ? 'OK' : 'FAIL') . ": crypto round trip " . ($ok ? 'passed' : 'failed') . ", $clients client keys, $bad unreadable\n";
        exit($ok && !$bad ? 0 : 1);
    case 'backup:create':
        echo Maintenance::backup() . "\n";
        break;
    case 'backup:restore':
        $file = $argv[2] ?? '';
        if (!is_file($file)) {
            fwrite(STDERR, "Backup file not found.\n");
            exit(1);
        }
        $keyHex = isset($argv[3]) ? trim((string) file_get_contents($argv[3])) : null;
        Schema::migrate();
        if ((int) Db::value('SELECT COUNT(*) FROM users') > 0) {
            fwrite(STDERR, "Refusing to restore over a database that already has users. Restore into a fresh database.\n");
            exit(1);
        }
        $tmp = DR_STORAGE . '/restore-' . bin2hex(random_bytes(4)) . '.jsonl';
        Maintenance::decryptBackup($file, $tmp, $keyHex);
        $n = Maintenance::importBackup($tmp);
        unlink($tmp);
        echo "Restored $n rows.\n";
        break;
    case 'key:rotate':
        print_r(Crypto::rotateMaster());
        Audit::log('key_rotated', 'system', null, [], $cli);
        break;
    case 'user:unlock':
    case 'user:reset-2fa':
        $u = Db::one('SELECT * FROM users WHERE email = ?', [mb_strtolower((string) ($argv[2] ?? ''))]);
        if (!$u) {
            fwrite(STDERR, "User not found.\n");
            exit(1);
        }
        if ($cmd === 'user:unlock') {
            Db::update('users', ['failed_logins' => 0, 'locked_until' => null], 'id = :id', ['id' => (int) $u['id']]);
        } else {
            Db::update('users', ['totp_secret_enc' => null, 'totp_enabled' => 0, 'recovery_hashes' => null], 'id = :id', ['id' => (int) $u['id']]);
            Session::revokeAllFor((int) $u['id']);
        }
        Audit::log($cmd === 'user:unlock' ? 'user_unlocked' : 'mfa_reset', 'user', (int) $u['id'], ['by' => 'cli'], $cli);
        echo "Done.\n";
        break;
    case 'migrate':
        Schema::migrate();
        echo "Schema version " . Schema::VERSION . "\n";
        break;
    default:
        echo file_get_contents(__FILE__, false, null, 0, 1200);
}
