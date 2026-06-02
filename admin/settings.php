<?php
ob_start();
$pageTitle = 'Configurações';
$requiredPerm = 'perm_settings';
require_once __DIR__ . '/../includes/header.php';

$userId = $_SESSION['admin_user_id'] ?? 0;

// Avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_avatar') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit; }

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $stmt = $db->prepare("SELECT avatar_url FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldAvatar = $stmt->fetchColumn();
        $result = uploadFile($_FILES['avatar'], 'avatars', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        if ($result['success']) {
            if (!empty($oldAvatar)) {
                if (str_starts_with($oldAvatar, '/')) {
                    deleteFile(ROOT_PATH . $oldAvatar);
                } else {
                    deleteFile(str_replace(SITE_URL . '/', ROOT_PATH . '/', $oldAvatar));
                }
            }
            $db = getDB();
            $stmt = $db->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
            $stmt->execute([$result['url'], $userId]);
            $_SESSION['admin_avatar_url'] = $result['url'];
            flashMessage('success', 'Foto de perfil atualizada!');
        } else {
            flashMessage('error', $result['message']);
        }
    }
    ob_end_clean();
    header('Location: ' . ADMIN_URL . '/settings');
    exit;
}

// Avatar remove
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_avatar') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit; }

    $db = getDB();
    $stmt = $db->prepare("SELECT avatar_url FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $current = $stmt->fetchColumn();
    if ($current) {
        if (str_starts_with($current, '/')) {
            deleteFile(ROOT_PATH . $current);
        } else {
            deleteFile(str_replace(SITE_URL . '/', ROOT_PATH . '/', $current));
        }
    }
    $stmt = $db->prepare("UPDATE users SET avatar_url = '' WHERE id = ?");
    $stmt->execute([$userId]);
    $_SESSION['admin_avatar_url'] = '';
    flashMessage('success', 'Foto de perfil removida.');
    ob_end_clean();
    header('Location: ' . ADMIN_URL . '/settings');
    exit;
}

