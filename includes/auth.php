<?php
/**
 * Authentication helpers
 */

function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . ADMIN_URL . '/login');
        exit;
    }
}

function login($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.id, u.password_hash, u.avatar_url, u.role_id, u.status,
               r.name AS role_name, r.level AS role_level
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.username = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) return false;

    if ($user['status'] === 'pending') return false;

    if ($user['password_hash'] && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_user_id'] = (int)$user['id'];
        $_SESSION['admin_avatar_url'] = $user['avatar_url'] ?? '';
        $_SESSION['admin_role_id'] = $user['role_id'] ? (int)$user['role_id'] : null;
        $_SESSION['admin_role_name'] = $user['role_name'] ?? '';
        $_SESSION['admin_role_level'] = $user['role_level'] ?? 'moderator';
        return true;
    }
    return false;
}

function requireRole($minLevel) {
    requireLogin();
    $levels = ['moderator' => 0, 'chief' => 1, 'ceo' => 2];
    $userLevel = $levels[$_SESSION['admin_role_level'] ?? 'moderator'] ?? 0;
    $required = $levels[$minLevel] ?? 0;
    if ($userLevel < $required) {
        header('Location: ' . ADMIN_URL . '/dashboard');
        exit;
    }
}

function getAssignableRoles($db) {
    $userLevel = $_SESSION['admin_role_level'] ?? 'moderator';
    $isAdminCeo = ($_SESSION['admin_user_id'] === 1 && $userLevel === 'ceo');

    if ($isAdminCeo) {
        return $db->query("SELECT id, name, level, description FROM roles ORDER BY id")->fetchAll();
    }

    $levels = ['moderator' => 0, 'chief' => 1, 'ceo' => 2];
    $userLevelNum = $levels[$userLevel] ?? 0;

    $allowed = [];
    foreach ($levels as $lvl => $num) {
        if ($num < $userLevelNum) {
            $allowed[] = $db->quote($lvl);
        }
    }

    if (empty($allowed)) return [];

    $sql = "SELECT id, name, level, description FROM roles WHERE level IN (" . implode(',', $allowed) . ") ORDER BY id";
    return $db->query($sql)->fetchAll();
}

function getRoleLevelRank($level) {
    $levels = ['moderator' => 0, 'chief' => 1, 'ceo' => 2];
    return $levels[$level] ?? -1;
}

function getUserRoleName() {
    return $_SESSION['admin_role_name'] ?? 'Sem cargo';
}

function canManageRole($targetLevel) {
    if ($_SESSION['admin_user_id'] === 1) return true;
    $levels = ['moderator' => 0, 'chief' => 1, 'ceo' => 2];
    $userLevel = $levels[$_SESSION['admin_role_level'] ?? 'moderator'] ?? 0;
    $target = $levels[$targetLevel] ?? 0;
    return $userLevel > $target;
}

function redirectOrError($msg, $detail) {
    if (file_exists(ROOT_PATH . '/install.php')) {
        header('Location: /install');
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
    header('Location: ' . ADMIN_URL . '/login');
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
