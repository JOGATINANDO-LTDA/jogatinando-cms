<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Templates';
$siteName = getSetting('site_name', SITE_NAME);
$siteTagline = getSetting('site_tagline', SITE_TAGLINE);
$footerDescription = getSetting('footer_description', '');

$engine = trim($_GET['engine'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = ['t.active = 1'];
$params = [];

if ($engine !== '') {
    $where[] = 'LOWER(e.slug) = LOWER(?)';
    $params[] = $engine;
}

if ($search !== '') {
    $where[] = '(t.title LIKE ? OR t.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$sql = 'SELECT t.*, e.slug as engine_slug, e.icon as engine_icon, e.color as engine_color, (SELECT COUNT(*) FROM template_links WHERE template_id = t.id) as link_count FROM game_templates t LEFT JOIN engines e ON t.engine = e.name WHERE ' . implode(' AND ', $where) . ' ORDER BY t.featured DESC, t.sort_order ASC, t.created_at DESC';

$templates = dbQuery($sql, $params);
$engines = dbQuery('SELECT name, slug, icon FROM engines WHERE active = 1 ORDER BY name ASC');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e($siteName) ?></title>
    <meta name="description" content="<?= e($footerDescription ?: $siteTagline) ?>">
    <link rel="icon" href="<?= siteFaviconUrl() ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= assetUrl('/assets/css/style.css') ?>">
</head>
<body>
    <div class="cosmic-bg"></div>
    <div class="stars" id="stars"></div>

    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="/" class="navbar-brand">
                <div class="logo-shield">
                    <img src="<?= siteLogoUrl() ?>" alt="<?= e($siteName) ?>">
                </div>
                <?= e($siteName) ?>
            </a>
            <ul class="navbar-menu">
                <li><a href="/">Início</a></li>
                <li><a href="/catalogo">Catálogo</a></li>
                <li><a href="/templates" class="active">Templates</a></li>
                <li><a href="/#contact">Contato</a></li>
            </ul>
            <button class="navbar-toggle" id="mobileToggle" aria-label="Menu">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>
    </nav>

    <div class="mobile-nav" id="mobileNav">
        <button class="mobile-nav-close" id="mobileClose" aria-label="Fechar">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <a href="/" class="mobile-link">Início</a>
        <a href="/catalogo" class="mobile-link">Catálogo</a>
        <a href="/templates" class="mobile-link">Templates</a>
        <a href="/#contact" class="btn btn-gold mobile-link">Contato</a>
    </div>

    <main class="section section-dark catalog-page">
        <div class="container">
            <div class="section-title catalog-title">
                <h2><span class="gold">Templates</span> para Downloads</h2>
                <p>Bases prontas para novos projetos. Filtre por engine e faça download do código fonte.</p>
            </div>

            <form method="GET" action="/templates" class="catalog-filters-panel">
                <div class="catalog-filters-grid">
                    <div class="catalog-filter">
                        <label for="engine">Engine</label>
                        <select id="engine" name="engine">
                            <option value="">Todas</option>
                            <?php foreach ($engines as $eng): ?>
                                <option value="<?= e($eng['slug']) ?>" <?= strcasecmp($engine, $eng['slug']) === 0 ? 'selected' : '' ?>><?= e($eng['icon'] ?? '') ?> <?= e($eng['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="catalog-filter catalog-filter-search">
                        <label for="search">Busca</label>
                        <div class="catalog-search">
                            <input type="text" id="search" name="search" value="<?= e($search) ?>" placeholder="Buscar templates...">
                            <button type="submit" class="btn btn-gold btn-sm">Buscar</button>
                        </div>
                    </div>
                </div>

                <div class="catalog-filter-actions">
                    <?php if ($engine !== '' || $search !== ''): ?>
                        <a href="/templates" class="btn btn-outline btn-sm">Limpar</a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="catalog-grid">
                <?php if (empty($templates)): ?>
                    <div class="catalog-empty">
                        <div class="empty-icon">📦</div>
                        <h3>Nenhum template encontrado</h3>
                        <p>Altere os filtros ou tente outra busca.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($templates as $t): ?>
                        <?php
                            $engineSlug = $t['engine_slug'] ?? generateSlug($t['engine']);
                            $templateUrl = '/template/' . $engineSlug . '/' . $t['slug'];
                            $hasStore = !empty($t['link_count']);
                        ?>
                        <a href="<?= e($templateUrl) ?>" class="catalog-card">
                            <div class="catalog-thumb">
                                <?php if (!empty($t['thumbnail_url'])): ?>
                                    <img src="<?= e(mediaUrl($t['thumbnail_url'])) ?>" alt="<?= e($t['title']) ?>">
                                <?php else: ?>
                                    <div class="catalog-thumb-placeholder">📦</div>
                                <?php endif; ?>

                                <div class="catalog-badges">
                                    <span class="catalog-badge catalog-badge-engine" style="background:<?= e($t['engine_color'] ?? getEngineColor($t['engine'])) ?>"><?= e($t['engine_icon'] ?? getEngineIcon($t['engine'])) ?> <?= e($t['engine']) ?></span>
                                    <?php if ($t['featured']): ?>
                                        <span class="catalog-badge catalog-badge-autoral">Destaque</span>
                                    <?php endif; ?>
                                    <?php if ($t['has_free_file']): ?>
                                        <span class="catalog-badge" style="background:oklch(55% 0.18 145);color:white">⬇ Download Grátis</span>
                                    <?php endif; ?>
                                    <?php if ($hasStore): ?>
                                        <span class="catalog-badge catalog-badge-web">Loja</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="catalog-content">
                                <h3><?= e($t['title']) ?></h3>
                                <?php if (!empty($t['language'])): ?>
                                    <p style="font-size:13px;color:var(--muted);margin:4px 0"><?= e($t['language']) ?> <?= e($t['language_version']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($t['description'])): ?>
                                    <p><?= e(truncateText($t['description'], 110)) ?></p>
                                <?php endif; ?>
                                <div class="catalog-card-footer">
                                    <span>Ver detalhes</span>
                                    <span class="catalog-arrow">→</span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-bottom" style="border-top:0;padding-top:0">
                <p>&copy; <?= date('Y') ?> <?= e($siteName) ?>. Todos os direitos reservados.</p>
                <p><a href="/">Voltar para a home</a></p>
            </div>
        </div>
    </footer>

    <script src="<?= assetUrl('/assets/js/main.js') ?>"></script>
</body>
</html>
