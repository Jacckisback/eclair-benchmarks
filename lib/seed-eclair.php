<?php
// Loads the shared test articles into an installed Éclair site through Éclair's own Content::save()
// (the same code path as the editor). Usage, as the web server user: php seed-eclair.php <docroot> <articles.json>
declare(strict_types=1);

[, $root, $articlesFile] = $argv;
define('ECLAIR', true);
define('ECLAIR_ROOT', $root);
preg_match("~define\('ECLAIR_VERSION', '([^']+)'\)~", (string) file_get_contents($root . '/index.php'), $v);
define('ECLAIR_VERSION', $v[1] ?? '0');
if (is_file($root . '/eclair-config.php')) {
    require $root . '/eclair-config.php';
}
defined('ECLAIR_DATA') || define('ECLAIR_DATA', $root . '/data');
defined('ECLAIR_DEBUG') || define('ECLAIR_DEBUG', false);
spl_autoload_register(static function (string $class) use ($root): void {
    if (strncmp($class, 'Eclair\\', 7) === 0) {
        $file = $root . '/app/lib/' . str_replace('\\', '/', substr($class, 7)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
require $root . '/app/lib/helpers.php';

use Eclair\Content;
use Eclair\Settings;
use Eclair\Users;

$user = array_values(Users::all())[0];
// Same number of articles per list page as WordPress (10; Éclair's default is 9).
Settings::save(['blog' => ['per_page' => 10]]);
// Remove the starter posts so that every CMS shows exactly the same 20 articles (pages are kept).
foreach (Content::index() as $id => $entry) {
    if (($entry['type'] ?? '') === 'post') {
        Content::delete((string) $id);
    }
}
$articles = json_decode((string) file_get_contents($articlesFile), true);
foreach ($articles as $a) {
    Content::save(['type' => 'post', 'status' => 'published', 'lang' => 'en', 'title' => $a['title'], 'slug' => $a['slug'],
        'date' => $a['date'], 'author' => $user['id'], 'seo' => ['description' => $a['excerpt']],
        'blocks' => array_map(static fn (string $p): array => ['type' => 'paragraph', 'text' => $p], $a['paragraphs'])], $user);
}
$first = null;
foreach (Content::published('post') as $e) {
    if ($e['slug'] === $articles[0]['slug']) {
        $first = Content::path($e);
    }
}
echo 'Eclair ' . ECLAIR_VERSION . ' ready with ' . count(Content::published('post')) . " articles\n";
echo "ARTICLE_PATH=$first\n";
