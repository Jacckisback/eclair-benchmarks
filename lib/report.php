<?php
// Turns the JSON results of one run into a Markdown report. Usage: php report.php <results-dir>
declare(strict_types=1);

$dir = $argv[1];
$sites = [];
foreach (['eclair', 'wordpress', 'wordpress-cache'] as $key) {
    $sites[$key] = json_decode((string) file_get_contents("$dir/$key.json"), true);
}
$cpu = trim((string) shell_exec("grep -m1 'model name' /proc/cpuinfo | cut -d: -f2"));
$cores = trim((string) shell_exec('nproc'));
$ram = round((int) trim((string) shell_exec("grep MemTotal /proc/meminfo | awk '{print $2}'")) / 1048576, 1);
$os = trim((string) shell_exec('. /etc/os-release && echo "$PRETTY_NAME"'));
$apache = trim((string) shell_exec("apache2 -v | head -1 | cut -d: -f2"));
$mariadb = trim((string) shell_exec("mariadb --version | grep -oP 'Distrib \\K[^,]+|from \\K[0-9.]+' | head -1"));
$samples = (int) (getenv('BENCH_SAMPLES') ?: 300);
$wrk = (int) (getenv('BENCH_WRK_SECONDS') ?: 20) . ' s, ' . (int) (getenv('BENCH_WRK_CONNECTIONS') ?: 20) . ' connections';

$n = static fn ($v, int $d = 0): string => $v === null ? 'n/a' : number_format((float) $v, $d, '.', ' ');
$kb = static fn (int $b): string => number_format($b / 1024, 1, '.', ' ') . ' KB';
/** "3.2× faster" when $ratio > 1, "1.4× slower" when < 1 (ratio = how many times better Éclair is). */
$cmp = static function (float $ratio, string $better, string $worse): string {
    if ($ratio <= 0) {
        return 'n/a';
    }
    if ($ratio >= 0.95 && $ratio <= 1.05) {
        return 'about the same';
    }
    return $ratio > 1 ? number_format($ratio, 1) . "× $better" : number_format(1 / $ratio, 1) . "× $worse";
};

echo "# Results — " . basename($dir) . "\n\n";
echo "Machine: $cpu, $cores vCPU, $ram GB RAM, $os. $apache (mpm_event) + PHP " . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION
    . " FPM with OPcache, MariaDB $mariadb. Load generator (wrk) runs on the same machine.\n";
echo "Response time = time to first byte, median and 95th percentile of $samples sequential requests after 50 warm-up requests. "
    . "Page built from scratch = median of 30 requests with the page cache emptied before each one (WordPress without plugin has no page cache). "
    . "Throughput = wrk, $wrk. Every request sends the headers of a real browser (Chrome). "
    . "Page weight = HTML plus the CSS, JavaScript and images it links to, gzip as served. Web fonts are listed separately and not counted, "
    . "because a browser only downloads the ones the text actually uses.\n\n";

foreach (['home' => 'Home page (list of articles)', 'article' => 'Article page'] as $page => $title) {
    echo "## $title\n\n";
    echo "| | Response time (median) | Response time (p95) | Page built from scratch (cache emptied) | Throughput | Latency under load (p99) | Page weight (HTML + CSS + JS + images, gzip) | Requests | Web fonts declared (not counted) |\n";
    echo "|---|---:|---:|---:|---:|---:|---:|---:|---:|\n";
    $e = $sites['eclair']['pages'][$page];
    foreach ($sites as $key => $s) {
        $p = $s['pages'][$page];
        $isE = $key === 'eclair';
        $bold = static fn (string $v): string => $isE ? "**$v**" : $v;
        echo '| ' . $bold($s['name']) . ' | ' . $n($p['ttfb_median_ms'], 2) . ' ms | ' . $n($p['ttfb_p95_ms'], 2) . ' ms | ' . $n($p['uncached_ttfb_median_ms'], 2) . ' ms | '
            . $n($p['load']['rps']) . ' req/s | ' . $n($p['load']['latency_p99_ms'], 1) . ' ms | '
            . $kb($p['weight']['total_wire']) . ' | ' . $p['weight']['requests'] . ' | '
            . (count($p['weight']['fonts_declared']) ? count($p['weight']['fonts_declared']) . ' (' . $kb(array_sum($p['weight']['fonts_declared'])) . ')' : 'none') . " |\n";
    }
    echo "\n";
    $wp = $sites['wordpress']['pages'][$page];
    $wc = $sites['wordpress-cache']['pages'][$page];
    foreach (['WordPress' => $wp, 'WordPress + Cache Enabler' => $wc] as $label => $o) {
        echo "- Éclair vs $label: first byte " . $cmp($o['ttfb_median_ms'] / max(0.001, $e['ttfb_median_ms']), 'faster', 'slower')
            . ', throughput ' . $cmp((float) $e['load']['rps'] / max(1, (float) $o['load']['rps']), 'higher', 'lower')
            . ', page weight ' . $cmp($o['weight']['total_wire'] / max(1, $e['weight']['total_wire']), 'lighter', 'heavier') . "\n";
    }
    echo "\n";
}

echo "## Installation\n\n| | Size on disk (code + content) | PHP files | Needs a database |\n|---|---:|---:|---|\n";
foreach ($sites as $key => $s) {
    echo '| ' . $s['name'] . ' | ' . number_format($s['disk_kb'] / 1024, 1) . ' MB | ' . number_format($s['php_files'], 0, '.', ' ')
        . ' | ' . ($key === 'eclair' ? 'No' : 'Yes (MariaDB)') . " |\n";
}
echo "\nRaw data: `eclair.json`, `wordpress.json`, `wordpress-cache.json` in this folder.\n";
