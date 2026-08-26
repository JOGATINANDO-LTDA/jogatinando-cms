<?php
require_once 'config.php';

$slug = trim($_GET['slug'] ?? '');

$siteName = getSetting('site_name', SITE_NAME);
$siteTagline = getSetting('site_tagline', SITE_TAGLINE);

// ── Single post mode ──
if ($slug !== '') {
    $post = dbQueryOne("SELECT * FROM blog_posts WHERE slug = ? AND active = 1", [$slug]);
    if (!$post) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        exit;
    }
}

// ── Listing mode ──
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$totalPosts = 0;
$posts = [];
$totalPages = 1;

if (!isset($post)) {
    $totalPosts = (int)dbQueryOne("SELECT COUNT(*) AS c FROM blog_posts WHERE active = 1")['c'];
    $totalPages = max(1, (int)ceil($totalPosts / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    $posts = dbQuery("SELECT * FROM blog_posts WHERE active = 1 ORDER BY published_at DESC LIMIT $perPage OFFSET $offset");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($post) && $post ? e($post['title']) . ' — ' . e($siteName) : 'Blog — ' . e($siteName) ?></title>
    <meta name="description" content="<?= isset($post) && $post ? e(truncateText(strip_tags(parseMarkdown($post['content'])), 160)) : e(getSetting('footer_description', '')) ?>">
    <link rel="icon" href="<?= siteFaviconUrl() ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= assetUrl('/assets/css/style.css') ?>">
</head>
<body>
    <div class="cosmic-bg"></div>
    <div class="stars" id="stars"></div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="/" class="navbar-brand">
                <div class="logo-shield">
                    <img src="<?= siteLogoUrl() ?>" alt="Logo">
                </div>
                <?= e($siteName) ?>
            </a>
            <ul class="navbar-menu">
                <li><a href="/">Início</a></li>
                <li><a href="/catalogo">Catálogo</a></li>
                <li><a href="/blog">Blog</a></li>
            </ul>
            <button class="navbar-toggle" id="mobileToggle" aria-label="Menu">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>
        <ul class="mobile-menu" id="mobileMenu">
            <li><a href="/">Início</a></li>
            <li><a href="/catalogo">Catálogo</a></li>
            <li><a href="/blog">Blog</a></li>
        </ul>
    </nav>

<?php if (isset($post) && $post): ?>
    <!-- Single Post -->
    <section class="section" style="padding-top:120px;">
        <div class="container" style="max-width:820px;">
            <article class="blog-post-full">
                <?php if (!empty($post['thumbnail_url'])): ?>
                <div class="blog-post-thumb" style="margin-bottom:32px;">
                    <img src="<?= e(mediaUrl($post['thumbnail_url'])) ?>" alt="<?= e($post['title']) ?>" style="width:100%;border-radius:12px;border:1px solid var(--border);">
                </div>
                <?php endif; ?>
                <h1 style="font-family:'Cinzel',serif;font-size:clamp(28px,4vw,42px);margin-bottom:16px;">
                    <?= e($post['title']) ?>
                    <?php if (!empty($post['is_premium'])): ?><span style="vertical-align:middle;margin-left:8px;padding:3px 10px;font-size:11px;font-weight:700;border-radius:999px;background:var(--gold);color:#000;">Premium</span><?php endif; ?>
                </h1>
                <time style="display:block;color:var(--muted);font-size:14px;margin-bottom:32px;">
                    <?= date('d/m/Y', strtotime($post['published_at'])) ?> · <?= e(timeAgo($post['published_at'])) ?>
                </time>

                <?php
                $fullHtml = parseMarkdown($post['content']);
                if (!empty($post['is_premium'])) {
                    // Premium gate: teaser + CTA
                    $plain = strip_tags($fullHtml);
                    $words = preg_split('/\s+/u', $plain);
                    $teaser = implode(' ', array_slice($words, 0, 80));
                    echo '<div class="blog-post-body">' . nl2br(e($teaser)) . '&hellip;</div>';
                    echo '<div class="premium-gate" style="margin-top:40px;padding:40px 24px;text-align:center;background:linear-gradient(135deg,oklch(0.22 0.08 85),oklch(0.18 0.06 45));border:1px solid var(--gold);border-radius:12px;">';
                    echo '<h3 style="color:var(--gold);margin-bottom:12px;">Conteúdo Premium</h3>';
                    echo '<p style="max-width:480px;margin:0 auto 20px;">Este artigo é exclusivo para membros. Apoie o estúdio para desbloquear todo o conteúdo.</p>';
                    echo '<a href="/#contact" class="btn btn-gold">Quero acessar</a> ';
                    echo '<a href="/blog" class="btn btn-outline">Voltar ao blog</a>';
                    echo '</div>';
                } else {
                    echo '<div class="blog-post-body">' . $fullHtml . '</div>';
                    if (!empty($post['external_url'])) {
                        echo '<p style="margin-top:24px;"><a href="' . e($post['external_url']) . '" class="btn btn-gold" target="_blank" rel="noopener">Fonte original</a></p>';
                    }
                }
                ?>

                <div style="margin-top:48px;">
                    <a href="/blog" class="btn btn-outline">&larr; Voltar ao blog</a>
                </div>
            </article>
        </div>
    </section>
<?php else: ?>
    <!-- Blog Listing -->
    <section class="section" style="padding-top:140px;">
        <div class="container">
            <div class="section-title">
                <h2>Blog</h2>
                <p><?= $totalPosts ?> artigo(s) publicado(s)</p>
            </div>
            <?php if (empty($posts)): ?>
                <p style="text-align:center;color:var(--muted);">Nenhum artigo publicado ainda.</p>
            <?php else: ?>
            <div class="blog-grid">
                <?php foreach ($posts as $p): ?>
                <a href="/blog/<?= e($p['slug']) ?>" class="blog-card" style="text-decoration:none;">
                    <?php if ($p['thumbnail_url']): ?>
                    <div class="blog-thumb">
                        <img src="<?= e(mediaUrl($p['thumbnail_url'])) ?>" alt="<?= e($p['title']) ?>">
                    </div>
                    <?php endif; ?>
                    <div class="blog-content">
                        <h3><?= e($p['title']) ?><?php if (!empty($p['is_premium'])): ?> <span style="padding:2px 8px;font-size:10px;font-weight:700;border-radius:999px;background:var(--gold);color:#000;">Premium</span><?php endif; ?></h3>
                        <p><?= e(truncateText(strip_tags(parseMarkdown($p['content'])), 150)) ?></p>
                        <time><?= date('d/m/Y', strtotime($p['published_at'])) ?></time>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php if ($totalPages > 1): ?>
            <div style="display:flex;gap:8px;justify-content:center;margin-top:40px;">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="/blog?page=<?= $i ?>" class="btn <?= $i === $page ? 'btn-gold' : 'btn-outline' ?> btn-sm"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

    <!-- Footer -->
    <?php require_once __DIR__ . '/includes/footer-front.php'; ?>

    <script src="<?= assetUrl('/assets/js/main.js') ?>"></script>
</body>
</html>
