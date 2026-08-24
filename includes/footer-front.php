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
            <div class="footer-col">
                <h4>Newsletter</h4>
                <p>Receba novidades e lançamentos por e-mail.</p>
                <form id="newsletterForm" class="newsletter-form-inline" style="display:flex; gap:8px; flex-direction:column;">
                    <input type="email" name="email" placeholder="seu@email.com" required style="padding:8px 12px; border:1px solid var(--border); border-radius:6px; background:var(--bg-input); color:var(--text); font-size:13px;">
                    <input type="text" name="name" placeholder="Nome (opcional)" style="padding:8px 12px; border:1px solid var(--border); border-radius:6px; background:var(--bg-input); color:var(--text); font-size:13px;">
                    <button type="submit" style="padding:8px 16px; border-radius:6px; border:1px solid var(--gold); background:var(--gold); color:#000; font-size:13px; font-weight:600; cursor:pointer;">Inscrever</button>
                </form>
                <div id="newsletterMsg" style="display:none; margin-top:8px; font-size:12px; color:var(--muted);"></div>
            </div>
        </div>
        <script>
        document.getElementById('newsletterForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            var msg = document.getElementById('newsletterMsg');
            var btn = this.querySelector('button[type="submit"]');
            var originalText = btn.textContent;
            btn.textContent = 'Enviando...';
            btn.disabled = true;

            var formData = new FormData(this);

            fetch('/subscribe.php', {
                method: 'POST',
                body: formData,
            })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    msg.style.color = 'oklch(80% 0.1 30)';
                    msg.textContent = data.error;
                    btn.textContent = originalText;
                    btn.disabled = false;
                } else if (data.success) {
                    msg.style.color = 'oklch(70% 0.3 120)';
                    msg.textContent = data.success;
                    this.reset();
                    btn.textContent = 'Inscrito!';
                    btn.disabled = true;
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }, 2000);
                }
            })
            .catch(err => {
                msg.style.color = 'oklch(80% 0.1 30)';
                msg.textContent = 'Erro de rede. Tente novamente.';
                btn.textContent = originalText;
                btn.disabled = false;
            });
            msg.style.display = 'block';
        });
        </script>
    </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= e(getSetting('site_name', 'CMS de Jogos')) ?>. Todos os direitos reservados.</p>
        </div>
    </div>
</footer>
