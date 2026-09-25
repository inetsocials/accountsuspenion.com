<?php
declare(strict_types=1);

namespace DR\Service;

/**
 * Minimal streaming ZIP writer (stored, no compression) so decrypted exports go straight
 * to the browser and plaintext is never written to disk. Uses data descriptors, so sizes
 * and CRCs are written after each entry. Suitable for archives under 4 GB.
 */
final class ZipStream
{
    /** @var resource */
    private $out;
    private int $offset = 0;
    private array $central = [];

    /** @param resource $out */
    public function __construct($out)
    {
        $this->out = $out;
    }

    private function write(string $b): void
    {
        fwrite($this->out, $b);
        $this->offset += strlen($b);
    }

    private static function dos(int $ts): array
    {
        $d = getdate($ts);
        $time = ($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2);
        $date = ((max(1980, $d['year']) - 1980) << 9) | ($d['mon'] << 5) | $d['mday'];
        return [$time, $date];
    }

    public function addString(string $name, string $data): void
    {
        $this->addStream($name, static function (callable $emit) use ($data): void {
            $emit($data);
        });
    }

    /** $producer receives an $emit(string) callback and writes the entry in pieces. */
    public function addStream(string $name, callable $producer): void
    {
        $name = ltrim(str_replace('\\', '/', $name), '/');
        [$t, $d] = self::dos(time());
        $flags = 0x0008 | 0x0800; // data descriptor + UTF-8 names
        $start = $this->offset;
        $this->write(pack('VvvvvvVVVvv', 0x04034b50, 20, $flags, 0, $t, $d, 0, 0, 0, strlen($name), 0) . $name);
        $crc = hash_init('crc32b');
        $size = 0;
        $producer(function (string $chunk) use (&$crc, &$size): void {
            hash_update($crc, $chunk);
            $size += strlen($chunk);
            $this->write($chunk);
        });
        $crcVal = (int) hexdec(hash_final($crc));
        $this->write(pack('VVVV', 0x08074b50, $crcVal, $size, $size));
        $this->central[] = [$name, $t, $d, $crcVal, $size, $start, $flags];
    }

    public function finish(): void
    {
        $cdStart = $this->offset;
        foreach ($this->central as [$name, $t, $d, $crc, $size, $start, $flags]) {
            $this->write(pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, $flags, 0, $t, $d, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $start) . $name);
        }
        $cdSize = $this->offset - $cdStart;
        $n = count($this->central);
        $this->write(pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, $cdSize, $cdStart, 0));
    }
}
