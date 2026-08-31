<?php
require_once __DIR__ . '/config.php';

$token = trim($_GET['token'] ?? '');
$status = 'invalid';
$siteName = getSetting('site_name', SITE_NAME);

if ($token !== '') {
    $db = getDB();
    if ($db) {
        $sub = dbQueryOne("SELECT id, email, is_active FROM newsletter_subscribers WHERE unsubscribe_token = ?", [$token]);
        if ($sub) {
            if ((int)$sub['is_active'] === 1) {
                dbExec("UPDATE newsletter_subscribers SET is_active = 0 WHERE id = ?", [$sub['id']]);
                $status = 'unsubscribed';
            } else {
                $status = 'already';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($siteName) ?> — Descadastro</title>
    <link rel="icon" href="<?= siteFaviconUrl() ?>" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= assetUrl('/assets/css/style.css') ?>">
</head>
<body>
    <div class="cosmic-bg"></div>
    <div class="stars" id="stars"></div>

    <section class="section" style="padding-top:160px;">
        <div class="container" style="max-width:560px;text-align:center;">
            <?php if ($status === 'unsubscribed'): ?>
                <h1 style="font-family:'Cinzel',serif;margin-bottom:16px;">Descadastro confirmado</h1>
                <p style="color:var(--muted);margin-bottom:32px;">Você não receberá mais nossos e-mails. Sentiremos sua falta!</p>
            <?php elseif ($status === 'already'): ?>
                <h1 style="font-family:'Cinzel',serif;margin-bottom:16px;">Já está desativado</h1>
                <p style="color:var(--muted);margin-bottom:32px;">Seu e-mail já estava fora da nossa lista. Fique tranquilo!</p>
            <?php else: ?>
                <h1 style="font-family:'Cinzel',serif;margin-bottom:16px;">Link inválido</h1>
                <p style="color:var(--muted);margin-bottom:32px;">Este link de descadastro é inválido ou expirou.</p>
            <?php endif; ?>
            <a href="/" class="btn btn-gold">Voltar ao site</a>
        </div>
    </section>

    <script src="<?= assetUrl('/assets/js/main.js') ?>"></script>
</body>
</html>
