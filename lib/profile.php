<?php
// Profiling pass: what does ONE request cost inside PHP? (files loaded, peak memory, SQL queries).
// Runs with profile-prepend.php enabled in PHP-FPM, separately from the timing measurements.
// Usage: php profile.php <out.json> <key>|<base-url>|<home-path>|<article-path>|<page-cache-dir> ...
declare(strict_types=1);

$out = $argv[1];
$log = '/var/lib/eclair-bench/profile.jsonl';
touch($log);
chmod($log, 0666); // written by PHP-FPM (www-data), emptied here (root)
$headers = ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
    'Accept-Encoding: gzip'];

function hit(string $url, array $headers): void
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers]);
    curl_exec($ch);
}

function purge(string $dir): void
{
    if ($dir !== '' && is_dir($dir)) {
        shell_exec('find ' . escapeshellarg($dir) . ' -mindepth 1 ! -name index.html ! -name .htaccess -delete 2>/dev/null');
    }
}

function median(array $v): float
{
    sort($v);
    $n = count($v);
    return $n === 0 ? 0.0 : (float) ($n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2);
}

/** Runs $n requests and returns the median of what the prepend file logged for them. */
function sample(string $url, int $n, array $headers, string $log, ?callable $before = null): array
{
    $rows = [];
    for ($i = 0; $i < $n; $i++) {
        if ($before) {
            $before();
        }
        file_put_contents($log, '');
        hit($url, $headers);
        usleep(50000);
        $lines = array_filter(array_map(static fn ($l) => json_decode($l, true), file($log) ?: []));
        // The site may call itself (cron, loopback): keep the request we sent.
        $path = (string) parse_url($url, PHP_URL_PATH);
        foreach ($lines as $l) {
            if (rtrim((string) $l['uri'], '/') === rtrim($path, '/') || $l['uri'] === $path) {
                $rows[] = $l;
                break;
            }
        }
    }
    $pick = static fn (string $k) => median(array_column($rows, $k));
    return ['samples' => count($rows), 'php_files' => (int) $pick('php_files'), 'peak_memory_kb' => (int) $pick('peak_memory_kb'),
        'sql_queries' => (int) $pick('sql_queries'), 'wall_ms' => round($pick('wall_ms'), 2)];
}

$result = [];
foreach (array_slice($argv, 2) as $spec) {
    [$key, $base, $home, $article, $cacheDir] = array_pad(explode('|', $spec), 5, '');
    foreach (['home' => $home, 'article' => $article] as $page => $path) {
        $url = $base . $path;
        hit($url, $headers); // warm OPcache
        $result[$key][$page]['uncached'] = sample($url, 7, $headers, $log, static fn () => purge($cacheDir));
        hit($url, $headers); // rebuild the page cache
        $result[$key][$page]['cached'] = sample($url, 7, $headers, $log);
        echo sprintf("  %-16s %-8s from scratch: %3d PHP files, %5d KB, %2d SQL | from cache: %3d PHP files, %5d KB, %2d SQL\n", $key, $page,
            $result[$key][$page]['uncached']['php_files'], $result[$key][$page]['uncached']['peak_memory_kb'], $result[$key][$page]['uncached']['sql_queries'],
            $result[$key][$page]['cached']['php_files'], $result[$key][$page]['cached']['peak_memory_kb'], $result[$key][$page]['cached']['sql_queries']);
    }
}
file_put_contents($out, json_encode($result, JSON_PRETTY_PRINT) . "\n");
