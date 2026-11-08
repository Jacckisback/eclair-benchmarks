<?php
// Compares runs of different site sizes (same server type): how does each CMS behave as the number of articles grows?
// Usage: php sizes.php <out-dir> <run-dir> <run-dir> ...   → <out-dir>/README.md and <out-dir>/charts/*.svg
declare(strict_types=1);
require __DIR__ . '/svg.php';

[, $outDir] = $argv;
$runs = [];
foreach (array_slice($argv, 2) as $dir) {
    $dir = rtrim($dir, '/');
    $meta = json_decode((string) file_get_contents("$dir/meta.json"), true);
    $data = [];
    foreach ($meta['sites'] as $s) {
        $data[$s['key']] = json_decode((string) file_get_contents("$dir/{$s['key']}.json"), true) + ['label' => $s['name']];
    }
    $runs[(int) $meta['articles']] = ['meta' => $meta, 'dir' => basename($dir), 'data' => $data];
}
ksort($runs);
$sizes = array_keys($runs);
$keys = array_keys(reset($runs)['data']);
@mkdir("$outDir/charts", 0755, true);
$sizeLabels = array_map(static fn ($n) => fmt((float) $n), $sizes);
$value = static fn (int $size, string $key, string $page, string $metric) => (float) ($runs[$size]['data'][$key]['pages'][$page][$metric] ?? 0);

// Lines: at most three set-ups per chart (colour palette rule).
$lineKeys = array_values(array_intersect(['eclair', 'wordpress-cache', 'wordpress'], $keys));
$chart = static function (string $file, string $title, string $sub, string $page, string $metric, bool $log) use ($outDir, $sizes, $sizeLabels, $lineKeys, $value): void {
    $series = [];
    foreach ($lineKeys as $k) {
        $series[$k] = [SERIES_SHORT[$k], array_map(static fn ($n) => $value($n, $k, $page, $metric), $sizes)];
    }
    lines("$outDir/charts/$file", $title, $sub, $sizeLabels, 'Articles in the site', 'ms', $series, static fn (float $v) => fmt($v, 2) . ' ms', $log);
};
$chart('size-build-list.svg', 'List page built from scratch, as the site grows', 'Median of 30 requests, page cache emptied before each one. Apache. Lower is better.', 'home', 'uncached_ttfb_median_ms', true);
$chart('size-build-article.svg', 'Article page built from scratch, as the site grows', 'Median of 30 requests, page cache emptied before each one. Apache. Lower is better.', 'article', 'uncached_ttfb_median_ms', true);
$chart('size-ttfb-article.svg', 'Article page from cache, as the site grows', 'Median time to first byte of 300 requests. Apache. Lower is better.', 'article', 'ttfb_median_ms', true);

$md = "# Large sites: " . implode(', ', $sizeLabels) . " articles\n\n";
$md .= 'The same benchmark run with more content. Runs: ' . implode(', ', array_map(static fn ($n) => "[$n articles](../{$runs[$n]['dir']}/)", $sizes)) . ".\n\n";
$md .= "Éclair 2.x accepts at most 3,000 contents (articles + pages), so the largest run uses 2,900 articles.\n\n";
foreach (['home' => 'List page (10 latest articles)', 'article' => 'Article page'] as $page => $title) {
    $md .= "## $title\n\n";
    $md .= '| | ' . implode(' | ', array_map(static fn ($l) => "$l articles: from cache / built", $sizeLabels)) . " |\n|---|" . str_repeat('---:|', count($sizes)) . "\n";
    foreach ($keys as $k) {
        $md .= '| ' . $runs[$sizes[0]]['data'][$k]['label'] . ' | ' . implode(' | ', array_map(static fn ($n) => fmt($value($n, $k, $page, 'ttfb_median_ms'), 2) . ' ms / '
            . fmt($value($n, $k, $page, 'uncached_ttfb_median_ms'), 2) . ' ms', $sizes)) . " |\n";
    }
    $md .= "\n";
}
$md .= "## Throughput, article page (req/s, 20 connections)\n\n| | " . implode(' | ', $sizeLabels) . " |\n|---|" . str_repeat('---:|', count($sizes)) . "\n";
foreach ($keys as $k) {
    $md .= '| ' . $runs[$sizes[0]]['data'][$k]['label'] . ' | ' . implode(' | ', array_map(static fn ($n) => fmt((float) $runs[$n]['data'][$k]['pages']['article']['load']['rps']), $sizes)) . " |\n";
}
$md .= "\n## Size on disk (code + content)\n\n| | " . implode(' | ', $sizeLabels) . " |\n|---|" . str_repeat('---:|', count($sizes)) . "\n";
foreach ($keys as $k) {
    $md .= '| ' . $runs[$sizes[0]]['data'][$k]['label'] . ' | ' . implode(' | ', array_map(static fn ($n) => fmt($runs[$n]['data'][$k]['disk_kb'] / 1024, 1) . ' MB', $sizes)) . " |\n";
}
$md .= "\nWordPress content lives in MariaDB and is not counted in its size on disk.\n";
file_put_contents("$outDir/README.md", $md);
echo "Size comparison written to $outDir\n";
