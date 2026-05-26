<?php
require_once 'config.php';

$engine = $_GET['engine'] ?? '';
$slug = $_GET['slug'] ?? '';

if (!$engine || !$slug) {
    header('Location: /');
    exit;
}

try {
    $game = dbQueryOne("SELECT * FROM games WHERE LOWER(engine) = LOWER(?) AND slug = ? AND active = 1 AND engine IN (SELECT name FROM engines WHERE active = 1)", [$engine, $slug]);
} catch (Exception $ex) {
    die('DB Error: ' . $ex->getMessage());
}

if (!$game || !$game['game_path']) {
    header('Location: /');
    exit;
}

$gameDir = UPLOAD_PATH . '/games/' . $game['game_path'];
$gameUrl = UPLOAD_URL . '/games/' . $game['game_path'] . '/';

if (!file_exists($gameDir . '/index.html')) {
    http_response_code(404);
    die('<h1 style="color:white;text-align:center;margin-top:40vh;font-family:sans-serif">Arquivo do jogo não encontrado.</h1>');
}

$orientation = $game['orientation'] ?? 'auto';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($game['title']) ?> — <?= e(getSetting('site_name', 'CMS de Jogos')) ?></title>
    <meta name="description" content="<?= e(truncateText($game['description'], 160)) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <?php if ($orientation === 'landscape'): ?>
    <meta name="screen-orientation" content="landscape">
    <?php elseif ($orientation === 'portrait'): ?>
    <meta name="screen-orientation" content="portrait">
    <?php endif; ?>
