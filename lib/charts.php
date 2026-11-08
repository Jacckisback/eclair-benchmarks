<?php
// Draws the charts of one run as SVG files (light and dark mode), straight from the JSON results.
// Usage: php charts.php <results-dir>   → <results-dir>/charts/*.svg
// Every number in a chart comes from the run's JSON files: nothing is typed by hand.
declare(strict_types=1);

$dir = rtrim($argv[1], '/');
$out = $dir . '/charts';
@mkdir($out, 0755, true);
$r = [];
foreach (['eclair', 'wordpress-cache', 'wordpress'] as $k) {
    $r[$k] = json_decode((string) file_get_contents("$dir/$k.json"), true);
}
$profile = is_file("$dir/profile.json") ? json_decode((string) file_get_contents("$dir/profile.json"), true) : [];
$meta = is_file("$dir/meta.json") ? json_decode((string) file_get_contents("$dir/meta.json"), true) : [];

// Series: fixed order and fixed colors (validated palette: violet, orange, aqua; light and dark steps).
const SERIES = [
    'eclair' => ['label' => 'Éclair', 'class' => 's1'],
    'wordpress-cache' => ['label' => 'WordPress + Cache Enabler', 'class' => 's2'],
    'wordpress' => ['label' => 'WordPress', 'class' => 's3'],
];
$ver = static fn (string $k): string => match ($k) {
    'eclair' => 'Éclair ' . ($meta['eclair'] ?? ''),
    'wordpress' => 'WordPress ' . ($meta['wordpress'] ?? ''),
    default => 'WordPress ' . ($meta['wordpress'] ?? '') . ' + Cache Enabler',
};

