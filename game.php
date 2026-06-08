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

// Toggle: tenta proxy (mesma origem) com fallback para URL direta
$useProxy = false;

if ($isExterno) {
    $externalUrl = $game['external_url'];
    $fallbackUrl = $externalUrl;
    if ($useProxy) {
        $gameUrl = '/proxy/game/' . $slug . '/';
        $frameOrigin = "'self'";
    } else {
        $gameUrl = $externalUrl;
        $parts = parse_url($gameUrl);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $frameOrigin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $port;
    }
} elseif ($isWebPlayable && $game['game_path']) {
    $gameDir = UPLOAD_PATH . '/games/' . $game['game_path'];
    $gameUrl = UPLOAD_URL . '/games/' . $game['game_path'] . '/';
    $fallbackUrl = $gameUrl;
    $frameOrigin = "'self'";
    $pathParts = explode('/', $game['game_path'], 2);
    $engineSlug = $pathParts[0];
    $gameSlug = $pathParts[1] ?? '';
    if (!file_exists($gameDir . '/index.html')) {
        if (Storage::isS3Configured()) {
            $zipS3Name = 'zips/' . $engineSlug . '/' . $gameSlug . '.zip';
            $restored = Storage::extractFromS3Zip($zipS3Name, 'games/' . $game['game_path']);

            if (!$restored || !file_exists($gameDir . '/index.html')) {
                http_response_code(404);
                require __DIR__ . '/404.php';
                exit;
            }
        } else {
            http_response_code(404);
            require __DIR__ . '/404.php';
            exit;
        }
    }
} else {
    $gameUrl = '';
    $fallbackUrl = '';
    $frameOrigin = "'self'";
}

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' 'wasm-unsafe-eval' blob: https://cdn.emulatorjs.org; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com http://fonts.googleapis.com https://cdn.emulatorjs.org; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob: https:; connect-src 'self' blob: data: https:; worker-src 'self' blob:; frame-src 'self' $frameOrigin; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

