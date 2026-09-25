<?php
declare(strict_types=1);

namespace DR\Service;

/** Server-rendered SVG charts (CSP-safe: classes and attributes only, no inline styles or scripts). */
final class Charts
{
    /**
     * Grouped bar chart. $labels: x labels; $series: [[class, name, values[]], ...]
     */
    public static function bars(array $labels, array $series, int $height = 220, ?callable $fmt = null): string
    {
        $w = 640;
        $padL = 40;
        $padB = 26;
        $padT = 12;
        $max = 0;
        foreach ($series as $k => $s) {
            $series[$k][2] = array_values(array_map('floatval', $s[2] ?: [0]));
            $max = max($max, ...$series[$k][2]);
        }
        $max = $max <= 0 ? 1 : self::nice($max);
        $n = max(1, count($labels));
        $plotW = $w - $padL - 8;
        $plotH = $height - $padB - $padT;
        $groupW = $plotW / $n;
        $barW = max(3, min(26, ($groupW - 8) / max(1, count($series))));
        $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $height . '" role="img" aria-label="Bar chart">';
        for ($i = 0; $i <= 4; $i++) {
            $y = $padT + $plotH - $plotH * $i / 4;
            $v = $max * $i / 4;
            $svg .= '<line class="grid-line" x1="' . $padL . '" x2="' . ($w - 8) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '"/>';
            $svg .= '<text class="axis" x="' . ($padL - 6) . '" y="' . round($y + 4, 1) . '" text-anchor="end">' . h($fmt ? $fmt($v) : (string) round($v)) . '</text>';
        }
        foreach (array_values($labels) as $i => $lab) {
            $gx = $padL + $groupW * $i + ($groupW - $barW * count($series)) / 2;
            foreach ($series as $si => [$cls, $name, $vals]) {
                $v = (float) ($vals[$i] ?? 0);
                $bh = $plotH * $v / $max;
                $x = $gx + $si * $barW;
                $svg .= '<rect class="' . h($cls) . '" x="' . round($x, 1) . '" y="' . round($padT + $plotH - $bh, 1) . '" width="' . round($barW - 2, 1) . '" height="' . round(max(0, $bh), 1) . '" rx="3"><title>'
                    . h($name . ', ' . $lab . ': ' . ($fmt ? $fmt($v) : (string) $v)) . '</title></rect>';
            }
            $svg .= '<text class="axis" x="' . round($padL + $groupW * $i + $groupW / 2, 1) . '" y="' . ($height - 8) . '" text-anchor="middle">' . h((string) $lab) . '</text>';
        }
        return $svg . '</svg>';
    }

    public static function donut(float $pct, string $label): string
    {
        $pct = max(0, min(100, $pct));
        $r = 50;
        $c = 2 * M_PI * $r;
        $dash = round($c * $pct / 100, 2);
        return '<svg class="donut" viewBox="0 0 130 130" role="img" aria-label="' . h($label . ' ' . round($pct) . '%') . '"><circle class="track" cx="65" cy="65" r="' . $r . '"/>'
            . '<circle class="val" cx="65" cy="65" r="' . $r . '" stroke-dasharray="' . $dash . ' ' . round($c, 2) . '"/>'
            . '<text x="65" y="68" text-anchor="middle">' . round($pct) . '%</text><text class="sub" x="65" y="86" text-anchor="middle">' . h($label) . '</text></svg>';
    }

    private static function nice(float $v): float
    {
        $exp = 10 ** floor(log10($v));
        $f = $v / $exp;
        $nf = $f <= 1 ? 1 : ($f <= 2 ? 2 : ($f <= 5 ? 5 : 10));
        return $nf * $exp;
    }
}
