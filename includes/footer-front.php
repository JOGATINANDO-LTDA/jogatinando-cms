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
            <?php $donConfig = json_decode(getSetting('donation_config', ''), true); ?>
            <?php if (!empty($donConfig['enabled'])): ?>
            <div class="footer-col">
                <h4>Apoie</h4>
                <?= !empty($donConfig['custom_html']) ? $donConfig['custom_html'] : '<p>Apoie nosso trabalho com uma doação:</p>' ?>
                <div class="donation-tiers">
                    <?php if (!empty($donConfig['pix_key'])): ?>
                        <button type="button" class="donation-tier-btn" onclick="showPixModal('<?= e($donConfig['pix_key']) ?>', '<?= e($donConfig['pix_description']) ?>')">PIX R$5</button>
                        <button type="button" class="donation-tier-btn" onclick="showPixModal('<?= e($donConfig['pix_key']) ?>', '<?= e($donConfig['pix_description']) ?>')">PIX R$15</button>
                    <?php endif; ?>
                    <?php if (!empty($donConfig['paypal_url'])): ?>
                        <a href="<?= e($donConfig['paypal_url']) ?>" class="donation-tier-btn" target="_blank">PayPal</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <script>
        function showPixModal(key, desc) {
            var existing = document.getElementById('pixModal');
            if (existing) existing.remove();
            var div = document.createElement('div');
            div.id = 'pixModal';
            div.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:9999;';
            var html = '<div style="background:var(--bg-card);padding:32px;border-radius:12px;text-align:center;max-width:320px;">';
            html += '<h3 style="margin-top:0;color:var(--gold);">Doação via PIX</h3>';
            html += '<p style="margin-bottom:16px;">Chave PIX:</p>';
            html += '<input type="text" value="' + key + '" readonly onclick="this.select()" style="width:100%;padding:8px;background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:4px;font-size:12px;margin-bottom:12px;">';
            if (desc) html += '<p style="font-size:12px;color:var(--muted);margin-bottom:16px;">' + desc + '</p>';
            html += '<button onclick="this.closest(\'#pixModal\').remove()" style="padding:8px 24px;border-radius:6px;border:1px solid var(--border);background:var(--border-light);cursor:pointer;">Fechar</button>';
            html += '</div>';
            div.innerHTML = html;
            document.body.appendChild(div);
        }

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
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= e(getSetting('site_name', 'CMS de Jogos')) ?>. Todos os direitos reservados.</p>
        </div>
    </div>
</footer>