$iframeWidth = $game['iframe_width'] ?? '100%';
$iframeHeight = $game['iframe_height'] ?? '100%';
$orientation = 'auto';
if ($isExterno) {
    $w = intval($iframeWidth);
    $h = intval($iframeHeight);
    if ($w > 0 && $h > 0) {
        $orientation = ($h > $w) ? 'portrait' : 'landscape';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($game['title']) ?> — <?= e(getSetting('site_name', 'CMS de Jogos')) ?></title>
    <meta name="description" content="<?= e(truncateText($game['description'], 160)) ?>">
    <link rel="icon" href="<?= siteFaviconUrl() ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= assetUrl('/assets/css/style.css') ?>">
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
                    <img src="<?= siteLogoUrl() ?>" alt="Logo">
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
            <div class="theater-player<?= $isExterno ? ' theater-player-externo' : '' ?>" id="theaterPlayer"<?= $isExterno ? ' style="width:' . e($iframeWidth) . ';height:' . e($iframeHeight) . '"' : '' ?>>
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
                <iframe class="theater-iframe" id="theaterIframe" src="about:blank" data-src="<?= e($gameUrl) ?>" allow="autoplay; fullscreen; gamepad"></iframe>
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
                                    <img class="store-link-logo" src="<?= logoImgSrc($link['logo_path']) ?>" alt="<?= e($link['platform_name']) ?>">
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


                    <?php if (!$isWebPlayable && $gameLinks): ?>
                    <div class="sidebar-card">
                        <h3>Distribuição</h3>
                        <div class="sidebar-links-list">
                            <?php foreach ($gameLinks as $link): ?>
                            <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener" class="sidebar-store-link">
                                <?php if (!empty($link['use_logo']) && !empty($link['logo_path'])): ?>
                                    <img class="store-link-logo" src="<?= logoImgSrc($link['logo_path']) ?>" alt="<?= e($link['platform_name']) ?>">
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
            const fallbackUrl = '<?= e($fallbackUrl) ?>';
            const useProxy = <?= ($useProxy && $isExterno) ? 'true' : 'false' ?>;
            const orientation = '<?= $orientation ?>';

            let fallbackTimer = null;

            iframe.addEventListener('load', () => {
                loader.style.display = 'none';
                if (fallbackTimer) {
                    clearTimeout(fallbackTimer);
                    fallbackTimer = null;
                }
                iframe.style.borderColor = '';
                if (!isExterno) {
                    try {
                        const doc = iframe.contentDocument || iframe.contentWindow.document;
                        if (doc) {
                            const html = doc.documentElement;
                            html.style.overflow = 'hidden';
                            html.style.width = '100%';
                            html.style.height = '100%';
                            const body = doc.body;
                            body.style.overflow = 'hidden';
                            body.style.width = '100%';
                            body.style.height = '100%';
                            body.style.margin = '0';
                            body.style.padding = '0';
                        }
                    } catch(e) {}
                }
            });

            iframe.addEventListener('error', () => {
                if (useProxy && fallbackUrl) {
                    loader.textContent = 'Proxy indisponível, redirecionando...';
                    setTimeout(() => {
                        iframe.src = fallbackUrl;
                    }, 1500);
                }
            });

            overlay.addEventListener('click', () => {
                overlay.style.opacity = '0';
                overlay.style.pointerEvents = 'none';
                loader.style.display = 'flex';
                iframe.src = gameUrl;
                if (useProxy && fallbackUrl) {
                    fallbackTimer = setTimeout(() => {
                        if (loader.style.display !== 'none') {
                            iframe.style.borderColor = '#e44';
                            loader.textContent = 'Proxy sem resposta, tentando origem direta...';
                            setTimeout(() => {
                                iframe.src = fallbackUrl;
                            }, 1000);
                        }
                    }, 8000);
                }
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
                let isDragging = false;
                let dragStartX, dragStartY, dragOrigX, dragOrigY;

                fsBtn.addEventListener('mousedown', (e) => {
                    if (e.button !== 0) return;
                    const rect = fsBtn.getBoundingClientRect();
                    const parentRect = player.getBoundingClientRect();
                    dragStartX = e.clientX;
                    dragStartY = e.clientY;
                    dragOrigX = rect.left - parentRect.left;
                    dragOrigY = rect.top - parentRect.top;
                    isDragging = false;
                    fsBtn.classList.add('dragging');

                    const onMouseMove = (ev) => {
                        const dx = ev.clientX - dragStartX;
                        const dy = ev.clientY - dragStartY;
                        if (!isDragging && (Math.abs(dx) > 4 || Math.abs(dy) > 4)) {
                            isDragging = true;
                        }
                        if (!isDragging) return;
                        ev.preventDefault();

                        const pRect = player.getBoundingClientRect();
                        const bRect = fsBtn.getBoundingClientRect();
                        let newLeft = dragOrigX + dx;
                        let newTop = dragOrigY + dy;
                        newLeft = Math.max(0, Math.min(pRect.width - bRect.width, newLeft));
                        newTop = Math.max(0, Math.min(pRect.height - bRect.height, newTop));
                        fsBtn.style.left = newLeft + 'px';
                        fsBtn.style.right = 'auto';
                        fsBtn.style.top = newTop + 'px';
                    };

                    const onMouseUp = () => {
                        document.removeEventListener('mousemove', onMouseMove);
                        document.removeEventListener('mouseup', onMouseUp);
                        fsBtn.classList.remove('dragging');
                    };

                    document.addEventListener('mousemove', onMouseMove);
                    document.addEventListener('mouseup', onMouseUp);
                });

                fsBtn.addEventListener('click', (e) => {
                    if (isDragging) {
                        e.stopPropagation();
                        isDragging = false;
                        return;
                    }
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
