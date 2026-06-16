<?php
ob_start();
$pageTitle = 'Configurações';
$requiredPerm = 'perm_settings';
require_once __DIR__ . '/../includes/header.php';

$userId = $_SESSION['admin_user_id'] ?? 0;
$db = getDB();

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
        'contact_email' => trim($_POST['contact_email']),
        'contact_whatsapp' => trim($_POST['contact_whatsapp']),
        'footer_description' => trim($_POST['footer_description']),
        'maintenance_mode' => $_POST['maintenance_mode'] ?? '0',
    ];

    foreach ($settings as $key => $value) {
        setSetting($key, $value);
    }

    // Sync .maintenance marker file with DB flag
    $maintenanceFiles = [DATA_PATH . '/.maintenance'];
    $parentFile = dirname(ROOT_PATH) . '/.maintenance';
    if (is_writable(dirname(ROOT_PATH)) || !file_exists($parentFile)) {
        $maintenanceFiles[] = $parentFile;
    }
    foreach ($maintenanceFiles as $f) {
        if ($settings['maintenance_mode'] === '1') @touch($f);
        else @unlink($f);
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

// Identity: logo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_logo') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit; }
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $url = resizeAndSaveLogo($_FILES['logo']['tmp_name']);
        if ($url) {
            flashMessage('success', 'Logo atualizada!');
        } else {
            flashMessage('error', 'Falha ao processar a logo. Envie uma imagem PNG, JPG, GIF ou WebP.');
        }
    } else {
        flashMessage('error', 'Nenhum arquivo enviado.');
    }
    ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit;
}

// Identity: remove logo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_logo') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit; }
    setSetting('site_logo_url', '');
    $logoPath = UPLOAD_PATH . '/site/logo.png';
    if (file_exists($logoPath)) @unlink($logoPath);
    flashMessage('success', 'Logo removida.');
    ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit;
}

// Identity: favicon upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_favicon') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit; }
    if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
        $urls = generateFavicons($_FILES['favicon']['tmp_name']);
        if ($urls) {
            flashMessage('success', 'Favicons geradas!');
        } else {
            flashMessage('error', 'Falha ao processar o favicon.');
        }
    } else {
        flashMessage('error', 'Nenhum arquivo enviado.');
    }
    ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit;
}

// Identity: remove favicon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_favicon') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/settings'); exit; }
    setSetting('site_favicon_url', '');
    foreach ([16, 32, 180] as $s) {
        $p = UPLOAD_PATH . "/site/favicon-{$s}.png";
        if (file_exists($p)) @unlink($p);
    }
    flashMessage('success', 'Favicon removido.');
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
    $localConfig .= "    define('SMTP_HOST', " . var_export($smtpHost, true) . ");\n";
    $localConfig .= "    define('SMTP_PORT', " . var_export($smtpPort, true) . ");\n";
    $localConfig .= "    define('SMTP_USER', " . var_export($smtpUser, true) . ");\n";
    $localConfig .= "    define('SMTP_PASS', " . var_export($smtpPass, true) . ");\n";
    $localConfig .= "    define('SMTP_FROM', " . var_export($smtpFrom, true) . ");\n";
    $localConfig .= "    define('SMTP_FROM_NAME', " . var_export($smtpFromName, true) . ");\n";
    $localConfig .= "}\n";

    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
    file_put_contents($configPath, $localConfig);
    $persistentDir = dirname(ROOT_PATH);
    file_put_contents($persistentDir . '/config.local.php', $localConfig);
    setSetting('contact_recipient', $contactRecipient);
}

