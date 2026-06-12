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
$hasRom = !empty($game['rom_path']);
$gameThumb = !empty($game['thumbnail_url']) ? mediaUrl($game['thumbnail_url']) : '';
$consoleThumb = !empty($console['thumbnail_url']) ? mediaUrl($console['thumbnail_url']) : '';

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' 'wasm-unsafe-eval' blob: https://cdn.emulatorjs.org; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com http://fonts.googleapis.com https://cdn.emulatorjs.org; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob: https:; connect-src 'self' blob: data: https:; worker-src 'self' blob:; frame-src 'self' blob: https://cdn.emulatorjs.org; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($gameTitle) ?> — <?= e($siteName) ?></title>
    <meta name="description" content="<?= e(truncateText($game['description'] ?? '', 160)) ?>">
    <link rel="icon" href="<?= siteFaviconUrl() ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= assetUrl('/assets/css/style.css') ?>">
    <style>
        .retro-splash {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #000;
            z-index: 10;
            transition: opacity 0.4s ease;
        }
        .retro-splash.hidden { opacity: 0; pointer-events: none; }
        .retro-splash-thumb {
            width: 180px;
            height: 180px;
            border-radius: 12px;
            object-fit: cover;
            border: 3px solid var(--gold);
            box-shadow: 0 0 30px oklch(75% 0.15 85 / 0.3);
            margin-bottom: 20px;
        }
        .retro-splash-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .retro-splash-title {
            font-family: 'Cinzel', serif;
            font-size: 22px;
            color: var(--fg);
            margin-bottom: 6px;
        }
        .retro-splash-sub {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 20px;
        }
        .retro-splash-status {
            font-size: 12px;
            color: var(--gold);
            margin-top: 8px;
        }
        .retro-play-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 36px;
            background: var(--gold);
            color: #000;
            border: none;
            border-radius: var(--radius-lg);
            font-family: 'Cinzel', serif;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .retro-play-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px oklch(75% 0.15 85 / 0.4);
        }
        .retro-play-btn:disabled {
            opacity: 0.5;
            cursor: wait;
        }
        .ejs_start_button { display: none !important; }
        .ejs_context_menu { display: none !important; }
        .ejs_loading_text { display: none !important; }
        .ejs_error_text { display: none !important; }
    </style>
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
            <a href="/retro/<?= e($consoleSlug) ?>" class="btn btn-outline btn-sm theater-back">← Voltar ao console</a>
        </div>
    </nav>

    <section class="theater-section">
        <div class="theater-container">
            <div class="theater-player" id="theaterPlayer">
                <!-- Splash / Thumbnail -->
                <div class="retro-splash" id="retroSplash">
                    <?php if (!empty($gameThumb)): ?>
                        <img src="<?= e($gameThumb) ?>" alt="<?= e($gameTitle) ?>" class="retro-splash-thumb">
                    <?php elseif (!empty($consoleThumb)): ?>
                        <img src="<?= e($consoleThumb) ?>" alt="<?= e($console['name']) ?>" class="retro-splash-thumb">
                    <?php else: ?>
                        <div class="retro-splash-icon"><?= e($console['icon'] ?? '🎮') ?></div>
                    <?php endif; ?>
                    <div class="retro-splash-title"><?= e($gameTitle) ?></div>
                    <div class="retro-splash-sub"><?= e($console['name']) ?> · <?= e($game['type'] === 'modified' ? ($game['modification_description'] ?: 'Modificado') : 'Original') ?></div>
                    <button class="retro-play-btn" id="retroPlayBtn" disabled <?= !$hasRom ? 'title="ROM não disponível"' : '' ?>>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        <?= $hasRom ? 'Iniciar Jogo' : 'ROM não disponível' ?>
                    </button>
                    <div class="retro-splash-status" id="splashStatus"></div>
                </div>

                <!-- Player (preloaded hidden) -->
                <div id="retroGame" style="width:100%;height:100%;display:none"></div>

                <!-- Fullscreen button (shown after game starts) -->
                <button class="theater-fs-btn" id="theaterFsBtn" title="Tela cheia" style="display:none">
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
                            <span class="tag"><?= e($game['type'] === 'modified' ? ($game['modification_description'] ?: 'Modificado') : 'Original') ?></span>
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
                                <dt>Tipo</dt>
                                <dd><?= e($game['type'] === 'modified' ? ($game['modification_description'] ?: 'Modificado') : 'Original') ?></dd>
                            </div>
                        </dl>
                    </div>

                    <a href="/retro/<?= e($consoleSlug) ?>" class="btn btn-gold btn-block">← Voltar ao console</a>
                </aside>
            </div>
        </div>
    </section>

    <?php if ($hasRom): ?>
    <!-- EmulatorJS: preload do core quando a página carrega -->
    <script>
        window.EJS_player = '#retroGame';
        window.EJS_core = <?= json_encode($emulatorCore) ?>;
        window.EJS_pathtodata = 'https://cdn.emulatorjs.org/stable/data/';
        window.EJS_gameUrl = <?= json_encode($romUrl) ?>;
        window.EJS_gameName = <?= json_encode($gameTitle) ?>;
        window.EJS_startOnLoaded = false;
        window.EJS_fullscreenOnLoaded = false;
        window.EJS_backgroundColor = '#000';
        window.EJS_DEBUG_XX = true;
        window.EJS_controlScheme = <?= json_encode(str_contains($consoleSlug, 'snes') ? 'snes' : (str_contains($consoleSlug, 'nes') ? 'nes' : (str_contains($consoleSlug, 'gb') ? 'gb' : (str_contains($consoleSlug, 'gba') ? 'gba' : (str_contains($consoleSlug, 'n64') ? 'n64' : ''))))) ?>;
        window.EJS_Buttons = {
            playPause: false,
            restart: true,
            mute: true,
            settings: false,
            fullscreen: false,
            saveState: true,
            loadState: true,
            screenRecord: false,
            gamepad: true,
            cheat: true,
            volume: true,
            saveSavFiles: true,
            loadSavFiles: true,
            quickSave: true,
            quickLoad: true,
            screenshot: false,
            cacheManager: false,
            exitEmulation: false
        };
        window.EJS_ready = function() {
            document.getElementById('splashStatus').textContent = 'Pronto para jogar!';
            document.getElementById('retroPlayBtn').disabled = false;
        };
    </script>
    <script src="https://cdn.emulatorjs.org/stable/data/loader.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const splash = document.getElementById('retroSplash');
        const playBtn = document.getElementById('retroPlayBtn');
        const gameDiv = document.getElementById('retroGame');
        const player = document.getElementById('theaterPlayer');
        const fsBtn = document.getElementById('theaterFsBtn');
        const status = document.getElementById('splashStatus');

        status.textContent = 'Carregando emulador...';

        playBtn.addEventListener('click', () => {
            playBtn.disabled = true;
            playBtn.innerHTML = '<div class="loader-spinner" style="width:18px;height:18px;border-width:2px"></div> Iniciando...';
            status.textContent = 'Preparando jogo...';

            splash.classList.add('hidden');
            gameDiv.style.display = 'block';
            fsBtn.style.display = 'flex';

            setTimeout(() => {
                splash.remove();
                // Clicar no botão do EmulatorJS programaticamente
                const ejsBtn = document.querySelector('.ejs_start_button');
                if (ejsBtn) ejsBtn.click();
            }, 300);
        });

        fsBtn.addEventListener('click', () => {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else if (player.requestFullscreen) {
                player.requestFullscreen();
            }
        });
    });
    </script>
    <?php else: ?>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('splashStatus').textContent = 'ROM ainda não disponível para este jogo';
    });
    </script>
    <?php endif; ?>
    <?php require_once __DIR__ . '/includes/footer-front.php'; ?>
</body>
</html>
