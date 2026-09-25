<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\App;
use DR\Core\Audit;
use DR\Core\Config;
use DR\Core\Crypto;
use DR\Core\Db;
use DR\Core\HttpError;
use DR\Core\Session;
use DR\Core\Settings;
use DR\Core\Vault;
use DR\Service\Maintenance;

/** Master admin: key rotation, encrypted backups, retention, maintenance mode. */
final class SecurityController extends Controller
{
    public function index(array $p, ?array $u): void
    {
        $backups = [];
        foreach (glob(Maintenance::backupDir() . '/backup-*.drbak') ?: [] as $f) {
            $backups[] = ['name' => basename($f, '.drbak'), 'size' => filesize($f) ?: 0, 'at' => date('Y-m-d H:i:s', filemtime($f) ?: time())];
        }
        usort($backups, fn($a, $b) => strcmp($b['at'], $a['at']));
        $vaultBytes = 0;
        $vaultFiles = 0;
        $it = is_dir(Vault::root()) ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(Vault::root(), \FilesystemIterator::SKIP_DOTS)) : [];
        foreach ($it as $f) {
            $vaultBytes += $f->getSize();
            $vaultFiles++;
        }
        $this->view('admin/security', [
            'title' => 'Security, keys and backups', 'backups' => $backups,
            'fingerprint' => substr(hash('sha256', 'fp|' . Crypto::master()), 0, 16),
            'clients' => (int) Db::value('SELECT COUNT(*) FROM clients WHERE dek_wrapped IS NOT NULL'),
            'vaultBytes' => $vaultBytes, 'vaultFiles' => $vaultFiles,
            'lastRotation' => Settings::get('key_rotated_at'), 'cronLast' => Settings::get('cron_last_run'),
            'cronUrl' => App::absoluteUrl('/cron/' . Config::get('cron_token', '')), 'maintenance' => Settings::get('maintenance') === '1',
            'sudo' => Session::sudoActive(), 'keyPath' => Crypto::masterKeyPath(),
            'failed24' => (int) Db::value("SELECT COUNT(*) FROM audit_log WHERE action IN ('login_failed','mfa_failed','sudo_failed','access_denied') AND at > ?", [date('Y-m-d H:i:s', time() - 86400)]),
        ], 'app');
    }

    public function rotate(array $p, ?array $u): void
    {
        $this->requireSudo(url('/admin/security'));
        $counts = Crypto::rotateMaster();
        Settings::set('key_rotated_at', now());
        Audit::log('key_rotated', 'system', null, $counts, $u);
        $this->flash('ok', 'Master key rotated. ' . $counts['clients'] . ' client keys re-wrapped. The previous key was kept beside the new one (.prev) so older backups can still be restored; move it offline.');
        App::redirect('/admin/security');
    }

    public function backup(array $p, ?array $u): void
    {
        $this->requireSudo(url('/admin/security'));
        $name = Maintenance::backup();
        Audit::log('backup_created', 'system', null, ['name' => $name], $u);
        $this->flash('ok', 'Encrypted backup created. Download it and store it with an offline copy of the master key.');
        App::redirect('/admin/security');
    }

    public function downloadBackup(array $p, ?array $u): void
    {
        $this->requireSudo(url('/admin/security'));
        $name = (string) ($p['name'] ?? '');
        if (!preg_match('/^backup-\d{8}-\d{6}-[a-f0-9]{6}$/', $name)) {
            throw new HttpError('Backup not found.', 404);
        }
        $path = Maintenance::backupDir() . '/' . $name . '.drbak';
        if (!is_file($path)) {
            throw new HttpError('Backup not found.', 404);
        }
        Audit::log('backup_downloaded', 'system', null, ['name' => $name], $u);
        Session::save();
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . $name . '.drbak"');
        readfile($path);
        exit;
    }

    public function retention(array $p, ?array $u): void
    {
        $r = Maintenance::retention();
        Maintenance::housekeeping();
        $this->flash('ok', 'Retention applied: ' . $r['leads_purged'] . ' declined leads purged, ' . $r['audit_deleted'] . ' old audit rows removed.');
        App::redirect('/admin/security');
    }

    public function maintenance(array $p, ?array $u): void
    {
        $on = Settings::get('maintenance') !== '1';
        Settings::set('maintenance', $on ? '1' : '0');
        Audit::log('maintenance_' . ($on ? 'on' : 'off'), 'system', null, [], $u);
        $this->flash('ok', $on ? 'Maintenance mode is on. Only master admins can use the portal.' : 'Maintenance mode is off.');
        App::redirect('/admin/security');
    }
}
