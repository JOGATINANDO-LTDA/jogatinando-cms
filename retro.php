<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Emulação Retro';
$siteName = getSetting('site_name', SITE_NAME);
$siteTagline = getSetting('site_tagline', SITE_TAGLINE);
$footerDescription = getSetting('footer_description', '');

$consoles = dbQuery("SELECT * FROM retro_consoles WHERE active = 1 ORDER BY sort_order ASC, name ASC");
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
                <li><a href="/templates">Templates</a></li>
                <li><a href="/retro">Retro</a></li>
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
        <a href="/retro" class="mobile-link">Retro</a>
    </div>

    <main class="section section-dark catalog-page">
        <div class="container">
            <div class="section-title catalog-title">
                <h2>Emulação <span class="gold">Retro</span></h2>
                <p>Jogue títulos clássicos direto no navegador.</p>
            </div>

            <div class="catalog-grid">
                <?php if (empty($consoles)): ?>
                    <div class="catalog-empty">
                        <div class="empty-icon">🕹️</div>
                        <h3>Nenhum emulador cadastrado</h3>
                        <p>Cadastre o primeiro console no painel de controle.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($consoles as $console): ?>
                        <a href="/retro/<?= e($console['slug']) ?>" class="catalog-card">
                            <div class="catalog-thumb">
                                <?php if (!empty($console['thumbnail_url'])): ?>
                                    <img src="<?= e($console['thumbnail_url']) ?>" alt="<?= e($console['name']) ?>">
                                <?php else: ?>
                                    <div class="catalog-thumb-placeholder"><?= e($console['icon'] ?? '🎮') ?></div>
                                <?php endif; ?>
                                <div class="catalog-badges">
                                    <span class="catalog-badge catalog-badge-web"><?= e($console['name']) ?></span>
                                </div>
                            </div>
                            <div class="catalog-content">
                                <h3><?= e($console['name']) ?></h3>
                                <p>Jogue títulos originais e modificados neste console.</p>
                                <div class="catalog-card-footer">
                                    <span>Abrir console</span>
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
