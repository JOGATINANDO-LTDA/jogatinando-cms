<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Catálogo';
$siteName = getSetting('site_name', SITE_NAME);
$siteTagline = getSetting('site_tagline', SITE_TAGLINE);
$footerDescription = getSetting('footer_description', '');

$type = in_array($_GET['type'] ?? '', ['autoral', 'cliente', 'externo'], true) ? $_GET['type'] : '';
$engine = trim($_GET['engine'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];

if ($type !== '') {
    $where[] = 'g.game_type = ?';
    $params[] = $type;
}

if ($engine !== '') {
    $where[] = 'LOWER(e.slug) = LOWER(?)';
    $params[] = $engine;
}

if ($search !== '') {
    $where[] = '(g.title LIKE ? OR g.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$sql = 'SELECT g.*, e.slug as engine_slug, e.icon as engine_icon, e.color as engine_color FROM games g LEFT JOIN engines e ON g.engine = e.name WHERE g.active = 1 AND e.active = 1';
if ($where) {
    $sql .= ' AND ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY g.featured DESC, g.sort_order ASC, g.created_at DESC';

$games = dbQuery($sql, $params);
$engines = dbQuery('SELECT name, slug, icon FROM engines WHERE active = 1 ORDER BY name ASC');

$routeEngineSlug = function ($value) {
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $value));
    return $slug !== '' ? $slug : generateSlug($value);
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e($siteName) ?></title>
    <meta name="description" content="<?= e($footerDescription ?: $siteTagline) ?>">
    <link rel="icon" href="<?= SITE_URL ?>/assets/svg/logo.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
    <div class="cosmic-bg"></div>
    <div class="stars" id="stars"></div>

    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="/" class="navbar-brand">
                <div class="logo-shield">
                    <img src="/assets/svg/logo.svg" alt="<?= e($siteName) ?>">
                </div>
                <?= e($siteName) ?>
            </a>
            <ul class="navbar-menu">
                <li><a href="/">Início</a></li>
                <li><a href="/catalogo">Catálogo</a></li>
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
        <a href="/#contact" class="btn btn-gold mobile-link">Contato</a>
    </div>

    <main class="section section-dark catalog-page">
        <div class="container">
            <div class="section-title catalog-title">
                <h2>Catálogo de <span class="gold">Jogos</span></h2>
                <p>Filtre por tipo e engine. Jogos web-playable abrem direto; os demais seguem para a página de detalhes.</p>
            </div>

            <form method="GET" class="catalog-filters-panel">
                <div class="catalog-filters-grid">
                    <div class="catalog-filter">
                        <label for="type">Tipo</label>
                        <select id="type" name="type">
                            <option value="">Todos</option>
                            <option value="autoral" <?= $type === 'autoral' ? 'selected' : '' ?>>Autoral</option>
                            <option value="cliente" <?= $type === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                            <option value="externo" <?= $type === 'externo' ? 'selected' : '' ?>>Externo</option>
                        </select>
                    </div>

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
                            <input type="text" id="search" name="search" value="<?= e($search) ?>" placeholder="Buscar jogos...">
                            <button type="submit" class="btn btn-gold btn-sm">Buscar</button>
                        </div>
                    </div>
                </div>

                <div class="catalog-filter-actions">
                    <button type="submit" class="btn btn-outline btn-sm">Aplicar filtros</button>
                    <?php if ($type !== '' || $engine !== '' || $search !== ''): ?>
                        <a href="/catalogo" class="btn btn-outline btn-sm">Limpar</a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="catalog-grid">
                <?php if (empty($games)): ?>
                    <div class="catalog-empty">
                        <div class="empty-icon">🎮</div>
                        <h3>Nenhum jogo encontrado</h3>
                        <p>Altere os filtros ou tente outra busca.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($games as $g): ?>
                        <?php
                            $engineSlug = $g['engine_slug'] ?? generateSlug($g['engine']);
                            $gameUrl = '/' . $engineSlug . '/' . $g['slug'];
                            $gameType = $g['game_type'] ?? 'autoral';
                            if ($gameType === 'cliente') {
                                $typeLabel = 'Cliente';
                                $typeClass = 'catalog-badge-client';
                            } elseif ($gameType === 'externo') {
                                $typeLabel = 'Externo';
                                $typeClass = 'catalog-badge-external';
                            } else {
                                $typeLabel = 'Autoral';
                                $typeClass = 'catalog-badge-autoral';
                            }
                        ?>
                        <a href="<?= e($gameUrl) ?>" class="catalog-card">
                            <div class="catalog-thumb">
                                <?php if (!empty($g['thumbnail_url'])): ?>
                                    <img src="<?= e($g['thumbnail_url']) ?>" alt="<?= e($g['title']) ?>">
                                <?php else: ?>
                                    <div class="catalog-thumb-placeholder"><?= e($g['engine_icon'] ?? getEngineIcon($g['engine'])) ?></div>
                                <?php endif; ?>

                                <div class="catalog-badges">
                                    <span class="catalog-badge <?= $typeClass ?>"><?= e($typeLabel) ?></span>
                                    <span class="catalog-badge catalog-badge-engine" style="background:<?= e($g['engine_color'] ?? getEngineColor($g['engine'])) ?>"><?= e($g['engine_icon'] ?? getEngineIcon($g['engine'])) ?> <?= e($g['engine']) ?></span>
                                    <?php if ($gameType === 'externo'): ?>
                                    <span class="catalog-badge catalog-badge-web">🌐 Site Externo</span>
                                    <?php else: ?>
                                    <span class="catalog-badge <?= !empty($g['is_web_playable']) ? 'catalog-badge-web' : 'catalog-badge-store' ?>">
                                        <?= !empty($g['is_web_playable']) ? 'Jogar' : 'Loja' ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="catalog-content">
                                <h3><?= e($g['title']) ?></h3>
                                <?php if (!empty($g['description'])): ?>
                                    <p><?= e(truncateText($g['description'], 110)) ?></p>
                                <?php endif; ?>
                                <div class="catalog-card-footer">
                                    <span><?= $gameType === 'externo' ? 'Acessar site' : (!empty($g['is_web_playable']) ? 'Abrir jogo' : 'Ver detalhes') ?></span>
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

    <script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
