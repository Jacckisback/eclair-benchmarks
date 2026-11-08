<?php
// Turns the JSON results of one run into a Markdown report. Usage: php report.php <results-dir>
declare(strict_types=1);

$dir = rtrim($argv[1], '/');
$meta = json_decode((string) file_get_contents("$dir/meta.json"), true);
$sites = [];
foreach ($meta['sites'] as $s) {
    $sites[$s['key']] = json_decode((string) file_get_contents("$dir/{$s['key']}.json"), true) + ['list' => $s['list'], 'article' => $s['article']];
}
$profile = is_file("$dir/profile.json") ? json_decode((string) file_get_contents("$dir/profile.json"), true) : [];
$lh = is_file("$dir/lighthouse.json") ? json_decode((string) file_get_contents("$dir/lighthouse.json"), true) : [];
$samples = (int) (getenv('BENCH_SAMPLES') ?: 300);
$wrk = (int) (getenv('BENCH_WRK_SECONDS') ?: 20) . ' s, ' . (int) (getenv('BENCH_WRK_CONNECTIONS') ?: 20) . ' connections';
$n = static fn ($v, int $d = 0): string => $v === null ? 'n/a' : number_format((float) $v, $d, '.', ',');
$kb = static fn (int $b): string => number_format($b / 1024, 1) . ' KB';
$cmp = static function (float $ratio, string $better, string $worse): string {
    if ($ratio <= 0) {
        return 'n/a';
    }
    if ($ratio >= 0.95 && $ratio <= 1.05) {
        return 'about the same';
    }
    return $ratio > 1 ? number_format($ratio, 1) . "× $better" : number_format(1 / $ratio, 1) . "× $worse";
};

echo '# Results — ' . basename($dir) . "\n\n";
echo "| | |\n|---|---|\n";
echo '| Date | ' . $meta['date'] . " |\n";
echo '| Machine | ' . $meta['machine'] . ", Ubuntu 24.04. The load generator (wrk) runs on the same machine. |\n";
echo '| Web server | ' . ($meta['server'] === 'nginx' ? 'Nginx' : 'Apache 2.4 (mpm_event, .htaccess on)') . " + PHP 8.3 FPM with OPcache, MariaDB for WordPress |\n";
echo '| Content | ' . $n($meta['articles']) . " articles in every site |\n";
echo '| Versions | Éclair ' . $meta['eclair'] . ' (sha256 `' . substr($meta['eclair_sha256'], 0, 16) . '…`) · WordPress ' . $meta['wordpress'] . ' · Cache Enabler ' . $meta['cache_enabler'] . ' · Kirby ' . $meta['kirby'] . " |\n\n";
echo "Response time = time to first byte, median and 95th percentile of $samples sequential requests after 50 warm-up requests. "
    . "Built from scratch = median of 30 requests with the page cache emptied before each one. Throughput = wrk, $wrk. "
    . "Every request sends the headers of a real Chrome browser. Page weight = HTML + linked CSS, JavaScript and images, gzip as served (web fonts listed apart). "
    . "List page = the page listing the latest articles (home page for Éclair and WordPress, `/notes` for Kirby).\n\n";

foreach (['home' => 'List page', 'article' => 'Article page'] as $page => $title) {
    echo "## $title\n\n";
    echo "| | Response time (median) | p95 | Built from scratch | Throughput | p99 under load | Page weight | Files | Web fonts (not counted) |\n";
    echo "|---|---:|---:|---:|---:|---:|---:|---:|---:|\n";
    foreach ($sites as $key => $s) {
        $p = $s['pages'][$page];
        $b = static fn (string $v): string => $key === 'eclair' ? "**$v**" : $v;
        echo '| ' . $b($s['name']) . ' | ' . $n($p['ttfb_median_ms'], 2) . ' ms | ' . $n($p['ttfb_p95_ms'], 2) . ' ms | ' . $n($p['uncached_ttfb_median_ms'], 2) . ' ms | '
            . $n($p['load']['rps']) . ' req/s | ' . $n($p['load']['latency_p99_ms'], 1) . ' ms | ' . $kb($p['weight']['total_wire']) . ' | ' . $p['weight']['requests'] . ' | '
            . (count($p['weight']['fonts_declared']) ? count($p['weight']['fonts_declared']) . ' (' . $kb(array_sum($p['weight']['fonts_declared'])) . ')' : 'none') . " |\n";
    }
    echo "\n";
    $e = $sites['eclair']['pages'][$page] ?? null;
    foreach ($sites as $key => $s) {
        if ($key === 'eclair' || !$e) {
            continue;
        }
        $o = $s['pages'][$page];
        echo '- Éclair vs ' . $s['name'] . ': first byte ' . $cmp($o['ttfb_median_ms'] / max(0.001, $e['ttfb_median_ms']), 'faster', 'slower')
            . ', built from scratch ' . $cmp($o['uncached_ttfb_median_ms'] / max(0.001, $e['uncached_ttfb_median_ms']), 'faster', 'slower')
            . ', throughput ' . $cmp((float) $e['load']['rps'] / max(1, (float) $o['load']['rps']), 'higher', 'lower')
            . ', page weight ' . $cmp($o['weight']['total_wire'] / max(1, $e['weight']['total_wire']), 'lighter', 'heavier') . "\n";
    }
    echo "\n";
}