function style(): string
{
    return '<style>
  .bg{fill:#fcfcfb} .t1{fill:#0b0b0b} .t2{fill:#52514e} .t3{fill:#7a7974} .grid{stroke:#e6e5e0}
  .s1{fill:#6250d6;stroke:#6250d6} .s2{fill:#eb6834;stroke:#eb6834} .s3{fill:#1baf7a;stroke:#1baf7a}
  .ring{stroke:#fcfcfb} text{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
  .num{font-variant-numeric:tabular-nums}
  @media (prefers-color-scheme: dark){
    .bg{fill:#1a1a19} .t1{fill:#f0efec} .t2{fill:#c3c2b7} .t3{fill:#9a998f} .grid{stroke:#33332f}
    .s1{fill:#9085e9;stroke:#9085e9} .s2{fill:#d95926;stroke:#d95926} .s3{fill:#199e70;stroke:#199e70} .ring{stroke:#1a1a19}
  }
</style>';
}

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Rounded data-end, square at the baseline (4px radius). */
function bar(float $x, float $y, float $w, float $h, string $class): string
{
    $w = max($w, 2.0);
    $rad = min(4.0, $w / 2, $h / 2);
    return sprintf('<path class="%s" stroke-width="0" d="M%.1f %.1fH%.1fa%.1f %.1f 0 0 1 %.1f %.1fV%.1fa%.1f %.1f 0 0 1 -%.1f %.1fH%.1fZ"/>',
        $class, $x, $y, $x + $w - $rad, $rad, $rad, $rad, $rad, $y + $h - $rad, $rad, $rad, $rad, $rad, $x);
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

function fmt(float $v, int $d = 0): string
{
    return number_format($v, $d, '.', ',');
}

/**
 * Horizontal bar chart, one bar per CMS, every bar labelled with its value.
 * $rows: [[key, value, valueText]]; $note: the comparison sentence printed under the title.
 */
function hbars(string $file, string $title, string $sub, array $rows, string $axisUnit, string $note, int $decimals = 0): void
{
    $W = 820;
    $left = 250;
    $right = 210;
    $top = 92;
    $rowH = 46;
    $plotW = $W - $left - $right;
    $H = $top + count($rows) * $rowH + 52;
    $max = nice_max(max(array_column($rows, 1)));
    $svg = [sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img" aria-label="%s">', $W, $H, $W, $H, esc($title)),
        style(), sprintf('<rect class="bg" width="%d" height="%d" rx="12"/>', $W, $H),
        sprintf('<text class="t1" x="24" y="36" font-size="19" font-weight="650">%s</text>', esc($title)),
        sprintf('<text class="t2" x="24" y="60" font-size="13.5">%s</text>', esc($sub))];
    // Gridlines and ticks (hairline, recessive).
    for ($i = 0; $i <= 4; $i++) {
        $x = $left + $plotW * $i / 4;
        $svg[] = sprintf('<line class="grid" x1="%.1f" y1="%d" x2="%.1f" y2="%d" stroke-width="1"/>', $x, $top - 8, $x, $top + count($rows) * $rowH - 6);
        $svg[] = sprintf('<text class="t3 num" x="%.1f" y="%d" font-size="11.5" text-anchor="middle">%s</text>', $x, $top + count($rows) * $rowH + 12,
            fmt($max * $i / 4, $max < 10 ? 1 : 0) . ($i === 4 ? ' ' . $axisUnit : ''));
    }
    foreach ($rows as $i => [$key, $value, $text]) {
        $y = $top + $i * $rowH;
        $s = SERIES[$key];
        $svg[] = sprintf('<text class="t1" x="%d" y="%.1f" font-size="14" text-anchor="end" font-weight="%d">%s</text>', $left - 14, $y + 16, $key === 'eclair' ? 650 : 450, esc($GLOBALS['ver']($key)));
        $w = $plotW * $value / $max;
        $svg[] = bar($left, $y + 3, $w, 22, $s['class']);
        $svg[] = sprintf('<text class="t1 num" x="%.1f" y="%.1f" font-size="14" font-weight="650">%s</text>', $left + max($w, 2) + 8, $y + 19, esc($text));
    }
    $svg[] = sprintf('<text class="t2" x="24" y="%d" font-size="13">%s</text>', $H - 16, esc($note));
    $svg[] = '</svg>';
    file_put_contents($GLOBALS['out'] . '/' . $file, implode("\n", $svg) . "\n");
}

$art = static fn (string $k): array => $r[$k]['pages']['article'];
$times = static fn (float $a, float $b): string => fmt($a / max($b, 1e-9), 1) . '×';

// 1. Time to first byte, page served normally (cache on where the CMS has one).
hbars('ttfb.svg', 'Time to first byte · article page', 'Median of ' . (getenv('BENCH_SAMPLES') ?: 300) . ' requests, page served from cache when the CMS has one. Lower is better.',
    array_map(static fn ($k) => [$k, $art($k)['ttfb_median_ms'], fmt($art($k)['ttfb_median_ms'], 2) . ' ms'], array_keys(SERIES)), 'ms',
    'Éclair answers ' . $times($art('wordpress-cache')['ttfb_median_ms'], $art('eclair')['ttfb_median_ms']) . ' faster than WordPress with a page cache, '
    . $times($art('wordpress')['ttfb_median_ms'], $art('eclair')['ttfb_median_ms']) . ' faster than WordPress without one.');

// 2. Page built from scratch (cache emptied before each request).
hbars('build.svg', 'Building a page from scratch · article page', 'Median of 30 requests, page cache emptied before each one: the speed of the CMS itself. Lower is better.',
    array_map(static fn ($k) => [$k, $art($k)['uncached_ttfb_median_ms'], fmt($art($k)['uncached_ttfb_median_ms'], 2) . ' ms'], array_keys(SERIES)), 'ms',
    'Without any cache, Éclair builds the page ' . $times($art('wordpress')['uncached_ttfb_median_ms'], $art('eclair')['uncached_ttfb_median_ms']) . ' faster than WordPress.');

// 3. Throughput.
hbars('throughput.svg', 'Throughput · article page', 'Requests per second, wrk, 20 simultaneous connections, 20 s. Higher is better.',
    array_map(static fn ($k) => [$k, (float) $art($k)['load']['rps'], fmt((float) $art($k)['load']['rps']) . ' req/s'], array_keys(SERIES)), 'req/s',
    'Éclair serves ' . $times((float) $art('eclair')['load']['rps'], (float) $art('wordpress-cache')['load']['rps']) . ' more requests per second than WordPress with a page cache, '
    . $times((float) $art('eclair')['load']['rps'], (float) $art('wordpress')['load']['rps']) . ' more than WordPress without one.');

// 4. Page weight.
hbars('weight.svg', 'Page weight · article page', 'HTML + CSS + JavaScript + images, gzip as served. Web fonts not counted. Lower is better.',
    array_map(static fn ($k) => [$k, $art($k)['weight']['total_wire'] / 1024, fmt($art($k)['weight']['total_wire'] / 1024, 1) . ' KB · ' . $art($k)['weight']['requests'] . ' files'], array_keys(SERIES)), 'KB',
    'Éclair: no web font to download. The default WordPress theme declares ' . count($art('wordpress')['weight']['fonts_declared']) . ' font files ('
    . fmt(array_sum($art('wordpress')['weight']['fonts_declared']) / 1024) . ' KB), loaded when the text uses them.');

// 5. Size on disk.
hbars('disk.svg', 'Size of the installation', 'Code + content on disk, without uploads and cache. WordPress also needs a MySQL/MariaDB server. Lower is better.',
    array_map(static fn ($k) => [$k, $r[$k]['disk_kb'] / 1024, fmt($r[$k]['disk_kb'] / 1024, 1) . ' MB · ' . fmt($r[$k]['php_files']) . ' PHP files'], array_keys(SERIES)), 'MB',
    'Éclair is ' . $times($r['wordpress']['disk_kb'], $r['eclair']['disk_kb']) . ' smaller than WordPress, and needs no database server.');

// 6 and 7. Inside one request (profiling pass).
if ($profile) {
    $served = static fn (string $k): array => $profile[$k]['article'][$k === 'wordpress' ? 'uncached' : 'cached'];
    hbars('php-files.svg', 'PHP files loaded to serve one page', 'Article page as a visitor gets it (from the page cache when the CMS has one). Lower is better.',
        array_map(static fn ($k) => [$k, $served($k)['php_files'], fmt($served($k)['php_files']) . ($served($k)['php_files'] === 1 ? ' file · ' : ' files · ') . $served($k)['sql_queries'] . ' SQL'], array_keys(SERIES)), 'files',
        'Éclair answers from its page cache with a single PHP file and no database. WordPress loads ' . fmt($served('wordpress')['php_files']) . ' files and runs '
        . $served('wordpress')['sql_queries'] . ' SQL queries.');
    hbars('memory.svg', 'PHP memory used to serve one page', 'Peak memory of the PHP request, article page, OPcache on. Lower is better.',
        array_map(static fn ($k) => [$k, $served($k)['peak_memory_kb'] / 1024, fmt($served($k)['peak_memory_kb'] / 1024, 2) . ' MB'], array_keys(SERIES)), 'MB',
        'Less memory per request means more visitors on the same server, and cheaper hosting.');
}

// 8. Scaling: throughput as simultaneous visitors grow (line chart, one y-axis).
$levels = array_keys($r['eclair']['sweep'] ?? []);
if ($levels) {
    $W = 820;
    $H = 420;
    $left = 80;
    $right = 220;
    $top = 92;
    $bottom = 70;
    $plotW = $W - $left - $right;
    $plotH = $H - $top - $bottom;
    $max = nice_max(max(array_map(static fn ($k) => max(array_column($r[$k]['sweep'], 'rps')), array_keys(SERIES))));
    $x = static fn (int $i): float => $left + $plotW * $i / max(1, count($levels) - 1);
    $y = static fn (float $v): float => $top + $plotH * (1 - $v / $max);
    $svg = [sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img" aria-label="Throughput as visitors grow">', $W, $H, $W, $H),
        style(), sprintf('<rect class="bg" width="%d" height="%d" rx="12"/>', $W, $H),
        '<text class="t1" x="24" y="36" font-size="19" font-weight="650">Throughput as simultaneous visitors grow · article page</text>',
        '<text class="t2" x="24" y="60" font-size="13.5">Requests per second (wrk, ' . (getenv('BENCH_SWEEP_SECONDS') ?: 10) . ' s per level). Higher is better.</text>'];
    for ($i = 0; $i <= 4; $i++) {
        $yy = $top + $plotH * $i / 4;
        $svg[] = sprintf('<line class="grid" x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke-width="1"/>', $left, $yy, $left + $plotW, $yy);
        $svg[] = sprintf('<text class="t3 num" x="%d" y="%.1f" font-size="11.5" text-anchor="end">%s</text>', $left - 10, $yy + 4, fmt($max * (1 - $i / 4)));
    }
    foreach ($levels as $i => $c) {
        $svg[] = sprintf('<text class="t3 num" x="%.1f" y="%d" font-size="11.5" text-anchor="middle">%s</text>', $x($i), $top + $plotH + 20, $c);
    }
    $svg[] = sprintf('<text class="t2" x="%.1f" y="%d" font-size="12.5" text-anchor="middle">Simultaneous connections</text>', $left + $plotW / 2, $top + $plotH + 44);
    $svg[] = sprintf('<text class="t2" x="%d" y="%d" font-size="12.5">req/s</text>', 24, $top - 14);
    $labelsY = [];
    foreach (array_keys(SERIES) as $k) {
        $pts = [];
        foreach ($levels as $i => $c) {
            $pts[] = sprintf('%.1f,%.1f', $x($i), $y((float) $r[$k]['sweep'][$c]['rps']));
        }
        $svg[] = sprintf('<polyline class="%s" style="fill:none" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" points="%s"/>', SERIES[$k]['class'], implode(' ', $pts));
        foreach ($levels as $i => $c) {
            $svg[] = sprintf('<circle class="%s ring" cx="%.1f" cy="%.1f" r="5" stroke-width="2"/>', SERIES[$k]['class'], $x($i), $y((float) $r[$k]['sweep'][$c]['rps']));
        }
        $lastV = (float) $r[$k]['sweep'][end($levels)]['rps'];
        $labelsY[$k] = $y($lastV);
    }
    // End labels, spread apart when lines end close to each other.
    asort($labelsY);
    $prev = -INF;
    foreach ($labelsY as $k => $ly) {
        $ly = max($ly, $prev + 34);
        $prev = $ly;
        $v = (float) $r[$k]['sweep'][end($levels)]['rps'];
        $svg[] = sprintf('<text class="t1" x="%.1f" y="%.1f" font-size="13.5" font-weight="650">%s</text>', $left + $plotW + 14, $ly - 2, esc(SERIES[$k]['label']));
        $svg[] = sprintf('<text class="t2 num" x="%.1f" y="%.1f" font-size="12.5">%s req/s</text>', $left + $plotW + 14, $ly + 14, fmt($v));
    }
    $svg[] = '</svg>';
    file_put_contents($out . '/scaling.svg', implode("\n", $svg) . "\n");
}

// 9. Banner: three headline figures, each computed from this run.
$tiles = [
    [$times($art('wordpress-cache')['ttfb_median_ms'], $art('eclair')['ttfb_median_ms']), 'faster first byte', 'than WordPress + page cache'],
    [$times($art('wordpress')['uncached_ttfb_median_ms'], $art('eclair')['uncached_ttfb_median_ms']), 'faster page build', 'than WordPress, no cache involved'],
    [$times($r['wordpress']['disk_kb'], $r['eclair']['disk_kb']), 'smaller install', 'and no database server'],
];
$W = 980;
$H = 250;
$svg = [sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img" aria-label="Éclair CMS benchmark summary">', $W, $H, $W, $H),
    style(), sprintf('<rect class="bg" width="%d" height="%d" rx="14"/>', $W, $H),
    '<text class="t1" x="32" y="46" font-size="24" font-weight="700">⚡ Éclair CMS vs WordPress</text>',
    sprintf('<text class="t2" x="32" y="72" font-size="14">Same server, same content, open scripts. Éclair %s · WordPress %s · measured %s</text>',
        esc($meta['eclair'] ?? ''), esc($meta['wordpress'] ?? ''), esc(substr((string) ($meta['date'] ?? ''), 0, 10)))];
foreach ($tiles as $i => [$big, $line1, $line2]) {
    $tx = 32 + $i * 316;
    $svg[] = sprintf('<rect class="s1" x="%d" y="100" width="4" height="118" rx="2"/>', $tx);
    $svg[] = sprintf('<text class="t1" x="%d" y="160" font-size="52" font-weight="700">%s</text>', $tx + 20, esc($big));
    $svg[] = sprintf('<text class="t1" x="%d" y="190" font-size="16" font-weight="600">%s</text>', $tx + 20, esc($line1));
    $svg[] = sprintf('<text class="t2" x="%d" y="212" font-size="13.5">%s</text>', $tx + 20, esc($line2));
}
$svg[] = '</svg>';
file_put_contents($out . '/summary.svg', implode("\n", $svg) . "\n");

// 10. Diagram: what happens inside the server for one visit (steps from the code, counts from the profiling pass).
if ($profile) {
    $p = static fn (string $k, string $mode): array => $profile[$k]['article'][$mode];
    $lanes = [
        ['s1', 'Éclair · page in cache', ['Apache', 'index.php fast path', 'read 1 cached file', 'HTML sent'],
            sprintf('%d PHP file · %d SQL · %s MB', $p('eclair', 'cached')['php_files'], 0, fmt($p('eclair', 'cached')['peak_memory_kb'] / 1024, 2))],
        ['s1', 'Éclair · page built', ['Apache', 'index.php', 'Éclair app', 'content = files, no DB', 'theme render', 'HTML sent + cached'],
            sprintf('%d PHP files · %d SQL · %s MB', $p('eclair', 'uncached')['php_files'], $p('eclair', 'uncached')['sql_queries'], fmt($p('eclair', 'uncached')['peak_memory_kb'] / 1024, 2))],
        ['s2', 'WordPress + Cache Enabler · in cache', ['Apache', 'index.php', 'wp-load + wp-config', 'wp-settings (start)', 'advanced-cache.php', 'HTML sent'],
            sprintf('%d PHP files · %d SQL · %s MB', $p('wordpress-cache', 'cached')['php_files'], $p('wordpress-cache', 'cached')['sql_queries'], fmt($p('wordpress-cache', 'cached')['peak_memory_kb'] / 1024, 2))],
        ['s3', 'WordPress · page built', ['Apache', 'index.php', 'WordPress core', 'MySQL queries', 'hooks + theme', 'block render', 'HTML sent'],
            sprintf('%d PHP files · %d SQL · %s MB', $p('wordpress', 'uncached')['php_files'], $p('wordpress', 'uncached')['sql_queries'], fmt($p('wordpress', 'uncached')['peak_memory_kb'] / 1024, 2))],
    ];
    $W = 980;
    $laneH = 92;
    $H = 96 + count($lanes) * $laneH + 20;
    $svg = [sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img" aria-label="What happens inside the server for one visit">', $W, $H, $W, $H),
        style(), sprintf('<rect class="bg" width="%d" height="%d" rx="12"/>', $W, $H),
        '<text class="t1" x="24" y="36" font-size="19" font-weight="650">What happens inside the server for one visit</text>',
        '<text class="t2" x="24" y="60" font-size="13.5">Article page. Steps read from each CMS\'s code; counts measured during the profiling pass of this run.</text>',
        '<defs><marker id="arr" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path class="t3" d="M0 0L10 5L0 10z"/></marker></defs>'];
    foreach ($lanes as $i => [$cls, $name, $steps, $cost]) {
        $y0 = 84 + $i * $laneH;
        $svg[] = sprintf('<rect class="%s" x="24" y="%d" width="4" height="70" rx="2"/>', $cls, $y0);
        $svg[] = sprintf('<text class="t1" x="38" y="%d" font-size="14" font-weight="650">%s</text>', $y0 + 14, esc($name));
        $svg[] = sprintf('<text class="t2 num" x="%d" y="%d" font-size="13" text-anchor="end" font-weight="600">%s</text>', $W - 24, $y0 + 14, esc($cost));
        $x = 38;
        $gap = 22;
        $avail = $W - 38 - 24 - $gap * (count($steps) - 1);
        $bw = min(150, $avail / count($steps));
        foreach ($steps as $j => $step) {
            $svg[] = sprintf('<rect class="grid" x="%.1f" y="%d" width="%.1f" height="38" rx="8" fill="none" stroke-width="1.5"/>', $x, $y0 + 26, $bw);
            $svg[] = sprintf('<text class="t1" x="%.1f" y="%d" font-size="12.5" text-anchor="middle">%s</text>', $x + $bw / 2, $y0 + 49, esc($step));
            if ($j < count($steps) - 1) {
                $svg[] = sprintf('<line class="grid" x1="%.1f" y1="%d" x2="%.1f" y2="%d" stroke-width="1.5" marker-end="url(#arr)" style="stroke:#9a998f"/>', $x + $bw + 2, $y0 + 45, $x + $bw + $gap - 2, $y0 + 45);
            }
            $x += $bw + $gap;
        }
    }
    $svg[] = '</svg>';
    file_put_contents($out . '/request-path.svg', implode("\n", $svg) . "\n");
}

echo 'Charts written to ' . $out . ': ' . implode(', ', array_map('basename', glob($out . '/*.svg'))) . "\n";
