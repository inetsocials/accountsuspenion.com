<?php
declare(strict_types=1);

namespace DR\Core;

/**
 * Minimal QR Code encoder (byte mode, error correction level M, versions 1 to 15)
 * rendered as SVG. Used for authenticator enrollment, so secrets never leave the server
 * to a third-party QR service.
 */
final class Qr
{
    /** version => [ec codewords per block, [[blocks, data codewords per block], ...]] for level M */
    private const EC_M = [
        1 => [10, [[1, 16]]], 2 => [16, [[1, 28]]], 3 => [26, [[1, 44]]], 4 => [18, [[2, 32]]], 5 => [24, [[2, 43]]],
        6 => [16, [[4, 27]]], 7 => [18, [[4, 31]]], 8 => [22, [[2, 38], [2, 39]]], 9 => [22, [[3, 36], [2, 37]]],
        10 => [26, [[4, 43], [1, 44]]], 11 => [30, [[1, 50], [4, 51]]], 12 => [22, [[6, 36], [2, 37]]],
        13 => [22, [[8, 37], [1, 38]]], 14 => [24, [[4, 40], [5, 41]]], 15 => [24, [[5, 41], [5, 42]]],
    ];
    private const ALIGN = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42],
        9 => [6, 26, 46], 10 => [6, 28, 50], 11 => [6, 30, 54], 12 => [6, 32, 58], 13 => [6, 34, 62], 14 => [6, 26, 46, 66],
        15 => [6, 26, 48, 70],
    ];

    private int $size;
    /** @var array<int,array<int,bool>> */
    private array $mod = [];
    /** @var array<int,array<int,bool>> */
    private array $fn = [];

    public static function svg(string $text, int $scale = 5): string
    {
        $q = new self();
        $m = $q->encode($text);
        $n = count($m);
        $border = 4;
        $dim = ($n + 2 * $border) * $scale;
        $path = '';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($m[$y][$x]) {
                    $path .= 'M' . (($x + $border) * $scale) . ',' . (($y + $border) * $scale) . 'h' . $scale . 'v' . $scale . 'h-' . $scale . 'z';
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" class="qr" viewBox="0 0 ' . $dim . ' ' . $dim . '" width="' . $dim . '" height="' . $dim
            . '" role="img" aria-label="QR code for your authenticator app"><rect width="100%" height="100%" fill="#fff"/><path fill="#000" d="' . $path . '"/></svg>';
    }

    /** @return array<int,array<int,bool>> */
    public function encode(string $text): array
    {
        $data = array_values(unpack('C*', $text) ?: []);
        $version = 0;
        foreach (self::EC_M as $v => [$ec, $groups]) {
            $cap = 0;
            foreach ($groups as [$b, $d]) {
                $cap += $b * $d;
            }
            $ccBits = $v < 10 ? 8 : 16;
            if (4 + $ccBits + 8 * count($data) <= $cap * 8) {
                $version = $v;
                break;
            }
        }
        if ($version === 0) {
            throw new \RuntimeException('QR payload too long');
        }
        [$ecLen, $groups] = self::EC_M[$version];
        $capacity = 0;
        foreach ($groups as [$b, $d]) {
            $capacity += $b * $d;
        }

        // Bit stream
        $bits = [];
        $push = static function (int $val, int $len) use (&$bits): void {
            for ($i = $len - 1; $i >= 0; $i--) {
                $bits[] = ($val >> $i) & 1;
            }
        };
        $push(0b0100, 4);
        $push(count($data), $version < 10 ? 8 : 16);
        foreach ($data as $byte) {
            $push($byte, 8);
        }
        $push(0, min(4, $capacity * 8 - count($bits)));
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        $cw = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $v = 0;
            for ($j = 0; $j < 8; $j++) {
                $v = ($v << 1) | $bits[$i + $j];
            }
            $cw[] = $v;
        }
        for ($pad = 0xEC; count($cw) < $capacity; $pad ^= 0xEC ^ 0x11) {
            $cw[] = $pad;
        }

        // Blocks + error correction, then interleave
        $blocks = [];
        $k = 0;
        foreach ($groups as [$b, $d]) {
            for ($i = 0; $i < $b; $i++) {
                $blocks[] = array_slice($cw, $k, $d);
                $k += $d;
            }
        }
        $div = self::rsDivisor($ecLen);
        $ecBlocks = array_map(static fn($blk) => self::rsRemainder($blk, $div), $blocks);
        $final = [];
        $maxLen = max(array_map('count', $blocks));
        for ($i = 0; $i < $maxLen; $i++) {
            foreach ($blocks as $blk) {
                if ($i < count($blk)) {
                    $final[] = $blk[$i];
                }
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($ecBlocks as $blk) {
                $final[] = $blk[$i];
            }
        }

        // Matrix
        $this->size = $version * 4 + 17;
        $n = $this->size;
        $this->mod = array_fill(0, $n, array_fill(0, $n, false));
        $this->fn = array_fill(0, $n, array_fill(0, $n, false));
        $this->drawFunctionPatterns($version);
        $this->drawCodewords($final);

        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        $base = $this->mod;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->mod = $base;
            $this->applyMask($mask);
            $this->drawFormatBits($mask);
            $p = $this->penalty();
            if ($p < $bestPenalty) {
                $bestPenalty = $p;
                $bestMask = $mask;
            }
        }
        $this->mod = $base;
        $this->applyMask($bestMask);
        $this->drawFormatBits($bestMask);
        return $this->mod;
    }

    private function set(int $x, int $y, bool $dark): void
    {
        $this->mod[$y][$x] = $dark;
        $this->fn[$y][$x] = true;
    }

    private function drawFunctionPatterns(int $version): void
    {
        $n = $this->size;
        for ($i = 0; $i < $n; $i++) {
            $this->set(6, $i, $i % 2 === 0);
            $this->set($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$n - 4, 3], [3, $n - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x < 0 || $y < 0 || $x >= $n || $y >= $n) {
                        continue;
                    }
                    $dist = max(abs($dx), abs($dy));
                    $this->set($x, $y, $dist !== 2 && $dist !== 4);
                }
            }
        }
        $pos = self::ALIGN[$version];
        $cnt = count($pos);
        for ($i = 0; $i < $cnt; $i++) {
            for ($j = 0; $j < $cnt; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $cnt - 1) || ($i === $cnt - 1 && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->set($pos[$i] + $dx, $pos[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        $this->drawFormatBits(0); // reserve
        if ($version >= 7) {
            $rem = $version;
            for ($i = 0; $i < 12; $i++) {
                $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            }
            $bits = ($version << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($bits >> $i) & 1) === 1;
                $a = $n - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->set($a, $b, $bit);
                $this->set($b, $a, $bit);
            }
        }
    }

    private function drawFormatBits(int $mask): void
    {
        $data = (0 << 3) | $mask; // level M = 00
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $n = $this->size;
        $g = static fn(int $i): bool => (($bits >> $i) & 1) === 1;
        for ($i = 0; $i <= 5; $i++) {
            $this->set(8, $i, $g($i));
        }
        $this->set(8, 7, $g(6));
        $this->set(8, 8, $g(7));
        $this->set(7, 8, $g(8));
        for ($i = 9; $i < 15; $i++) {
            $this->set(14 - $i, 8, $g($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->set($n - 1 - $i, 8, $g($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->set(8, $n - 15 + $i, $g($i));
        }
        $this->set(8, $n - 8, true);
    }

    private function drawCodewords(array $data): void
    {
        $n = $this->size;
        $total = count($data) * 8;
        $i = 0;
        for ($right = $n - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $n; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $n - 1 - $vert : $vert;
                    if (!$this->fn[$y][$x] && $i < $total) {
                        $this->mod[$y][$x] = (($data[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        $n = $this->size;
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($this->fn[$y][$x]) {
                    continue;
                }
                $inv = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    default => ((($x + $y) % 2) + ($x * $y) % 3) % 2 === 0,
                };
                if ($inv) {
                    $this->mod[$y][$x] = !$this->mod[$y][$x];
                }
            }
        }
    }

    private function penalty(): int
    {
        $n = $this->size;
        $m = $this->mod;
        $p = 0;
        for ($pass = 0; $pass < 2; $pass++) {
            for ($a = 0; $a < $n; $a++) {
                $run = 1;
                $prev = $pass === 0 ? $m[$a][0] : $m[0][$a];
                $line = [];
                for ($b = 0; $b < $n; $b++) {
                    $line[] = $pass === 0 ? $m[$a][$b] : $m[$b][$a];
                }
                for ($b = 1; $b < $n; $b++) {
                    if ($line[$b] === $prev) {
                        $run++;
                        if ($run === 5) {
                            $p += 3;
                        } elseif ($run > 5) {
                            $p++;
                        }
                    } else {
                        $run = 1;
                        $prev = $line[$b];
                    }
                }
                $s = implode('', array_map(static fn($v) => $v ? '1' : '0', $line));
                $p += 40 * (substr_count($s, '10111010000') + substr_count($s, '00001011101'));
            }
        }
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                $c = $m[$y][$x];
                if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                    $p += 3;
                }
            }
        }
        $dark = 0;
        foreach ($m as $row) {
            foreach ($row as $v) {
                $dark += $v ? 1 : 0;
            }
        }
        $k = intdiv(abs($dark * 20 - $n * $n * 10) + $n * $n - 1, $n * $n) - 1;
        return $p + max(0, $k) * 10;
    }

    private static function gfMul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z & 0xFF;
    }

    private static function rsDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMul($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gfMul($root, 0x02);
        }
        return $result;
    }

    private static function rsRemainder(array $data, array $div): array
    {
        $result = array_fill(0, count($div), 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($result);
            $result[] = 0;
            foreach ($div as $i => $coef) {
                $result[$i] ^= self::gfMul($coef, $factor);
            }
        }
        return $result;
    }
}
