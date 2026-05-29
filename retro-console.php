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

$games = dbQuery("SELECT * FROM retro_games WHERE console = ? AND active = 1 ORDER BY featured DESC, sort_order ASC, created_at DESC", [$consoleSlug]);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($console['name']) ?> — <?= e($siteName) ?></title>
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
                <li><a href="/retro">Retro</a></li>
            </ul>
        </div>
    </nav>

    <main class="section section-dark catalog-page">
        <div class="container">
            <div class="section-title catalog-title">
                <h2><?= e($console['icon'] ?? '🎮') ?> <?= e($console['name']) ?></h2>
                <p>Jogos retro disponíveis para este console.</p>
            </div>

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
                                    <img src="<?= e($game['thumbnail_url']) ?>" alt="<?= e($game['title']) ?>">
                                <?php else: ?>
                                    <div class="catalog-thumb-placeholder">🎮</div>
                                <?php endif; ?>
                                <div class="catalog-badges">
                                    <span class="catalog-badge catalog-badge-web"><?= e($game['type'] === 'modified' ? 'Modificado' : 'Original') ?></span>
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

    <footer class="footer">
        <div class="container">
            <div class="footer-bottom" style="border-top:0;padding-top:0">
                <p>&copy; <?= date('Y') ?> <?= e($siteName) ?>. Todos os direitos reservados.</p>
                <p><a href="/retro">Voltar para retro</a></p>
            </div>
        </div>
    </footer>

    <script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
