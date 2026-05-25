<?php
/**
 * Authentication helpers
 */

function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . ADMIN_URL . '/login.php');
        exit;
    }
}

function login($username, $password) {
    $stmt = getDB()->prepare("SELECT id, password_hash, avatar_url FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_user_id'] = (int)$user['id'];
        $_SESSION['admin_avatar_url'] = $user['avatar_url'] ?? '';
        return true;
    }
    return false;
}

function redirectOrError($msg, $detail) {
    if (file_exists(ROOT_PATH . '/install.php')) {
        header('Location: /install.php');
        exit;
    }
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>CMS — Erro</title>';
    echo '<style>body{font-family:sans-serif;background:#111;color:#eee;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px}';
    echo '.card{background:#1a1a2e;border:1px solid #c9a84c;border-radius:12px;padding:40px;max-width:520px;text-align:center}';
    echo 'h1{font-family:Georgia,serif;color:#c9a84c;margin-bottom:12px}';
    echo 'p{color:#999;line-height:1.6;margin-bottom:16px}</style>';
    echo '</head><body><div class="card"><h1>CMS de Jogos</h1>';
    echo '<p>' . e($msg) . '</p>';
    echo '<p style="font-size:13px">' . e($detail) . '</p>';
    echo '</div></body></html>';
    exit;
}

function requireInstalled() {
    if (basename($_SERVER['PHP_SELF']) === 'install.php') return;
    try {
        $db = getDB();
        if (!$db) {
            redirectOrError(
                'O sistema n\u00e3o est\u00e1 instalado.',
                'Para instalar, fa\u00e7a upload do arquivo install.php ou restaure o backup.'
            );
        }
        $count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($count == 0) {
            redirectOrError(
                'O banco de dados est\u00e1 vazio.',
                'O instalador n\u00e3o est\u00e1 dispon\u00edvel. Restaure o backup ou fa\u00e7a upload do install.php.'
            );
        }
    } catch (Exception $e) {
        redirectOrError(
            'Erro ao conectar ao banco de dados.',
            'Verifique as configura\u00e7\u00f5es ou restaure o backup.'
        );
    }
}

function logout() {
    session_destroy();
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

function getCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCSRFToken()) . '">';
}
