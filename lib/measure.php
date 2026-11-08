<?php
// Measures one site: response time, throughput, page weight, size on disk.
// Usage: php measure.php <name> <base-url> <docroot> <article-path> <out.json> [page-cache-dir]
// page-cache-dir: emptied before each "uncached" request, to measure how fast the CMS builds a page from scratch.
declare(strict_types=1);

[, $name, $base, $root, $articlePath, $out] = $argv;
$cacheDir = $argv[6] ?? '';
$samples = (int) (getenv('BENCH_SAMPLES') ?: 300);
$wrkSeconds = (int) (getenv('BENCH_WRK_SECONDS') ?: 20);
$wrkConnections = (int) (getenv('BENCH_WRK_CONNECTIONS') ?: 20);

// Every request looks like a real browser visit (some page caches skip requests that do not ask for HTML).
const BROWSER_HEADERS = ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
    'Accept-Language: en-US,en;q=0.9'];

/** One request. $gzip = true: compressed as served to a browser ('wire' = bytes received). false: raw content. */
function get(string $url, bool $gzip = true): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => array_merge(BROWSER_HEADERS, [$gzip ? 'Accept-Encoding: gzip' : 'Accept-Encoding: identity'])]);
    $body = (string) curl_exec($ch);
    return ['status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => $body,
        'ttfb' => curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME) * 1000, 'wire' => strlen($body)];
}

function percentile(array $values, float $p): float
{
    sort($values);
    $i = ($p / 100) * (count($values) - 1);
    $lo = (int) floor($i);
    $hi = (int) ceil($i);
    return round($values[$lo] + ($values[$hi] - $values[$lo]) * ($i - $lo), 2);
}

/** Every file the browser must download to display the page: HTML, CSS, JS, fonts, images. */
function page_weight(string $base, string $path): array
{
    $page = get($base . $path, true);
    $html = get($base . $path, false)['body'];
    $assets = [];
    preg_match_all('~<link[^>]+rel=["\'](?:stylesheet|preload)["\'][^>]*>~i', $html, $links);
    foreach ($links[0] as $tag) {
        if (preg_match('~href=["\']([^"\']+)~', $tag, $m)) {
            $assets[] = $m[1];
        }
    }
    preg_match_all('~<script[^>]+src=["\']([^"\']+)~i', $html, $scripts);
    preg_match_all('~<img[^>]+src=["\']([^"\']+)~i', $html, $imgs);
    $assets = array_merge($assets, $scripts[1], $imgs[1]);
    $css = '';
    preg_match_all('~<style[^>]*>(.*?)</style>~is', $html, $inline);
    $css .= implode("\n", $inline[1]);
    $files = [];
    $resolve = static function (string $src) use ($base): ?string {
        $src = html_entity_decode($src);
        if (str_starts_with($src, 'data:')) {
            return null;
        }
        if (str_starts_with($src, '//')) {
            $src = 'http:' . $src;
        }
        if (!preg_match('~^https?://~', $src)) {
            $src = rtrim($base, '/') . '/' . ltrim($src, '/');
        }
        // Absolute URLs pointing to the site itself (WordPress writes its own address).
        return preg_replace('~^https?://[^/]+~', rtrim($base, '/'), $src);
    };
    foreach (array_unique($assets) as $src) {
        $u = $resolve($src);
        if ($u === null || isset($files[$u])) {
            continue;
        }
        $r = get($u, true);
        $files[$u] = $r['wire'];
        if (preg_match('~\.css(\?|$)~', $u)) {
            $css .= "\n" . get($u, false)['body'];
        }
    }
    // Web fonts: a browser only downloads the ones the page text actually uses, which cannot be known without a browser.
    // They are therefore NOT added to the page weight; the fonts declared by the theme are listed separately.
    preg_match_all('~url\(["\']?([^"\')]+\.woff2[^"\')]*)~i', $css, $fonts);
    $declared = [];
    foreach (array_unique($fonts[1]) as $src) {
        $u = $resolve($src);
        if ($u !== null) {
            $declared[$u] = get($u, true)['wire'];
        }
    }
    foreach (array_keys($declared) as $u) {
        unset($files[$u]); // a preloaded font would also be in $files
    }
    return ['html_bytes' => strlen($html), 'html_wire' => $page['wire'], 'requests' => 1 + count($files),
        'total_wire' => $page['wire'] + array_sum($files), 'assets' => $files, 'fonts_declared' => $declared];
}

