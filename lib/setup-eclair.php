<?php
// Installs Éclair through its own web installer (the same 4 questions a user answers),
// then loads the shared test articles. Usage: php setup-eclair.php <docroot> <port> <articles.json>
declare(strict_types=1);

[, $root, $port, $articlesFile] = $argv;
$base = 'http://127.0.0.1:' . $port;
$jar = tempnam(sys_get_temp_dir(), 'eclair-jar');

function http(string $url, array $post = [], string $jar = ''): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $post ? ['Accept: application/json'] : []]);
    if ($post) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = (string) curl_exec($ch);
    return ['status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => $body];
}

function token(string $body): array
{
    return preg_match('/name="(_ft|_csrf)" value="([a-f0-9]+)"/', $body, $m) ? [$m[1] => $m[2]] : [];
}

$form = http($base . '/', [], $jar);
$res = http($base . '/', token($form['body']) + [
    'ui' => 'en', 'site_name' => 'Benchmark Blog', 'site_tagline' => 'Practical guides', 'site_lang' => 'en',
    'site_type' => 'blog', 'user_name' => 'Bench Admin', 'user_email' => 'bench@example.com',
    'user_pass' => 'Quiet-Harbor-Lantern-91', 'index' => '1', 'ai' => 'open', 'admin_path' => 'admin',
], $jar);
if (!is_file($root . '/data/content') && !is_dir($root . '/data/content')) {
    fwrite(STDERR, "Eclair install failed (HTTP {$res['status']}): " . substr($res['body'], 0, 300) . "\n");
    exit(1);
}

@unlink($jar);
echo "Eclair installed\n";
// The articles are then loaded by seed-eclair.php, run as the web server user (see run.sh).
