<?php
// Draws the charts of one run as SVG files (light and dark mode), straight from the JSON results.
// Usage: php charts.php <results-dir>   → <results-dir>/charts/*.svg
// Every number in a chart comes from the run's JSON files: nothing is typed by hand.
declare(strict_types=1);
require __DIR__ . '/svg.php';

$dir = rtrim($argv[1], '/');
$out = $dir . '/charts';
@mkdir($out, 0755, true);
$meta = json_decode((string) file_get_contents("$dir/meta.json"), true);
$r = [];
$names = [];
foreach ($meta['sites'] as $s) {
    $r[$s['key']] = json_decode((string) file_get_contents("$dir/{$s['key']}.json"), true);
    $names[$s['key']] = $s['name'];
}
$profile = is_file("$dir/profile.json") ? json_decode((string) file_get_contents("$dir/profile.json"), true) : [];
$lh = is_file("$dir/lighthouse.json") ? json_decode((string) file_get_contents("$dir/lighthouse.json"), true) : [];
$keys = array_keys($r);
$has = static fn (string $k): bool => isset($r[$k]);
$art = static fn (string $k): array => $r[$k]['pages']['article'];
$times = static fn (float $a, float $b): string => fmt($a / max($b, 1e-9), 1) . '×';
$ctx = sprintf('%s · %s articles', $meta['server'] === 'nginx' ? 'Nginx' : 'Apache', fmt((float) $meta['articles']));
$best = $has('wordpress-fastcgi') ? 'wordpress-fastcgi' : 'wordpress-cache'; // the fastest WordPress set-up in this run is compared below
$bestLabel = SERIES_SHORT[$best];

// 1. Time to first byte.
hbars("$out/ttfb.svg", 'Time to first byte · article page', "Median of 300 requests, page served from cache when the set-up has one. $ctx. Lower is better.",
    array_map(static fn ($k) => [$k, $names[$k], $art($k)['ttfb_median_ms'], fmt($art($k)['ttfb_median_ms'], 2) . ' ms'], $keys), 'ms',
    $has('wordpress') ? 'Éclair answers ' . $times($art('wordpress-cache')['ttfb_median_ms'], $art('eclair')['ttfb_median_ms']) . ' faster than WordPress + Cache Enabler, '
    . $times($art('wordpress')['ttfb_median_ms'], $art('eclair')['ttfb_median_ms']) . ' faster than WordPress without cache.' : '');

// 2. Page built from scratch.
hbars("$out/build.svg", 'Building a page from scratch · article page', "Median of 30 requests, page cache emptied before each one: the speed of the CMS engine. $ctx. Lower is better.",
    array_map(static fn ($k) => [$k, $names[$k], $art($k)['uncached_ttfb_median_ms'], fmt($art($k)['uncached_ttfb_median_ms'], 2) . ' ms'], $keys), 'ms',
    $has('wordpress') ? 'Without any cache, Éclair builds the page ' . $times($art('wordpress')['uncached_ttfb_median_ms'], $art('eclair')['uncached_ttfb_median_ms']) . ' faster than WordPress'
    . ($has('kirby') ? ' and ' . $times($art('kirby')['uncached_ttfb_median_ms'], $art('eclair')['uncached_ttfb_median_ms']) . ' faster than Kirby.' : '.') : '');

// 3. Throughput.
hbars("$out/throughput.svg", 'Throughput · article page', "Requests per second, wrk, 20 simultaneous connections, 20 s. $ctx. Higher is better.",
    array_map(static fn ($k) => [$k, $names[$k], (float) $art($k)['load']['rps'], fmt((float) $art($k)['load']['rps']) . ' req/s'], $keys), 'req/s',
    (float) $art('eclair')['load']['rps'] >= (float) $art($best)['load']['rps']
        ? 'Éclair serves ' . $times((float) $art('eclair')['load']['rps'], (float) $art($best)['load']['rps']) . " more requests per second than $bestLabel."
        : "$bestLabel serves " . $times((float) $art($best)['load']['rps'], (float) $art('eclair')['load']['rps']) . ' more requests per second than Éclair.');

// 4. Page weight.
hbars("$out/weight.svg", 'Page weight · article page', 'HTML + CSS + JavaScript + images, gzip as served. Web fonts listed apart. Lower is better.',
    array_map(static fn ($k) => [$k, $names[$k], $art($k)['weight']['total_wire'] / 1024,
        fmt($art($k)['weight']['total_wire'] / 1024, 1) . ' KB · ' . $art($k)['weight']['requests'] . ' files'
        . (count($art($k)['weight']['fonts_declared']) ? ' · +' . count($art($k)['weight']['fonts_declared']) . ' fonts' : '')], $keys), 'KB',
    'Fonts: declared by the theme, downloaded by the browser only when the text uses them. Éclair uses system fonts.');

