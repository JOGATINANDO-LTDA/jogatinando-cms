<?php
require_once 'config.php';

// Handle contact form submission
$contactSuccess = false;
$contactError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $cName = trim($_POST['c_name'] ?? '');
    $cEmail = trim($_POST['c_email'] ?? '');
    $cEngine = trim($_POST['c_engine'] ?? '');
    $cEngineOther = trim($_POST['c_engine_other'] ?? '');
    $cMessage = trim($_POST['c_message'] ?? '');

    $validEngines = array_merge(array_column(getEngines(), 'name'), ['Outra']);

    if (empty($cName) || empty($cEmail) || empty($cMessage)) {
        $contactError = 'Preencha todos os campos obrigatórios.';
    } elseif (strpos($cName, ' ') === false) {
        $contactError = 'Informe seu nome completo (nome e sobrenome).';
    } elseif (strlen($cName) < 5) {
        $contactError = 'Nome muito curto. Informe seu nome completo.';
    } elseif (strpos($cEmail, '@') === false) {
        $contactError = 'Email inválido.';
    } elseif (empty($cEngine)) {
        $contactError = 'Selecione a engine do projeto.';
    } elseif (!in_array($cEngine, $validEngines)) {
        $contactError = 'Engine inválida.';
    } elseif ($cEngine === 'Outra' && empty($cEngineOther)) {
        $contactError = 'Especifique qual engine você está usando.';
    } elseif (strlen($cMessage) < 20) {
        $contactError = 'Descreva seu projeto com mais detalhes (mínimo 20 caracteres).';
    } else {
        $engineDisplay = ($cEngine === 'Outra') ? ucfirst($cEngineOther) : $cEngine;
        $to = getSetting('contact_recipient', 'sulivan.lineage2@gmail.com');
        $subject = "Orçamento - $cName";
        $body = "Novo pedido de orçamento recebido pelo site:\n\n";
        $body .= "Nome: $cName\n";
        $body .= "Email: $cEmail\n";
        $body .= "Engine: $engineDisplay\n\n";
        $body .= "Mensagem:\n$cMessage\n";

        if (sendSmtpMail($to, $subject, $body)) {
            $contactSuccess = true;
        } else {
            $logFile = ROOT_PATH . '/data/contact_log.txt';
            if (@file_put_contents($logFile, date('Y-m-d H:i:s') . " | $cName | $cEmail | $engineDisplay\n$cMessage\n---\n", FILE_APPEND | LOCK_EX)) {
                $contactSuccess = true;
            } else {
                $contactError = 'Erro ao enviar. Tente novamente ou use nosso email direto.';
            }
        }
    }

    // AJAX response for no-reload submission
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => $contactSuccess, 'message' => $contactError]);
        exit;
    }
}

$banners = dbQuery("SELECT * FROM banners WHERE active = 1 ORDER BY sort_order ASC");
$activeEngines = dbQuery("SELECT name, slug FROM engines WHERE active = 1");
$engineSlugByName = [];
foreach ($activeEngines as $eng) {
    $engineSlugByName[$eng['name']] = $eng['slug'];
}
$activeEngineNames = array_column($activeEngines, 'name');
$games = !empty($activeEngineNames)
    ? dbQuery("SELECT * FROM games WHERE active = 1 AND engine IN (" . implode(',', array_fill(0, count($activeEngineNames), '?')) . ") ORDER BY featured DESC, sort_order ASC", $activeEngineNames)
    : [];
$featuredGames = dbQuery("SELECT g.* FROM games g INNER JOIN engines e ON e.name = g.engine WHERE g.active = 1 AND e.active = 1 ORDER BY g.featured DESC, g.sort_order ASC, g.created_at DESC LIMIT 8");
$blogPosts = dbQuery("SELECT * FROM blog_posts WHERE active = 1 ORDER BY published_at DESC LIMIT 3");
$testimonials = dbQuery("SELECT * FROM testimonials WHERE active = 1 ORDER BY sort_order ASC");
$faqItems = dbQuery("SELECT * FROM faq_items WHERE active = 1 ORDER BY sort_order ASC");
$teamMembers = dbQuery("SELECT * FROM team_members WHERE active = 1 ORDER BY sort_order ASC");

