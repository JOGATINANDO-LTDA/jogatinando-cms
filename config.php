<?php
/**
 * Jogatinando CMS — Configuration
 */

// Load local overrides (secrets, passwords) if they exist
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Paths
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__FILE__));
if (!defined('DATA_PATH')) define('DATA_PATH', ROOT_PATH . '/data');
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', ROOT_PATH . '/uploads');
if (!defined('DB_PATH')) define('DB_PATH', DATA_PATH . '/jogatinando.db');

// Database type: 'sqlite' (default) or 'mysql'
if (!defined('DB_TYPE')) define('DB_TYPE', 'sqlite');
// MySQL connection (ignored when DB_TYPE is 'sqlite')
if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'jogatinando');
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

// Admin credentials
if (!defined('ADMIN_USERNAME')) define('ADMIN_USERNAME', 'admin');
if (!defined('ADMIN_PASSWORD_HASH')) define('ADMIN_PASSWORD_HASH', password_hash('admin1234', PASSWORD_DEFAULT));

// Site info
if (!defined('SITE_NAME')) define('SITE_NAME', 'Jogatinando');
if (!defined('SITE_TAGLINE')) define('SITE_TAGLINE', 'Desenvolvimento de Jogos Sob Medida');

// SMTP Configuration (Zoho Mail)
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.zoho.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USER')) define('SMTP_USER', 'sulivan.leite@jogatinando.com.br');
if (!defined('SMTP_PASS')) define('SMTP_PASS', '');
if (!defined('SMTP_FROM')) define('SMTP_FROM', 'orcamento@jogatinando.com.br');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'Jogatinando LTDA');

// Auto-load helpers
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';
