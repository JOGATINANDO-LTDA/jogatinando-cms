<?php
require_once __DIR__ . '/config.php';

$consoleSlug = trim($_GET['console'] ?? '');
if ($consoleSlug === '') {
    header('Location: /retro');
    exit;
}

$siteName = getSetting('site_name', SITE_NAME);
$siteTagline = getSetting('site_tagline', SITE_TAGLINE);
$footerDescription = getSetting('footer_description', '');

$console = dbQueryOne("SELECT * FROM retro_consoles WHERE slug = ? AND active = 1", [$consoleSlug]);
if (!$console) {
    header('Location: /retro');
    exit;
}

$where = "console = ? AND active = 1";
$params = [$consoleSlug];

$typeFilter = $_GET['type'] ?? '';
if (in_array($typeFilter, ['original', 'modified'], true)) {
    $where .= " AND type = ?";
    $params[] = $typeFilter;
}

$letter = $_GET['letter'] ?? '';
if ($letter !== '' && preg_match('/^[A-Z]$/i', $letter)) {
    $where .= " AND UPPER(SUBSTR(title, 1, 1)) = ?";
    $params[] = strtoupper($letter);
}

$sort = $_GET['sort'] ?? '';
$allowedOrders = ['title ASC', 'title DESC', 'featured DESC, sort_order ASC, created_at DESC'];
if ($sort === 'alpha_asc') {
    $order = 'title ASC';
} elseif ($sort === 'alpha_desc') {
    $order = 'title DESC';
} else {
    $order = 'featured DESC, sort_order ASC, created_at DESC';
}

$games = dbQuery("SELECT * FROM retro_games WHERE $where ORDER BY $order", $params);

// Available letters from existing games
$letterRows = dbQuery("SELECT DISTINCT UPPER(SUBSTR(title, 1, 1)) as letter FROM retro_games WHERE console = ? AND active = 1 AND title != '' ORDER BY letter ASC", [$consoleSlug]);
$availableLetters = array_column($letterRows, 'letter');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($console['name']) ?> — <?= e($siteName) ?></title>
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
                <li><a href="/retro">Retro</a></li>
            </ul>
        </div>
    </nav>

    <main class="section section-dark catalog-page">
        <div class="container">
            <div class="section-title catalog-title">
                <?php if (!empty($console['thumbnail_url'])): ?>
                    <img src="<?= e(mediaUrl($console['thumbnail_url'])) ?>" alt="<?= e($console['name']) ?>" style="width:80px;height:80px;border-radius:12px;object-fit:cover;margin:0 auto 12px;border:2px solid var(--gold);box-shadow:0 0 20px var(--gold-glow)">
                <?php else: ?>
                    <div style="font-size:56px;margin-bottom:8px"><?= e($console['icon'] ?? '🎮') ?></div>
                <?php endif; ?>
                <h2><?= e($console['name']) ?></h2>
                <p>Jogos retro disponíveis para este console.</p>
            </div>

            <?php if (!empty($games)): ?>
            <div class="retro-filter-bar">
                <div class="retro-filter-group">
                    <label>Ordenar</label>
                    <select onchange="window.location.href=this.value">
                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => ''])) ?>" <?= $sort === '' ? 'selected' : '' ?>>Padrão</option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'alpha_asc'])) ?>" <?= $sort === 'alpha_asc' ? 'selected' : '' ?>>A-Z</option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'alpha_desc'])) ?>" <?= $sort === 'alpha_desc' ? 'selected' : '' ?>>Z-A</option>
                    </select>
                </div>

                <div class="retro-filter-group retro-filter-letters">
                    <label>Letra</label>
                    <div class="retro-filter-letter-list">
                        <?php foreach (range('A', 'Z') as $l): ?>
                            <?php $hasLetter = in_array($l, $availableLetters); ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['letter' => $hasLetter ? $l : ''])) ?>"
                               class="retro-filter-letter <?= $letter === $l ? 'active' : '' ?> <?= !$hasLetter ? 'disabled' : '' ?>"
                               <?= !$hasLetter ? 'tabindex="-1" aria-disabled="true"' : '' ?>><?= e($l) ?></a>
                        <?php endforeach; ?>
                        <?php if ($letter !== ''): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['letter' => ''])) ?>" class="retro-filter-letter retro-filter-clear">✕</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="retro-filter-group">
                    <label>Tipo</label>
                    <div class="retro-filter-type-list">
                        <a href="?<?= http_build_query(array_merge($_GET, ['type' => ''])) ?>" class="retro-filter-type <?= $typeFilter === '' ? 'active' : '' ?>">Todos</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['type' => 'original'])) ?>" class="retro-filter-type <?= $typeFilter === 'original' ? 'active' : '' ?>">Original</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['type' => 'modified'])) ?>" class="retro-filter-type <?= $typeFilter === 'modified' ? 'active' : '' ?>">Modificado</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="catalog-grid">
                <?php if (empty($games)): ?>
                    <div class="catalog-empty">
                        <div class="empty-icon">🕹️</div>
                        <h3>Nenhum jogo cadastrado</h3>
                        <p>O primeiro lançamento do console <?= e($console['name']) ?> será o JoeEMac2.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($games as $game): ?>
                        <a href="/retro/<?= e($consoleSlug) ?>/<?= e($game['slug']) ?>" class="catalog-card">
                            <div class="catalog-thumb">
                                <?php if (!empty($game['thumbnail_url'])): ?>
                                    <img src="<?= e(mediaUrl($game['thumbnail_url'])) ?>" alt="<?= e($game['title']) ?>">
                                <?php else: ?>
                                    <div class="catalog-thumb-placeholder">🎮</div>
                                <?php endif; ?>
                                <div class="catalog-badges">
                                    <?php if ($game['type'] === 'modified'): ?>
                                        <span class="catalog-badge catalog-badge-modified" title="<?= e($game['modification_description'] ?: 'Modificado') ?>"><?= e($game['modification_description'] ?: 'Modificado') ?></span>
                                    <?php else: ?>
                                        <span class="catalog-badge catalog-badge-web">Original</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="catalog-content">
                                <h3><?= e($game['title']) ?></h3>
                                <?php if (!empty($game['description'])): ?>
                                    <p><?= e(truncateText($game['description'], 110)) ?></p>
                                <?php endif; ?>
                                <div class="catalog-card-footer">
                                    <span>Jogar</span>
                                    <span class="catalog-arrow">→</span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/footer-front.php'; ?>

    <script src="<?= assetUrl('/assets/js/main.js') ?>"></script>
</body>
</html>