$siteName = getSetting('site_name', SITE_NAME);
$siteTagline = getSetting('site_tagline', SITE_TAGLINE);
$heroTitle = getSetting('hero_title', 'Criamos <span class="gold">Jogos</span><br>que <span class="accent">Encantam</span>');
$heroSubtitle = getSetting('hero_subtitle', 'Estúdio brasileiro especializado em desenvolvimento de jogos sob medida. Da ideia ao lançamento — em qualquer engine que seu projeto exigir.');
$contactEmail = getSetting('contact_email', '');
$contactWhatsapp = getSetting('contact_whatsapp', '');
$youtubeUrl = getSetting('youtube_url', '');
$twitchUrl = getSetting('twitch_url', '');
$blogUrl = getSetting('blog_url', '');
$footerDescription = getSetting('footer_description', '');

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($siteName) ?> — <?= e($siteTagline) ?></title>
    <meta name="description" content="<?= e($footerDescription) ?>">
    <link rel="icon" href="/assets/svg/logo.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
    <div class="cosmic-bg"></div>
    <div class="stars" id="stars"></div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="/" class="navbar-brand">
                <div class="logo-shield">
                    <img src="/assets/svg/logo.svg" alt="Logo">
                </div>
                <?= e($siteName) ?>
            </a>
            <ul class="navbar-menu">
                <li><a href="#home">Início</a></li>
                <li><a href="#categories">Categorias</a></li>
                <li><a href="/catalogo">Catálogo</a></li>
                <li><a href="/templates">Templates</a></li>
                <?php if (!empty($blogPosts)): ?><li><a href="#blog">Blog</a></li><?php endif; ?>
                <?php if (!empty($testimonials)): ?><li><a href="#testimonials">Depoimentos</a></li><?php endif; ?>
                <?php if (!empty($faqItems)): ?><li><a href="#faq">FAQ</a></li><?php endif; ?>
                <?php if (!empty($teamMembers)): ?><li><a href="#team">Equipe</a></li><?php endif; ?>
                <li><a href="#contact">Contato</a></li>
            </ul>
            <button class="navbar-toggle" id="mobileToggle" aria-label="Menu">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>
    </nav>

    <!-- Mobile Nav -->
    <div class="mobile-nav" id="mobileNav">
        <button class="mobile-nav-close" id="mobileClose" aria-label="Fechar">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <a href="#home" class="mobile-link">Início</a>
        <a href="#categories" class="mobile-link">Categorias</a>
        <a href="/catalogo" class="mobile-link">Catálogo</a>
        <a href="/templates" class="mobile-link">Templates</a>
        <?php if (!empty($blogPosts)): ?><a href="#blog" class="mobile-link">Blog</a><?php endif; ?>
        <?php if (!empty($testimonials)): ?><a href="#testimonials" class="mobile-link">Depoimentos</a><?php endif; ?>
        <?php if (!empty($faqItems)): ?><a href="#faq" class="mobile-link">FAQ</a><?php endif; ?>
        <?php if (!empty($teamMembers)): ?><a href="#team" class="mobile-link">Equipe</a><?php endif; ?>
        <a href="#contact" class="btn btn-gold mobile-link">Contato</a>
    </div>

    <!-- Hero -->
    <section id="home" class="hero">
        <div class="hero-bg"></div>
        <div class="hero-particles" id="heroParticles"></div>
        <div class="hero-content">
            <?php if (!empty($banners)): ?>
            <div class="hero-carousel">
                <?php foreach ($banners as $i => $banner): ?>
                <div class="hero-slide <?= $i === 0 ? 'active' : '' ?>" style="background-image: url('<?= e($banner['image_url']) ?>')">
                    <div class="hero-overlay"></div>
                    <div class="hero-content">
                        <h1><?= e($banner['title']) ?></h1>
                        <?php if ($banner['subtitle']): ?><p class="hero-subtitle"><?= e($banner['subtitle']) ?></p><?php endif; ?>
                        <?php if ($banner['description']): ?><p class="hero-description"><?= e($banner['description']) ?></p><?php endif; ?>
                        <div class="hero-actions">
                            <?php if ($banner['cta_text']): ?><a href="<?= e($banner['cta_url']) ?>" class="btn btn-gold"><?= e($banner['cta_text']) ?></a><?php endif; ?>
                            <a href="#games" class="btn btn-outline">Ver Jogos</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="carousel-controls">
                    <button class="carousel-btn prev" aria-label="Anterior">&#8249;</button>
                    <button class="carousel-btn next" aria-label="Próximo">&#8250;</button>
                </div>
                <div class="carousel-dots">
                    <?php foreach ($banners as $i => $banner): ?>
                    <button class="carousel-dot <?= $i === 0 ? 'active' : '' ?>" data-slide="<?= $i ?>" aria-label="Slide <?= $i + 1 ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="hero-crest">
                <img src="/assets/svg/logo.svg" alt="Logo">
            </div>
            <h1><?= $heroTitle ?></h1>
            <p class="hero-subtitle"><?= $heroSubtitle ?></p>
            <div class="hero-actions">
                <a href="#games" class="btn btn-gold">Ver Portfólio</a>
                <a href="#contact" class="btn btn-outline">Solicitar Orçamento</a>
            </div>
            <div class="hero-sub-nav">
                <?php if ($youtubeUrl): ?><a href="<?= e($youtubeUrl) ?>" target="_blank" rel="noopener">YouTube</a><?php endif; ?>
                <?php if ($twitchUrl): ?><a href="<?= e($twitchUrl) ?>" target="_blank" rel="noopener">Twitch</a><?php endif; ?>
                <?php if ($blogUrl): ?><a href="<?= e($blogUrl) ?>" target="_blank" rel="noopener">Blog</a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Ornament -->
    <div class="ornament-divider">
        <div class="line"></div>
        <div class="diamond small"></div>
        <div class="diamond"></div>
        <div class="diamond small"></div>
        <div class="line"></div>
    </div>

    <!-- Categories -->
    <section id="categories" class="section section-dark">
        <div class="container">
            <div class="section-title">
                <h2>Explore por <span class="gold">Categoria</span></h2>
                <p>Autorais, clientes, templates e emulação em uma única navegação</p>
            </div>
            <div class="games-ring-wrapper category-ring-wrapper">
                <button class="games-nav-btn games-nav-prev" aria-label="Anterior">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <div class="games-ring category-ring">
                    <a href="/autoral" class="game-card category-card" data-index="0">
                        <div class="game-thumb">
                            <div class="game-thumb-placeholder">🛠️</div>
                            <div class="game-badges">
                                <span class="game-badge-featured">Autorais</span>
                            </div>
                        </div>
                        <div class="game-info">
                            <h3>Autorais</h3>
                            <p class="game-engine">Projetos originais do estúdio</p>
                            <p class="game-desc">Jogos próprios publicados pela equipe.</p>
                        </div>
                    </a>
                    <a href="/cliente" class="game-card category-card" data-index="1">
                        <div class="game-thumb">
                            <div class="game-thumb-placeholder">🤝</div>
                            <div class="game-badges">
                                <span class="game-badge-featured">Clientes</span>
                            </div>
                        </div>
                        <div class="game-info">
                            <h3>Clientes</h3>
                            <p class="game-engine">Projetos feitos sob demanda</p>
                            <p class="game-desc">Jogos desenvolvidos para parceiros e clientes.</p>
                        </div>
                    </a>
                    <a href="/externo" class="game-card category-card" data-index="2">
                        <div class="game-thumb">
                            <div class="game-thumb-placeholder">🌐</div>
                            <div class="game-badges">
                                <span class="game-badge-featured">Externos</span>
                            </div>
                        </div>
                        <div class="game-info">
                            <h3>Externos</h3>
                            <p class="game-engine">Jogos hospedados externamente</p>
                            <p class="game-desc">Títulos que rodam em sites de terceiros via iframe.</p>
                        </div>
                    </a>
                    <a href="/templates" class="game-card category-card" data-index="3">
                        <div class="game-thumb">
                            <div class="game-thumb-placeholder">📦</div>
                            <div class="game-badges">
                                <span class="game-badge-featured">Templates</span>
                            </div>
                        </div>
                        <div class="game-info">
                            <h3>Templates</h3>
                            <p class="game-engine">Bases prontas para novos projetos</p>
                            <p class="game-desc">Estruturas reutilizáveis por engine.</p>
                        </div>
                    </a>
                    <a href="/retro" class="game-card category-card" data-index="3">
                        <div class="game-thumb">
                            <div class="game-thumb-placeholder">🕹️</div>
                            <div class="game-badges">
                                <span class="game-badge-featured">Emulação</span>
                            </div>
                        </div>
                        <div class="game-info">
                            <h3>Emulação</h3>
                            <p class="game-engine">SNES, SEGA, PSONE e mais</p>
                            <p class="game-desc">Jogos retro originais e modificados.</p>
                        </div>
                    </a>
                </div>
                <button class="games-nav-btn games-nav-next" aria-label="Próximo">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
        </div>
    </section>

    <!-- Featured Games -->
    <section id="games" class="section">
        <div class="container">
            <div class="section-title">
                <h2>Destaques de <span class="gold">Jogos</span></h2>
                <p>Uma amostra dos projetos publicados no site</p>
            </div>
            <div class="blog-grid">
                <?php foreach ($featuredGames as $game): ?>
                    <?php $engineSlug = $engineSlugByName[$game['engine']] ?? generateSlug($game['engine']); ?>
                    <a href="/<?= e($engineSlug) ?>/<?= e($game['slug']) ?>" class="blog-card game-card-compact">
                    <?php if ($game['thumbnail_url']): ?>
                    <div class="blog-thumb">
                        <img src="<?= e($game['thumbnail_url']) ?>" alt="<?= e($game['title']) ?>">
                    </div>
                    <?php endif; ?>
                    <div class="blog-content">
                        <h3><?= e($game['title']) ?></h3>
                        <p><?= e(truncateText($game['description'], 150)) ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Ornament -->
    <div class="ornament-divider">
        <div class="line"></div>
        <div class="diamond small"></div>
        <div class="diamond"></div>
        <div class="diamond small"></div>
        <div class="line"></div>
    </div>

    <!-- Blog -->
    <?php if (!empty($blogPosts)): ?>
    <section id="blog" class="section">
        <div class="container">
            <div class="section-title">
                <h2>Blog</h2>
                <p>Notícias, análises e artigos sobre o mundo dos jogos</p>
            </div>
            <div class="blog-grid">
                <?php foreach ($blogPosts as $post): ?>
                <article class="blog-card">
                    <?php if ($post['thumbnail_url']): ?>
                    <div class="blog-thumb">
                        <img src="<?= e($post['thumbnail_url']) ?>" alt="<?= e($post['title']) ?>">
                    </div>
                    <?php endif; ?>
                    <div class="blog-content">
                        <h3><?= e($post['title']) ?></h3>
                        <p><?= e(truncateText($post['content'], 150)) ?></p>
                        <time><?= date('d/m/Y', strtotime($post['published_at'])) ?></time>
                        <?php if ($post['external_url']): ?>
                        <a href="<?= e($post['external_url']) ?>" class="btn btn-outline btn-sm" style="margin-top:12px" target="_blank" rel="noopener">Ler mais</a>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php if ($blogUrl): ?>
            <div class="section-footer">
                <a href="<?= e($blogUrl) ?>" class="btn btn-outline" target="_blank" rel="noopener">Ver Blog Completo →</a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Ornament -->
    <div class="ornament-divider">
        <div class="line"></div>
        <div class="diamond small"></div>
        <div class="diamond large"></div>
        <div class="diamond small"></div>
        <div class="line"></div>
    </div>
    <?php endif; ?>

    <!-- Testimonials -->
    <?php if (!empty($testimonials)): ?>
    <section id="testimonials" class="section section-dark">
        <div class="container">
            <div class="section-title">
                <h2>O Que Dizem <span class="gold">Nossos Clientes</span></h2>
                <p>Feedback de quem já trabalhou com a <?= e($siteName) ?></p>
            </div>
            <div class="testimonials-grid">
                <?php foreach ($testimonials as $t): ?>
                <div class="testimonial-card">
                    <p class="testimonial-text">"<?= e($t['quote']) ?>"</p>
                    <div class="testimonial-author">
                        <?php if ($t['avatar_url']): ?>
                        <img src="<?= e($t['avatar_url']) ?>" alt="<?= e($t['name']) ?>" class="testimonial-avatar">
                        <?php else: ?>
                        <div class="testimonial-avatar"><?= strtoupper(substr($t['name'], 0, 2)) ?></div>
                        <?php endif; ?>
                        <div>
                            <div class="testimonial-name"><?= e($t['name']) ?></div>
                            <?php if ($t['role']): ?><div class="testimonial-role"><?= e($t['role']) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Ornament -->
    <div class="ornament-divider">
        <div class="line"></div>
        <div class="diamond small"></div>
        <div class="diamond"></div>
        <div class="diamond small"></div>
        <div class="line"></div>
    </div>
    <?php endif; ?>

    <!-- FAQ -->
    <?php if (!empty($faqItems)): ?>
    <section id="faq" class="section">
        <div class="container">
            <div class="section-title">
                <h2>Perguntas <span class="gold">Frequentes</span></h2>
                <p>Tire suas dúvidas sobre nossos serviços</p>
            </div>
            <div class="faq-list">
                <?php foreach ($faqItems as $faq): ?>
                <details class="faq-item">
                    <summary><?= e($faq['question']) ?></summary>
                    <p><?= e($faq['answer']) ?></p>
                </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Ornament -->
    <div class="ornament-divider">
        <div class="line"></div>
        <div class="diamond small"></div>
        <div class="diamond large"></div>
        <div class="diamond small"></div>
        <div class="line"></div>
    </div>
    <?php endif; ?>

    <!-- Team -->
    <?php if (!empty($teamMembers)): ?>
    <section id="team" class="section section-dark">
        <div class="container">
            <div class="section-title">
                <h2>Nossa <span class="gold">Equipe</span></h2>
                <p>Conheça a equipe por trás dos jogos</p>
            </div>
            <div class="team-grid">
                <?php foreach ($teamMembers as $m): ?>
                <div class="team-card">
                    <?php if ($m['avatar_url']): ?>
                    <img src="<?= e($m['avatar_url']) ?>" alt="<?= e($m['name']) ?>" class="team-avatar">
                    <?php else: ?>
                    <div class="team-avatar-placeholder"><?= strtoupper(substr($m['name'], 0, 1)) ?></div>
                    <?php endif; ?>
                    <h3><?= e($m['name']) ?></h3>
                    <p class="team-role"><?= e($m['role']) ?></p>
                    <?php if ($m['bio']): ?><p class="team-bio"><?= e(truncateText($m['bio'], 120)) ?></p><?php endif; ?>
                    <div class="team-social">
                        <?php if ($m['social_youtube']): ?><a href="<?= e($m['social_youtube']) ?>" target="_blank" rel="noopener" aria-label="YouTube">📺</a><?php endif; ?>
                        <?php if ($m['social_twitch']): ?><a href="<?= e($m['social_twitch']) ?>" target="_blank" rel="noopener" aria-label="Twitch">🎬</a><?php endif; ?>
                        <?php if ($m['social_linkedin']): ?><a href="<?= e($m['social_linkedin']) ?>" target="_blank" rel="noopener" aria-label="LinkedIn">💼</a><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Ornament -->
    <div class="ornament-divider">
        <div class="line"></div>
        <div class="diamond small"></div>
        <div class="diamond large"></div>
        <div class="diamond small"></div>
        <div class="line"></div>
    </div>
    <?php endif; ?>

    <!-- Contact -->
    <section id="contact" class="section">
        <div class="container">
            <div class="section-title">
                <h2>Solicite seu <span class="gold">Orçamento</span></h2>
                <p>Conte-nos sobre seu projeto e retornaremos em até 24 horas</p>
            </div>
            <div class="contact-grid">
                <!-- Form -->
                <div class="contact-form-wrapper">
                    <div id="contactFeedback" class="contact-feedback" style="display:none"></div>
                    <form id="contactForm" class="contact-form">
                        <div class="form-group">
                            <label for="c_name">Nome *</label>
                            <input type="text" id="c_name" name="c_name" required placeholder="Seu nome completo">
                            <span id="nameCounter" class="field-hint">0 caracteres</span>
                        </div>
                        <div class="form-group">
                            <label for="c_email">Email *</label>
                            <input type="email" id="c_email" name="c_email" required placeholder="seu@email.com">
                        </div>
                        <div class="form-group">
                            <label for="c_engine">Engine do Projeto *</label>
                            <select id="c_engine" name="c_engine" required>
                                <option value="">Selecione uma engine</option>
                                <?php foreach (getEngines() as $eng): ?>
                                <option value="<?= e($eng['name']) ?>"><?= e($eng['icon'] ?? '') ?> <?= e($eng['name']) ?></option>
                                <?php endforeach; ?>
                                <option value="Outra">🎮 Outra</option>
                            </select>
                        </div>
                        <div class="form-group" id="engineOtherGroup" style="display:none">
                            <label for="c_engine_other">Qual engine? *</label>
                            <input type="text" id="c_engine_other" name="c_engine_other" placeholder="Ex: UDK, CryEngine, Phaser...">
                        </div>
                        <div class="form-group">
                            <label for="c_message">Mensagem *</label>
                            <textarea id="c_message" name="c_message" rows="6" required placeholder="Descreva seu projeto, plataforma alvo, prazo estimado..." minlength="20"></textarea>
                            <span id="msgCounter" class="field-hint">0 / 20 caracteres mínimos</span>
                        </div>
                        <button type="submit" id="contactBtn" class="btn btn-gold btn-lg">Enviar Solicitação</button>
                    </form>
                </div>

                <!-- Info -->
                <div class="contact-info">
                    <h3>Outros <span class="gold">Canais</span></h3>
                    <p>Também pode nos encontrar por aqui:</p>
                    <div class="contact-links">
                        <?php if ($contactEmail): ?>
                        <a href="mailto:<?= e($contactEmail) ?>" class="contact-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            <?= e($contactEmail) ?>
                        </a>
                        <?php endif; ?>
                        <?php if ($contactWhatsapp): ?>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $contactWhatsapp) ?>" class="contact-link" target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.5.5 0 00.61.609l4.507-1.478A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.344 0-4.518-.724-6.306-1.964l-.44-.308-2.667.875.892-2.606-.336-.464A9.935 9.935 0 012 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/></svg>
                            <?= e($contactWhatsapp) ?>
                        </a>
                        <?php endif; ?>
                        <?php if ($youtubeUrl): ?>
                        <a href="<?= e($youtubeUrl) ?>" class="contact-link" target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.5 6.2c-.3-1-1-1.8-2-2.1C19.6 3.5 12 3.5 12 3.5s-7.6 0-9.5.6c-1 .3-1.7 1.1-2 2.1C0 8.1 0 12 0 12s0 3.9.5 5.8c.3 1 1 1.8 2 2.1 1.9.6 9.5.6 9.5.6s7.6 0 9.5-.6c1-.3 1.7-1.1 2-2.1.5-1.9.5-5.8.5-5.8s0-3.9-.5-5.8zM9.5 15.6V8.4L15.8 12l-6.3 3.6z"/></svg>
                            YouTube
                        </a>
                        <?php endif; ?>
                        <?php if ($twitchUrl): ?>
                        <a href="<?= e($twitchUrl) ?>" class="contact-link" target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.6 6.4V2.8H8.8v3.6H6v7.2h2.8v2.8h2.8V13.6h2.8V6.4h-2.8zm5.6 0V2.8h-2.8v3.6h2.8zM2.8 0L0 2.8v18.4h5.6V24h2.8l2.8-2.8h4.8L24 13.2V0H2.8zm18.4 12.4l-3.6 3.6h-4.8l-2.8 2.8v-2.8H6.4V2.8h14.8v9.6z"/></svg>
                            Twitch
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Ornament -->
    <div class="ornament-divider">
        <div class="line"></div>
        <div class="diamond small"></div>
        <div class="diamond large"></div>
        <div class="diamond small"></div>
        <div class="line"></div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <div class="logo">
                        <div class="logo-shield">
                            <img src="/assets/svg/logo.svg" alt="Logo">
                        </div>
                        <?= e($siteName) ?>
                    </div>
                    <p><?= e($footerDescription) ?></p>
                    <div class="footer-social">
                        <?php if ($youtubeUrl): ?>
                        <a href="<?= e($youtubeUrl) ?>" target="_blank" rel="noopener" aria-label="YouTube">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.5 6.2c-.3-1-1-1.8-2-2.1C19.6 3.5 12 3.5 12 3.5s-7.6 0-9.5.6c-1 .3-1.7 1.1-2 2.1C0 8.1 0 12 0 12s0 3.9.5 5.8c.3 1 1 1.8 2 2.1 1.9.6 9.5.6 9.5.6s7.6 0 9.5-.6c1-.3 1.7-1.1 2-2.1.5-1.9.5-5.8.5-5.8s0-3.9-.5-5.8zM9.5 15.6V8.4L15.8 12l-6.3 3.6z"/></svg>
                        </a>
                        <?php endif; ?>
                        <?php if ($twitchUrl): ?>
                        <a href="<?= e($twitchUrl) ?>" target="_blank" rel="noopener" aria-label="Twitch">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.6 6.4V2.8H8.8v3.6H6v7.2h2.8v2.8h2.8V13.6h2.8V6.4h-2.8zm5.6 0V2.8h-2.8v3.6h2.8zM2.8 0L0 2.8v18.4h5.6V24h2.8l2.8-2.8h4.8L24 13.2V0H2.8zm18.4 12.4l-3.6 3.6h-4.8l-2.8 2.8v-2.8H6.4V2.8h14.8v9.6z"/></svg>
                        </a>
                        <?php endif; ?>
                        <?php if ($blogUrl): ?>
                        <a href="<?= e($blogUrl) ?>" target="_blank" rel="noopener" aria-label="Blog">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Navegação</h4>
                    <a href="#home">Início</a>
                    <a href="#games">Jogos</a>
                    <?php if (!empty($blogPosts)): ?><a href="#blog">Blog</a><?php endif; ?>
                    <?php if (!empty($testimonials)): ?><a href="#testimonials">Depoimentos</a><?php endif; ?>
                    <?php if (!empty($faqItems)): ?><a href="#faq">FAQ</a><?php endif; ?>
                </div>
                <div class="footer-col">
                    <h4>Engines</h4>
                    <a href="#games">GDevelop</a>
                    <a href="#games">Godot</a>
                    <a href="#games">RPG Maker</a>
                    <a href="#games">Unity</a>
                    <a href="#games">Unreal</a>
                </div>
                <div class="footer-col">
                    <h4>Contato</h4>
                    <?php if ($contactEmail): ?><a href="mailto:<?= e($contactEmail) ?>">E-mail</a><?php endif; ?>
                    <?php if ($youtubeUrl): ?><a href="<?= e($youtubeUrl) ?>" target="_blank" rel="noopener">YouTube</a><?php endif; ?>
                    <?php if ($twitchUrl): ?><a href="<?= e($twitchUrl) ?>" target="_blank" rel="noopener">Twitch</a><?php endif; ?>
                    <?php if ($blogUrl): ?><a href="<?= e($blogUrl) ?>" target="_blank" rel="noopener">Blog</a><?php endif; ?>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <?= e($siteName) ?>. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script src="<?= SITE_URL ?>/assets/js/main.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('contactForm');
        const feedback = document.getElementById('contactFeedback');
        const btn = document.getElementById('contactBtn');
        const engineSelect = document.getElementById('c_engine');
        const engineOtherGroup = document.getElementById('engineOtherGroup');
        const engineOtherInput = document.getElementById('c_engine_other');
        const msgTextarea = document.getElementById('c_message');
        const msgCounter = document.getElementById('msgCounter');
        const nameInput = document.getElementById('c_name');
        const nameCounter = document.getElementById('nameCounter');
        if (!form) return;

        // Engine "Outra" toggle
        engineSelect.addEventListener('change', () => {
            const show = engineSelect.value === 'Outra';
            engineOtherGroup.style.display = show ? 'block' : 'none';
            if (!show) {
                engineOtherInput.value = '';
                engineOtherInput.removeAttribute('required');
            } else {
                engineOtherInput.setAttribute('required', 'required');
            }
        });

        // Name character counter
        nameInput.addEventListener('input', () => {
            const len = nameInput.value.trim().length;
            const min = 5;
            const hasSpace = nameInput.value.trim().indexOf(' ') !== -1;
            nameCounter.textContent = len < min
                ? `${len} / ${min} caracteres mínimos`
                : `${len} caracteres${hasSpace ? '' : ' — informe nome e sobrenome'}`;
            nameCounter.style.color = (len >= min && hasSpace) ? 'oklch(70% 0.18 150)' : 'oklch(60% 0.22 25)';
        });

        // Message character counter
        msgTextarea.addEventListener('input', () => {
            const len = msgTextarea.value.length;
            const min = 20;
            msgCounter.textContent = `${len} / ${min} caracteres mínimos`;
            msgCounter.style.color = len < min ? 'oklch(60% 0.22 25)' : 'oklch(70% 0.18 150)';
        });

        // Form submission
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Client-side validation
            const name = nameInput.value.trim();
            const email = document.getElementById('c_email').value.trim();
            const msg = msgTextarea.value.trim();
            if (name.length < 5) {
                showFeedback('error', 'Nome muito curto. Informe seu nome completo.');
                return;
            }
            if (name.indexOf(' ') === -1) {
                showFeedback('error', 'Informe seu nome completo (nome e sobrenome).');
                return;
            }
            if (email.indexOf('@') === -1) {
                showFeedback('error', 'Email inválido.');
                return;
            }
            if (msg.length < 20) {
                showFeedback('error', 'Descreva seu projeto com mais detalhes (mínimo 20 caracteres).');
                return;
            }
            if (!engineSelect.value) {
                showFeedback('error', 'Selecione a engine do projeto.');
                return;
            }
            if (engineSelect.value === 'Outra' && !engineOtherInput.value.trim()) {
                showFeedback('error', 'Especifique qual engine você está usando.');
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Enviando...';
            feedback.style.display = 'none';

            const data = new FormData(form);
            data.append('contact_submit', '1');

            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: data,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();

                if (json.success) {
                    showFeedback('success', 'Mensagem Enviada!', 'Recebemos seu pedido. Retornaremos em breve.');
                    form.reset();
                    engineOtherGroup.style.display = 'none';
                    engineOtherInput.removeAttribute('required');
                    nameCounter.textContent = '0 caracteres';
                    nameCounter.style.color = 'oklch(60% 0.22 25)';
                    msgCounter.textContent = `0 / 20 caracteres mínimos`;
                    msgCounter.style.color = 'oklch(60% 0.22 25)';
                    setTimeout(() => { feedback.style.display = 'none'; }, 5000);
                } else {
                    showFeedback('error', json.message || 'Erro ao enviar. Tente novamente.');
                }
            } catch (err) {
                showFeedback('error', 'Erro de conexão. Verifique sua internet.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Enviar Solicitação';
            }
        });

        function showFeedback(type, title, subtitle) {
            const svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
            const errorSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
            feedback.className = 'contact-feedback ' + type;
            feedback.innerHTML = (type === 'success' ? svg : errorSvg) +
                '<h3>' + title + '</h3>' +
                (subtitle ? '<p>' + subtitle + '</p>' : '<p>' + title + '</p>');
            feedback.style.display = 'flex';
        }
    });
    </script>
</body>
</html>
