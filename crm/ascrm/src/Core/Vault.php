<?php
declare(strict_types=1);

namespace DR\Core;

/**
 * Encrypted document vault.
 *
 *  - Each client has a folder named with 64 random hex characters, outside the web root.
 *  - Files are stored under random 48-character names with no extension.
 *  - Uploads are encrypted chunk by chunk AS THEY ARRIVE (XChaCha20-Poly1305 secretstream);
 *    plaintext is never written to disk. The stream state between chunks is itself sealed.
 *  - Downloads are decrypted on the fly after access checks and every one is audited.
 */
final class Vault
{
    public const CHUNK_BYTES = 4 * 1024 * 1024;
    private const PUSH_BYTES = 65536;

    /** extension => allowed detected MIME types */
    public const ALLOWED = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'gif' => ['image/gif'], 'webp' => ['image/webp'],
        'heic' => ['image/heic', 'image/heif'], 'txt' => ['text/plain'], 'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'rtf' => ['text/rtf', 'application/rtf'], 'eml' => ['message/rfc822', 'text/plain'], 'msg' => ['application/vnd.ms-outlook', 'application/CDFV2', 'application/x-ole-storage'],
        'doc' => ['application/msword', 'application/CDFV2', 'application/x-ole-storage'],
        'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/CDFV2', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'], 'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'mp4' => ['video/mp4'], 'mov' => ['video/quicktime'], 'mp3' => ['audio/mpeg'], 'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4'],
        'wav' => ['audio/x-wav', 'audio/wav'],
    ];
    /** Types a browser may render inline (never HTML, SVG or scripts). */
    private const INLINE = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    public static function root(): string
    {
        return rtrim((string) Config::get('vault_path', DR_STORAGE . '/vault'), '/');
    }

    public static function clientDir(array $client): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', (string) $client['vault_dir'])) {
            throw new \RuntimeException('Invalid vault folder');
        }
        $dir = self::root() . '/' . $client['vault_dir'];
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        return $dir;
    }

    public static function pathFor(array $client, string $storageId): string
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $storageId)) {
            throw new \RuntimeException('Invalid storage id');
        }
        return self::clientDir($client) . '/' . $storageId;
    }

    public static function maxBytes(): int
    {
        return max(1, Settings::int('upload_max_mb')) * 1024 * 1024;
    }

    public static function extensionOf(string $name): string
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    public static function cleanName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '_', $name) ?? 'file';
        $name = trim($name, " .\t");
        return mb_substr($name !== '' ? $name : 'file', 0, 180);
    }

    // ---------------------------------------------------------------- chunked, encrypt-on-arrival upload

    public static function start(array $user, array $client, ?int $caseId, ?int $folderId, string $name, int $size, string $visibility, ?int $replacesId, ?int $messageId): array
    {
        $name = self::cleanName($name);
        $ext = self::extensionOf($name);
        if (!isset(self::ALLOWED[$ext])) {
            throw new HttpError('That file type is not accepted. Allowed: ' . implode(', ', array_keys(self::ALLOWED)) . '.', 422);
        }
        if ($size < 1 || $size > self::maxBytes()) {
            throw new HttpError('Files must be between 1 byte and ' . Settings::int('upload_max_mb') . ' MB.', 413);
        }
        $chunks = (int) ceil($size / self::CHUNK_BYTES);
        $id = Crypto::randomHex(16);
        $storageId = Crypto::randomHex(24);
        $key = Crypto::clientKey((int) $client['id']);
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        $path = self::pathFor($client, $storageId);
        file_put_contents($path, "DRV1" . $header, LOCK_EX);
        @chmod($path, 0600);
        Db::insert('uploads', [
            'id' => $id, 'user_id' => (int) $user['id'], 'client_id' => (int) $client['id'], 'case_id' => $caseId, 'folder_id' => $folderId,
            'name_enc' => Crypto::forClient((int) $client['id'], $name), 'size' => $size, 'chunks' => $chunks, 'received' => 0,
            'visibility' => $visibility, 'replaces_id' => $replacesId, 'message_id' => $messageId,
            'state_enc' => Crypto::forSystem('general', $state), 'hashes' => '', 'mime' => null, 'storage_id' => $storageId,
            'created_at' => now(),
        ]);
        return ['id' => $id, 'chunk_bytes' => self::CHUNK_BYTES, 'chunks' => $chunks];
    }

    /** Append one chunk (must arrive in order). Returns received count. */
    public static function chunk(array $user, string $uploadId, int $index, string $bytes): int
    {
        $up = self::loadUpload($user, $uploadId);
        if ($index !== (int) $up['received']) {
            throw new HttpError('Chunks must be sent in order.', 422);
        }
        $isLast = $index === (int) $up['chunks'] - 1;
        $expected = $isLast ? (int) $up['size'] - $index * self::CHUNK_BYTES : self::CHUNK_BYTES;
        if (strlen($bytes) !== $expected) {
            throw new HttpError('Chunk size mismatch.', 422);
        }
        $client = Db::one('SELECT * FROM clients WHERE id = ?', [(int) $up['client_id']]);
        $mime = $up['mime'];
        if ($index === 0) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
            $name = (string) Crypto::fromClient((int) $up['client_id'], $up['name_enc']);
            $ext = self::extensionOf($name);
            if (!in_array($mime, self::ALLOWED[$ext] ?? [], true)) {
                self::abort($up, $client);
                Audit::log('upload_rejected', 'client', (int) $up['client_id'], ['ext' => $ext, 'detected' => $mime], $user);
                throw new HttpError('The file content does not match its type (.' . $ext . '). Upload rejected.', 422);
            }
        }
        $state = (string) Crypto::fromSystem('general', $up['state_enc']);
        $fh = fopen(self::pathFor($client, $up['storage_id']), 'ab');
        $len = strlen($bytes);
        for ($off = 0; $off < $len; $off += self::PUSH_BYTES) {
            $piece = substr($bytes, $off, self::PUSH_BYTES);
            $final = $isLast && ($off + self::PUSH_BYTES >= $len);
            $tag = $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            $c = sodium_crypto_secretstream_xchacha20poly1305_push($state, $piece, '', $tag);
            fwrite($fh, pack('N', strlen($c)) . $c);
        }
        fclose($fh);
        Db::update('uploads', [
            'state_enc' => Crypto::forSystem('general', $state), 'received' => $index + 1, 'mime' => $mime,
            'hashes' => $up['hashes'] . hash('sha256', $bytes),
        ], 'id = :id', ['id' => $uploadId]);
        sodium_memzero($state);
        return $index + 1;
    }

    /** Finalise: create the document row. */
    public static function finish(array $user, string $uploadId): array
    {
        $up = self::loadUpload($user, $uploadId);
        if ((int) $up['received'] !== (int) $up['chunks']) {
            throw new HttpError('Upload incomplete.', 422);
        }
        $version = 1;
        $rootId = null;
        if ($up['replaces_id']) {
            $prev = Db::one('SELECT * FROM documents WHERE id = ? AND client_id = ?', [(int) $up['replaces_id'], (int) $up['client_id']]);
            if ($prev) {
                $rootId = (int) ($prev['root_id'] ?: $prev['id']);
                $version = (int) Db::value('SELECT MAX(version) FROM documents WHERE root_id = ? OR id = ?', [$rootId, $rootId]) + 1;
                Db::run('UPDATE documents SET is_current = 0 WHERE root_id = ? OR id = ?', [$rootId, $rootId]);
            }
        }
        $docId = Db::insert('documents', [
            'client_id' => (int) $up['client_id'], 'case_id' => $up['case_id'] ? (int) $up['case_id'] : null,
            'folder_id' => $up['folder_id'] ? (int) $up['folder_id'] : null, 'name_enc' => $up['name_enc'], 'mime' => (string) $up['mime'],
            'size' => (int) $up['size'], 'storage_id' => $up['storage_id'], 'sha256' => hash('sha256', (string) $up['hashes']),
            'visibility' => $up['visibility'], 'version' => $version, 'root_id' => $rootId, 'is_current' => 1,
            'message_id' => $up['message_id'] ? (int) $up['message_id'] : null, 'uploaded_by' => (int) $user['id'],
            'uploaded_role' => $user['role'], 'created_at' => now(), 'deleted_at' => null,
        ]);
        Db::run('DELETE FROM uploads WHERE id = ?', [$uploadId]);
        Audit::log('document_uploaded', 'document', $docId, ['client_id' => (int) $up['client_id'], 'case_id' => $up['case_id'] ? (int) $up['case_id'] : null, 'size' => (int) $up['size'], 'version' => $version], $user);
        return Db::one('SELECT * FROM documents WHERE id = ?', [$docId]) ?? [];
    }

    private static function loadUpload(array $user, string $id): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            throw new HttpError('Upload not found.', 404);
        }
        $up = Db::one('SELECT * FROM uploads WHERE id = ? AND user_id = ?', [$id, (int) $user['id']]);
        if (!$up) {
            throw new HttpError('Upload not found or expired.', 404);
        }
        return $up;
    }

    private static function abort(array $up, ?array $client): void
    {
        if ($client && $up['storage_id']) {
            @unlink(self::pathFor($client, $up['storage_id']));
        }
        Db::run('DELETE FROM uploads WHERE id = ?', [$up['id']]);
    }

    /** Remove abandoned uploads older than a day. */
    public static function pruneUploads(): int
    {
        $n = 0;
        foreach (Db::all('SELECT * FROM uploads WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]) as $up) {
            self::abort($up, Db::one('SELECT * FROM clients WHERE id = ?', [(int) $up['client_id']]));
            $n++;
        }
        return $n;
    }

    // ---------------------------------------------------------------- download

    public static function stream(array $doc, bool $inline = false): never
    {
        $client = Db::one('SELECT * FROM clients WHERE id = ?', [(int) $doc['client_id']]);
        if (!$client || !$client['dek_wrapped']) {
            throw new HttpError('File unavailable.', 410);
        }
        $name = (string) Crypto::fromClient((int) $client['id'], $doc['name_enc']);
        $path = self::pathFor($client, $doc['storage_id']);
        if (!is_file($path)) {
            throw new HttpError('File missing from the vault.', 410);
        }
        $canInline = $inline && in_array($doc['mime'], self::INLINE, true);
        Session::save();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . ($canInline ? $doc['mime'] : 'application/octet-stream'));
        header('Content-Length: ' . (int) $doc['size']);
        $ascii = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: 'file';
        header('Content-Disposition: ' . ($canInline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');
        $out = fopen('php://output', 'wb');
        Crypto::decryptFileTo($path, $out, Crypto::clientKey((int) $client['id']));
        fclose($out);
        exit;
    }

    /** Decrypt into an open handle (used by exports). */
    public static function decryptTo(array $doc, $handle): void
    {
        $client = Db::one('SELECT * FROM clients WHERE id = ?', [(int) $doc['client_id']]);
        Crypto::decryptFileTo(self::pathFor($client, $doc['storage_id']), $handle, Crypto::clientKey((int) $client['id']));
    }

    /** Decrypt frame by frame to a callback (streaming exports; no plaintext on disk). */
    public static function decryptEach(array $doc, callable $emit): void
    {
        $client = Db::one('SELECT * FROM clients WHERE id = ?', [(int) $doc['client_id']]);
        Crypto::decryptFileEach(self::pathFor($client, $doc['storage_id']), Crypto::clientKey((int) $client['id']), $emit);
    }

    /** Crypto-shred a client's vault: delete every file and the folder. */
    public static function shredClient(array $client): int
    {
        $dir = self::root() . '/' . $client['vault_dir'];
        $n = 0;
        if (is_dir($dir)) {
            foreach (scandir($dir) ?: [] as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                $p = $dir . '/' . $f;
                $size = filesize($p) ?: 0;
                $fh = fopen($p, 'r+b');
                if ($fh) {
                    for ($o = 0; $o < $size; $o += 65536) {
                        fwrite($fh, random_bytes((int) min(65536, $size - $o)));
                    }
                    fclose($fh);
                }
                unlink($p);
                $n++;
            }
            rmdir($dir);
        }
        return $n;
    }
}
