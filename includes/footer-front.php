<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="logo">
                    <div class="logo-shield">
                        <img src="<?= siteLogoUrl() ?>" alt="Logo">
                    </div>
                    <?= e(getSetting('site_name', SITE_NAME)) ?>
                </div>
                <p><?= e(getSetting('footer_description', '')) ?></p>
                <div class="footer-social">
                    <?php $yt = getSetting('youtube_url'); if ($yt): ?>
                    <a href="<?= e($yt) ?>" target="_blank" rel="noopener" aria-label="YouTube">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 33 33 0 0 0 0 12a33 33 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1 33 33 0 0 0 .5-5.8 33 33 0 0 0-.5-5.8zM9.5 15.5V8.5l6.3 3.5z"/></svg>
                    </a>
                    <?php endif; $tw = getSetting('twitch_url'); if ($tw): ?>
                    <a href="<?= e($tw) ?>" target="_blank" rel="noopener" aria-label="Twitch">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143l-3.428 3.428h-3.428l-3 3v-3H6.857V1.714h13.714Z"/></svg>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="footer-col">
                <h4>Navegação</h4>
                <a href="/">Home</a>
                <a href="/catalogo">Catálogo</a>
                <a href="/templates">Templates</a>
                <a href="/retro">Retro</a>
            </div>
            <div class="footer-col">
                <h4>Links</h4>
                <a href="/catalogo">Jogos</a>
                <?php $blog = getSetting('blog_url'); if ($blog): ?>
                <a href="<?= e($blog) ?>">Blog</a>
                <?php endif; ?>
            </div>
            <div class="footer-col">
                <h4>Redes</h4>
                <?php if ($yt): ?><a href="<?= e($yt) ?>">YouTube</a><?php endif; ?>
                <?php if ($tw): ?><a href="<?= e($tw) ?>">Twitch</a><?php endif; ?>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= e(getSetting('site_name', 'CMS de Jogos')) ?>. Todos os direitos reservados.</p>
        </div>
    </div>
</footer>
