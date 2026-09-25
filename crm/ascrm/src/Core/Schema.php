<?php
declare(strict_types=1);

namespace DR\Core;

/**
 * Portable schema definition rendered to MySQL or SQLite DDL.
 * Types: id | int | bigint | str:N | char:N | text | longtext | date | datetime
 * Suffix "?" makes a column nullable; "=value" sets a default.
 */
final class Schema
{
    public const VERSION = 1;

    public static function tables(): array
    {
        return [
            'settings' => [
                'cols' => ['k' => 'str:80', 'v' => 'longtext?'],
                'pk' => 'k',
            ],
            'users' => [
                'cols' => [
                    'id' => 'id', 'role' => 'str:20', 'email' => 'str:190', 'name' => 'str:120',
                    'password_hash' => 'str:255?', 'status' => 'str:20=invited', 'client_id' => 'bigint?',
                    'totp_secret_enc' => 'text?', 'totp_enabled' => 'int=0', 'recovery_hashes' => 'text?',
                    'failed_logins' => 'int=0', 'locked_until' => 'datetime?', 'last_login_at' => 'datetime?',
                    'last_login_ip' => 'str:45?', 'known_devices' => 'text?', 'created_at' => 'datetime', 'updated_at' => 'datetime',
                ],
                'unique' => [['email']],
                'index' => [['role'], ['client_id']],
            ],
            'sessions' => [
                'cols' => [
                    'id' => 'char:64', 'user_id' => 'bigint', 'ip' => 'str:45', 'user_agent' => 'str:255',
                    'mfa_passed' => 'int=0', 'sudo_until' => 'datetime?', 'data' => 'text?', 'csrf' => 'char:64',
                    'created_at' => 'datetime', 'last_seen_at' => 'datetime', 'expires_at' => 'datetime', 'revoked' => 'int=0',
                ],
                'pk' => 'id',
                'index' => [['user_id']],
            ],
            'tokens' => [
                'cols' => [
                    'id' => 'id', 'user_id' => 'bigint?', 'purpose' => 'str:20', 'token_hash' => 'char:64',
                    'expires_at' => 'datetime', 'used_at' => 'datetime?', 'created_at' => 'datetime', 'meta' => 'text?',
                ],
                'unique' => [['token_hash']],
            ],
            'rate_limits' => [
                'cols' => ['id' => 'id', 'bucket' => 'str:120', 'hit_at' => 'bigint'],
                'index' => [['bucket', 'hit_at']],
            ],
            'audit_log' => [
                'cols' => [
                    'id' => 'id', 'at' => 'datetime', 'user_id' => 'bigint?', 'role' => 'str:20?', 'action' => 'str:60',
                    'entity' => 'str:40?', 'entity_id' => 'str:40?', 'ip' => 'str:45?', 'meta' => 'text?',
                ],
                'index' => [['at'], ['user_id'], ['entity', 'entity_id'], ['action']],
            ],
            'clients' => [
                'cols' => [
                    'id' => 'id', 'number' => 'str:12', 'type' => 'str:20=individual', 'display_name' => 'str:160',
                    'is_codename' => 'int=0', 'status' => 'str:20=active', 'vault_dir' => 'char:64', 'dek_wrapped' => 'text?',
                    'risk_level' => 'str:20=standard', 'notes_enc' => 'longtext?', 'screening' => 'text?',
                    'created_by' => 'bigint?', 'created_at' => 'datetime', 'updated_at' => 'datetime', 'erased_at' => 'datetime?',
                ],
                'unique' => [['number'], ['vault_dir']],
                'index' => [['status']],
            ],
            'contacts' => [
                'cols' => [
                    'id' => 'id', 'client_id' => 'bigint', 'kind' => 'str:20=primary', 'name' => 'str:120',
                    'email' => 'str:190?', 'organisation' => 'str:160?', 'notes_enc' => 'text?', 'created_at' => 'datetime',
                ],
                'index' => [['client_id']],
            ],
            'client_advisers' => [
                'cols' => ['client_id' => 'bigint', 'user_id' => 'bigint', 'created_at' => 'datetime'],
                'pk' => ['client_id', 'user_id'],
            ],
            'cases' => [
                'cols' => [
                    'id' => 'id', 'ref' => 'str:20', 'client_id' => 'bigint?', 'title' => 'str:190',
                    'status' => 'str:20=lead', 'stage' => 'str:20=diagnose', 'priority' => 'str:20=medium',
                    'source' => 'str:30=manual', 'subject_type' => 'str:20?', 'issue' => 'str:30?', 'jurisdiction' => 'str:10?',
                    'platform' => 'str:40?', 'appeal_history' => 'str:20?',
                    'urgency' => 'int=1', 'services' => 'text?', 'lead_user_id' => 'bigint?', 'nda_required' => 'int=1',
                    'nda_signed_at' => 'datetime?', 'intake_enc' => 'longtext?', 'intake_email_hash' => 'char:64?',
                    'decline_reason' => 'text?', 'screening' => 'text?', 'opened_at' => 'datetime?', 'closed_at' => 'datetime?',
                    'created_at' => 'datetime', 'updated_at' => 'datetime', 'last_activity_at' => 'datetime',
                ],
                'unique' => [['ref']],
                'index' => [['client_id'], ['status'], ['priority'], ['lead_user_id'], ['intake_email_hash'], ['last_activity_at'], ['platform']],
            ],
            'case_staff' => [
                'cols' => ['case_id' => 'bigint', 'user_id' => 'bigint', 'created_at' => 'datetime'],
                'pk' => ['case_id', 'user_id'],
            ],
            'targets' => [
                'cols' => [
                    'id' => 'id', 'case_id' => 'bigint', 'url_enc' => 'text', 'platform' => 'str:60', 'route' => 'str:60',
                    'strength' => 'str:20=case-dependent', 'status' => 'str:20=drafted', 'platform_ref' => 'str:120?',
                    'submitted_on' => 'date?', 'decided_on' => 'date?', 'outcome_enc' => 'text?', 'client_visible' => 'int=1',
                    'created_by' => 'bigint?', 'created_at' => 'datetime', 'updated_at' => 'datetime',
                ],
                'index' => [['case_id'], ['status'], ['platform']],
            ],
            'posts' => [
                'cols' => [
                    'id' => 'id', 'slug' => 'str:160', 'title' => 'str:190', 'excerpt' => 'str:300?', 'body' => 'longtext',
                    'meta_title' => 'str:70?', 'meta_desc' => 'str:170?', 'category' => 'str:40?', 'platforms' => 'str:255?',
                    'status' => 'str:20=draft', 'author_id' => 'bigint?', 'published_at' => 'datetime?',
                    'created_at' => 'datetime', 'updated_at' => 'datetime',
                ],
                'unique' => [['slug']],
                'index' => [['status', 'published_at']],
            ],
            'testimonials' => [
                'cols' => [
                    'id' => 'id', 'name' => 'str:120', 'role' => 'str:120?', 'platform' => 'str:40?', 'service' => 'str:40?',
                    'rating' => 'int=5', 'body' => 'text', 'given_on' => 'date', 'consent_ref' => 'str:200', 'client_id' => 'bigint?',
                    'status' => 'str:20=pending', 'created_by' => 'bigint?', 'approved_by' => 'bigint?', 'approved_at' => 'datetime?',
                    'created_at' => 'datetime', 'updated_at' => 'datetime',
                ],
                'index' => [['status'], ['platform']],
            ],
            'redirects' => [
                'cols' => ['id' => 'id', 'from_path' => 'str:255', 'to_path' => 'str:255', 'hits' => 'int=0', 'created_by' => 'bigint?', 'created_at' => 'datetime'],
                'unique' => [['from_path']],
            ],
            'funds' => [
                'cols' => [
                    'id' => 'id', 'case_id' => 'bigint', 'platform' => 'str:60', 'amount' => 'bigint=0', 'released' => 'bigint=0',
                    'currency' => 'char:3=USD', 'status' => 'str:20=held', 'held_since' => 'date?', 'expected_on' => 'date?',
                    'reference_enc' => 'text?', 'notes_enc' => 'text?', 'client_visible' => 'int=1',
                    'created_by' => 'bigint?', 'created_at' => 'datetime', 'updated_at' => 'datetime',
                ],
                'index' => [['case_id'], ['status']],
            ],
            'folders' => [
                'cols' => [
                    'id' => 'id', 'client_id' => 'bigint', 'case_id' => 'bigint?', 'parent_id' => 'bigint?', 'name_enc' => 'text',
                    'created_by' => 'bigint?', 'created_at' => 'datetime',
                ],
                'index' => [['client_id'], ['case_id']],
            ],
            'documents' => [
                'cols' => [
                    'id' => 'id', 'client_id' => 'bigint', 'case_id' => 'bigint?', 'folder_id' => 'bigint?',
                    'name_enc' => 'text', 'mime' => 'str:120', 'size' => 'bigint', 'storage_id' => 'char:48',
                    'sha256' => 'char:64', 'visibility' => 'str:20=shared', 'version' => 'int=1', 'root_id' => 'bigint?',
                    'is_current' => 'int=1', 'message_id' => 'bigint?', 'uploaded_by' => 'bigint?', 'uploaded_role' => 'str:20?',
                    'created_at' => 'datetime', 'deleted_at' => 'datetime?',
                ],
                'unique' => [['storage_id']],
                'index' => [['client_id'], ['case_id'], ['folder_id'], ['root_id']],
            ],
            'uploads' => [
                'cols' => [
                    'id' => 'char:32', 'user_id' => 'bigint', 'client_id' => 'bigint', 'case_id' => 'bigint?', 'folder_id' => 'bigint?',
                    'name_enc' => 'text', 'size' => 'bigint', 'chunks' => 'int', 'received' => 'int=0', 'visibility' => 'str:20',
                    'replaces_id' => 'bigint?', 'message_id' => 'bigint?', 'state_enc' => 'text?', 'hashes' => 'longtext?',
                    'mime' => 'str:120?', 'storage_id' => 'char:48?', 'created_at' => 'datetime',
                ],
                'pk' => 'id',
            ],
            'messages' => [
                'cols' => [
                    'id' => 'id', 'case_id' => 'bigint', 'sender_id' => 'bigint', 'body_enc' => 'longtext', 'internal' => 'int=0',
                    'created_at' => 'datetime',
                ],
                'index' => [['case_id', 'id']],
            ],
            'case_reads' => [
                'cols' => ['case_id' => 'bigint', 'user_id' => 'bigint', 'last_message_id' => 'bigint=0', 'read_at' => 'datetime'],
                'pk' => ['case_id', 'user_id'],
            ],
            'tasks' => [
                'cols' => [
                    'id' => 'id', 'title' => 'str:190', 'description_enc' => 'text?', 'status' => 'str:20=open',
                    'priority' => 'str:20=medium', 'due_on' => 'date?', 'case_id' => 'bigint?', 'assignee_id' => 'bigint?',
                    'created_by' => 'bigint?', 'created_at' => 'datetime', 'completed_at' => 'datetime?',
                ],
                'index' => [['assignee_id', 'status'], ['case_id'], ['due_on']],
            ],
            'deadlines' => [
                'cols' => [
                    'id' => 'id', 'title' => 'str:190', 'kind' => 'str:30=other', 'due_at' => 'datetime', 'remind_days' => 'int=2',
                    'status' => 'str:20=upcoming', 'case_id' => 'bigint?', 'notes_enc' => 'text?', 'client_visible' => 'int=0',
                    'reminded_at' => 'datetime?', 'created_by' => 'bigint?', 'created_at' => 'datetime',
                ],
                'index' => [['due_at'], ['case_id'], ['status']],
            ],
            'catalog' => [
                'cols' => [
                    'id' => 'id', 'name' => 'str:160', 'description' => 'text?', 'unit_price' => 'bigint=0',
                    'vat_bp' => 'int=0', 'active' => 'int=1', 'created_at' => 'datetime',
                ],
            ],
            'invoices' => [
                'cols' => [
                    'id' => 'id', 'number' => 'str:24', 'client_id' => 'bigint', 'case_id' => 'bigint?', 'status' => 'str:20=draft',
                    'currency' => 'char:3=USD', 'issued_on' => 'date', 'due_on' => 'date', 'subtotal' => 'bigint=0',
                    'discount' => 'bigint=0', 'vat' => 'bigint=0', 'total' => 'bigint=0', 'paid' => 'bigint=0', 'notes' => 'text?',
                    'created_by' => 'bigint?', 'created_at' => 'datetime', 'updated_at' => 'datetime',
                ],
                'unique' => [['number']],
                'index' => [['client_id'], ['status']],
            ],
            'invoice_items' => [
                'cols' => [
                    'id' => 'id', 'invoice_id' => 'bigint', 'description' => 'str:255', 'qty_x100' => 'int=100',
                    'unit_price' => 'bigint=0', 'vat_bp' => 'int=0', 'line_total' => 'bigint=0', 'sort' => 'int=0',
                ],
                'index' => [['invoice_id']],
            ],
            'payments' => [
                'cols' => [
                    'id' => 'id', 'invoice_id' => 'bigint', 'amount' => 'bigint', 'method' => 'str:20', 'paid_on' => 'date',
                    'reference' => 'str:120?', 'created_by' => 'bigint?', 'created_at' => 'datetime',
                ],
                'index' => [['invoice_id']],
            ],
            'nda_signatures' => [
                'cols' => [
                    'id' => 'id', 'case_id' => 'bigint', 'user_id' => 'bigint', 'signed_name' => 'str:160', 'text_enc' => 'longtext',
                    'text_hash' => 'char:64', 'ip' => 'str:45', 'user_agent' => 'str:255', 'signed_at' => 'datetime',
                ],
                'index' => [['case_id']],
            ],
            'notifications' => [
                'cols' => [
                    'id' => 'id', 'user_id' => 'bigint', 'kind' => 'str:40', 'title' => 'str:190', 'link' => 'str:255?',
                    'created_at' => 'datetime', 'read_at' => 'datetime?',
                ],
                'index' => [['user_id', 'read_at']],
            ],
        ];
    }

