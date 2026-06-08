<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Paths
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__FILE__));
if (!defined('DATA_PATH')) define('DATA_PATH', ROOT_PATH . '/data');

// Local session files (avoids /tmp contention on shared hosting).
// Falls back to default save path if data/ is not writable.
$sessionPath = DATA_PATH . '/sessions';
if (!is_dir($sessionPath)) { @mkdir($sessionPath, 0700, true); }
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', ROOT_PATH . '/uploads');
if (!defined('DB_PATH')) define('DB_PATH', DATA_PATH . '/jogatinando.db');

define('CMS_VERSION', '1.1.0');
define('LOCAL_CONFIG', DATA_PATH . '/config.local.php');

// Load local config from data/ first
if (file_exists(LOCAL_CONFIG)) {
    require_once LOCAL_CONFIG;
}

if (!defined('INSTALL_LOCK')) {
    $envLock = $_ENV['INSTALL_LOCK'] ?? '';
    define('INSTALL_LOCK', $envLock === '1' || $envLock === 'true');
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
        $nonce = $_POST['fresh_nonce'] ?? '';
        if ($nonce !== ($_SESSION['fresh_nonce'] ?? '')) {
            http_response_code(403);
            exit('Token inválido. Recarregue a página e tente novamente.');
        }
        unset($_SESSION['fresh_nonce']);
        if (!unlink(LOCAL_CONFIG)) {
            http_response_code(500);
            exit('Erro ao remover arquivo de configuração. Verifique permissões.');
        }
        header('Location: /install');
        exit;
    }
    $_SESSION['fresh_nonce'] = bin2hex(random_bytes(32));
    ?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CMS de Jogos — Versão Desatualizada</title>
<link rel="icon" href="/assets/svg/logo.svg" type="image/svg+xml">
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
<input type="hidden" name="fresh_nonce" value="<?= htmlspecialchars($_SESSION['fresh_nonce']) ?>">
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
    $host = strtolower(trim($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = preg_replace('/[^a-z0-9.:\[\]-]/', '', $host);
    define('SITE_URL', $proto . '://' . $host);
} else {
    define('SITE_URL', 'http://localhost');
}
if (!defined('ADMIN_URL')) define('ADMIN_URL', SITE_URL . '/admin');
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', SITE_URL . '/uploads');

// S3-compatible storage (R2) — env via docker-compose, constants via config.local.php
if (!defined('S3_ACCESS_KEY')) define('S3_ACCESS_KEY', $_ENV['S3_ACCESS_KEY'] ?? '');
if (!defined('S3_SECRET_KEY')) define('S3_SECRET_KEY', $_ENV['S3_SECRET_KEY'] ?? '');
if (!defined('S3_ENDPOINT')) define('S3_ENDPOINT', $_ENV['S3_ENDPOINT'] ?? '');
if (!defined('S3_REGION')) define('S3_REGION', $_ENV['S3_REGION'] ?? 'auto');
if (!defined('S3_BUCKET')) define('S3_BUCKET', $_ENV['S3_BUCKET'] ?? '');
if (!defined('S3_PUBLIC_URL')) define('S3_PUBLIC_URL', $_ENV['S3_PUBLIC_URL'] ?? '');

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
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'Orçamento');

// Auto-load helpers
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/storage.php';

// Redirect to install if not set up yet
requireInstalled();

// Maintenance mode check (skip in CLI)
if (php_sapi_name() !== 'cli') {
    require_once ROOT_PATH . '/includes/maintenance.php';
    if (isMaintenanceActive()) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (!str_starts_with($uri, '/admin/') && !str_starts_with($uri, '/install')) {
            if (empty($_SESSION['admin_logged_in'])) {
                renderMaintenancePage();
            }
        }
    }
}