$settings = [];
    $keys = ['site_name', 'site_tagline', 'contact_email', 'contact_whatsapp', 'footer_description', 'contact_recipient', 'maintenance_mode'];
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
$stmt = $db->prepare("SELECT u.username, u.avatar_url, r.name as role_name, l.name as level_name FROM users u LEFT JOIN roles r ON u.role_id = r.id LEFT JOIN levels l ON r.level_id = l.id WHERE u.id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
$profileInitial = strtoupper(substr($userData['username'] ?? 'A', 0, 1));
?>

<!-- Meu Perfil -->
<div class="card settings-profile-card">
    <div class="card-header">
        <h2 class="card-title">Meu Perfil</h2>
    </div>
    <div class="card-body">
        <div class="profile-layout">
            <div>
                <div class="profile-avatar">
                    <?php if ($userData && $userData['avatar_url']): ?>
                        <img src="<?= e($userData['avatar_url']) ?>" alt="">
                    <?php else: ?>
                        <?= $profileInitial ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="profile-content">
                <p class="settings-profile-title"><strong><?= e($userData['username'] ?? '') ?></strong>
                    <?php if (isset($userData['role_name'])): ?>
                    <span class="badge badge-featured"><?= e($userData['level_name'] ?? $userData['role_name'] ?? '') ?></span>
                    <?php endif; ?>
                </p>
                <p class="field-hint">Seu perfil de acesso ao painel administrativo.</p>
                <div class="settings-action-row">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save_avatar">
                        <?= csrfField() ?>
                        <label for="avatar-upload" class="btn btn-outline btn-sm">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Upload
                        </label>
                        <input type="file" id="avatar-upload" name="avatar" accept="image/png,image/jpeg,image/gif,image/webp" class="sr-only-file" onchange="this.form.submit()">
                    </form>
                    <?php if ($userData && $userData['avatar_url']): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="remove_avatar">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn-danger-outline btn-sm" onclick="return confirm('Remover foto de perfil?')">Remover</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="settings-action-row settings-password-toggle">
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('passwordForm').classList.toggle('hidden'); this.classList.toggle('hidden')">Alterar Senha</button>
            <form id="passwordForm" method="POST" class="hidden">
                <input type="hidden" name="action" value="change_password">
                <?= csrfField() ?>
                <div class="form-row">
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
                </div>
                <button type="submit" class="btn btn-gold btn-sm">Salvar Nova Senha</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Configurações do Site</h2>
        <div>
            <span class="settings-db-badge <?= DB_TYPE === 'mysql' ? 'mysql' : 'sqlite' ?>">
                <span class="settings-db-dot"></span>
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
            <div class="toggle-group">
                <input type="hidden" name="maintenance_mode" value="0">
                <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                <label for="maintenance_mode">Ativar modo de manutenção</label>
                <?php if (file_exists(DATA_PATH . '/.maintenance') || file_exists(dirname(ROOT_PATH) . '/.maintenance')): ?>
                    <span class="badge badge-active">arquivo .maintenance presente</span>
                <?php endif; ?>
            </div>
            <div class="field-hint">Quando ativo, visitantes veem uma página de "Em Manutenção". Administradores logados continuam acessando normalmente.</div>
        </div>

        <h3 class="form-section-title">Hero / Banner Principal</h3>
        <div class="settings-section-subtitle">
            O título e subtítulo do Hero são definidos <strong>individualmente em cada banner</strong> na seção
            <a href="/admin/banners">Banners</a>.
            Quando não há banners cadastrados, mensagens padrão são exibidas automaticamente.
        </div>

        <h3 class="form-section-title">Contato</h3>
        <div class="form-row">
            <div class="form-group"><label for="contact_email">Email de Contato</label><input type="email" id="contact_email" name="contact_email" value="<?= e($settings['contact_email']) ?>"></div>
            <div class="form-group"><label for="contact_whatsapp">WhatsApp (número com DDD)</label><input type="text" id="contact_whatsapp" name="contact_whatsapp" value="<?= e($settings['contact_whatsapp']) ?>" placeholder="5511999999999"></div>
        </div>

        <h3 class="form-section-title">Redes Sociais</h3>
        <div class="settings-section-subtitle compact">
            Os links sociais agora são gerenciados em <a href="/admin/social-links">Redes Sociais</a>, com presets dinâmicos e ordenação própria.
        </div>

        <h3 class="form-section-title">Footer</h3>
        <div class="form-group"><label for="footer_description">Descrição do Footer</label><textarea id="footer_description" name="footer_description" rows="3"><?= e($settings['footer_description']) ?></textarea></div>

        <div class="form-actions">
            <button type="submit" class="btn btn-gold">Salvar Configurações</button>
        </div>
    </form>
    </div>
</div>

<!-- Identidade Visual -->
<?php
$currentLogoUrl = siteLogoUrl();
$currentFaviconUrl = siteFaviconUrl();
$hasCustomLogo = getSetting('site_logo_url', '') !== '';
$hasCustomFavicon = getSetting('site_favicon_url', '') !== '';
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <svg class="settings-card-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            Identidade Visual
        </h2>
    </div>
    <div class="card-body">
        <div class="identity-layout">
            <div class="form-group">
                <label for="logo-upload">Logo do Site</label>
                <div class="field-hint">Imagem quadrada, redimensionada para 256×256 automaticamente.</div>
                <div class="settings-preview logo">
                    <img src="<?= e($currentLogoUrl) ?>" alt="Logo">
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save_logo">
                    <div class="settings-upload-actions">
                        <label for="logo-upload" class="btn btn-outline btn-sm">
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Upload Logo
                        </label>
                        <input type="file" id="logo-upload" name="logo" accept="image/png,image/jpeg,image/gif,image/webp" class="sr-only-file" onchange="this.form.submit()">
                        <?php if ($hasCustomLogo): ?>
                        <button type="submit" class="btn btn-danger-outline btn-sm" formaction="" formmethod="POST" name="action" value="remove_logo" onclick="return confirm('Remover logo?')">Remover</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="form-group">
                <label for="favicon-upload">Favicon</label>
                <div class="field-hint">Gera automaticamente versões 16×16, 32×32 e 180×180.</div>
                <div class="settings-preview favicon">
                    <img src="<?= e($currentFaviconUrl) ?>" alt="Favicon">
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save_favicon">
                    <div class="settings-upload-actions">
                        <label for="favicon-upload" class="btn btn-outline btn-sm">
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Upload Favicon
                        </label>
                        <input type="file" id="favicon-upload" name="favicon" accept="image/png,image/jpeg,image/gif,image/webp" class="sr-only-file" onchange="this.form.submit()">
                        <?php if ($hasCustomFavicon): ?>
                        <button type="submit" class="btn btn-danger-outline btn-sm" formaction="" formmethod="POST" name="action" value="remove_favicon" onclick="return confirm('Remover favicon?')">Remover</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Configurações SMTP -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <svg class="settings-card-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 6L12 13L2 6M22 6v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6l10 7l10-7z"/></svg>
            Configurações SMTP / E-mail
        </h2>
    </div>
    <div class="card-body">
        <?php if ($smtpConfigured): ?>
        <div class="settings-status-card gold">
            <span class="settings-status-dot gold"></span>
            <div>
                <strong>SMTP configurado</strong>
                <span class="field-hint">
                    De <strong><?= e($smtpFrom ?: $smtpUser ?: '—') ?></strong>
                    → Para <strong><?= e($settings['contact_recipient'] ?: '—') ?></strong>
                </span>
            </div>
        </div>
        <?php else: ?>
        <div class="settings-status-card muted">
            <span class="settings-status-dot muted"></span>
            <div>
                <strong>SMTP não configurado</strong>
                <span class="field-hint">Os e-mails do formulário de orçamento não serão enviados até configurar.</span>
            </div>
        </div>
        <?php endif; ?>
        <form method="POST" id="smtp-form">
            <?= csrfField() ?>
            <div id="smtp-fields" class="<?= $smtpConfigured ? 'settings-locked-fields' : '' ?>">
            <div class="form-row">
                <div class="form-group">
                    <label for="smtp_host">Servidor *</label>
                    <input type="text" id="smtp_host" name="smtp_host" value="<?= e($smtpHost) ?>" required placeholder="smtp.gmail.com" <?= $smtpConfigured ? 'disabled' : '' ?>>
                </div>
                <div class="form-group">
                    <label for="smtp_port">Porta *</label>
                    <input type="text" id="smtp_port" name="smtp_port" value="<?= e($smtpPort) ?>" required placeholder="587" <?= $smtpConfigured ? 'disabled' : '' ?>>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="smtp_user">Usuário</label>
                    <input type="text" id="smtp_user" name="smtp_user" value="<?= e($smtpUser) ?>" placeholder="seu@email.com" autocomplete="off" <?= $smtpConfigured ? 'disabled' : '' ?>>
                </div>
                <div class="form-group">
                    <label for="smtp_pass">Senha</label>
                    <input type="password" id="smtp_pass" name="smtp_pass" placeholder="<?= $smtpConfigured ? '•••••• (deixe vazio para manter)' : 'Senha do SMTP' ?>" autocomplete="new-password" <?= $smtpConfigured ? 'disabled' : '' ?>>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="smtp_from">E-mail remetente</label>
                    <input type="email" id="smtp_from" name="smtp_from" value="<?= e($smtpFrom) ?>" placeholder="noreply@seudominio.com" <?= $smtpConfigured ? 'disabled' : '' ?>>
                </div>
                <div class="form-group">
                    <label for="smtp_from_name">Nome do remetente</label>
                    <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?= e($smtpFromName) ?>" placeholder="Jogatinando CMS" <?= $smtpConfigured ? 'disabled' : '' ?>>
                </div>
            </div>
            <hr>
            <div class="form-group">
                <label for="contact_recipient">Email destinatário (formulário de orçamento)</label>
                <input type="email" id="contact_recipient" name="contact_recipient" value="<?= e($settings['contact_recipient'] ?? '') ?>" placeholder="para quem enviar os orçamentos" <?= $smtpConfigured ? 'disabled' : '' ?>>
                <div class="field-hint">Este email receberá os pedidos de orçamento enviados pelo formulário de contato do site.</div>
            </div>
            </div><!-- /smtp-fields -->
            <div class="form-actions <?= $smtpConfigured ? 'hidden' : '' ?>" id="smtp-actions">
                <button type="submit" class="btn btn-gold" name="action" value="test_smtp">Testar e Salvar</button>
            </div>
            <?php if ($smtpConfigured): ?>
            <div class="form-actions" id="smtp-locked-actions">
                <button type="button" class="btn btn-gold" onclick="unlockSmtp()">✏️ Editar Configurações</button>
            </div>
            <div class="form-actions hidden" id="smtp-edit-actions">
                <button type="submit" class="btn btn-gold" name="action" value="test_smtp">💾 Salvar</button>
                <button type="button" class="btn btn-outline" onclick="relockSmtp()">Cancelar</button>
            </div>
            <?php endif; ?>
        </form>

        <hr>
        <form method="POST" id="noreply-form">
            <?= csrfField() ?>
            <h3 class="form-section-title">Notificações Automáticas</h3>
            <div class="field-hint">Usado para verificação de email e outros avisos do sistema.</div>
            <div class="form-row">
                <div class="form-group">
                    <label for="noreply_email">E-mail noreply</label>
                    <input type="email" id="noreply_email" name="noreply_email" value="<?= e(getSetting('noreply_email', '')) ?>" placeholder="noreply@seudominio.com">
                </div>
                <div class="form-group">
                    <label for="noreply_name">Nome do remetente</label>
                    <input type="text" id="noreply_name" name="noreply_name" value="<?= e(getSetting('noreply_name', '')) ?>" placeholder="No Reply">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-gold btn-sm" name="action" value="save_noreply">Salvar</button>
            </div>
        </form>
    </div>
</div>


<script>
function unlockSmtp() {
    var fields = document.getElementById('smtp-fields');
    fields.classList.remove('settings-locked-fields');
    fields.querySelectorAll('input').forEach(function(el) {
        el.disabled = false;
    });
    document.getElementById('smtp-locked-actions').classList.add('hidden');
    document.getElementById('smtp-edit-actions').classList.remove('hidden');
}

function relockSmtp() {
    var fields = document.getElementById('smtp-fields');
    fields.classList.add('settings-locked-fields');
    fields.querySelectorAll('input').forEach(function(el) {
        el.disabled = true;
    });
    document.getElementById('smtp-edit-actions').classList.add('hidden');
    document.getElementById('smtp-locked-actions').classList.remove('hidden');
    document.getElementById('smtp_pass').value = '';
}
</script>

<script>
function unlockS3() {
    var fields = document.getElementById('s3-fields');
    fields.classList.remove('settings-locked-fields');
    fields.querySelectorAll('input, select').forEach(function(el) {
        el.disabled = false;
    });
    document.getElementById('s3-locked-actions').classList.add('hidden');
    document.getElementById('s3-edit-actions').classList.remove('hidden');
    document.getElementById('s3-bucket-display').classList.add('hidden');
    document.getElementById('s3-bucket-select-container').classList.remove('hidden');
}
function relockS3() {
    var fields = document.getElementById('s3-fields');
    fields.classList.add('settings-locked-fields');
    fields.querySelectorAll('input, select').forEach(function(el) {
        el.disabled = true;
    });
    document.getElementById('s3-edit-actions').classList.add('hidden');
    document.getElementById('s3-locked-actions').classList.remove('hidden');
    document.getElementById('s3_secret_key').value = '';
    document.getElementById('s3-bucket-select-container').classList.add('hidden');
    document.getElementById('s3-bucket-display').classList.remove('hidden');
}

var s3ErrorTimer = null;

function showS3Error(msg) {
    var el = document.getElementById('s3-bucket-error');
    el.textContent = msg;
    el.classList.remove('hidden');
    if (s3ErrorTimer) clearTimeout(s3ErrorTimer);
    s3ErrorTimer = setTimeout(function() { el.classList.add('hidden'); }, 8000);
}

function fetchS3Buckets() {
    var endpoint = document.getElementById('s3_endpoint').value.trim();
    var accessKey = document.getElementById('s3_access_key').value.trim();
    var secretKey = document.getElementById('s3_secret_key').value.trim();
    if (!endpoint || !accessKey || !secretKey) {
        showS3Error('Preencha Endpoint, Access Key e Secret Key primeiro.');
        return;
    }
    var btn = document.getElementById('btn-fetch-s3-buckets');
    btn.disabled = true;
    btn.textContent = '⏳ Buscando...';
    var csrf = document.querySelector('#s3-form input[name="csrf_token"]').value;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        btn.disabled = false;
        btn.textContent = '🔍 Buscar Buckets';
        try {
            var data = JSON.parse(xhr.responseText);
            console.log('S3 buckets response:', data);
            if (!data.success) {
                showS3Error(data.message || 'Falha na requisição');
                return;
            }
            var sel = document.getElementById('s3_bucket_select');
            sel.innerHTML = '';
            if (!data.buckets || data.buckets.length === 0) {
                sel.innerHTML = '<option value="">Nenhum bucket encontrado.</option>';
                document.getElementById('s3-bucket-select-container').classList.remove('hidden');
                document.getElementById('s3-bucket-display').classList.add('hidden');
                return;
            }
            var html = '<option value="">— Selecione um bucket —</option>';
            for (var i = 0; i < data.buckets.length; i++) {
                var b = data.buckets[i];
                html += '<option value="' + b.name + '">' + b.name + '</option>';
            }
            sel.innerHTML = html;
            sel.onchange = function() { onS3BucketSelect(this); };
            document.getElementById('s3-bucket-select-container').classList.remove('hidden');
            document.getElementById('s3-bucket-display').classList.add('hidden');
        } catch (e) {
            showS3Error('Erro ao processar resposta: ' + e.message);
        }
    };
    xhr.onerror = function() {
        btn.disabled = false;
        btn.textContent = '🔍 Buscar Buckets';
        showS3Error('Erro de rede ao buscar buckets.');
    };
    xhr.send('action=fetch_s3_buckets&endpoint=' + encodeURIComponent(endpoint) + '&access_key=' + encodeURIComponent(accessKey) + '&secret_key=' + encodeURIComponent(secretKey) + '&region=auto&csrf_token=' + encodeURIComponent(csrf));
}

