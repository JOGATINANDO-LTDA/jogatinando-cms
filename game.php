<?php
require_once 'config.php';

$engine = $_GET['engine'] ?? '';
$slug = $_GET['slug'] ?? '';

if (!$engine || !$slug) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

try {
    $game = dbQueryOne("SELECT g.*, e.icon as engine_icon, e.color as engine_color FROM games g LEFT JOIN engines e ON g.engine = e.name WHERE (LOWER(g.engine) = LOWER(?) OR LOWER(e.slug) = LOWER(?)) AND g.slug = ? AND g.active = 1", [$engine, $engine, $slug]);
} catch (Exception $ex) {
    error_log('Game query failed: ' . $ex->getMessage());
    http_response_code(500);
    require __DIR__ . '/404.php';
    exit;
}

if (!$game) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$isWebPlayable = !empty($game['is_web_playable']);
$isExterno = ($game['game_type'] ?? '') === 'externo' && !empty($game['external_url']);
$isUploadado = !$isExterno && !empty($game['game_path']);
$gameLinks = dbQuery("SELECT gl.*, p.name as platform_name, p.icon as platform_icon, p.use_logo, p.logo_path FROM game_links gl INNER JOIN store_platforms p ON p.id = gl.platform_id WHERE gl.game_id = ? AND p.active = 1 ORDER BY gl.sort_order ASC, p.sort_order ASC, p.name ASC", [$game['id']]);

