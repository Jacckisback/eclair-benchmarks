<?php
// Prepares the official Kirby Starterkit with the shared test articles, as Kirby "notes".
// Usage: php setup-kirby.php <docroot> <articles.json>
// Changes from the Starterkit defaults (all documented Kirby options):
//   - debug off (production setting) and the built-in pages cache on (cache.pages.active), so that Kirby, like Éclair,
//     serves pages from its own cache;
//   - 10 notes per list page instead of 6, the same as Éclair and WordPress.
// The Starterkit's sample notes are replaced by the test articles; its other pages (photography, about) are kept.
declare(strict_types=1);

[, $root, $articlesFile] = $argv;
$notes = $root . '/content/2_notes';

foreach (glob($notes . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
    shell_exec('rm -rf ' . escapeshellarg($dir));
}

$articles = json_decode((string) file_get_contents($articlesFile), true);
foreach ($articles as $i => $a) {
    $ts = strtotime($a['date'] . ' UTC');
    $dir = $notes . '/' . gmdate('Ymd', $ts) . '_' . $a['slug'];
    mkdir($dir, 0775, true);
    $blocks = [[
        'content' => ['text' => implode('', array_map(static fn (string $p): string => '<p>' . htmlspecialchars($p) . '</p>', $a['paragraphs']))],
        'id' => sprintf('%08x-0000-4000-8000-%012x', $i + 1, $i + 1), 'isHidden' => false, 'type' => 'text',
    ]];
    $fields = [
        'Title' => $a['title'],
        'Text' => json_encode($blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'Cover' => '[]',
        'Date' => gmdate('Y-m-d H:i:s', $ts),
        'Author' => '[]',
        'Tags' => '',
        'Subheading' => $a['excerpt'],
        'Uuid' => substr(hash('sha256', $a['slug']), 0, 16),
    ];
    $txt = implode("\n\n----\n\n", array_map(static fn ($k, $v) => "$k: $v", array_keys($fields), $fields));
    file_put_contents($dir . '/note.txt', $txt . "\n");
}

file_put_contents($root . '/site/config/config.php', <<<'PHP'
<?php
// eclair-benchmarks: production settings + Kirby's built-in pages cache.
return [
    'debug' => false,
    'yaml.handler' => 'symfony',
    'cache' => ['pages' => ['active' => true]],
];
PHP);

$controller = $root . '/site/controllers/notes.php';
file_put_contents($controller, str_replace('->paginate(6)', '->paginate(10)', (string) file_get_contents($controller)));

shell_exec('rm -rf ' . escapeshellarg($root . '/site/cache') . '/*');
echo 'Kirby Starterkit ready with ' . count($articles) . " articles (notes), pages cache on\n";
echo 'ARTICLE_PATH=/notes/' . $articles[0]['slug'] . "\n";
