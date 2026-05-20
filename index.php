<?php
require_once 'config.php';

if (!file_exists(DB_PATH)) {
    header('Location: /install.php');
    exit;
}

$banners = dbQuery("SELECT * FROM banners WHERE active = 1 ORDER BY sort_order ASC");
$games = dbQuery("SELECT * FROM games WHERE active = 1 ORDER BY featured DESC, sort_order ASC");
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

function engineBadgeClass($engine) {
    $map = [
        'GDevelop' => 'badge-gdevelop',
        'Godot' => 'badge-godot',
        'RPG Maker' => 'badge-rpgmaker',
        'Unity' => 'badge-unity',
        'Unreal Engine' => 'badge-unreal',
    ];
    return $map[$engine] ?? 'badge-other';
}

function engineBadgeStyle($engine) {
    $map = [
        'GDevelop' => 'background: oklch(55% 0.15 145); color: #fff;',
        'Godot' => 'background: oklch(55% 0.15 200); color: #fff;',
        'RPG Maker' => 'background: oklch(55% 0.15 30); color: #fff;',
        'Unity' => 'background: oklch(45% 0.12 250); color: #fff;',
        'Unreal Engine' => 'background: oklch(35% 0.08 260); color: #fff;',
    ];
    return $map[$engine] ?? 'background: oklch(50% 0.12 85); color: var(--bg-deep);';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($siteName) ?> — <?= e($siteTagline) ?></title>
    <meta name="description" content="<?= e($footerDescription) ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 36 36' fill='none'><path d='M18 2L32 8V20C32 28 26 33 18 35C10 33 4 28 4 20V8L18 2Z' fill='oklch(75%25 0.15 85 / 0.15)' stroke='oklch(75%25 0.15 85)' stroke-width='1.5'/><path d='M18 6L28 10V20C28 26 24 30 18 32C12 30 8 26 8 20V10L18 6Z' fill='oklch(75%25 0.15 85 / 0.1)' stroke='oklch(75%25 0.15 85 / 0.5)' stroke-width='1'/><text x='18' y='19' text-anchor='middle' dominant-baseline='central' font-family='Cinzel, serif' font-size='7' font-weight='800' fill='oklch(75%25 0.15 85)'>JTN</text></svg>" type="image/svg+xml">
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
                    <svg viewBox="0 0 36 36" fill="none">
                        <path d="M18 2L32 8V20C32 28 26 33 18 35C10 33 4 28 4 20V8L18 2Z" fill="oklch(75% 0.15 85 / 0.15)" stroke="oklch(75% 0.15 85)" stroke-width="1.5"/>
                        <path d="M18 6L28 10V20C28 26 24 30 18 32C12 30 8 26 8 20V10L18 6Z" fill="oklch(75% 0.15 85 / 0.1)" stroke="oklch(75% 0.15 85 / 0.5)" stroke-width="1"/>
                        <text x="18" y="19" text-anchor="middle" dominant-baseline="central" font-family="Cinzel, serif" font-size="7" font-weight="800" fill="oklch(75% 0.15 85)">JTN</text>
                    </svg>
                </div>
                <?= e($siteName) ?>
            </a>
            <ul class="navbar-menu">
                <li><a href="#home">Início</a></li>
                <li><a href="#games">Jogos</a></li>
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
        <a href="#games" class="mobile-link">Jogos</a>
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
                <svg viewBox="0 0 200 200" fill="none">
                    <path d="M100 10L180 40V100C180 150 140 185 100 195C60 185 20 150 20 100V40L100 10Z" fill="oklch(75% 0.15 85 / 0.08)" stroke="oklch(75% 0.15 85)" stroke-width="2.5"/>
                    <path d="M100 22L168 48V100C168 142 136 172 100 182C64 172 32 142 32 100V48L100 22Z" fill="oklch(75% 0.15 85 / 0.05)" stroke="oklch(75% 0.15 85 / 0.4)" stroke-width="1.5"/>
                    <path d="M100 34L156 56V100C156 134 132 158 100 168C68 158 44 134 44 100V56L100 34Z" fill="oklch(75% 0.15 85 / 0.03)"/>
                    <text x="100" y="98" text-anchor="middle" dominant-baseline="central" font-family="Cinzel, serif" font-size="36" font-weight="900" fill="oklch(75% 0.15 85)" style="text-shadow: 0 0 20px oklch(75% 0.15 85 / 0.5);">JTN</text>
                    <rect x="72" y="128" width="56" height="28" rx="14" fill="oklch(65% 0.18 220 / 0.2)" stroke="oklch(65% 0.18 220)" stroke-width="1.5"/>
                    <circle cx="88" cy="142" r="4" fill="oklch(65% 0.18 220)"/>
                    <circle cx="112" cy="142" r="4" fill="oklch(55% 0.20 25)"/>
                    <circle cx="100" cy="136" r="3" fill="oklch(65% 0.18 145)"/>
                    <circle cx="100" cy="148" r="3" fill="oklch(80% 0.16 90)"/>
                    <line x1="62" y1="46" x2="62" y2="74" stroke="oklch(65% 0.18 220)" stroke-width="2.5" stroke-linecap="round"/>
                    <polygon points="62,38 58,48 66,48" fill="oklch(65% 0.18 220)"/>
                    <line x1="56" y1="56" x2="68" y2="56" stroke="oklch(75% 0.15 85)" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="138" y1="46" x2="138" y2="74" stroke="oklch(75% 0.15 85)" stroke-width="2.5" stroke-linecap="round"/>
                    <path d="M138 46 L148 40 L148 54 Z" fill="oklch(75% 0.15 85 / 0.6)" stroke="oklch(75% 0.15 85)" stroke-width="1"/>
                    <line x1="132" y1="56" x2="144" y2="56" stroke="oklch(65% 0.18 220)" stroke-width="1.5" stroke-linecap="round"/>
                    <circle cx="50" cy="42" r="1.5" fill="oklch(75% 0.15 85)" opacity="0.5"/>
                    <circle cx="150" cy="42" r="1.5" fill="oklch(75% 0.15 85)" opacity="0.5"/>
                </svg>
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

    <!-- Games -->
    <section id="games" class="section section-dark">
        <div class="container">
            <div class="section-title">
                <h2>Portfólio de <span class="gold">Jogos</span></h2>
                <p>Projetos reais desenvolvidos pela nossa equipe</p>
            </div>
            <div class="games-ring-wrapper">
                <button class="games-nav-btn games-nav-prev" aria-label="Anterior">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <div class="games-ring">
                    <?php foreach ($games as $i => $game): ?>
                    <a href="/<?= strtolower(preg_replace('/[^a-zA-Z]/', '', $game['engine'])) ?>/<?= e($game['slug']) ?>" class="game-card" data-index="<?= $i ?>">
                        <div class="game-thumb">
                            <?php if ($game['thumbnail_url']): ?>
                            <img src="<?= e($game['thumbnail_url']) ?>" alt="<?= e($game['title']) ?>">
                            <?php else: ?>
                            <div class="game-thumb-placeholder"><?= getEngineIcon($game['engine']) ?></div>
                            <?php endif; ?>
                            <?php if ($game['featured'] || $game['engine']): ?>
                            <div class="game-badges">
                                <?php if ($game['featured']): ?><span class="game-badge-featured">Destaque</span><?php endif; ?>
                                <span class="game-engine-badge engine-<?= strtolower(preg_replace('/[^a-zA-Z]/', '', $game['engine'])) ?>"><?= e($game['engine']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="game-info">
                            <h3><?= e($game['title']) ?></h3>
                            <p class="game-engine"><?= getEngineIcon($game['engine']) ?> <?= e($game['engine']) ?></p>
                            <p class="game-desc"><?= e(truncateText($game['description'], 100)) ?></p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <button class="games-nav-btn games-nav-next" aria-label="Próximo">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
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
                <div class="contact-info">
                    <h3>Vamos Criar Algo <span class="gold">Incrível</span></h3>
                    <p>Estamos prontos para transformar sua ideia em um jogo real. Entre em contato pelos nossos canais.</p>
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
                            YouTube — <?= e($siteName) ?>
                        </a>
                        <?php endif; ?>
                        <?php if ($twitchUrl): ?>
                        <a href="<?= e($twitchUrl) ?>" class="contact-link" target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.6 6.4V2.8H8.8v3.6H6v7.2h2.8v2.8h2.8V13.6h2.8V6.4h-2.8zm5.6 0V2.8h-2.8v3.6h2.8zM2.8 0L0 2.8v18.4h5.6V24h2.8l2.8-2.8h4.8L24 13.2V0H2.8zm18.4 12.4l-3.6 3.6h-4.8l-2.8 2.8v-2.8H6.4V2.8h14.8v9.6z"/></svg>
                            Twitch — <?= e($siteName) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="contact-visual">
                    <svg viewBox="0 0 200 200" fill="none">
                        <path d="M100 10L180 40V100C180 150 140 185 100 195C60 185 20 150 20 100V40L100 10Z" fill="oklch(75% 0.15 85 / 0.08)" stroke="oklch(75% 0.15 85)" stroke-width="2.5"/>
                        <path d="M100 22L168 48V100C168 142 136 172 100 182C64 172 32 142 32 100V48L100 22Z" fill="oklch(75% 0.15 85 / 0.05)" stroke="oklch(75% 0.15 85 / 0.4)" stroke-width="1.5"/>
                        <path d="M100 34L156 56V100C156 134 132 158 100 168C68 158 44 134 44 100V56L100 34Z" fill="oklch(75% 0.15 85 / 0.03)"/>
                        <text x="100" y="98" text-anchor="middle" dominant-baseline="central" font-family="Cinzel, serif" font-size="36" font-weight="900" fill="oklch(75% 0.15 85)" style="text-shadow: 0 0 20px oklch(75% 0.15 85 / 0.5);">JTN</text>
                        <rect x="72" y="128" width="56" height="28" rx="14" fill="oklch(65% 0.18 220 / 0.2)" stroke="oklch(65% 0.18 220)" stroke-width="1.5"/>
                        <circle cx="88" cy="142" r="4" fill="oklch(65% 0.18 220)"/>
                        <circle cx="112" cy="142" r="4" fill="oklch(55% 0.20 25)"/>
                        <circle cx="100" cy="136" r="3" fill="oklch(65% 0.18 145)"/>
                        <circle cx="100" cy="148" r="3" fill="oklch(80% 0.16 90)"/>
                    </svg>
                    <p>Da concepção ao lançamento — criamos experiências que encantam jogadores e geram resultados.</p>
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
                            <svg viewBox="0 0 36 36" fill="none">
                                <path d="M18 2L32 8V20C32 28 26 33 18 35C10 33 4 28 4 20V8L18 2Z" fill="oklch(75% 0.15 85 / 0.15)" stroke="oklch(75% 0.15 85)" stroke-width="1.5"/>
                                <path d="M18 6L28 10V20C28 26 24 30 18 32C12 30 8 26 8 20V10L18 6Z" fill="oklch(75% 0.15 85 / 0.1)" stroke="oklch(75% 0.15 85 / 0.5)" stroke-width="1"/>
                                <text x="18" y="19" text-anchor="middle" dominant-baseline="central" font-family="Cinzel, serif" font-size="7" font-weight="800" fill="oklch(75% 0.15 85)">JTN</text>
                            </svg>
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
</body>
</html>
