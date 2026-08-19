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
                <?= renderSocialLinks('site', 'footer-social') ?>
            </div>
            <div class="footer-col">
                <h4>Navegação</h4>
                <a href="/">Home</a>
                <a href="/catalogo">Catálogo</a>
                <a href="/retro">Retro</a>
            </div>
            <div class="footer-col">
                <h4>Links</h4>
                <a href="/catalogo">Jogos</a>
            </div>
            <div class="footer-col">
                <h4>Redes</h4>
                <?= renderSocialLinks('site', 'footer-social-links') ?>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= e(getSetting('site_name', 'CMS de Jogos')) ?>. Todos os direitos reservados.</p>
        </div>
    </div>
</footer>