// 5. Size on disk.
hbars("$out/disk.svg", 'Size of the installation', 'Code + content on disk, without uploads and caches. WordPress also needs a MySQL/MariaDB server. Lower is better.',
    array_map(static fn ($k) => [$k, $names[$k], $r[$k]['disk_kb'] / 1024, fmt($r[$k]['disk_kb'] / 1024, 1) . ' MB · ' . fmt($r[$k]['php_files']) . ' PHP files'], $keys), 'MB',
    $has('wordpress') ? 'Éclair is ' . $times($r['wordpress']['disk_kb'], $r['eclair']['disk_kb']) . ' smaller than WordPress, and needs no database server.' : '');

// 6 and 7. Inside one request (profiling pass). An outside cache (Nginx FastCGI) never reaches PHP when it hits.
if ($profile) {
    $served = static fn (string $k): array => $profile[$k]['article'][$k === 'wordpress' ? 'uncached' : 'cached'];
    $phpKeys = array_values(array_filter($keys, static fn ($k) => $k !== 'wordpress-fastcgi'));
    hbars("$out/php-files.svg", 'PHP files loaded to serve one page', 'Article page as a visitor gets it (from the page cache when the CMS has one). Lower is better.',
        array_map(static fn ($k) => [$k, $names[$k], $served($k)['php_files'], fmt($served($k)['php_files']) . ($served($k)['php_files'] === 1 ? ' file · ' : ' files · ') . $served($k)['sql_queries'] . ' SQL'], $phpKeys), 'files',
        'Éclair answers from its page cache with a single PHP file and no database.');
    hbars("$out/memory.svg", 'PHP memory used to serve one page', 'Peak memory of the PHP request, article page, OPcache on. Lower is better.',
        array_map(static fn ($k) => [$k, $names[$k], $served($k)['peak_memory_kb'] / 1024, fmt($served($k)['peak_memory_kb'] / 1024, 2) . ' MB'], $phpKeys), 'MB',
        'Less memory per request means more visitors on the same server.');
}

// 8. Scaling (3 lines at most: the palette only guarantees 3 colours on a line chart).
$levels = array_keys($r['eclair']['sweep'] ?? []);
if ($levels) {
    $lineKeys = array_values(array_intersect(['eclair', $best, 'wordpress'], $keys));
    $series = [];
    foreach ($lineKeys as $k) {
        $series[$k] = [SERIES_SHORT[$k], array_map(static fn ($c) => (float) $r[$k]['sweep'][$c]['rps'], $levels)];
    }
    lines("$out/scaling.svg", 'Throughput as simultaneous visitors grow · article page', "Requests per second (wrk, 10 s per level). $ctx. Higher is better.",
        $levels, 'Simultaneous connections', 'req/s', $series, static fn (float $v) => fmt($v) . ' req/s');
}

// 9. Lighthouse (lab Core Web Vitals in a real Chromium).
if ($lh) {
    hbars("$out/lighthouse.svg", 'Largest Contentful Paint · Lighthouse, article page', 'Real Chromium, mobile, simulated slow 4G (PageSpeed Insights settings), median of 5 runs. Lower is better.',
        array_map(static fn ($k) => [$k, $names[$k], $lh[$k]['article']['lcp_ms'] / 1000,
            fmt($lh[$k]['article']['lcp_ms'] / 1000, 2) . ' s · score ' . $lh[$k]['article']['performance']], $keys), 's',
        'Google considers an LCP under 2.5 s "good". Score = Lighthouse performance score (0 to 100).');
    hbars("$out/lighthouse-bytes.svg", 'What the browser downloads · article page', 'Total transfer measured by Lighthouse in Chromium (HTML, CSS, JS, fonts, images), compressed. Lower is better.',
        array_map(static fn ($k) => [$k, $names[$k], $lh[$k]['article']['total_bytes'] / 1024,
            fmt($lh[$k]['article']['total_bytes'] / 1024, 1) . ' KB · ' . $lh[$k]['article']['requests'] . ' requests'], $keys), 'KB',
        $has('wordpress') ? 'A visitor downloads ' . $times((float) $lh['wordpress']['article']['total_bytes'], (float) $lh['eclair']['article']['total_bytes'])
            . ' less with Éclair than with the default WordPress theme (web fonts included).' : '');
}