function onS3BucketSelect(sel) {
    var val = sel.value;
    document.getElementById('s3_bucket').value = val;
    document.getElementById('s3-bucket-select-container').classList.add('hidden');
    document.getElementById('s3-bucket-display').classList.remove('hidden');
    document.getElementById('s3-bucket-display').querySelector('div').textContent = val || 'Nenhum bucket selecionado';
}
</script>

<!-- Migração SQLite → MySQL -->
<?php if (DB_TYPE === 'sqlite'): ?>
<div class="card">
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
                    $name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['db_name'] ?? 'cms_db');
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
                    $localConfig .= "if (!defined('CMS_INSTALL_VERSION')) define('CMS_INSTALL_VERSION', " . var_export(CMS_VERSION, true) . ");\n";
                    $localConfig .= "if (!defined('DB_TYPE')) {\n";
                    $localConfig .= "    define('DB_TYPE', 'mysql');\n";
                    $localConfig .= "    define('DB_HOST', " . var_export($host, true) . ");\n";
                    $localConfig .= "    define('DB_PORT', " . var_export($port, true) . ");\n";
                    $localConfig .= "    define('DB_NAME', " . var_export($name, true) . ");\n";
                    $localConfig .= "    define('DB_USER', " . var_export($user, true) . ");\n";
                    $localConfig .= "    define('DB_PASS', " . var_export($pass, true) . ");\n";
                    $localConfig .= "}\n\n";

                    $configPath = DATA_PATH . '/config.local.php';
                    $existingContent = file_exists($configPath) ? file_get_contents($configPath) : '';
                    if ($existingContent !== '' && preg_match_all('/define\(\'(SMTP_\w+)\',\s*\'(.*?)\'\);/', $existingContent, $m)) {
                        $localConfig .= "if (!defined('SMTP_PASS')) {\n";
                        foreach ($m[1] as $i => $const) {
                            $localConfig .= "    define('$const', " . var_export($m[2][$i], true) . ");\n";
                        }
                        $localConfig .= "}\n";
                    } elseif (file_exists(ROOT_PATH . '/config.local.php')) {
                        $rootContent = file_get_contents(ROOT_PATH . '/config.local.php');
                        if (preg_match_all('/define\(\'(SMTP_\w+)\',\s*\'(.*?)\'\);/', $rootContent, $m)) {
                            $localConfig .= "if (!defined('SMTP_PASS')) {\n";
                            foreach ($m[1] as $i => $const) {
                                $localConfig .= "    define('$const', " . var_export($m[2][$i], true) . ");\n";
                            }
                            $localConfig .= "}\n";
                        }
                    }
                    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
                    file_put_contents($configPath, $localConfig);
                    $persistentDir = dirname(ROOT_PATH);
                    file_put_contents($persistentDir . '/config.local.php', $localConfig);

                    $migrateMessage = '<div class="status success">Migração concluída! Redirecionando para o login…</div>';
                    $migrateSuccess = true;
                    $migrateRedirect = true;
                    clearSession();
                } catch (Exception $ex) {
                    $migrateMessage = '<div class="status error">' . e($ex->getMessage()) . '</div>';
                }
            }
        }
        echo $migrateMessage;
        ?>
        <?php if (!$migrateSuccess): ?>
        <div class="settings-migration-note">
            Seus dados atuais (SQLite) serão copiados para um banco MySQL. O arquivo SQLite original será preservado.
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="migrate">
            <?= csrfField() ?>
            <h3 class="form-section-title">Conexão MySQL</h3>
            <div class="form-row">
                <div class="form-group"><label for="db_host">Host</label><input type="text" id="db_host" name="db_host" value="<?= e(DB_HOST) ?>"></div>
                <div class="form-group"><label for="db_port">Porta</label><input type="text" id="db_port" name="db_port" value="<?= e(DB_PORT) ?>"></div>
            </div>
            <div class="form-group"><label for="db_name">Database</label><input type="text" id="db_name" name="db_name" value="<?= e(DB_NAME) ?>"></div>
            <div class="form-row">
                <div class="form-group"><label for="db_user">Usuário MySQL</label><input type="text" id="db_user" name="db_user" value="<?= e(DB_USER) ?>"></div>
                <div class="form-group"><label for="db_pass">Senha MySQL</label><input type="password" id="db_pass" name="db_pass" value="<?= e(DB_PASS) ?>"></div>
            </div>

            <h3 class="form-section-title">Administrador</h3>
            <div class="field-hint">Defina o novo login e senha do painel admin para o MySQL.</div>
            <div class="form-row">
                <div class="form-group"><label for="admin_user">Usuário Admin *</label><input type="text" id="admin_user" name="admin_user" placeholder="admin" required></div>
                <div class="form-group"><label for="admin_pass">Senha Admin *</label><input type="password" id="admin_pass" name="admin_pass" placeholder="Nova senha" required></div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-gold">Migrar para MySQL</button>
            </div>
        </form>
        <?php endif; ?>
        <?php if ($migrateRedirect): ?>
        <script>setTimeout(function(){window.location.href='/admin/login'},2500);</script>
        <?php endif; ?>
    </div>
