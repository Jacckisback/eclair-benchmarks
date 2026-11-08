<?php
// Shared SVG drawing helpers for the charts (light and dark mode, validated colour palette, direct labels).
declare(strict_types=1);

/** Fixed colour per CMS set-up (colour follows the entity, never its rank). Bars always appear in this order. */
const SERIES_CLASS = ['eclair' => 's1', 'wordpress-cache' => 's2', 'wordpress-fastcgi' => 's3', 'wordpress' => 's4', 'kirby' => 's5'];
const SERIES_SHORT = ['eclair' => 'Éclair', 'wordpress-cache' => 'WordPress + Cache Enabler', 'wordpress-fastcgi' => 'WordPress + Nginx FastCGI cache',
    'wordpress' => 'WordPress', 'kirby' => 'Kirby'];

function svg_style(): string
{
    // Light: violet, orange, blue, aqua, yellow. Dark: the same hues stepped for the dark surface. Validated (adjacent pairs).
    return '<style>
  .bg{fill:#fcfcfb} .t1{fill:#0b0b0b} .t2{fill:#52514e} .t3{fill:#7a7974} .grid{stroke:#e6e5e0}
  .s1{fill:#6250d6;stroke:#6250d6} .s2{fill:#eb6834;stroke:#eb6834} .s3{fill:#2a78d6;stroke:#2a78d6} .s4{fill:#1baf7a;stroke:#1baf7a} .s5{fill:#eda100;stroke:#eda100}
  .ring{stroke:#fcfcfb} .arrow{stroke:#9a998f} text{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
  .num{font-variant-numeric:tabular-nums}
  @media (prefers-color-scheme: dark){
    .bg{fill:#1a1a19} .t1{fill:#f0efec} .t2{fill:#c3c2b7} .t3{fill:#9a998f} .grid{stroke:#33332f}
    .s1{fill:#9085e9;stroke:#9085e9} .s2{fill:#d95926;stroke:#d95926} .s3{fill:#3987e5;stroke:#3987e5} .s4{fill:#199e70;stroke:#199e70} .s5{fill:#c98500;stroke:#c98500}
    .ring{stroke:#1a1a19}
  }
</style>';
}

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function fmt(float $v, int $d = 0): string
{
    return number_format($v, $d, '.', ',');
}

function nice_max(float $v): float
{
    $p = 10 ** floor(log10(max($v, 1e-9)));
    foreach ([1, 1.2, 1.6, 2, 2.4, 3.2, 4, 5, 6, 8, 10] as $m) {
        if ($m * $p >= $v) {
            return $m * $p;
        }
    }
    return 10 * $p;
}

/** Bar with a 4px rounded data-end, square at the baseline. */
function svg_bar(float $x, float $y, float $w, float $h, string $class): string
{
    $w = max($w, 2.0);
    $rad = min(4.0, $w / 2, $h / 2);
    return sprintf('<path class="%s" stroke-width="0" d="M%.1f %.1fH%.1fa%.1f %.1f 0 0 1 %.1f %.1fV%.1fa%.1f %.1f 0 0 1 -%.1f %.1fH%.1fZ"/>',
        $class, $x, $y, $x + $w - $rad, $rad, $rad, $rad, $rad, $y + $h - $rad, $rad, $rad, $rad, $rad, $x);
}

function svg_open(int $W, int $H, string $title, string $sub): array
{
    return [sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img" aria-label="%s">', $W, $H, $W, $H, esc($title)),
        svg_style(), sprintf('<rect class="bg" width="%d" height="%d" rx="12"/>', $W, $H),
        sprintf('<text class="t1" x="24" y="36" font-size="19" font-weight="650">%s</text>', esc($title)),
        sprintf('<text class="t2" x="24" y="60" font-size="13.5">%s</text>', esc($sub))];
}

/**
 * Horizontal bars, one per CMS set-up, every bar labelled with its value.
 * $rows: [[seriesKey, label, value, valueText]]
 */
function hbars(string $file, string $title, string $sub, array $rows, string $axisUnit, string $note): void
{
    $W = 860;
    $left = 290;
    $right = 210;
    $top = 92;
    $rowH = 44;
    $plotW = $W - $left - $right;
    $H = $top + count($rows) * $rowH + 52;
    $max = nice_max(max(array_map(static fn ($r) => (float) $r[2], $rows)));
    $svg = svg_open($W, $H, $title, $sub);
    for ($i = 0; $i <= 4; $i++) {
        $x = $left + $plotW * $i / 4;
        $svg[] = sprintf('<line class="grid" x1="%.1f" y1="%d" x2="%.1f" y2="%d" stroke-width="1"/>', $x, $top - 8, $x, $top + count($rows) * $rowH - 6);
        $svg[] = sprintf('<text class="t3 num" x="%.1f" y="%d" font-size="11.5" text-anchor="middle">%s</text>', $x, $top + count($rows) * $rowH + 12,
            fmt($max * $i / 4, $max < 10 ? 1 : 0) . ($i === 4 ? ' ' . $axisUnit : ''));
    }
    foreach ($rows as $i => [$key, $label, $value, $text]) {
        $y = $top + $i * $rowH;
        $svg[] = sprintf('<text class="t1" x="%d" y="%.1f" font-size="14" text-anchor="end" font-weight="%d">%s</text>', $left - 14, $y + 16, $key === 'eclair' ? 650 : 450, esc($label));
        $w = $plotW * $value / $max;
        $svg[] = svg_bar($left, $y + 3, $w, 22, SERIES_CLASS[$key] ?? 's4');
        $svg[] = sprintf('<text class="t1 num" x="%.1f" y="%.1f" font-size="14" font-weight="650">%s</text>', $left + max($w, 2) + 8, $y + 19, esc($text));
    }
    $svg[] = sprintf('<text class="t2" x="24" y="%d" font-size="13">%s</text>', $H - 16, esc($note));
    $svg[] = '</svg>';
    file_put_contents($file, implode("\n", $svg) . "\n");
}

/**
 * Line chart, one line per CMS set-up, categorical x positions, end labels.
 * $lines: [seriesKey => [label, [xIndex => value]]], $xLabels: list of x labels.
 */
function lines(string $file, string $title, string $sub, array $xLabels, string $xTitle, string $yUnit, array $lines, callable $valueText, bool $log = false): void
{
    $W = 860;
    $H = 430;
    $left = 80;
    $right = 240;
    $top = 96;
    $bottom = 72;
    $plotW = $W - $left - $right;
    $plotH = $H - $top - $bottom;
    $all = array_merge(...array_map(static fn ($l) => array_values($l[1]), array_values($lines)));
    $max = nice_max(max($all));
    $min = $log ? 10 ** floor(log10(max(min($all), 1e-3))) : 0.0;
    if ($log) {
        $max = 10 ** ceil(log10($max));
    }
    $x = static fn (int $i): float => $left + $plotW * $i / max(1, count($xLabels) - 1);
    $y = static fn (float $v): float => $log
        ? $top + $plotH * (1 - (log10(max($v, $min)) - log10($min)) / (log10($max) - log10($min)))
        : $top + $plotH * (1 - $v / $max);
    $svg = svg_open($W, $H, $title, $sub);
    $ticks = [];
    if ($log) {
        for ($t = $min; $t <= $max * 1.0001; $t *= 10) {
            $ticks[] = $t;
        }
    } else {
        for ($i = 0; $i <= 4; $i++) {
            $ticks[] = $max * $i / 4;
        }
    }
    foreach ($ticks as $t) {
        $svg[] = sprintf('<line class="grid" x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke-width="1"/>', $left, $y($t), $left + $plotW, $y($t));
        $svg[] = sprintf('<text class="t3 num" x="%d" y="%.1f" font-size="11.5" text-anchor="end">%s</text>', $left - 10, $y($t) + 4, fmt($t, $t < 1 ? 1 : 0));
    }
    foreach ($xLabels as $i => $lab) {
        $svg[] = sprintf('<text class="t3 num" x="%.1f" y="%d" font-size="11.5" text-anchor="middle">%s</text>', $x($i), $top + $plotH + 20, esc((string) $lab));
    }
    $svg[] = sprintf('<text class="t2" x="%.1f" y="%d" font-size="12.5" text-anchor="middle">%s</text>', $left + $plotW / 2, $top + $plotH + 46, esc($xTitle));
    $svg[] = sprintf('<text class="t2" x="24" y="%d" font-size="12.5">%s</text>', $top - 16, esc($yUnit . ($log ? ' (log scale)' : '')));
    $ends = [];
    foreach ($lines as $key => [$label, $values]) {
        $pts = [];
        foreach ($values as $i => $v) {
            $pts[] = sprintf('%.1f,%.1f', $x($i), $y((float) $v));
        }
        $cls = SERIES_CLASS[$key] ?? 's4';
        $svg[] = sprintf('<polyline class="%s" style="fill:none" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" points="%s"/>', $cls, implode(' ', $pts));
        foreach ($values as $i => $v) {
            $svg[] = sprintf('<circle class="%s ring" cx="%.1f" cy="%.1f" r="5" stroke-width="2"/>', $cls, $x($i), $y((float) $v));
        }
        $last = array_key_last($values);
        $ends[$key] = [$y((float) $values[$last]), $label, $valueText((float) $values[$last])];
    }
    uasort($ends, static fn ($a, $b) => $a[0] <=> $b[0]);
    $prev = -INF;
    foreach ($ends as [$ly, $label, $text]) {
        $ly = max($ly, $prev + 34);
        $prev = $ly;
        $svg[] = sprintf('<text class="t1" x="%.1f" y="%.1f" font-size="13.5" font-weight="650">%s</text>', $left + $plotW + 14, $ly - 2, esc($label));
        $svg[] = sprintf('<text class="t2 num" x="%.1f" y="%.1f" font-size="12.5">%s</text>', $left + $plotW + 14, $ly + 14, esc($text));
    }
    $svg[] = '</svg>';
    file_put_contents($file, implode("\n", $svg) . "\n");
}
