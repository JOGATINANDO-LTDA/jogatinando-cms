<?php
require_once 'config.php';

$engine = $_GET['engine'] ?? '';
$slug = $_GET['slug'] ?? '';

if (!$engine || !$slug) {
    header('Location: /templates');
    exit;
}

try {
    $template = dbQueryOne("SELECT t.*, e.icon as engine_icon, e.color as engine_color FROM game_templates t LEFT JOIN engines e ON t.engine = e.name WHERE (LOWER(t.engine) = LOWER(?) OR LOWER(e.slug) = LOWER(?)) AND t.slug = ? AND t.active = 1", [$engine, $engine, $slug]);
} catch (Exception $ex) {
    die('DB Error: ' . $ex->getMessage());
}

if (!$template) {
    header('Location: /templates');
    exit;
}

$templateLinks = dbQuery("SELECT tl.*, p.name as platform_name, p.icon as platform_icon, p.use_logo, p.logo_path FROM template_links tl JOIN store_platforms p ON tl.platform_id = p.id WHERE tl.template_id = ? AND p.active = 1 ORDER BY tl.sort_order ASC, p.sort_order ASC, p.name ASC", [$template['id']]);
$siteName = getSetting('site_name', SITE_NAME);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($template['title']) ?> — Templates — <?= e($siteName) ?></title>
    <meta name="description" content="<?= e(truncateText($template['description'], 160)) ?>">
    <link rel="icon" href="<?= siteFaviconUrl() ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
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
                <?= e($siteName) ?>
            </a>
            <a href="/templates" class="btn btn-outline btn-sm theater-back">← Voltar aos Templates</a>
        </div>
    </nav>

    <!-- Template Preview -->
    <?php if (!empty($template['thumbnail_url'])): ?>
    <section class="theater-section">
        <div class="theater-container">
            <div class="theater-player" style="aspect-ratio:16/9;background:#0a0a12;display:flex;align-items:center;justify-content:center;border:2px solid var(--border-gold);border-radius:var(--radius-lg);overflow:hidden">
                <img src="<?= e($template['thumbnail_url']) ?>" alt="<?= e($template['title']) ?>" style="max-width:100%;max-height:100%;object-fit:contain">
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Template Info -->
    <section class="game-info-section">
        <div class="container">
            <div class="game-info-grid">
                <div class="game-info-main">
                    <div class="game-info-header">
                        <div class="game-info-badges">
                            <span class="game-engine-badge" style="background:<?= e($template['engine_color'] ?? getEngineColor($template['engine'])) ?>"><?= e($template['engine_icon'] ?? getEngineIcon($template['engine'])) ?> <?= e($template['engine']) ?></span>
                            <?php if ($template['has_free_file']): ?><span class="game-badge-featured" style="background:oklch(55% 0.18 145);color:white">⬇ Download Grátis</span><?php endif; ?>
                            <?php if ($template['featured']): ?><span class="game-badge-featured">Destaque</span><?php endif; ?>
                            <?php if (!empty($template['language'])): ?><span class="game-badge-featured" style="background:oklch(55% 0.18 200);color:white"><?= e($template['language']) ?></span><?php endif; ?>
                        </div>
                        <h1><?= e($template['title']) ?></h1>
                    </div>

                    <?php if (!empty($templateLinks)): ?>
                    <div class="game-info-description">
                        <h3>Links de Distribuição</h3>
                        <div class="store-links-list">
                            <?php foreach ($templateLinks as $tl): ?>
                            <a class="store-link-item" href="<?= e($tl['url']) ?>" target="_blank" rel="noopener">
                                <?php if (!empty($tl['use_logo']) && !empty($tl['logo_path'])): ?>
                                <img src="<?= logoImgSrc($tl['logo_path']) ?>" alt="<?= e($tl['platform_name']) ?>" class="store-link-logo">
                                <?php else: ?>
                                <span class="store-link-icon"><?= e($tl['platform_icon'] ?? '🛒') ?></span>
                                <?php endif; ?>
                                <span class="store-link-name"><?= e($tl['platform_name']) ?></span>
                                <span class="store-link-game">— Adquirir Template</span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($template['description']): ?>
                    <div class="game-info-description">
                        <h3>Sobre o Template</h3>
                        <p><?= nl2br(e($template['description'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php
                    $gallery = json_decode($template['gallery'] ?? '[]', true) ?: [];
                    if (!empty($gallery)):
                    ?>
                    <div class="game-info-description">
                        <h3>Galeria</h3>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-top:8px">
                            <?php foreach ($gallery as $img): ?>
                            <a href="<?= e($img) ?>" target="_blank" style="display:block;border-radius:8px;overflow:hidden;border:2px solid var(--border);transition:border-color .2s">
                                <img src="<?= e($img) ?>" alt="Gallery" style="width:100%;height:130px;object-fit:cover;display:block">
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($template['features'])): ?>
                    <div class="game-info-description">
                        <h3>Recursos</h3>
                        <ul style="list-style:none;padding:0">
                            <?php foreach (explode("\n", $template['features']) as $feat): ?>
                                <?php if (trim($feat)): ?>
                                <li style="padding:6px 0;border-bottom:1px solid var(--border);color:var(--fg)">✓ <?= e(trim($feat)) ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div class="game-info-tags">
                        <h3>Categorias</h3>
                        <div class="tags-list">
                            <span class="tag"><?= e($template['engine']) ?></span>
                            <?php if (!empty($template['language'])): ?>
                                <span class="tag"><?= e($template['language']) ?></span>
                            <?php endif; ?>
                            <span class="tag">Template</span>
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
                                <dd><?= e($template['engine']) ?></dd>
                            </div>
                            <?php if (!empty($template['language'])): ?>
                            <div class="info-item">
                                <dt>Linguagem</dt>
                                <dd><?= e($template['language']) ?> <?= e($template['language_version']) ?></dd>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($template['requirements'])): ?>
                            <div class="info-item">
                                <dt>Requisitos</dt>
                                <dd><?= e($template['requirements']) ?></dd>
                            </div>
                            <?php endif; ?>
                            <div class="info-item">
                                <dt>Adicionado</dt>
                                <dd><?= date('d/m/Y', strtotime($template['created_at'])) ?></dd>
                            </div>
                        </dl>
                    </div>

                    <?php if ($template['thumbnail_url']): ?>
                    <div class="sidebar-card">
                        <h3>Preview</h3>
                        <img src="<?= e($template['thumbnail_url']) ?>" alt="<?= e($template['title']) ?>" class="sidebar-thumb">
                    </div>
                    <?php endif; ?>

                    <?php if ($template['has_free_file'] && !empty($template['game_path'])): ?>
                    <a href="<?= e($template['game_path']) ?>" download class="btn btn-gold btn-block">⬇ Download Grátis</a>
                    <?php endif; ?>

                    <?php if (!empty($templateLinks)): ?>
                    <div class="sidebar-card">
                        <h3>Links de Distribuição</h3>
                        <div class="store-links-list">
                            <?php foreach ($templateLinks as $tl): ?>
                            <a class="store-link-item store-link-item--sidebar" href="<?= e($tl['url']) ?>" target="_blank" rel="noopener">
                                <?php if (!empty($tl['use_logo']) && !empty($tl['logo_path'])): ?>
                                <img src="<?= logoImgSrc($tl['logo_path']) ?>" alt="<?= e($tl['platform_name']) ?>" class="store-link-logo">
                                <?php else: ?>
                                <span class="store-link-icon"><?= e($tl['platform_icon'] ?? '🛒') ?></span>
                                <?php endif; ?>
                                <span class="store-link-name"><?= e($tl['platform_name']) ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <a href="/templates" class="btn btn-outline btn-block">← Voltar aos Templates</a>
                </aside>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <?= e($siteName) ?>. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>
</body>
</html>