</div>
<?php endif; // DB_TYPE === 'sqlite' ?>

<?php
// S3 save handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_s3') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Token inválido.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/settings');
        exit;
    }

    $s3AccessKey = trim($_POST['s3_access_key'] ?? '');
    $s3SecretKey = trim($_POST['s3_secret_key'] ?? '');
    $s3Endpoint  = trim($_POST['s3_endpoint'] ?? '');
    $s3Bucket    = trim($_POST['s3_bucket'] ?? '');
    $s3PublicUrl = trim($_POST['s3_public_url'] ?? '');

    if ($s3SecretKey === '' && Storage::isS3Configured()) {
        $cfg = S3::getResolvedConfig();
        $s3SecretKey = $cfg['secret_key'];
    }

    setSetting('s3_access_key', $s3AccessKey);
    setSetting('s3_secret_key', $s3SecretKey);
    setSetting('s3_endpoint', $s3Endpoint);
    setSetting('s3_region', 'auto');
    setSetting('s3_bucket', $s3Bucket);
    setSetting('s3_public_url', $s3PublicUrl);

    flashMessage('success', 'Configurações S3 (R2) salvas!');
    ob_end_clean();
    header('Location: ' . ADMIN_URL . '/settings');
    exit;
}
?>

<?php
// Fetch buckets handler (JSON response for AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_s3_buckets') {
    ob_end_clean();
    header('Content-Type: application/json');
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Token inválido.']);
        exit;
    }
    try {
        $endpoint  = trim($_POST['endpoint'] ?? '');
        $accessKey = trim($_POST['access_key'] ?? '');
        $secretKey = trim($_POST['secret_key'] ?? '');
        if ($endpoint === '' || $accessKey === '' || $secretKey === '') {
            throw new Exception('Endpoint, Access Key e Secret Key são obrigatórios.');
        }
        $buckets = S3::listBucketsWithCreds($endpoint, $accessKey, $secretKey, 'auto');
        if (empty($buckets)) {
            $body = S3::getLastResponseBody();
            $isDenied = $body && strpos($body, 'AccessDenied') !== false;
            if ($isDenied) {
                throw new Exception('Acesso negado. O token R2 precisa da permissão "Admin Read & Write" para listar buckets.');
            }
        }
        echo json_encode(['success' => true, 'buckets' => $buckets]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$s3AccessKey = getSetting('s3_access_key', '');
$s3Bucket    = getSetting('s3_bucket', '');
$s3PublicUrl = getSetting('s3_public_url', '');
$s3Configured = Storage::isS3Configured();
$s3Config = $s3Configured ? S3::getResolvedConfig() : [];
?>

<!-- Cloudflare R2 (S3-compatible) -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <svg class="settings-card-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2m4-4l4 4 4-4m-4-4v8"/></svg>
            Cloudflare R2 (S3)
        </h2>
    </div>
    <div class="card-body">
        <?php if ($s3Configured): ?>
        <div class="settings-status-card gold">
            <span class="settings-status-dot gold"></span>
            <div>
                <strong>S3 configurado</strong>
                <span class="field-hint">
                    Bucket: <strong><?= e($s3Config['bucket'] ?? '') ?></strong>
                    <?php if (!empty($s3Config['public_url'])): ?>
                    &middot; URL Pública: <strong><?= e($s3Config['public_url']) ?></strong>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <?php else: ?>
        <div class="settings-status-card muted">
            <span class="settings-status-dot muted"></span>
            <div>
                <strong>S3 não configurado</strong>
                <span class="field-hint">Preencha os campos abaixo para configurar Cloudflare R2.</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- S3 Credential Form -->
        <form method="POST" id="s3-form">
            <?= csrfField() ?>
            <div id="s3-fields" class="<?= $s3Configured ? 'settings-locked-fields' : '' ?>">
            <div class="form-row">
                <div class="form-group">
                    <label for="s3_endpoint">Endpoint *</label>
                    <input type="url" id="s3_endpoint" name="s3_endpoint" value="<?= e($s3Config['endpoint'] ?? '') ?>" required placeholder="https://&lt;accountid&gt;.r2.cloudflarestorage.com" <?= $s3Configured ? 'disabled' : '' ?>>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="s3_access_key">Access Key ID *</label>
                    <input type="text" id="s3_access_key" name="s3_access_key" value="<?= e($s3Config['access_key'] ?? '') ?>" required placeholder="&lt;access_key_id&gt;" <?= $s3Configured ? 'disabled' : '' ?>>
                </div>
                <div class="form-group">
                    <label for="s3_secret_key">Secret Access Key *</label>
                    <input type="password" id="s3_secret_key" name="s3_secret_key" placeholder="<?= $s3Configured ? '•••••• (deixe vazio para manter)' : '&lt;secret_access_key&gt;' ?>" autocomplete="new-password" <?= $s3Configured ? 'disabled' : '' ?>>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="s3_bucket_fetch">Buckets</label>
                    <div class="settings-action-row">
                        <button type="button" id="btn-fetch-s3-buckets" class="btn btn-outline" onclick="fetchS3Buckets()">🔍 Buscar Buckets</button>
                        <span id="s3-bucket-error" class="field-hint hidden"></span>
                    </div>
                </div>
            </div>
            <div id="s3-bucket-select-container" class="hidden">
                <div class="form-group">
                    <label for="s3_bucket_select">Bucket *</label>
                    <select id="s3_bucket_select">
                        <option value="">— Clique em "Buscar Buckets" primeiro —</option>
                    </select>
                </div>
            </div>
            <div id="s3-bucket-display" class="form-group <?= $s3Configured ? '' : 'hidden' ?>">
                <label>Bucket</label>
                <div class="settings-read-only">
                    <?= $s3Bucket ? e($s3Bucket) : '<span class="settings-muted-text">Selecione usando "Buscar Buckets"</span>' ?>
                </div>
                <input type="hidden" id="s3_bucket" name="s3_bucket" value="<?= e($s3Bucket) ?>">
            </div>
            <div class="form-group">
                <label for="s3_public_url">URL Pública <span class="settings-muted-text">(opcional)</span></label>
                <input type="url" id="s3_public_url" name="s3_public_url" value="<?= e($s3PublicUrl) ?>" placeholder="https://pub-xxxxx.r2.dev" <?= $s3Configured ? 'disabled' : '' ?>>
                <div class="field-hint">Se tiver um domínio customizado ou URL pública do R2, insira aqui. Sem isso, as URLs usarão o endpoint diretamente.</div>
            </div>
            </div><!-- /s3-fields -->

            <div class="form-actions <?= $s3Configured ? 'hidden' : '' ?>" id="s3-actions">
                <button type="submit" class="btn btn-gold" name="action" value="save_s3">Salvar Configurações S3</button>
            </div>
            <?php if ($s3Configured): ?>
            <div class="form-actions" id="s3-locked-actions">
                <button type="button" class="btn btn-gold" onclick="unlockS3()">✏️ Editar Configurações</button>
            </div>
            <div class="form-actions hidden" id="s3-edit-actions">
                <button type="submit" class="btn btn-gold" name="action" value="save_s3">💾 Salvar</button>
                <button type="button" class="btn btn-outline" onclick="relockS3()">Cancelar</button>
            </div>
            <?php endif; ?>
        </form>

        <?php if ($s3Configured): ?>
        <hr>
        <div class="settings-action-row">
            <form method="POST" action="/admin/bucket-sync">
                <button type="submit" class="btn btn-gold">⬆ Sincronizar Uploads com S3</button>
            </form>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="s3_test">
                <button type="submit" class="btn btn-outline">🔌 Testar Conexão</button>
            </form>
        </div>
        <?php endif; ?>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            if ($_POST['action'] === 's3_test') {
                if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
                    echo '<div class="status error">Token inválido.</div>';
                } elseif (!Storage::isS3Configured()) {
                    echo '<div class="status error">S3 não configurado.</div>';
                } else {
                    $ok = S3::isConfigured() && S3::listBuckets() !== [];
                    echo $ok
                        ? '<div class="status success">✅ Conexão S3 OK!</div>'
                        : '<div class="status error">❌ Falha na conexão. Verifique as credenciais.</div>';
                }
            }
        }
        ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