// Password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit; }

    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentHash = $stmt->fetchColumn();

    if (!password_verify($currentPass, $currentHash)) {
        flashMessage('error', 'Senha atual incorreta.');
    } elseif (strlen($newPass) < 6) {
        flashMessage('error', 'A nova senha deve ter no mínimo 6 caracteres.');
    } elseif ($newPass !== $confirmPass) {
        flashMessage('error', 'As senhas não conferem.');
    } else {
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);
        flashMessage('success', 'Senha alterada com sucesso!');
    }
    ob_end_clean();
    header('Location: ' . ADMIN_URL . '/settings');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit; }

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
        'maintenance_mode' => $_POST['maintenance_mode'] ?? '0',
    ];

    foreach ($settings as $key => $value) {
        setSetting($key, $value);
    }
    flashMessage('success', 'Configurações salvas!');
    ob_end_clean();
    header('Location: ' . ADMIN_URL . '/settings');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'save_noreply') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit; }
    setSetting('noreply_email', trim($_POST['noreply_email'] ?? ''));
    setSetting('noreply_name', trim($_POST['noreply_name'] ?? ''));
    flashMessage('success', 'Configurações de notificação salvas!');
    ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['save_smtp', 'test_smtp'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit; }

    $smtpHost  = trim($_POST['smtp_host'] ?? '');
    $smtpPort  = trim($_POST['smtp_port'] ?? '');
    $smtpUser  = trim($_POST['smtp_user'] ?? '');
    $smtpPass  = $_POST['smtp_pass'] ?? '';
    $smtpFrom  = trim($_POST['smtp_from'] ?? '');
    $smtpFromName = trim($_POST['smtp_from_name'] ?? '');

    if (empty($smtpHost) || empty($smtpPort)) {
        flashMessage('error', 'Servidor e porta são obrigatórios.');
        ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit;
    }

    $contactRecipient = trim($_POST['contact_recipient'] ?? '');

    // --- test_smtp: try connection before saving ---
    if ($_POST['action'] === 'test_smtp') {
        // Fall back to existing values if fields were left empty (SMTP locked)
        if (empty($smtpUser) && defined('SMTP_USER') && SMTP_USER !== '') {
            $smtpUser = SMTP_USER;
        }
        if (empty($smtpPass) && defined('SMTP_PASS') && SMTP_PASS !== '') {
            $smtpPass = SMTP_PASS;
        }
        if (empty($smtpHost) && defined('SMTP_HOST') && SMTP_HOST !== '') {
            $smtpHost = SMTP_HOST;
            $smtpPort = defined('SMTP_PORT') ? SMTP_PORT : '587';
        }

        $error = null;
        $fp = @fsockopen($smtpHost, (int)$smtpPort, $errno, $errstr, 15);
        if (!$fp) {
            flashMessage('error', "Falha ao conectar em $smtpHost:$smtpPort — $errstr");
            ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit;
        }

        $read = function($fp) {
            $out = '';
            while (($line = fgets($fp)) !== false) {
                $out .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            return trim($out);
        };
        $write = function($fp, $cmd) { fwrite($fp, "$cmd\r\n"); };

        $banner = $read($fp);
        $write($fp, "EHLO test.jogatinando.com.br");
        $ehlo = $read($fp);

        // Try STARTTLS if available
        if (stripos($ehlo, 'STARTTLS') !== false && function_exists('stream_socket_enable_crypto')) {
            $write($fp, "STARTTLS");
            $starttlsResp = $read($fp);
            if (substr($starttlsResp, 0, 3) === '220') {
                @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $write($fp, "EHLO test.jogatinando.com.br");
                $ehlo = $read($fp);
            }
        }

        $authSupported = stripos($ehlo, 'AUTH') !== false;
        if (!$authSupported) {
            fclose($fp);
            flashMessage('error', 'Servidor SMTP não suporta autenticação (AUTH não listado).');
            ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit;
        }

        // AUTH LOGIN
        $write($fp, "AUTH LOGIN");
        $authResp = $read($fp);
        if (substr($authResp, 0, 3) === '334') {
            $write($fp, base64_encode($smtpUser));
            $userResp = $read($fp);
            if (substr($userResp, 0, 3) === '334') {
                $write($fp, base64_encode($smtpPass));
                $passResp = $read($fp);
                if (substr($passResp, 0, 3) === '235') {
                    $write($fp, "QUIT");
                    fclose($fp);

                    // Test passed — save config
                    _writeSmtpConfig($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpFromName, $contactRecipient);
                    flashMessage('success', 'Teste SMTP OK — conexão e autenticação funcionam. Configurações salvas!');
                    ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit;
                } else {
                    $error = "Falha na autenticação: $passResp";
                }
            } else {
                $error = "Usuário rejeitado: $userResp";
            }
        } else {
            $error = "AUTH LOGIN não aceito: $authResp";
        }

        fclose($fp);
        flashMessage('error', $error);
        ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit;
    }

    // --- save_smtp: just write config ---
    _writeSmtpConfig($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpFromName, $contactRecipient);
    flashMessage('success', 'Configurações SMTP salvas!');
    ob_end_clean();
    header('Location: ' . ADMIN_URL . '/settings');
    exit;
}

