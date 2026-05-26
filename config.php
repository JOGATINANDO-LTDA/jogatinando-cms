<?php

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Paths
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__FILE__));
if (!defined('DATA_PATH')) define('DATA_PATH', ROOT_PATH . '/data');
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', ROOT_PATH . '/uploads');
if (!defined('DB_PATH')) define('DB_PATH', DATA_PATH . '/jogatinando.db');

define('CMS_VERSION', '1.1.0');
define('LOCAL_CONFIG', DATA_PATH . '/config.local.php');

// Load local config from data/ first
if (file_exists(LOCAL_CONFIG)) {
    require_once LOCAL_CONFIG;
}

// Auto-migrate legacy root config.local.php → data/
$legacyConfig = ROOT_PATH . '/config.local.php';
if (file_exists($legacyConfig) && !file_exists(LOCAL_CONFIG)) {
    $content = file_get_contents($legacyConfig);
    if (!str_contains($content, 'CMS_INSTALL_VERSION')) {
        $content = preg_replace(
            '/^<\?php/',
            "<?php\ndefine('CMS_INSTALL_VERSION', '" . CMS_VERSION . "');",
            $content
        );
    }
    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
    file_put_contents(LOCAL_CONFIG, $content);
    unlink($legacyConfig);
    require_once LOCAL_CONFIG;
}

// Version mismatch — ask user before booting
if (defined('CMS_INSTALL_VERSION') && CMS_INSTALL_VERSION !== CMS_VERSION) {
    $vAction = $_POST['vaction'] ?? '';
    if ($vAction === 'keep') {
        $oldPath = LOCAL_CONFIG;
        $content = file_get_contents($oldPath);
        $content = preg_replace(
            "/define\('CMS_INSTALL_VERSION',\s*'[^']*'\);/",
            "define('CMS_INSTALL_VERSION', '" . CMS_VERSION . "');",
            $content
        );
        file_put_contents($oldPath, $content);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    if ($vAction === 'fresh') {
        unlink(LOCAL_CONFIG);
        header('Location: /install');
        exit;
    }
    ?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CMS de Jogos — Versão Desatualizada</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:oklch(10% 0.03 260);color:oklch(96% 0.003 250);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:oklch(16% 0.035 265);border:1px solid oklch(55% 0.12 85);border-radius:12px;padding:40px;max-width:520px;width:100%;text-align:center}
h1{font-family:'Cinzel',Georgia,serif;font-size:22px;color:oklch(75% 0.15 85);margin-bottom:12px}
p{color:oklch(60% 0.012 250);margin-bottom:24px;line-height:1.6}
.version-badge{display:inline-block;padding:4px 14px;border-radius:20px;font-size:13px;font-weight:600;margin-bottom:20px}
.version-old{background:oklch(55% 0.20 25 / 0.15);border:1px solid oklch(55% 0.20 25);color:oklch(55% 0.20 25)}
.version-new{background:oklch(68% 0.16 220 / 0.15);border:1px solid oklch(68% 0.16 220);color:oklch(68% 0.16 220)}
.btn{display:block;width:100%;padding:14px;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;margin-bottom:12px;text-decoration:none}
.btn-gold{background:linear-gradient(135deg,oklch(75% 0.15 85),oklch(62% 0.13 85));color:oklch(8% 0.02 260)}
.btn-gold:hover{background:linear-gradient(135deg,oklch(85% 0.13 85),oklch(75% 0.15 85))}
.btn-outline{background:transparent;border:1px solid oklch(55% 0.12 85);color:oklch(75% 0.15 85)}
.btn-outline:hover{background:oklch(75% 0.15 85 / 0.1)}
.btn-danger{background:transparent;border:1px solid oklch(55% 0.20 25);color:oklch(55% 0.20 25)}
.btn-danger:hover{background:oklch(55% 0.20 25 / 0.1)}
hr{border:none;border-top:1px solid oklch(25% 0.02 260);margin:20px 0}
</style>
</head>
<body>
<div class="card">
<h1>CMS de Jogos</h1>
<div class="version-badge version-old">Config: v<?= htmlspecialchars(CMS_INSTALL_VERSION) ?></div>
<div class="version-badge version-new">Sistema: v<?= htmlspecialchars(CMS_VERSION) ?></div>
<p>A configuração existente foi criada por uma versão anterior do sistema. Como deseja prosseguir?</p>
<form method="POST">
<button type="submit" name="vaction" value="keep" class="btn btn-gold">Usar config atual e migrar dados</button>
</form>
<hr>
<form method="POST" onsubmit="return confirm('Todos os dados serão perdidos. Confirma?')">
<button type="submit" name="vaction" value="fresh" class="btn btn-danger">Nova instalação (dados serão perdidos)</button>
</form>
</div>
</body>
</html>
<?php
    exit;
}

// MySQL defaults (ignored when DB_TYPE is 'sqlite')
if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'cms_db');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

// URLs
if (defined('SITE_URL')) {
    // already defined in local config
} elseif (!empty($_ENV['SITE_URL'])) {
    define('SITE_URL', rtrim($_ENV['SITE_URL'], '/'));
} elseif (php_sapi_name() !== 'cli') {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('SITE_URL', $proto . '://' . $host);
} else {
    define('SITE_URL', 'http://localhost');
}
if (!defined('ADMIN_URL')) define('ADMIN_URL', SITE_URL . '/admin');
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', SITE_URL . '/uploads');

// Upload limits
if (!defined('MAX_UPLOAD_SIZE')) define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024);
if (!defined('ALLOWED_GAME_EXTENSIONS')) define('ALLOWED_GAME_EXTENSIONS', ['zip']);
if (!defined('ALLOWED_IMAGE_EXTENSIONS')) define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Admin credentials (seed-only, used by migration_001)
if (!defined('ADMIN_USERNAME')) define('ADMIN_USERNAME', 'admin');
if (!defined('ADMIN_PASSWORD_HASH')) define('ADMIN_PASSWORD_HASH', password_hash('admin1234', PASSWORD_DEFAULT));

// Site info
if (!defined('SITE_NAME')) define('SITE_NAME', 'CMS de Jogos');
if (!defined('SITE_TAGLINE')) define('SITE_TAGLINE', 'Gerencie seu portfólio de jogos');

// SMTP Configuration
if (!defined('SMTP_HOST')) define('SMTP_HOST', '');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USER')) define('SMTP_USER', '');
if (!defined('SMTP_PASS')) define('SMTP_PASS', '');
if (!defined('SMTP_FROM')) define('SMTP_FROM', '');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'CMS de Jogos');

// Auto-load helpers
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

// Redirect to install if not set up yet
requireInstalled();
