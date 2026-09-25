<?php
declare(strict_types=1);

namespace DR\Core;

/**
 * Envelope encryption with libsodium.
 *
 *  master key (32 bytes, file outside web root)
 *    ├── wraps each client's data key (DEK)            -> clients.dek_wrapped
 *    └── derives system subkeys (intake, totp, general) via crypto_kdf
 *  client DEK
 *    ├── seals client text fields (messages, notes, URLs, file names)
 *    └── encrypts files (XChaCha20-Poly1305 secretstream)
 */
final class Crypto
{
    private const KDF_CTX = 'DRVAULT1';
    private const SUBKEYS = ['intake' => 1, 'totp' => 2, 'general' => 3, 'backup' => 4, 'hmac' => 5];

    private static ?string $master = null;
    /** @var array<int,string> */
    private static array $dekCache = [];

    public static function masterKeyPath(): string
    {
        return (string) Config::get('master_key_path', DR_ROOT . '/keys/master.key');
    }

    public static function master(): string
    {
        if (self::$master === null) {
            $path = self::masterKeyPath();
            if (!is_file($path)) {
                throw new \RuntimeException('Master key missing.');
            }
            $raw = trim((string) file_get_contents($path));
            $key = sodium_hex2bin($raw);
            if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                throw new \RuntimeException('Master key invalid.');
            }
            self::$master = $key;
        }
        return self::$master;
    }

    public static function generateMasterKey(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $key = sodium_crypto_secretbox_keygen();
        file_put_contents($path, sodium_bin2hex($key) . "\n", LOCK_EX);
        @chmod($path, 0600);
        self::$master = null;
        self::$dekCache = [];
    }

    public static function subkey(string $purpose, ?string $master = null): string
    {
        if (!isset(self::SUBKEYS[$purpose])) {
            throw new \InvalidArgumentException('Unknown subkey');
        }
        return sodium_crypto_kdf_derive_from_key(32, self::SUBKEYS[$purpose], self::KDF_CTX, $master ?? self::master());
    }

    /** Encrypt a string with a 32-byte key. Output: "v1." + base64(nonce || ciphertext). */
    public static function seal(string $plain, string $key): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'v1.' . sodium_bin2base64($nonce . sodium_crypto_secretbox($plain, $nonce, $key), SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    public static function open(?string $sealed, string $key): ?string
    {
        if ($sealed === null || $sealed === '') {
            return null;
        }
        if (!str_starts_with($sealed, 'v1.')) {
            throw new \RuntimeException('Unknown ciphertext format');
        }
        $raw = sodium_base642bin(substr($sealed, 3), SODIUM_BASE64_VARIANT_ORIGINAL);
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
        if ($plain === false) {
            throw new \RuntimeException('Decryption failed: data altered or wrong key');
        }
        return $plain;
    }

    /** Create a new client data key, return [dek, wrapped]. */
    public static function newClientKey(): array
    {
        $dek = sodium_crypto_secretbox_keygen();
        return [$dek, self::seal($dek, self::master())];
    }

    public static function clientKey(int $clientId): string
    {
        if (isset(self::$dekCache[$clientId])) {
            return self::$dekCache[$clientId];
        }
        $wrapped = Db::value('SELECT dek_wrapped FROM clients WHERE id = ?', [$clientId]);
        if (!$wrapped) {
            throw new \RuntimeException('Client key unavailable (client erased or missing).');
        }
        return self::$dekCache[$clientId] = (string) self::open((string) $wrapped, self::master());
    }

    public static function forClient(int $clientId, ?string $plain): ?string
    {
        return ($plain === null || $plain === '') ? null : self::seal($plain, self::clientKey($clientId));
    }

    public static function fromClient(int $clientId, ?string $sealed): ?string
    {
        return self::open($sealed, self::clientKey($clientId));
    }

    public static function forSystem(string $purpose, ?string $plain): ?string
    {
        return ($plain === null || $plain === '') ? null : self::seal($plain, self::subkey($purpose));
    }

    public static function fromSystem(string $purpose, ?string $sealed): ?string
    {
        return self::open($sealed, self::subkey($purpose));
    }

    /** Keyed hash for lookups without storing plaintext (e.g. intake email). */
    public static function lookupHash(string $value): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($value)), self::subkey('hmac'));
    }

    public static function randomHex(int $bytes): string
    {
        return bin2hex(random_bytes($bytes));
    }

    // ------------------------------------------------------------------ files

    private const FILE_MAGIC = "DRV1";
    private const CHUNK = 65536;

    /** Stream-encrypt $src into $dst with key. Returns sha256 of plaintext. */
    public static function encryptFile(string $src, string $dst, string $key): string
    {
        $in = fopen($src, 'rb');
        $out = fopen($dst, 'wb');
        if (!$in || !$out) {
            throw new \RuntimeException('Cannot open files for encryption');
        }
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        fwrite($out, self::FILE_MAGIC . $header);
        $hash = hash_init('sha256');
        $chunk = fread($in, self::CHUNK);
        while ($chunk !== false) {
            $next = fread($in, self::CHUNK);
            $last = ($next === false || $next === '');
            hash_update($hash, $chunk);
            $tag = $last ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            $c = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);
            fwrite($out, pack('N', strlen($c)) . $c);
            if ($last) {
                break;
            }
            $chunk = $next;
        }
        fclose($in);
        fflush($out);
        fclose($out);
        @chmod($dst, 0600);
        return hash_final($hash);
    }

    /** Stream-decrypt to a writable stream (php://output or a file handle). */
    public static function decryptFileTo(string $src, $out, string $key): void
    {
        self::decryptFileEach($src, $key, static function (string $plain) use ($out): void {
            fwrite($out, $plain);
        });
    }

    /** Stream-decrypt, handing each authenticated plaintext frame to $emit. */
    public static function decryptFileEach(string $src, string $key, callable $emit): void
    {
        $in = fopen($src, 'rb');
        if (!$in) {
            throw new \RuntimeException('Encrypted file missing');
        }
        $magic = fread($in, 4);
        if ($magic !== self::FILE_MAGIC) {
            fclose($in);
            throw new \RuntimeException('Not a vault file');
        }
        $header = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
        $done = false;
        while (!feof($in)) {
            $lenRaw = fread($in, 4);
            if ($lenRaw === false || strlen($lenRaw) < 4) {
                break;
            }
            $len = unpack('N', $lenRaw)[1];
            $c = '';
            while (strlen($c) < $len && !feof($in)) {
                $c .= fread($in, $len - strlen($c));
            }
            $res = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $c);
            if ($res === false) {
                fclose($in);
                throw new \RuntimeException('File integrity check failed');
            }
            [$plain, $tag] = $res;
            $emit($plain);
            if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                $done = true;
                break;
            }
        }
        fclose($in);
        if (!$done) {
            throw new \RuntimeException('File truncated');
        }
    }

    /** Re-wrap every client key and system-sealed field under a new master key. */
    public static function rotateMaster(): array
    {
        $old = self::master();
        $newKey = sodium_crypto_secretbox_keygen();
        $counts = ['clients' => 0, 'intakes' => 0, 'totp' => 0, 'settings' => 0];
        Db::tx(function () use ($old, $newKey, &$counts): void {
            foreach (Db::all('SELECT id, dek_wrapped FROM clients WHERE dek_wrapped IS NOT NULL') as $c) {
                $dek = self::open($c['dek_wrapped'], $old);
                Db::update('clients', ['dek_wrapped' => self::seal((string) $dek, $newKey)], 'id = :id', ['id' => (int) $c['id']]);
                $counts['clients']++;
            }
            $oldIntake = self::subkey('intake', $old);
            $newIntake = self::subkey('intake', $newKey);
            foreach (Db::all('SELECT id, intake_enc FROM cases WHERE intake_enc IS NOT NULL') as $r) {
                Db::update('cases', ['intake_enc' => self::seal((string) self::open($r['intake_enc'], $oldIntake), $newIntake)], 'id = :id', ['id' => (int) $r['id']]);
                $counts['intakes']++;
            }
            $oldTotp = self::subkey('totp', $old);
            $newTotp = self::subkey('totp', $newKey);
            foreach (Db::all('SELECT id, totp_secret_enc FROM users WHERE totp_secret_enc IS NOT NULL') as $u) {
                Db::update('users', ['totp_secret_enc' => self::seal((string) self::open($u['totp_secret_enc'], $oldTotp), $newTotp)], 'id = :id', ['id' => (int) $u['id']]);
                $counts['totp']++;
            }
            $oldGen = self::subkey('general', $old);
            $newGen = self::subkey('general', $newKey);
            foreach (Db::all("SELECT k, v FROM settings WHERE v LIKE 'v1.%'") as $s) {
                Db::update('settings', ['v' => self::seal((string) self::open($s['v'], $oldGen), $newGen)], 'k = :k', ['k' => $s['k']]);
                $counts['settings']++;
            }
            foreach (Db::all('SELECT id, description_enc FROM tasks WHERE description_enc IS NOT NULL') as $t) {
                Db::update('tasks', ['description_enc' => self::seal((string) self::open($t['description_enc'], $oldGen), $newGen)], 'id = :id', ['id' => (int) $t['id']]);
            }
            foreach (Db::all('SELECT id, notes_enc FROM deadlines WHERE notes_enc IS NOT NULL') as $d) {
                Db::update('deadlines', ['notes_enc' => self::seal((string) self::open($d['notes_enc'], $oldGen), $newGen)], 'id = :id', ['id' => (int) $d['id']]);
            }
            foreach (Db::all("SELECT id, data FROM sessions WHERE revoked = 0 AND data LIKE '%totp_pending%'") as $s) {
                Db::update('sessions', ['revoked' => 1], 'id = :id', ['id' => $s['id']]);
            }
            // The HMAC subkey changes too: recompute intake email lookup hashes from the decrypted intake.
            $newHmac = self::subkey('hmac', $newKey);
            foreach (Db::all('SELECT id, intake_enc FROM cases WHERE intake_enc IS NOT NULL') as $r) {
                $data = json_decode((string) self::open($r['intake_enc'], $newIntake), true) ?: [];
                if (!empty($data['email'])) {
                    Db::update('cases', ['intake_email_hash' => hash_hmac('sha256', mb_strtolower(trim($data['email'])), $newHmac)], 'id = :id', ['id' => (int) $r['id']]);
                }
            }
            // Stage the new key before commit; it is moved into place only after the commit succeeds.
            $tmp = self::masterKeyPath() . '.new';
            file_put_contents($tmp, sodium_bin2hex($newKey) . "\n", LOCK_EX);
            @chmod($tmp, 0600);
        });
        $path = self::masterKeyPath();
        $prev = $path . '.prev-' . date('YmdHis');
        copy($path, $prev);
        @chmod($prev, 0600);
        rename($path . '.new', $path);
        self::$master = $newKey;
        self::$dekCache = [];
        return $counts;
    }
}