function _writeSmtpConfig($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpFromName, $contactRecipient) {
    $configPath = DATA_PATH . '/config.local.php';
    $existing = file_exists($configPath) ? file_get_contents($configPath) : '';

    $smtpPos = strpos($existing, "if (!defined('SMTP_PASS'))");
    $localConfig = $smtpPos !== false ? substr($existing, 0, $smtpPos) : rtrim($existing);
    if ($localConfig !== '' && !str_ends_with($localConfig, "\n\n")) {
        $localConfig .= "\n\n";
    }

    if ($smtpPass === '' && $smtpPos !== false) {
        if (preg_match("/define\('SMTP_PASS',\s*'(.*?)'\)/", $existing, $m)) {
            $smtpPass = stripslashes($m[1]);
        }
    }

    $localConfig .= "if (!defined('SMTP_PASS')) {\n";
    $localConfig .= "    define('SMTP_HOST', '" . addslashes($smtpHost) . "');\n";
    $localConfig .= "    define('SMTP_PORT', '" . addslashes($smtpPort) . "');\n";
    $localConfig .= "    define('SMTP_USER', '" . addslashes($smtpUser) . "');\n";
    $localConfig .= "    define('SMTP_PASS', '" . addslashes($smtpPass) . "');\n";
    $localConfig .= "    define('SMTP_FROM', '" . addslashes($smtpFrom) . "');\n";
    $localConfig .= "    define('SMTP_FROM_NAME', '" . addslashes($smtpFromName) . "');\n";
    $localConfig .= "}\n";

    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
    file_put_contents($configPath, $localConfig);
    setSetting('contact_recipient', $contactRecipient);
}

$settings = [];
$keys = ['site_name', 'site_tagline', 'hero_title', 'hero_subtitle', 'contact_email', 'contact_whatsapp', 'youtube_url', 'twitch_url', 'blog_url', 'footer_description', 'contact_recipient', 'maintenance_mode'];
foreach ($keys as $key) {
    $settings[$key] = getSetting($key, '');
}

// Current SMTP values from constants
$smtpHost = defined('SMTP_HOST') && SMTP_HOST !== '' ? SMTP_HOST : 'smtp.gmail.com';
$smtpPort = defined('SMTP_PORT') && SMTP_PORT !== '' ? SMTP_PORT : '587';
$smtpUser = defined('SMTP_USER') ? SMTP_USER : '';
$smtpFrom = defined('SMTP_FROM') ? SMTP_FROM : '';
$smtpFromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : '';
$smtpConfigured = defined('SMTP_PASS') && SMTP_PASS !== '';

