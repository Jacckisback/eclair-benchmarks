<?php
// Installs WordPress with its default theme, pretty permalinks and the shared test articles.
// Usage: php setup-wordpress.php <docroot> <site-url> <articles.json> <with-cache: 0|1>
declare(strict_types=1);

[, $root, $url, $articlesFile, $withCache] = $argv;

define('WP_INSTALLING', true);
$_SERVER['HTTP_HOST'] = parse_url($url, PHP_URL_HOST) . ':' . parse_url($url, PHP_URL_PORT);
$_SERVER['REQUEST_URI'] = '/';
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
add_filter('pre_wp_mail', '__return_false'); // no welcome e-mail on a test machine

if (!is_blog_installed()) {
    wp_install('Benchmark Blog', 'admin', 'bench@example.com', true, '', 'Quiet-Harbor-Lantern-91', 'en_US');
}
update_option('siteurl', $url);
update_option('home', $url);
update_option('blogdescription', 'Practical guides');
global $wp_rewrite;
$wp_rewrite->set_permalink_structure('/%postname%/');
update_option('posts_per_page', 10);

// Remove the sample content so every CMS shows exactly the same 20 articles.
foreach (get_posts(['post_type' => 'post', 'numberposts' => -1, 'post_status' => 'any']) as $p) {
    wp_delete_post($p->ID, true);
}

$articles = json_decode((string) file_get_contents($articlesFile), true);
wp_defer_term_counting(true); // faster bulk import, counts updated at the end
wp_suspend_cache_invalidation(true);
foreach ($articles as $a) {
    $content = implode("\n\n", array_map(static fn (string $p): string => "<!-- wp:paragraph -->\n<p>$p</p>\n<!-- /wp:paragraph -->", $a['paragraphs']));
    wp_insert_post(['post_title' => $a['title'], 'post_name' => $a['slug'], 'post_content' => $content,
        'post_excerpt' => $a['excerpt'], 'post_status' => 'publish', 'post_date' => $a['date'], 'post_author' => 1]);
}

wp_suspend_cache_invalidation(false);
wp_defer_term_counting(false);

if ($withCache === '1') {
    $result = activate_plugin('cache-enabler/cache-enabler.php');
    if (is_wp_error($result)) {
        fwrite(STDERR, 'Cache Enabler activation failed: ' . $result->get_error_message() . "\n");
        exit(1);
    }
}
flush_rewrite_rules(true);
echo 'WordPress ' . get_bloginfo('version') . ' ready with ' . count($articles) . ' articles, theme ' . get_stylesheet()
    . ($withCache === '1' ? ', Cache Enabler on' : ', no cache plugin') . "\n";
$firstPost = get_posts(['name' => $articles[0]['slug'], 'post_type' => 'post', 'numberposts' => 1]);
echo 'ARTICLE_PATH=' . wp_make_link_relative(get_permalink($firstPost[0])) . "\n";
