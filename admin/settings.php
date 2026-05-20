<?php
ob_start();
$pageTitle = 'Configurações';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: settings.php'); exit; }

    $settings = [
        'site_name' => trim($_POST['site_name']),
        'site_tagline' => trim($_POST['site_tagline']),
        'hero_title' => trim($_POST['hero_title']),
        'hero_subtitle' => trim($_POST['hero_subtitle']),
        'contact_email' => trim($_POST['contact_email']),
        'contact_whatsapp' => trim($_POST['contact_whatsapp']),
        'youtube_url' => trim($_POST['youtube_url']),
        'twitch_url' => trim($_POST['twitch_url']),
        'blog_url' => trim($_POST['blog_url']),
        'footer_description' => trim($_POST['footer_description']),
    ];

    foreach ($settings as $key => $value) {
        setSetting($key, $value);
    }
    flashMessage('success', 'Configurações salvas!');
    ob_end_clean();
    header('Location: settings.php');
    exit;
}

$settings = [];
$keys = ['site_name', 'site_tagline', 'hero_title', 'hero_subtitle', 'contact_email', 'contact_whatsapp', 'youtube_url', 'twitch_url', 'blog_url', 'footer_description'];
foreach ($keys as $key) {
    $settings[$key] = getSetting($key, '');
}
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Configurações do Site</h2>
    </div>
    <div class="card-body">
    <form method="POST">
        <input type="hidden" name="action" value="save">
        <?= csrfField() ?>

        <h3 class="form-section-title">Informações Gerais</h3>
        <div class="form-row">
            <div class="form-group"><label for="site_name">Nome do Site</label><input type="text" id="site_name" name="site_name" value="<?= e($settings['site_name']) ?>"></div>
            <div class="form-group"><label for="site_tagline">Tagline</label><input type="text" id="site_tagline" name="site_tagline" value="<?= e($settings['site_tagline']) ?>"></div>
        </div>

        <h3 class="form-section-title">Hero / Banner Principal</h3>
        <div class="form-group"><label for="hero_title">Título do Hero (HTML permitido)</label><textarea id="hero_title" name="hero_title" rows="2"><?= e($settings['hero_title']) ?></textarea></div>
        <div class="form-group"><label for="hero_subtitle">Subtítulo do Hero</label><textarea id="hero_subtitle" name="hero_subtitle" rows="3"><?= e($settings['hero_subtitle']) ?></textarea></div>

        <h3 class="form-section-title">Contato</h3>
        <div class="form-row">
            <div class="form-group"><label for="contact_email">Email de Contato</label><input type="email" id="contact_email" name="contact_email" value="<?= e($settings['contact_email']) ?>"></div>
            <div class="form-group"><label for="contact_whatsapp">WhatsApp (número com DDD)</label><input type="text" id="contact_whatsapp" name="contact_whatsapp" value="<?= e($settings['contact_whatsapp']) ?>" placeholder="5511999999999"></div>
        </div>

        <h3 class="form-section-title">Redes Sociais</h3>
        <div class="form-row">
            <div class="form-group"><label for="youtube_url">YouTube URL</label><input type="url" id="youtube_url" name="youtube_url" value="<?= e($settings['youtube_url']) ?>"></div>
            <div class="form-group"><label for="twitch_url">Twitch URL</label><input type="url" id="twitch_url" name="twitch_url" value="<?= e($settings['twitch_url']) ?>"></div>
        </div>
        <div class="form-group"><label for="blog_url">Blog URL</label><input type="url" id="blog_url" name="blog_url" value="<?= e($settings['blog_url']) ?>"></div>

        <h3 class="form-section-title">Footer</h3>
        <div class="form-group"><label for="footer_description">Descrição do Footer</label><textarea id="footer_description" name="footer_description" rows="3"><?= e($settings['footer_description']) ?></textarea></div>

        <div class="form-actions">
            <button type="submit" class="btn btn-gold">Salvar Configurações</button>
        </div>
    </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