// Current user data for profile card
$userData = null;
$db = getDB();
$stmt = $db->prepare("SELECT u.username, u.avatar_url, r.name as role_name, r.level as role_level, l.name as level_name FROM users u LEFT JOIN roles r ON u.role_id = r.id LEFT JOIN levels l ON r.level_id = l.id WHERE u.id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
$profileInitial = strtoupper(substr($userData['username'] ?? 'A', 0, 1));
?>

<!-- Meu Perfil -->
<div class="card" style="margin-bottom: 24px; border-color: oklch(75% 0.15 85 / 0.3);">
    <div class="card-header">
        <h2 class="card-title">Meu Perfil</h2>
    </div>
    <div class="card-body">
        <div style="display: flex; align-items: center; gap: 24px; flex-wrap: wrap;">
            <div style="text-align: center;">
                <div style="width: 80px; height: 80px; border-radius: 50%; overflow: hidden; background: linear-gradient(135deg, oklch(75% 0.15 85), oklch(62% 0.13 85)); display: flex; align-items: center; justify-content: center; font-family: 'Cinzel', serif; font-size: 28px; font-weight: 700; color: oklch(8% 0.02 260); margin: 0 auto 8px;">
                    <?php if ($userData && $userData['avatar_url']): ?>
                        <img src="<?= e($userData['avatar_url']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <?= $profileInitial ?>
                    <?php endif; ?>
                </div>
            </div>
            <div style="flex:1;min-width:200px;">
                <p style="margin-bottom:4px;"><strong style="color:var(--fg)"><?= e($userData['username'] ?? '') ?></strong>
                    <?php if (isset($userData['role_name'])): ?>
                    <span class="badge badge-featured" style="margin-left:8px;font-size:11px;"><?= e($userData['level_name'] ?? $userData['role_name'] ?? '') ?></span>
                    <?php endif; ?>
                </p>
                <p style="font-size:13px;color:var(--fg-muted);margin-bottom:12px;">Seu perfil de acesso ao painel administrativo.</p>
                <form method="POST" enctype="multipart/form-data" style="display:inline-block;">
                    <input type="hidden" name="action" value="save_avatar">
                    <?= csrfField() ?>
                    <label for="avatar-upload" class="btn btn-outline" style="cursor:pointer;display:inline-flex;align-items:center;gap:6px;padding:8px 16px;font-size:13px;">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Upload
                    </label>
                    <input type="file" id="avatar-upload" name="avatar" accept="image/*" style="display:none;" onchange="this.form.submit()">
                </form>
                <?php if ($userData && $userData['avatar_url']): ?>
                <form method="POST" style="display:inline-block;margin-left:8px;">
                    <input type="hidden" name="action" value="remove_avatar">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-outline" style="border-color:oklch(55% 0.20 25);color:oklch(55% 0.20 25);padding:8px 16px;font-size:13px;cursor:pointer;" onclick="return confirm('Remover foto de perfil?')">Remover</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
            <button class="btn btn-outline btn-sm" onclick="document.getElementById('passwordForm').classList.toggle('hidden'); this.classList.toggle('hidden')">Alterar Senha</button>
            <form id="passwordForm" method="POST" class="hidden" style="margin-top: 16px; max-width: 400px;">
                <input type="hidden" name="action" value="change_password">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="current_password">Senha Atual *</label>
                    <input type="password" id="current_password" name="current_password" required placeholder="Sua senha atual">
                </div>
                <div class="form-group">
                    <label for="new_password">Nova Senha *</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6" placeholder="Mínimo 6 caracteres">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmar Nova Senha *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Repita a nova senha">
                </div>
                <button type="submit" class="btn btn-gold btn-sm">Salvar Nova Senha</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Configurações do Site</h2>
        <div style="margin-top: 8px;">
            <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: <?= DB_TYPE === 'mysql' ? 'oklch(68% 0.16 220 / 0.15)' : 'oklch(75% 0.15 85 / 0.15)' ?>; color: <?= DB_TYPE === 'mysql' ? 'oklch(68% 0.16 220)' : 'oklch(75% 0.15 85)' ?>;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: <?= DB_TYPE === 'mysql' ? 'oklch(68% 0.16 220)' : 'oklch(75% 0.15 85)' ?>;"></span>
                Banco: <?= DB_TYPE === 'mysql' ? 'MySQL' : 'SQLite' ?>
            </span>
        </div>
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

        <h3 class="form-section-title">Manutenção</h3>
        <div class="form-group">
            <label class="toggle-label" for="maintenance_mode">
                <input type="hidden" name="maintenance_mode" value="0">
                <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?> style="accent-color:oklch(75% 0.15 85);width:18px;height:18px;cursor:pointer;">
                <span style="margin-left:8px;font-weight:600;">Ativar modo de manutenção</span>
            </label>
            <p style="font-size:12px;color:oklch(60% 0.012 250);margin-top:4px;">Quando ativo, visitantes veem uma página de "Em Manutenção". Administradores logados continuam acessando normalmente.</p>
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

<!-- Configurações SMTP -->
<div class="card" style="margin-top: 24px; border-color: <?= $smtpConfigured ? 'oklch(75% 0.15 85 / 0.4)' : 'oklch(30% 0.02 260 / 0.3)' ?>;">
    <div class="card-header">
        <h2 class="card-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;"><path d="M22 6L12 13L2 6M22 6v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6l10 7l10-7z"/></svg>
            Configurações SMTP / E-mail
        </h2>
    </div>
    <div class="card-body">
        <?php if ($smtpConfigured): ?>
        <div style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: oklch(75% 0.15 85 / 0.08); border: 1px solid oklch(75% 0.15 85 / 0.2); border-radius: 8px; margin-bottom: 20px;">
            <span style="width: 12px; height: 12px; border-radius: 50%; background: oklch(75% 0.15 85); flex-shrink: 0;"></span>
            <div>
                <strong style="color: oklch(75% 0.15 85);">SMTP configurado</strong>
                <span style="font-size: 13px; color: oklch(60% 0.012 250); margin-left: 8px;">
                    De <strong style="color: var(--fg);"><?= e($smtpFrom ?: $smtpUser ?: '—') ?></strong>
                    → Para <strong style="color: var(--fg);"><?= e($settings['contact_recipient'] ?: '—') ?></strong>
                </span>
            </div>
        </div>
        <?php else: ?>
        <div style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: oklch(30% 0.02 260 / 0.2); border: 1px solid oklch(40% 0.02 260 / 0.3); border-radius: 8px; margin-bottom: 20px;">
            <span style="width: 12px; height: 12px; border-radius: 50%; background: oklch(50% 0.02 260); flex-shrink: 0;"></span>
            <div>
                <strong style="color: oklch(60% 0.012 250);">SMTP não configurado</strong>
                <span style="font-size: 13px; color: oklch(50% 0.02 260); margin-left: 8px;">Os e-mails do formulário de orçamento não serão enviados até configurar.</span>
            </div>
        </div>
        <?php endif; ?>
        <form method="POST" id="smtp-form">
            <?= csrfField() ?>
            <div id="smtp-fields" style="<?= $smtpConfigured ? 'opacity:0.5;pointer-events:none;' : '' ?>">
            <div class="form-row" style="display: flex; gap: 16px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 2; min-width: 200px;">
                    <label for="smtp_host">Servidor *</label>
                    <input type="text" id="smtp_host" name="smtp_host" value="<?= e($smtpHost) ?>" required placeholder="smtp.gmail.com" <?= $smtpConfigured ? 'disabled' : '' ?>>
                </div>
                <div class="form-group" style="flex: 1; min-width: 100px;">
                    <label for="smtp_port">Porta *</label>
                    <input type="text" id="smtp_port" name="smtp_port" value="<?= e($smtpPort) ?>" required placeholder="587" <?= $smtpConfigured ? 'disabled' : '' ?>>
                </div>
            </div>
            <div class="form-row" style="display: flex; gap: 16px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="smtp_user">Usuário</label>
                    <input type="text" id="smtp_user" name="smtp_user" value="<?= e($smtpUser) ?>" placeholder="seu@email.com" autocomplete="off" <?= $smtpConfigured ? 'disabled' : '' ?>>
                </div>
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="smtp_pass">Senha</label>
                    <input type="password" id="smtp_pass" name="smtp_pass" placeholder="<?= $smtpConfigured ? '•••••• (deixe vazio para manter)' : 'Senha do SMTP' ?>" autocomplete="new-password" <?= $smtpConfigured ? 'disabled' : '' ?>>
                </div>
            </div>
            <div class="form-row" style="display: flex; gap: 16px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="smtp_from">E-mail remetente</label>
                    <input type="email" id="smtp_from" name="smtp_from" value="<?= e($smtpFrom) ?>" placeholder="noreply@seudominio.com" <?= $smtpConfigured ? 'disabled' : '' ?>>
                </div>
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="smtp_from_name">Nome do remetente</label>
                    <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?= e($smtpFromName) ?>" placeholder="Jogatinando CMS" <?= $smtpConfigured ? 'disabled' : '' ?>>
                </div>
            </div>
            <hr style="border: none; border-top: 1px solid oklch(22% 0.025 260); margin: 20px 0;">
            <div class="form-group">
                <label for="contact_recipient">Email destinatário (formulário de orçamento)</label>
                <input type="email" id="contact_recipient" name="contact_recipient" value="<?= e($settings['contact_recipient'] ?? '') ?>" placeholder="para quem enviar os orçamentos" style="max-width: 400px;" <?= $smtpConfigured ? 'disabled' : '' ?>>
                <p style="font-size: 12px; color: oklch(60% 0.012 250); margin-top: 4px;">Este email receberá os pedidos de orçamento enviados pelo formulário de contato do site.</p>
            </div>
            </div><!-- /smtp-fields -->
            <div class="form-actions" id="smtp-actions" style="<?= $smtpConfigured ? 'display:none' : '' ?>">
                <button type="submit" class="btn btn-gold" name="action" value="test_smtp">Testar e Salvar</button>
            </div>
            <?php if ($smtpConfigured): ?>
            <div class="form-actions" id="smtp-locked-actions">
                <button type="button" class="btn btn-gold" onclick="unlockSmtp()">✏️ Editar Configurações</button>
            </div>
            <div class="form-actions" id="smtp-edit-actions" style="display:none">
                <button type="submit" class="btn btn-gold" name="action" value="test_smtp">💾 Salvar</button>
                <button type="button" class="btn btn-outline" onclick="relockSmtp()">Cancelar</button>
            </div>
            <?php endif; ?>
        </form>

        <hr style="border: none; border-top: 1px solid oklch(22% 0.025 260); margin: 20px 0;">
        <form method="POST" id="noreply-form">
            <?= csrfField() ?>
            <h4 style="font-size:14px;font-weight:700;color:var(--fg);margin:0 0 4px;">Notificações Automáticas</h4>
            <p style="font-size:12px;color:oklch(60% 0.012 250);margin:0 0 12px;">Usado para verificação de email e outros avisos do sistema.</p>
            <div class="form-row" style="display: flex; gap: 16px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="noreply_email">E-mail noreply</label>
                    <input type="email" id="noreply_email" name="noreply_email" value="<?= e(getSetting('noreply_email', '')) ?>" placeholder="noreply@seudominio.com">
                </div>
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="noreply_name">Nome do remetente</label>
                    <input type="text" id="noreply_name" name="noreply_name" value="<?= e(getSetting('noreply_name', '')) ?>" placeholder="No Reply">
                </div>
            </div>
            <div class="form-actions" style="margin-top: 12px;">
                <button type="submit" class="btn btn-gold btn-sm" name="action" value="save_noreply">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function unlockSmtp() {
    var fields = document.getElementById('smtp-fields');
    fields.style.opacity = '1';
    fields.style.pointerEvents = '';
    fields.querySelectorAll('input').forEach(function(el) {
        el.disabled = false;
    });
    document.getElementById('smtp-locked-actions').style.display = 'none';
    document.getElementById('smtp-edit-actions').style.display = '';
}

function relockSmtp() {
    var fields = document.getElementById('smtp-fields');
    fields.style.opacity = '0.5';
    fields.style.pointerEvents = 'none';
    fields.querySelectorAll('input').forEach(function(el) {
        el.disabled = true;
    });
    document.getElementById('smtp-edit-actions').style.display = 'none';
    document.getElementById('smtp-locked-actions').style.display = '';
    document.getElementById('smtp_pass').value = '';
}
</script>

<!-- Migração SQLite → MySQL -->
<?php if (DB_TYPE === 'sqlite'): ?>
<div class="card" style="margin-top: 24px; border-color: oklch(68% 0.16 220 / 0.3);">
    <div class="card-header">
        <h2 class="card-title">Migrar para MySQL</h2>
    </div>
    <div class="card-body">
        <?php
        $migrateMessage = '';
        $migrateSuccess = false;
        $migrateRedirect = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'migrate') {
            if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
                $migrateMessage = '<div class="status error">Token inválido.</div>';
            } else {
                try {
                    $host = $_POST['db_host'] ?? '127.0.0.1';
                    $port = $_POST['db_port'] ?? '3306';
                    $name = $_POST['db_name'] ?? 'cms_db';
                    $user = $_POST['db_user'] ?? 'root';
                    $pass = $_POST['db_pass'] ?? '';

                    // === FASE 1: Ler SQLite antes de tocar no MySQL ===
                    $sqlite = getDB();
                    $migrateTables = ['users', 'banners', 'games', 'blog_posts', 'testimonials', 'faq_items', 'team_members', 'site_settings'];
                    $tableData = [];
                    foreach ($migrateTables as $table) {
                        $rows = $sqlite->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                        $tableData[$table] = $rows;
                    }

                    $adminUser = trim($_POST['admin_user'] ?? '');
                    $adminPass = $_POST['admin_pass'] ?? '';
                    if ($adminUser === '' || $adminPass === '') {
                        throw new Exception('Defina o usuário e senha do administrador para continuar.');
                    }
                    $adminHash = password_hash($adminPass, PASSWORD_DEFAULT);

                    // === FASE 2: Conectar MySQL sem criar DB ainda ===
                    $dsnNoDb = "mysql:host=$host;port=$port;charset=utf8mb4";
                    $pdo = new PDO($dsnNoDb, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = " . $pdo->quote($name));
                    $dbExistedAntes = (bool)$stmt->fetchColumn();

                    if (!$dbExistedAntes) {
                        $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    }
                    $pdo = null;

                    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
                    $mysql = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    ]);

                    // === FASE 3: Migração + cópia ===
                    try {
                        require_once ROOT_PATH . '/includes/migrations.php';
                        dbMigrate($mysql, 'mysql');

                        $ceoRoleId = $mysql->query("SELECT id FROM roles WHERE name = 'CEO Administrador'")->fetchColumn();

                        $mysql->beginTransaction();
                        try {
                            $mysql->exec("SET FOREIGN_KEY_CHECKS = 0");
                            foreach ($migrateTables as $table) {
                                $mysql->exec("DELETE FROM `$table`");
                            }

                            foreach ($tableData as $table => $rows) {
                                if (empty($rows)) continue;
                                $columns = array_keys($rows[0]);
                                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                                $cols = implode(', ', array_map(fn($c) => "`$c`", $columns));
                                $stmt = $mysql->prepare("INSERT INTO `$table` ($cols) VALUES ($placeholders)");
                                foreach ($rows as $row) {
                                    $stmt->execute(array_values($row));
                                }
                            }

                            $stmtUpd = $mysql->prepare("UPDATE users SET username = ?, password_hash = ?, role_id = ? WHERE id = 1");
                            $stmtUpd->execute([$adminUser, $adminHash, $ceoRoleId]);

                            $mysql->exec("SET FOREIGN_KEY_CHECKS = 1");
                            $mysql->commit();
                        } catch (Exception $e) {
                            $mysql->rollBack();
                            throw $e;
                        }
                    } catch (Exception $e) {
                        // Limpeza: se CRIAMOS o DB, dropamos ele
                        if (!$dbExistedAntes) {
                            $cleanup = new PDO($dsnNoDb, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                            $cleanup->exec("DROP DATABASE IF EXISTS `$name`");
                            $cleanup = null;
                        }
                        throw $e;
                    }

                    // Write config.local.php
                    $localConfig = '<?php' . "\n\n";
                    $localConfig .= "if (!defined('CMS_INSTALL_VERSION')) define('CMS_INSTALL_VERSION', '" . CMS_VERSION . "');\n";
                    $localConfig .= "if (!defined('DB_TYPE')) {\n";
                    $localConfig .= "    define('DB_TYPE', 'mysql');\n";
                    $localConfig .= "    define('DB_HOST', '$host');\n";
                    $localConfig .= "    define('DB_PORT', '$port');\n";
                    $localConfig .= "    define('DB_NAME', '$name');\n";
                    $localConfig .= "    define('DB_USER', '$user');\n";
                    $localConfig .= "    define('DB_PASS', '$pass');\n";
                    $localConfig .= "}\n\n";

                    $configPath = DATA_PATH . '/config.local.php';
                    $existingContent = file_exists($configPath) ? file_get_contents($configPath) : '';
                    if ($existingContent !== '' && preg_match_all('/define\(\'(SMTP_\w+)\',\s*\'(.*?)\'\);/', $existingContent, $m)) {
                        $localConfig .= "if (!defined('SMTP_PASS')) {\n";
                        foreach ($m[1] as $i => $const) {
                            $localConfig .= "    define('$const', '" . addslashes($m[2][$i]) . "');\n";
                        }
                        $localConfig .= "}\n";
                    } elseif (file_exists(ROOT_PATH . '/config.local.php')) {
                        $rootContent = file_get_contents(ROOT_PATH . '/config.local.php');
                        if (preg_match_all('/define\(\'(SMTP_\w+)\',\s*\'(.*?)\'\);/', $rootContent, $m)) {
                            $localConfig .= "if (!defined('SMTP_PASS')) {\n";
                            foreach ($m[1] as $i => $const) {
                                $localConfig .= "    define('$const', '" . addslashes($m[2][$i]) . "');\n";
                            }
                            $localConfig .= "}\n";
                        }
                    }
                    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
                    file_put_contents($configPath, $localConfig);

                    $migrateMessage = '<div class="status success">Migração concluída! Redirecionando para o login…</div>';
                    $migrateSuccess = true;
                    $migrateRedirect = true;
                    session_destroy();
                } catch (Exception $ex) {
                    $migrateMessage = '<div class="status error">' . e($ex->getMessage()) . '</div>';
                }
            }
        }
        echo $migrateMessage;
        ?>
        <?php if (!$migrateSuccess): ?>
        <p style="margin-bottom: 16px; color: oklch(68% 0.16 220);">
            Seus dados atuais (SQLite) serão copiados para um banco MySQL. O arquivo SQLite original será preservado.
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="migrate">
            <?= csrfField() ?>
            <div class="form-row">
                <div class="form-group"><label for="db_host">Host</label><input type="text" id="db_host" name="db_host" value="<?= e(DB_HOST) ?>"></div>
                <div class="form-group"><label for="db_port">Porta</label><input type="text" id="db_port" name="db_port" value="<?= e(DB_PORT) ?>"></div>
            </div>
            <div class="form-group"><label for="db_name">Database</label><input type="text" id="db_name" name="db_name" value="<?= e(DB_NAME) ?>"></div>
            <div class="form-row">
                <div class="form-group"><label for="db_user">Usuário MySQL</label><input type="text" id="db_user" name="db_user" value="<?= e(DB_USER) ?>"></div>
                <div class="form-group"><label for="db_pass">Senha MySQL</label><input type="password" id="db_pass" name="db_pass" value="<?= e(DB_PASS) ?>"></div>
            </div>

            <h3 class="form-section-title" style="margin-top: 24px;">Administrador</h3>
            <p style="color: oklch(60% 0.012 250); font-size: 13px; margin-bottom: 16px;">Defina o novo login e senha do painel admin para o MySQL.</p>
            <div class="form-row">
                <div class="form-group"><label for="admin_user">* Usuário Admin</label><input type="text" id="admin_user" name="admin_user" placeholder="admin" required></div>
                <div class="form-group"><label for="admin_pass">* Senha Admin</label><input type="password" id="admin_pass" name="admin_pass" placeholder="Nova senha" required></div>
            </div>

            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, oklch(68% 0.16 220), oklch(55% 0.14 220)); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer;">Migrar para MySQL</button>
        </form>
        <?php endif; ?>
        <?php if ($migrateRedirect): ?>
        <script>setTimeout(function(){window.location.href='/admin/login'},2500);</script>
        <?php endif; ?>
    </div>
</div>
<?php endif; // DB_TYPE === 'sqlite' ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