if ($isExterno) {
    $gameUrl = $game['external_url'];
} elseif ($isWebPlayable && $game['game_path']) {
    $gameDir = UPLOAD_PATH . '/games/' . $game['game_path'];
    $gameUrl = UPLOAD_URL . '/games/' . $game['game_path'] . '/';
    if (!file_exists($gameDir . '/index.html')) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        exit;
    }
} else {
    $gameUrl = '';
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
    <link rel="icon" href="/assets/svg/logo.svg" type="image/svg+xml">
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

    <?php if ($isWebPlayable && ($isExterno || $game['game_path'])): ?>
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
            <?php if ($isExterno): ?>
            <div class="theater-fallback">
                <p>Se o jogo não carregou acima, acesse diretamente pelo botão abaixo:</p>
                <a href="<?= e($game['external_url']) ?>" target="_blank" rel="noopener" class="btn btn-gold">🌐 Acessar Site Oficial →</a>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Game Info -->
    <section class="game-info-section">
        <div class="container">
            <div class="game-info-grid">
                <!-- Main Info -->
                <div class="game-info-main">
                    <div class="game-info-header">
                        <div class="game-info-badges">
                            <span class="game-engine-badge" style="background:<?= e($game['engine_color'] ?? getEngineColor($game['engine'])) ?>"><?= e($game['engine_icon'] ?? getEngineIcon($game['engine'])) ?> <?= e($game['engine']) ?></span>
                            <?php if ($game['featured']): ?><span class="game-badge-featured">Destaque</span><?php endif; ?>
                            <?php if ($isExterno): ?><span class="game-badge-featured" style="background:oklch(65% 0.15 250);color:white">🌐 Site Externo</span><?php endif; ?>
                            <?php if (!$isExterno && !$isWebPlayable): ?><span class="game-badge-featured" style="background:var(--accent);color:white">Loja</span><?php endif; ?>
                            <?php if (!empty($game['is_open_source'])): ?><span class="game-badge-featured" style="background:oklch(65% 0.18 145);color:white">🔧 Open Source</span><?php endif; ?>
                        </div>
                        <h1><?= e($game['title']) ?></h1>
                    </div>

                    <?php if ($gameLinks): ?>
                    <div class="game-info-description">
                        <h3><?= $isExterno ? 'Links de Download' : 'Onde comprar' ?></h3>
                        <div class="store-links-list">
                            <?php foreach ($gameLinks as $link): ?>
                            <a class="store-link-item" href="<?= e($link['url']) ?>" target="_blank" rel="noopener">
                                <?php if (!empty($link['use_logo']) && !empty($link['logo_path'])): ?>
                                    <img class="store-link-logo" src="/<?= e($link['logo_path']) ?>" alt="<?= e($link['platform_name']) ?>">
                                <?php else: ?>
                                    <span class="store-link-icon"><?= e($link['platform_icon'] ?? '🛒') ?></span>
                                <?php endif; ?>
                                <span class="store-link-name"><?= e($link['platform_name']) ?></span>
                                <span class="store-link-game"><?= e($game['title']) ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($game['description']): ?>
                    <div class="game-info-description">
                        <h3>Sobre o Jogo</h3>
                        <p><?= nl2br(e($game['description'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if ($isWebPlayable): ?>
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
                    <?php endif; ?>

                    <div class="game-info-tags">
                        <h3>Categorias</h3>
                        <div class="tags-list">
                            <span class="tag"><?= e($game['engine']) ?></span>
                            <?php if ($isExterno): ?>
                                <span class="tag">Site Externo</span>
                            <?php elseif ($isUploadado): ?>
                                <span class="tag">Navegador</span>
                                <span class="tag">HTML5</span>
                            <?php else: ?>
                                <span class="tag"><?= ($game['game_type'] ?? 'autoral') === 'cliente' ? 'Cliente' : 'Autoral' ?></span>
                                <span class="tag">Distribuição</span>
                            <?php endif; ?>
                            <?php if (!empty($game['is_open_source'])): ?><span class="tag">Open Source</span><?php endif; ?>
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
                                <dd><?= $isExterno ? 'Site Externo (iframe)' : ($isUploadado ? 'Navegador (HTML5)' : 'Distribuição / Loja') ?></dd>
                            </div>
                            <div class="info-item">
                                <dt>Orientação</dt>
                                <dd><?= $orientation === 'landscape' ? 'Paisagem' : ($orientation === 'portrait' ? 'Retrato' : 'Automático') ?></dd>
                            </div>
                            <div class="info-item">
                                <dt>Tipo</dt>
                                <dd><?= $isExterno ? 'Externo' : (($game['game_type'] ?? 'autoral') === 'cliente' ? 'Cliente' : 'Autoral') ?></dd>
                            </div>
                            <?php if (!empty($game['is_open_source']) && !empty($game['repo_url'])): ?>
                            <div class="info-item">
                                <dt>Repositório</dt>
                                <dd><a href="<?= e($game['repo_url']) ?>" target="_blank" rel="noopener" style="color:var(--gold)">Ver código →</a></dd>
                            </div>
                            <?php endif; ?>
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

                    <?php if (!$isWebPlayable && $gameLinks): ?>
                    <div class="sidebar-card">
                        <h3>Distribuição</h3>
                        <div class="sidebar-links-list">
                            <?php foreach ($gameLinks as $link): ?>
                            <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener" class="sidebar-store-link">
                                <?php if (!empty($link['use_logo']) && !empty($link['logo_path'])): ?>
                                    <img class="store-link-logo" src="/<?= e($link['logo_path']) ?>" alt="<?= e($link['platform_name']) ?>">
                                <?php else: ?>
                                    <span><?= e($link['platform_icon'] ?? '🛒') ?></span>
                                <?php endif; ?>
                                <span><?= e($link['platform_name']) ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
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
            const isWebPlayable = <?= $isWebPlayable ? 'true' : 'false' ?>;
            const isExterno = <?= $isExterno ? 'true' : 'false' ?>;
            if (!isWebPlayable) return;

            const overlay = document.getElementById('theaterOverlay');
            const iframe = document.getElementById('theaterIframe');
            const loader = document.getElementById('theaterLoader');
            const player = document.getElementById('theaterPlayer');
            const fsBtn = document.getElementById('theaterFsBtn');
            const gameUrl = iframe.dataset.src;
            const orientation = '<?= $orientation ?>';

            iframe.addEventListener('load', () => {
                loader.style.display = 'none';
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

            if (fsBtn) {
                fsBtn.addEventListener('click', () => {
                    if (document.fullscreenElement) exitFullscreen();
                    else enterFullscreen();
                });
            }

            document.addEventListener('fullscreenchange', () => {
                if (document.fullscreenElement) {
                    if (fsBtn) {
                        fsBtn.classList.add('fs-active');
                        fsBtn.querySelector('svg').innerHTML = '<polyline points="4 14 10 14 10 20"></polyline><polyline points="20 10 14 10 14 4"></polyline><line x1="14" y1="10" x2="21" y2="3"></line><line x1="3" y1="21" x2="10" y2="14"></line>';
                    }
                } else {
                    if (fsBtn) {
                        fsBtn.classList.remove('fs-active');
                        fsBtn.querySelector('svg').innerHTML = '<polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line>';
                    }
                }
            });
        });
    </script>
</body>
</html>