// 10. Request path diagram (steps from the code, counts from the profiling pass).
if ($profile && $has('wordpress') && $has('wordpress-cache')) {
    $p = static fn (string $k, string $mode): array => $profile[$k]['article'][$mode];
    $cost = static fn (array $x): string => sprintf('%d PHP %s · %d SQL · %s MB', $x['php_files'], $x['php_files'] === 1 ? 'file' : 'files', $x['sql_queries'], fmt($x['peak_memory_kb'] / 1024, 2));
    $lanes = [
        ['s1', 'Éclair · page in cache', ['web server', 'index.php fast path', 'read 1 cached file', 'HTML sent'], $cost($p('eclair', 'cached'))],
        ['s1', 'Éclair · page built', ['web server', 'index.php', 'Éclair app', 'content = files, no DB', 'theme render', 'HTML sent + cached'], $cost($p('eclair', 'uncached'))],
        ['s2', 'WordPress + Cache Enabler · in cache', ['web server', 'index.php', 'wp-load + wp-config', 'wp-settings (start)', 'advanced-cache.php', 'HTML sent'], $cost($p('wordpress-cache', 'cached'))],
        ['s4', 'WordPress · page built', ['web server', 'index.php', 'WordPress core', 'MySQL queries', 'hooks + theme', 'block render', 'HTML sent'], $cost($p('wordpress', 'uncached'))],
    ];
    $W = 980;
    $laneH = 92;
    $H = 96 + count($lanes) * $laneH + 20;
    $svg = svg_open($W, $H, 'What happens inside the server for one visit', 'Article page. Steps read from each CMS\'s code; counts measured during the profiling pass of this run.');
    $svg[] = '<defs><marker id="arr" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path class="t3" d="M0 0L10 5L0 10z"/></marker></defs>';
    foreach ($lanes as $i => [$cls, $name, $steps, $c]) {
        $y0 = 84 + $i * $laneH;
        $svg[] = sprintf('<rect class="%s" x="24" y="%d" width="4" height="70" rx="2"/>', $cls, $y0);
        $svg[] = sprintf('<text class="t1" x="38" y="%d" font-size="14" font-weight="650">%s</text>', $y0 + 14, esc($name));
        $svg[] = sprintf('<text class="t2 num" x="%d" y="%d" font-size="13" text-anchor="end" font-weight="600">%s</text>', $W - 24, $y0 + 14, esc($c));
        $x = 38;
        $gap = 22;
        $bw = min(150, ($W - 38 - 24 - $gap * (count($steps) - 1)) / count($steps));
        foreach ($steps as $j => $step) {
            $svg[] = sprintf('<rect class="grid" x="%.1f" y="%d" width="%.1f" height="38" rx="8" fill="none" stroke-width="1.5"/>', $x, $y0 + 26, $bw);
            $svg[] = sprintf('<text class="t1" x="%.1f" y="%d" font-size="12.5" text-anchor="middle">%s</text>', $x + $bw / 2, $y0 + 49, esc($step));
            if ($j < count($steps) - 1) {
                $svg[] = sprintf('<line class="arrow" x1="%.1f" y1="%d" x2="%.1f" y2="%d" stroke-width="1.5" marker-end="url(#arr)"/>', $x + $bw + 2, $y0 + 45, $x + $bw + $gap - 2, $y0 + 45);
            }
            $x += $bw + $gap;
        }
    }
    $svg[] = '</svg>';
    file_put_contents("$out/request-path.svg", implode("\n", $svg) . "\n");
}

// 11. Banner: three headline figures computed from this run.
if ($has('wordpress') && $has('wordpress-cache')) {
    $tiles = [
        [$times($art('wordpress-cache')['ttfb_median_ms'], $art('eclair')['ttfb_median_ms']), 'faster first byte', 'than WordPress + page cache plugin'],
        [$times($art('wordpress')['uncached_ttfb_median_ms'], $art('eclair')['uncached_ttfb_median_ms']), 'faster page build', 'than WordPress, no cache involved'],
        [$times($r['wordpress']['disk_kb'], $r['eclair']['disk_kb']), 'smaller install', 'and no database server'],
    ];
    $W = 980;
    $H = 250;
    $svg = [sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img" aria-label="Éclair CMS benchmark summary">', $W, $H, $W, $H),
        svg_style(), sprintf('<rect class="bg" width="%d" height="%d" rx="14"/>', $W, $H),
        '<text class="t1" x="32" y="46" font-size="24" font-weight="700">⚡ Éclair CMS vs WordPress</text>',
        sprintf('<text class="t2" x="32" y="72" font-size="14">Same server, same content, open scripts. Éclair %s · WordPress %s · %s · measured %s</text>',
            esc($meta['eclair']), esc($meta['wordpress']), esc($ctx), esc(substr((string) $meta['date'], 0, 10)))];
    foreach ($tiles as $i => [$big, $line1, $line2]) {
        $tx = 32 + $i * 316;
        $svg[] = sprintf('<rect class="s1" x="%d" y="100" width="4" height="118" rx="2"/>', $tx);
        $svg[] = sprintf('<text class="t1" x="%d" y="160" font-size="52" font-weight="700">%s</text>', $tx + 20, esc($big));
        $svg[] = sprintf('<text class="t1" x="%d" y="190" font-size="16" font-weight="600">%s</text>', $tx + 20, esc($line1));
        $svg[] = sprintf('<text class="t2" x="%d" y="212" font-size="13.5">%s</text>', $tx + 20, esc($line2));
    }
    $svg[] = '</svg>';
    file_put_contents("$out/summary.svg", implode("\n", $svg) . "\n");
}

echo 'Charts written to ' . $out . ': ' . implode(', ', array_map('basename', glob($out . '/*.svg'))) . "\n";
