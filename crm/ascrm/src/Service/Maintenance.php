<?php
declare(strict_types=1);

namespace DR\Service;

use DR\Core\Audit;
use DR\Core\Crypto;
use DR\Core\Db;
use DR\Core\RateLimit;
use DR\Core\Schema;
use DR\Core\Settings;
use DR\Core\Vault;

/** Scheduled jobs, retention and encrypted database backups. */
final class Maintenance
{
    public static function runAll(): array
    {
        $out = [];
        $out['reminders'] = self::deadlineReminders();
        $out['uploads_pruned'] = Vault::pruneUploads();
        RateLimit::prune();
        $out['housekeeping'] = self::housekeeping();
        $out['retention'] = self::retention();
        Settings::set('cron_last_run', now());
        return $out;
    }

    public static function deadlineReminders(): int
    {
        $n = 0;
        $rows = Db::all("SELECT * FROM deadlines WHERE status = 'upcoming' AND reminded_at IS NULL");
        foreach ($rows as $d) {
            $remindAt = strtotime($d['due_at']) - max(0, (int) $d['remind_days']) * 86400;
            if ($remindAt > time()) {
                continue;
            }
            $title = 'Deadline approaching: ' . mb_substr($d['title'], 0, 120) . ' (' . fdate($d['due_at'], true) . ')';
            $case = $d['case_id'] ? Db::one('SELECT * FROM cases WHERE id = ?', [(int) $d['case_id']]) : null;
            if ($case) {
                // Titles are staff-authored; include only the case reference in email-facing text.
                Notify::caseTeam($case, 'deadline', 'Deadline approaching on ' . $case['ref'] . ' (' . fdate($d['due_at'], true) . ')');
            } elseif ($d['created_by']) {
                Notify::user((int) $d['created_by'], 'deadline', $title, '/calendar');
            }
            Db::update('deadlines', ['reminded_at' => now()], 'id = :id', ['id' => (int) $d['id']]);
            $n++;
        }
        return $n;
    }

    public static function housekeeping(): array
    {
        $cut = date('Y-m-d H:i:s', time() - 30 * 86400);
        $s = Db::run('DELETE FROM sessions WHERE (revoked = 1 OR expires_at < ?) AND last_seen_at < ?', [now(), $cut])->rowCount();
        $t = Db::run('DELETE FROM tokens WHERE (used_at IS NOT NULL OR expires_at < ?) AND created_at < ?', [now(), $cut])->rowCount();
        $nt = Db::run('DELETE FROM notifications WHERE read_at IS NOT NULL AND read_at < ?', [date('Y-m-d H:i:s', time() - 180 * 86400)])->rowCount();
        return ['sessions' => $s, 'tokens' => $t, 'notifications' => $nt];
    }

    /** Apply the retention schedule from settings. */
    public static function retention(): array
    {
        $declDays = max(30, Settings::int('retention_declined_days'));
        $auditDays = max(365, Settings::int('retention_audit_days'));
        $cut = date('Y-m-d H:i:s', time() - $declDays * 86400);
        $leads = Db::run(
            "UPDATE cases SET intake_enc = NULL, intake_email_hash = NULL, decline_reason = NULL, title = 'Purged lead', updated_at = ?
             WHERE status = 'declined' AND intake_enc IS NOT NULL AND updated_at < ?",
            [now(), $cut]
        )->rowCount();
        $audit = Db::run('DELETE FROM audit_log WHERE at < ?', [date('Y-m-d H:i:s', time() - $auditDays * 86400)])->rowCount();
        if ($leads || $audit) {
            Audit::log('retention_applied', 'system', null, ['leads_purged' => $leads, 'audit_rows_deleted' => $audit], ['id' => null, 'role' => 'system']);
        }
        return ['leads_purged' => $leads, 'audit_deleted' => $audit];
    }

    public static function backupDir(): string
    {
        $dir = DR_STORAGE . '/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        return $dir;
    }

    /**
     * Encrypted logical backup of every table (vault files are already encrypted and are
     * backed up by copying storage/vault). Sealed with the backup subkey of the master key,
     * streamed so no plaintext dump touches the disk.
     */
    public static function backup(): string
    {
        $name = 'backup-' . date('Ymd-His') . '-' . Crypto::randomHex(3);
        $path = self::backupDir() . '/' . $name . '.drbak';
        $fh = fopen($path, 'wb');
        if (!$fh) {
            throw new \RuntimeException('Cannot write backup');
        }
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push(Crypto::subkey('backup'));
        fwrite($fh, 'DRV1' . $header);
        $push = static function (string $line, bool $final) use (&$state, $fh): void {
            $tag = $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            $c = sodium_crypto_secretstream_xchacha20poly1305_push($state, $line, '', $tag);
            fwrite($fh, pack('N', strlen($c)) . $c);
        };
        $push(json_encode(['ascv_backup' => 1, 'version' => DR_VERSION, 'schema' => Schema::VERSION, 'created' => now()]) . "\n", false);
        foreach (array_keys(Schema::tables()) as $table) {
            $buf = '';
            foreach (Db::run('SELECT * FROM ' . $table)->fetchAll() as $row) {
                $buf .= json_encode(['t' => $table, 'r' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                if (strlen($buf) > 60000) {
                    $push($buf, false);
                    $buf = '';
                }
            }
            if ($buf !== '') {
                $push($buf, false);
            }
        }
        $push(json_encode(['end' => true]) . "\n", true);
        fclose($fh);
        @chmod($path, 0600);
        foreach (glob(self::backupDir() . '/backup-*.drbak') ?: [] as $old) {
            if (filemtime($old) < time() - 30 * 86400) {
                @unlink($old);
            }
        }
        return $name;
    }

    /** Restore helper for the CLI: decrypt a backup into a JSON-lines file. */
    public static function decryptBackup(string $src, string $dst, ?string $masterHex = null): void
    {
        $master = $masterHex ? sodium_hex2bin(trim($masterHex)) : null;
        $out = fopen($dst, 'wb');
        Crypto::decryptFileTo($src, $out, Crypto::subkey('backup', $master));
        fclose($out);
    }

    /** Load a decrypted JSON-lines backup into an empty, migrated database. */
    public static function importBackup(string $jsonl): int
    {
        $in = fopen($jsonl, 'rb');
        $n = 0;
        $tables = Schema::tables();
        Db::tx(function () use ($in, &$n, $tables): void {
            foreach (array_keys($tables) as $t) {
                Db::run('DELETE FROM ' . $t);
            }
            while (($line = fgets($in)) !== false) {
                $rec = json_decode($line, true);
                if (!is_array($rec) || !isset($rec['t'], $rec['r']) || !isset($tables[$rec['t']])) {
                    continue;
                }
                $row = array_intersect_key($rec['r'], $tables[$rec['t']]['cols']);
                Db::insert($rec['t'], $row);
                $n++;
            }
        });
        fclose($in);
        return $n;
    }
}
