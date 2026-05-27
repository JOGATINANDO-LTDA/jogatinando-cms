<?php
require_once __DIR__ . '/config.php';

$consoleSlug = trim($_GET['console'] ?? '');
$slug = trim($_GET['slug'] ?? '');

if ($consoleSlug === '' || $slug === '') {
    header('Location: /retro');
    exit;
}

$siteName = getSetting('site_name', SITE_NAME);
$console = dbQueryOne("SELECT * FROM retro_consoles WHERE slug = ? AND active = 1", [$consoleSlug]);
if (!$console) {
    header('Location: /retro');
    exit;
}

$game = dbQueryOne("SELECT * FROM retro_games WHERE console = ? AND slug = ? AND active = 1", [$consoleSlug, $slug]);
if (!$game) {
    header('Location: /retro/' . $consoleSlug, true, 302);
    exit;
}

$gameTitle = $game['title'];
$emulatorCore = $game['emulator_core'] ?: $console['emulator_core'];
$romUrl = SITE_URL . '/uploads/' . ltrim($game['rom_path'] ?? '', '/');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($gameTitle) ?> — <?= e($siteName) ?></title>
    <meta name="description" content="<?= e(truncateText($game['description'] ?? '', 160)) ?>">
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
            <a href="/retro/<?= e($consoleSlug) ?>" class="btn btn-outline btn-sm theater-back">← Voltar ao console</a>
        </div>
    </nav>

    <section class="theater-section">
        <div class="theater-container">
            <div class="theater-player" id="theaterPlayer">
                <div class="theater-loader" id="theaterLoader" style="display:flex">
                    <div class="loader-spinner"></div>
                    <span class="loader-text">Preparando EmulatorJS...</span>
                </div>
                <div class="theater-overlay" id="theaterOverlay">
                    <div class="theater-overlay-content">
                        <svg class="theater-play-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        <span class="theater-overlay-text">Clique para iniciar</span>
                    </div>
                </div>
                <div class="theater-iframe" id="retroGame"></div>
                <button class="theater-fs-btn" id="theaterFsBtn" title="Tela cheia">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <polyline points="9 21 3 21 3 15"></polyline>
                        <line x1="21" y1="3" x2="14" y2="10"></line>
                        <line x1="3" y1="21" x2="10" y2="14"></line>
                    </svg>
                </button>
            </div>
        </div>
    </section>

    <section class="game-info-section">
        <div class="container">
            <div class="game-info-grid">
                <div class="game-info-main">
                    <div class="game-info-header">
                        <div class="game-info-badges">
                            <span class="game-engine-badge" style="background:<?= e($console['icon'] ?? '🎮') ?>"><?= e($console['icon'] ?? '🎮') ?> <?= e($console['name']) ?></span>
                            <span class="game-badge-featured">Retro</span>
                        </div>
                        <h1><?= e($gameTitle) ?></h1>
                    </div>

                    <?php if (!empty($game['description'])): ?>
                    <div class="game-info-description">
                        <h3>Sobre o Jogo</h3>
                        <p><?= nl2br(e($game['description'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="game-info-tags">
                        <h3>Categorias</h3>
                        <div class="tags-list">
                            <span class="tag"><?= e($console['name']) ?></span>
                            <span class="tag"><?= e($game['type'] === 'modified' ? 'Modificado' : 'Original') ?></span>
                            <span class="tag">Emulação</span>
                        </div>
                    </div>
                </div>

                <aside class="game-info-sidebar">
                    <div class="sidebar-card">
                        <h3>Informações</h3>
                        <dl class="info-list">
                            <div class="info-item">
                                <dt>Console</dt>
                                <dd><?= e($console['name']) ?></dd>
                            </div>
                            <div class="info-item">
                                <dt>Core</dt>
                                <dd><?= e($emulatorCore) ?></dd>
                            </div>
                            <div class="info-item">
                                <dt>Tipo</dt>
                                <dd><?= e($game['type'] === 'modified' ? 'Modificado' : 'Original') ?></dd>
                            </div>
                        </dl>
                    </div>

                    <a href="/retro/<?= e($consoleSlug) ?>" class="btn btn-gold btn-block">← Voltar ao console</a>
                </aside>
            </div>
        </div>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const overlay = document.getElementById('theaterOverlay');
        const game = document.getElementById('retroGame');
        const loader = document.getElementById('theaterLoader');
        const player = document.getElementById('theaterPlayer');
        const fsBtn = document.getElementById('theaterFsBtn');
        let loaded = false;

        overlay.addEventListener('click', () => {
            if (loaded) return;
            overlay.style.opacity = '0';
            overlay.style.pointerEvents = 'none';
            loader.style.display = 'flex';

            if (!<?= !empty($game['rom_path']) ? 'true' : 'false' ?>) {
                loader.querySelector('.loader-text').textContent = 'ROM ainda não cadastrada';
                return;
            }

            window.EJS_player = '#retroGame';
            window.EJS_core = <?= json_encode($emulatorCore) ?>;
            window.EJS_pathtodata = 'https://cdn.emulatorjs.org/stable/data/';
            window.EJS_gameUrl = <?= json_encode($romUrl) ?>;
            window.EJS_gameName = <?= json_encode($gameTitle) ?>;
            window.EJS_startOnLoaded = true;
            window.EJS_fullscreenOnLoaded = false;
            window.EJS_backgroundColor = 'transparent';
            window.EJS_controlScheme = <?= json_encode(str_contains($consoleSlug, 'snes') ? 'snes' : '') ?>;
            window.EJS_Buttons = {
                settings: false,
                gamepad: false,
                cheat: false,
                volume: false,
                saveState: false,
                loadState: false,
                screenRecord: false,
                cacheManager: false,
                exitEmulation: false
            };
            window.EJS_ready = function() {
                loader.style.display = 'none';
                loaded = true;
            };

            const script = document.createElement('script');
            script.src = 'https://cdn.emulatorjs.org/stable/data/loader.js';
            script.async = true;
            document.body.appendChild(script);
        });

        fsBtn.addEventListener('click', () => {
            const el = player;
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else if (el.requestFullscreen) {
                el.requestFullscreen();
            }
        });
    });
    </script>
</body>
</html>