</head>
<body>
    <div class="cosmic-bg"></div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="/" class="navbar-brand">
                <div class="logo-shield">
                    <img src="/assets/svg/logo.svg" alt="Logo">
                </div>
                <?= e(getSetting('site_name', 'CMS de Jogos')) ?>
            </a>
            <a href="/" class="btn btn-outline btn-sm theater-back">← Voltar ao site</a>
        </div>
    </nav>

    <!-- Theater Mode Player -->
    <section class="theater-section">
        <div class="theater-container">
            <div class="theater-player" id="theaterPlayer">
                <div class="theater-loader" id="theaterLoader">
                    <div class="loader-spinner"></div>
                    <span class="loader-text">Carregando jogo...</span>
                </div>
                <div class="theater-overlay" id="theaterOverlay">
                    <div class="theater-overlay-content">
                        <svg class="theater-play-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        <span class="theater-overlay-text">Clique para jogar</span>
                    </div>
                </div>
                <iframe class="theater-iframe" id="theaterIframe" src="about:blank" data-src="<?= e($gameUrl) ?>" allowfullscreen allow="autoplay; fullscreen; gamepad"></iframe>
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

    <!-- Game Info -->
    <section class="game-info-section">
        <div class="container">
            <div class="game-info-grid">
                <!-- Main Info -->
                <div class="game-info-main">
                    <div class="game-info-header">
                        <div class="game-info-badges">
                            <span class="game-engine-badge" style="background:<?= getEngineColor($game['engine']) ?>"><?= getEngineIcon($game['engine']) ?> <?= e($game['engine']) ?></span>
                            <?php if ($game['featured']): ?><span class="game-badge-featured">Destaque</span><?php endif; ?>
                        </div>
                        <h1><?= e($game['title']) ?></h1>
                    </div>

                    <?php if ($game['description']): ?>
                    <div class="game-info-description">
                        <h3>Sobre o Jogo</h3>
                        <p><?= nl2br(e($game['description'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Controls -->
                    <div class="game-info-controls">
                        <h3>Controles</h3>
                        <div class="controls-grid">
                            <div class="control-item">
                                <kbd>Mouse</kbd>
                                <span>Interação principal</span>
                            </div>
                            <div class="control-item">
                                <kbd>F11</kbd>
                                <span>Tela cheia</span>
                            </div>
                            <div class="control-item">
                                <kbd>Esc</kbd>
                                <span>Sair do jogo</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div class="game-info-tags">
                        <h3>Categorias</h3>
                        <div class="tags-list">
                            <span class="tag"><?= e($game['engine']) ?></span>
                            <span class="tag">Navegador</span>
                            <span class="tag">HTML5</span>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <aside class="game-info-sidebar">
                    <div class="sidebar-card">
                        <h3>Informações</h3>
                        <dl class="info-list">
                            <div class="info-item">
                                <dt>Engine</dt>
                                <dd><?= e($game['engine']) ?></dd>
                            </div>
                            <div class="info-item">
                                <dt>Plataforma</dt>
                                <dd>Navegador (HTML5)</dd>
                            </div>
                            <div class="info-item">
                                <dt>Orientação</dt>
                                <dd><?= $orientation === 'landscape' ? 'Paisagem' : ($orientation === 'portrait' ? 'Retrato' : 'Automático') ?></dd>
                            </div>
                            <div class="info-item">
                                <dt>Adicionado</dt>
                                <dd><?= date('d/m/Y', strtotime($game['created_at'])) ?></dd>
                            </div>
                        </dl>
                    </div>

                    <?php if ($game['thumbnail_url']): ?>
                    <div class="sidebar-card">
                        <h3>Thumbnail</h3>
                        <img src="<?= e($game['thumbnail_url']) ?>" alt="<?= e($game['title']) ?>" class="sidebar-thumb">
                    </div>
                    <?php endif; ?>

                    <a href="/" class="btn btn-gold btn-block">← Voltar ao Portfólio</a>
                </aside>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <?= e(getSetting('site_name', 'CMS de Jogos')) ?>. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const overlay = document.getElementById('theaterOverlay');
            const iframe = document.getElementById('theaterIframe');
            const loader = document.getElementById('theaterLoader');
            const player = document.getElementById('theaterPlayer');
            const fsBtn = document.getElementById('theaterFsBtn');
            const gameUrl = iframe.dataset.src;
            const orientation = '<?= $orientation ?>';
            let gameLoaded = false;

            iframe.addEventListener('load', () => {
                loader.style.display = 'none';
                gameLoaded = true;
            });

            overlay.addEventListener('click', () => {
                overlay.style.opacity = '0';
                overlay.style.pointerEvents = 'none';
                loader.style.display = 'flex';
                iframe.src = gameUrl;
            });

            function lockOrientation(type) {
                if (screen.orientation && screen.orientation.lock) {
                    screen.orientation.lock(type).catch(() => {});
                }
            }

            function unlockOrientation() {
                if (screen.orientation && screen.orientation.unlock) {
                    screen.orientation.unlock();
                }
            }

            function enterFullscreen() {
                const el = player;
                if (el.requestFullscreen) el.requestFullscreen();
                else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
                else if (el.msRequestFullscreen) el.msRequestFullscreen();

                if (orientation === 'landscape') lockOrientation('landscape');
                else if (orientation === 'portrait') lockOrientation('portrait');
            }

            function exitFullscreen() {
                if (document.exitFullscreen) document.exitFullscreen();
                else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
                else if (document.msExitFullscreen) document.msExitFullscreen();
                unlockOrientation();
            }

            fsBtn.addEventListener('click', () => {
                if (document.fullscreenElement) exitFullscreen();
                else enterFullscreen();
            });

            document.addEventListener('fullscreenchange', () => {
                if (document.fullscreenElement) {
                    fsBtn.classList.add('fs-active');
                    fsBtn.querySelector('svg').innerHTML = '<polyline points="4 14 10 14 10 20"></polyline><polyline points="20 10 14 10 14 4"></polyline><line x1="14" y1="10" x2="21" y2="3"></line><line x1="3" y1="21" x2="10" y2="14"></line>';
                } else {
                    fsBtn.classList.remove('fs-active');
                    fsBtn.querySelector('svg').innerHTML = '<polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line>';
                }
            });
        });
    </script>
</body>
</html>
