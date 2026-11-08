<?php
// Generates the shared test content: the same 20 articles are loaded into every CMS.
// Deterministic: running it twice gives exactly the same file.
declare(strict_types=1);

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
foreach ($topics as $i => $topic) {
    $n = $i + 1;
    $blocks = [];
    foreach ($paragraphs as $p) {
        $blocks[] = sprintf($p, $topic);
    }
    $articles[] = [
        'slug' => 'guide-' . str_replace(' ', '-', $topic),
        'title' => 'A practical guide to ' . $topic,
        'excerpt' => 'Simple, honest advice to get started with ' . $topic . '.',
        'paragraphs' => $blocks,
        'date' => sprintf('2026-10-%02d 09:00:00', $n),
    ];
}

file_put_contents(__DIR__ . '/articles.json', json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo count($articles) . " articles written to content/articles.json\n";