function wrk(string $url, int $seconds, int $connections): array
{
    $h = implode(' ', array_map(static fn (string $x): string => '-H ' . escapeshellarg($x), array_merge(BROWSER_HEADERS, ['Accept-Encoding: gzip'])));
    $cmd = sprintf('wrk -t2 -c%d -d%ds --latency %s %s 2>&1', $connections, $seconds, $h, escapeshellarg($url));
    $o = (string) shell_exec($cmd);
    preg_match('~Requests/sec:\s+([\d.]+)~', $o, $rps);
    preg_match('~50%\s+([\d.]+)(us|ms|s)~', $o, $p50);
    preg_match('~99%\s+([\d.]+)(us|ms|s)~', $o, $p99);
    preg_match('~Non-2xx or 3xx responses:\s+(\d+)~', $o, $errors);
    $ms = static fn (array $m): ?float => $m ? round((float) $m[1] * ['us' => 0.001, 'ms' => 1, 's' => 1000][$m[2]], 2) : null;
    return ['rps' => isset($rps[1]) ? round((float) $rps[1]) : null, 'latency_p50_ms' => $ms($p50),
        'latency_p99_ms' => $ms($p99), 'errors' => (int) ($errors[1] ?? 0)];
}

/** Empties the page cache (files only: the folder itself stays). */
function purge(string $dir): void
{
    if ($dir !== '' && is_dir($dir)) {
        shell_exec('find ' . escapeshellarg($dir) . ' -mindepth 1 ! -name index.html ! -name .htaccess -delete 2>/dev/null');
    }
}

$result = ['name' => $name, 'pages' => []];
foreach (['home' => '/', 'article' => $articlePath] as $label => $path) {
    $first = get($base . $path);
    if ($first['status'] !== 200) {
        fwrite(STDERR, "$name $path answered HTTP {$first['status']}\n");
        exit(1);
    }
    for ($i = 0; $i < 50; $i++) { // warm-up: caches, OPcache
        get($base . $path);
    }
    $times = [];
    for ($i = 0; $i < $samples; $i++) {
        $times[] = get($base . $path)['ttfb'];
    }
    $uncached = [];
    for ($i = 0; $i < 30; $i++) {
        purge($cacheDir);
        $uncached[] = get($base . $path)['ttfb'];
    }
    get($base . $path); // cache rebuilt before the load test
    $result['pages'][$label] = ['path' => $path, 'ttfb_median_ms' => percentile($times, 50), 'ttfb_p95_ms' => percentile($times, 95),
        'uncached_ttfb_median_ms' => percentile($uncached, 50),
        'load' => wrk($base . $path, $wrkSeconds, $wrkConnections), 'weight' => page_weight($base, $path)];
    echo sprintf("  %-16s %-8s TTFB median %6.2f ms | %6s req/s | %4d KB, %d requests\n", $name, $label,
        $result['pages'][$label]['ttfb_median_ms'], $result['pages'][$label]['load']['rps'],
        $result['pages'][$label]['weight']['total_wire'] / 1024, $result['pages'][$label]['weight']['requests']);
}
$result['disk_kb'] = (int) trim((string) shell_exec('du -sk --exclude=cache --exclude=uploads --exclude=cache-enabler ' . escapeshellarg($root) . ' | cut -f1'));
$result['php_files'] = (int) trim((string) shell_exec('find ' . escapeshellarg($root) . ' -name "*.php" | wc -l'));
file_put_contents($out, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
