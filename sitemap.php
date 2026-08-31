<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

$db = getDB();
if (!$db) {
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    exit;
}

$baseUrl = SITE_URL;

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Static pages
$staticPages = [
    ['loc' => '/', 'changefreq' => 'weekly', 'priority' => '1.0'],
    ['loc' => '/catalogo', 'changefreq' => 'weekly', 'priority' => '0.9'],
    ['loc' => '/retro', 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => '/blog', 'changefreq' => 'weekly', 'priority' => '0.8'],
];

foreach ($staticPages as $p) {
    echo '<url>' . "\n";
    echo '  <loc>' . e($baseUrl . $p['loc']) . '</loc>' . "\n";
    echo '  <changefreq>' . $p['changefreq'] . '</changefreq>' . "\n";
    echo '  <priority>' . $p['priority'] . '</priority>' . "\n";
    echo '</url>' . "\n";
}

// Games
$games = $db->query("SELECT g.slug, g.engine, g.updated_at, e.slug as engine_slug FROM games g LEFT JOIN engines e ON g.engine = e.name WHERE g.active = 1 ORDER BY g.updated_at DESC")->fetchAll();
foreach ($games as $g) {
    $engineSlug = $g['engine_slug'] ?: strtolower($g['engine']);
    $lastmod = $g['updated_at'] ? date('Y-m-d', strtotime($g['updated_at'])) : date('Y-m-d');
    echo '<url>' . "\n";
    echo '  <loc>' . e($baseUrl . '/' . $engineSlug . '/' . $g['slug']) . '</loc>' . "\n";
    echo '  <lastmod>' . $lastmod . '</lastmod>' . "\n";
    echo '  <changefreq>monthly</changefreq>' . "\n";
    echo '  <priority>0.8</priority>' . "\n";
    echo '</url>' . "\n";
}

// Blog posts
$posts = $db->query("SELECT slug, updated_at, published_at FROM blog_posts WHERE active = 1 ORDER BY published_at DESC")->fetchAll();
foreach ($posts as $p) {
    $lastmod = $p['updated_at'] ? date('Y-m-d', strtotime($p['updated_at'])) : ($p['published_at'] ? date('Y-m-d', strtotime($p['published_at'])) : date('Y-m-d'));
    echo '<url>' . "\n";
    echo '  <loc>' . e($baseUrl . '/blog/' . $p['slug']) . '</loc>' . "\n";
    echo '  <lastmod>' . $lastmod . '</lastmod>' . "\n";
    echo '  <changefreq>monthly</changefreq>' . "\n";
    echo '  <priority>0.6</priority>' . "\n";
    echo '</url>' . "\n";
}

echo '</urlset>' . "\n";
exit;