$levels = array_keys(reset($sites)['sweep'] ?? []);
if ($levels) {
    echo "## Scaling (article page)\n\nRequests per second and p99 latency as the number of simultaneous connections grows (wrk, 10 s per level).\n\n";
    echo '| | ' . implode(' | ', array_map(static fn ($c) => "$c conn.", $levels)) . " |\n|---|" . str_repeat('---:|', count($levels)) . "\n";
    foreach ($sites as $s) {
        echo '| ' . $s['name'] . ' | ' . implode(' | ', array_map(static fn ($c) => $n($s['sweep'][$c]['rps']) . ' req/s · p99 ' . $n($s['sweep'][$c]['latency_p99_ms'], 1) . ' ms', $levels)) . " |\n";
    }
    echo "\n";
}

if ($lh) {
    echo "## Lighthouse (real Chromium)\n\nLighthouse " . (reset($lh)['article']['lighthouse'] ?? '') . ', ' . (reset($lh)['article']['chrome'] ?? '')
        . ", default settings: mobile emulation, simulated slow 4G and 4× CPU slowdown (the settings of PageSpeed Insights). "
        . "Run reported = the median of " . (reset($lh)['article']['runs'] ?? 5) . " runs by performance score. These are lab measurements; field Core Web Vitals (including INP) need real visitors.\n\n";
    echo "| | Page | Performance | LCP | FCP | TBT | CLS | Speed Index | Transfer | Requests | Accessibility | Best practices | SEO |\n";
    echo "|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|\n";
    foreach ($sites as $key => $s) {
        foreach (['home' => 'list', 'article' => 'article'] as $page => $label) {
            $l = $lh[$key][$page] ?? null;
            if (!$l) {
                continue;
            }
            echo '| ' . $s['name'] . " | $label | " . $l['performance'] . ' | ' . $n($l['lcp_ms'] / 1000, 2) . ' s | ' . $n($l['fcp_ms'] / 1000, 2) . ' s | ' . $l['tbt_ms'] . ' ms | '
                . $l['cls'] . ' | ' . $n($l['speed_index_ms'] / 1000, 2) . ' s | ' . $kb($l['total_bytes']) . ' | ' . $l['requests'] . ' | ' . $l['accessibility'] . ' | '
                . $l['best_practices'] . ' | ' . $l['seo'] . " |\n";
        }
    }
    echo "\n";
}

if ($profile) {
    echo "## Inside one request\n\nSeparate profiling pass (a PHP `auto_prepend_file` that logs at shutdown), removed before any timing measurement. Median of 7 requests. "
        . "\"Built\" = page cache emptied first; \"from cache\" = page cache warm. WordPress without plugin has no page cache; "
        . "a hit in the Nginx FastCGI cache never reaches PHP (0 PHP files).\n\n";
    echo "| | Page | Built: PHP files | Built: peak memory | Built: SQL | From cache: PHP files | From cache: peak memory | From cache: SQL |\n";
    echo "|---|---|---:|---:|---:|---:|---:|---:|\n";
    foreach ($sites as $key => $s) {
        foreach (['home' => 'list', 'article' => 'article'] as $page => $label) {
            $u = $profile[$key][$page]['uncached'];
            $c = $profile[$key][$page]['cached'];
            $cachedCells = match ($key) {
                'wordpress' => 'n/a | n/a | n/a',
                'wordpress-fastcgi' => '0 | 0 KB | 0',
                default => $c['php_files'] . ' | ' . $n($c['peak_memory_kb']) . ' KB | ' . $c['sql_queries'],
            };
            echo '| ' . $s['name'] . " | $label | " . $u['php_files'] . ' | ' . $n($u['peak_memory_kb']) . ' KB | ' . $u['sql_queries'] . " | $cachedCells |\n";
        }
    }
    echo "\n";
}

echo "## Installation\n\n| | Size on disk (code + content) | PHP files | Database server |\n|---|---:|---:|---|\n";
foreach ($sites as $key => $s) {
    echo '| ' . $s['name'] . ' | ' . number_format($s['disk_kb'] / 1024, 1) . ' MB | ' . number_format($s['php_files']) . ' | ' . (str_starts_with($key, 'wordpress') ? 'MariaDB / MySQL' : 'none') . " |\n";
}
echo "\nRaw data: the `.json` files in this folder. Charts: `charts/`.\n";