    /** Render DDL statements for a driver. */
    public static function ddl(string $driver): array
    {
        $out = [];
        foreach (self::tables() as $name => $t) {
            $lines = [];
            $pk = $t['pk'] ?? null;
            foreach ($t['cols'] as $col => $spec) {
                $lines[] = '  ' . $col . ' ' . self::colType($spec, $driver, $pk === $col);
            }
            if (is_array($pk)) {
                $lines[] = '  PRIMARY KEY (' . implode(', ', $pk) . ')';
            }
            $suffix = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
            $out[] = 'CREATE TABLE IF NOT EXISTS ' . $name . " (\n" . implode(",\n", $lines) . "\n)" . $suffix;
            foreach ($t['unique'] ?? [] as $cols) {
                $out[] = 'CREATE UNIQUE INDEX ' . ($driver === 'sqlite' ? 'IF NOT EXISTS ' : '') . 'ux_' . $name . '_' . implode('_', $cols)
                    . ' ON ' . $name . ' (' . implode(', ', $cols) . ')';
            }
            foreach ($t['index'] ?? [] as $cols) {
                $out[] = 'CREATE INDEX ' . ($driver === 'sqlite' ? 'IF NOT EXISTS ' : '') . 'ix_' . $name . '_' . implode('_', $cols)
                    . ' ON ' . $name . ' (' . implode(', ', $cols) . ')';
            }
        }
        return $out;
    }

