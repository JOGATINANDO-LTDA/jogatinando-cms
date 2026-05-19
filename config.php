<?php
/**
 * Jogatinando CMS — Configuration
 */
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Paths
define('ROOT_PATH', dirname(__FILE__));
define('DATA_PATH', ROOT_PATH . '/data');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('DB_PATH', DATA_PATH . '/jogatinando.db');

// URLs — prioridade: constante definida antes do config > env var > detecção automática
if (defined('SITE_URL')) {
    // já definido externamente
} elseif (!empty($_ENV['SITE_URL'])) {
    define('SITE_URL', rtrim($_ENV['SITE_URL'], '/'));
} elseif (php_sapi_name() !== 'cli') {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('SITE_URL', $proto . '://' . $host);
} else {
    define('SITE_URL', 'http://localhost');
}
define('ADMIN_URL', SITE_URL . '/admin');
define('UPLOAD_URL', SITE_URL . '/uploads');

// Upload limits
define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024); // 100MB for game archives
define('ALLOWED_GAME_EXTENSIONS', ['zip', 'rar']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Admin credentials (change after first login!)
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', password_hash('jogatinando2024', PASSWORD_DEFAULT));

// Site info
define('SITE_NAME', 'Jogatinando');
define('SITE_TAGLINE', 'Desenvolvimento de Jogos Sob Medida');

// Auto-load helpers
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';
