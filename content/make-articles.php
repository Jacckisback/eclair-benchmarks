<?php
// Generates the shared test content: the same articles are loaded into every CMS.
// Usage: php make-articles.php [count=20]   → content/articles.json
// Deterministic: the same count always gives exactly the same file.
declare(strict_types=1);

$count = max(1, (int) ($argv[1] ?? 20));
$topics = ['coffee roasting', 'urban gardening', 'bread baking', 'trail running', 'home espresso', 'pottery glazes',
    'bike maintenance', 'balcony herbs', 'sourdough starters', 'film photography', 'chess openings', 'wine pairing',
    'woodworking joints', 'indoor plants', 'camping meals', 'tea brewing', 'knife sharpening', 'composting', 'pizza ovens', 'cocktail basics'];

$paragraphs = [
    'Getting started with %s does not require expensive equipment. A few reliable tools, some patience and a clear routine are enough to see real progress within a few weeks.',
    'The most common mistake beginners make with %s is trying to change everything at once. Change one variable at a time, write down what you did, and compare the results honestly.',
    'Quality matters more than quantity. In %s, a small number of good habits repeated every week will always beat an intense weekend followed by a month of nothing.',
    'Ask people who have practised %s for years what they wish they had known at the start. Most answers are simple: keep notes, clean your tools, and do not skip the basics.',
    'Once the basics of %s feel natural, you can start experimenting. This is where the activity becomes personal, and where you will find your own style and preferences.',
];

$articles = [];
$start = strtotime('2026-10-20 09:00:00 UTC');
for ($i = 0; $i < $count; $i++) {
    $topic = $topics[$i % count($topics)];
    $round = intdiv($i, count($topics)) + 1; // 1 for the first 20 articles, then 2, 3…
    $articles[] = [
        'slug' => 'guide-' . str_replace(' ', '-', $topic) . ($round > 1 ? '-part-' . $round : ''),
        'title' => 'A practical guide to ' . $topic . ($round > 1 ? ', part ' . $round : ''),
        'excerpt' => 'Simple, honest advice to get started with ' . $topic . '.',
        'paragraphs' => array_map(static fn (string $p): string => sprintf($p, $topic), $paragraphs),
        // One article per hour, the first one being the oldest.
        'date' => gmdate('Y-m-d H:i:s', $start - ($count - 1 - $i) * 3600),
    ];
}

file_put_contents(__DIR__ . '/articles.json', json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo count($articles) . " articles written to content/articles.json\n";