    private static function colType(string $spec, string $driver, bool $isPk): string
    {
        $default = null;
        if (str_contains($spec, '=')) {
            [$spec, $default] = explode('=', $spec, 2);
        }
        $nullable = str_ends_with($spec, '?');
        $spec = rtrim($spec, '?');
        [$type, $len] = array_pad(explode(':', $spec), 2, null);
        if ($type === 'id') {
            return $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        }
        $sqlType = match ($type) {
            'int' => $driver === 'sqlite' ? 'INTEGER' : 'INT',
            'bigint' => $driver === 'sqlite' ? 'INTEGER' : 'BIGINT',
            'str' => 'VARCHAR(' . (int) $len . ')',
            'char' => 'CHAR(' . (int) $len . ')',
            'text' => 'TEXT',
            'longtext' => $driver === 'sqlite' ? 'TEXT' : 'MEDIUMTEXT',
            'date' => $driver === 'sqlite' ? 'TEXT' : 'DATE',
            'datetime' => $driver === 'sqlite' ? 'TEXT' : 'DATETIME',
            default => throw new \RuntimeException('Unknown type ' . $type),
        };
        $s = $sqlType . ($nullable ? ' NULL' : ' NOT NULL');
        if ($default !== null) {
            $s .= ' DEFAULT ' . (ctype_digit($default) ? $default : "'" . str_replace("'", "''", $default) . "'");
        }
        if ($isPk) {
            $s .= ' PRIMARY KEY';
        }
        return $s;
    }

    public static function migrate(): void
    {
        $driver = Db::driver();
        foreach (self::ddl($driver) as $sql) {
            try {
                Db::pdo()->exec($sql);
            } catch (\PDOException $e) {
                // MySQL has no "IF NOT EXISTS" for indexes; ignore duplicate index errors on re-run.
                if ($driver === 'mysql' && str_contains($e->getMessage(), 'Duplicate key name')) {
                    continue;
                }
                throw $e;
            }
        }
        Settings::set('schema_version', (string) self::VERSION);
    }
}
