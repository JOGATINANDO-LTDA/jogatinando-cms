<?php
/**
 * Authentication helpers
 */

function clearSession() {
    $_SESSION = [];
    session_destroy();
}

function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        $redirect = isset($_SERVER['REQUEST_URI']) ? '?redirect=' . urlencode($_SERVER['REQUEST_URI']) : '';
        header('Location: ' . ADMIN_URL . '/login' . $redirect);
        exit;
    }
    $timeout = 1800;
    if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity']) > $timeout) {
        clearSession();
        $redirect = isset($_SERVER['REQUEST_URI']) ? '?redirect=' . urlencode($_SERVER['REQUEST_URI']) : '';
        header('Location: ' . ADMIN_URL . '/login' . $redirect);
        exit;
    }
    $_SESSION['admin_last_activity'] = time();
}

function login($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.id, u.password_hash, u.avatar_url, u.role_id, u.status,
               r.name AS role_name,
               r.level_id, l.name AS level_name, l.slug AS level_slug,
               l.perm_banners, l.perm_games, l.perm_blog, l.perm_testimonials,
               l.perm_faq, l.perm_team, l.perm_users, l.perm_roles,
               l.perm_engines, l.perm_platforms, l.perm_consoles,
               l.perm_retro_games, l.perm_templates, l.perm_optimizer,
               l.perm_settings
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN levels l ON r.level_id = l.id
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
        $_SESSION['admin_role_level'] = $user['level_slug'] ?? 'moderator';
        $_SESSION['admin_level_slug'] = $user['level_slug'] ?? 'moderator';
        $_SESSION['admin_level_name'] = $user['level_name'] ?? 'Moderator';
        $_SESSION['admin_permissions'] = [];
        foreach ($user as $key => $val) {
            if (strpos($key, 'perm_') === 0) {
                $_SESSION['admin_permissions'][$key] = (bool)$val;
            }
        }
        session_regenerate_id(true);
        return true;
    }
    return false;
}

function can($perm) {
    if (!isset($_SESSION['admin_permissions'])) {
        loadSessionPermissions();
    }
    if (($_SESSION['admin_user_id'] ?? 0) === 1) return true;
    return !empty($_SESSION['admin_permissions'][$perm]);
}

function loadSessionPermissions() {
    if (!isLoggedIn()) return;
    $db = getDB();
    if (!$db) return;
    $stmt = $db->prepare("SELECT l.perm_banners, l.perm_games, l.perm_blog, l.perm_testimonials, l.perm_faq, l.perm_team, l.perm_users, l.perm_roles, l.perm_engines, l.perm_platforms, l.perm_consoles, l.perm_retro_games, l.perm_templates, l.perm_optimizer, l.perm_settings FROM levels l JOIN roles r ON r.level_id = l.id JOIN users u ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$_SESSION['admin_user_id']]);
    $level = $stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['admin_permissions'] = [];
    if ($level) {
        foreach ($level as $key => $val) {
            if (strpos($key, 'perm_') === 0) {
                $_SESSION['admin_permissions'][$key] = (bool)$val;
            }
        }
    }
}

function getSessionRank() {
    return count(array_filter($_SESSION['admin_permissions'] ?? []));
}

function getLevelRank($levelId) {
    if (!$levelId) return 0;
    $db = getDB();
    $stmt = $db->prepare("SELECT perm_banners, perm_games, perm_blog, perm_testimonials, perm_faq, perm_team, perm_users, perm_roles, perm_engines, perm_platforms, perm_consoles, perm_retro_games, perm_templates, perm_optimizer, perm_settings FROM levels WHERE id = ?");
    $stmt->execute([$levelId]);
    $level = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$level) return 0;
    return count(array_filter($level));
}

function requireRole($minLevel) {
    requireLogin();
    $rank = getSessionRank();
    $db = getDB();
    $stmt = $db->prepare("SELECT (perm_banners + perm_games + perm_blog + perm_testimonials + perm_faq + perm_team + perm_users + perm_roles + perm_engines + perm_platforms + perm_consoles + perm_retro_games + perm_templates + perm_optimizer + perm_settings) as r FROM levels WHERE slug = ?");
    $stmt->execute([$minLevel]);
    $required = (int)$stmt->fetchColumn();
    if ($required === 0) $required = 10;
    if ($rank < $required) {
        header('Location: ' . ADMIN_URL . '/dashboard');
        exit;
    }
}

function getAssignableRoles($db) {
    $currentId = (int)($_SESSION['admin_user_id'] ?? 0);
    if ($currentId === 1) {
        return $db->query("SELECT r.id, r.name, l.name AS level_name, l.slug AS level_slug, r.description FROM roles r LEFT JOIN levels l ON r.level_id = l.id WHERE r.id != 1 ORDER BY r.id")->fetchAll();
    }
    $currentRank = getSessionRank();
    $stmt = $db->prepare("SELECT r.id, r.name, l.name AS level_name, l.slug AS level_slug, r.description FROM roles r LEFT JOIN levels l ON r.level_id = l.id WHERE r.id != 1 AND (l.perm_banners + l.perm_games + l.perm_blog + l.perm_testimonials + l.perm_faq + l.perm_team + l.perm_users + l.perm_roles + l.perm_engines + l.perm_platforms + l.perm_consoles + l.perm_retro_games + l.perm_templates + l.perm_optimizer + l.perm_settings) < ? ORDER BY r.id");
    $stmt->execute([$currentRank]);
    return $stmt->fetchAll();
}

function getRoleLevelRank($level) {
    $db = getDB();
    $stmt = $db->prepare("SELECT (perm_banners + perm_games + perm_blog + perm_testimonials + perm_faq + perm_team + perm_users + perm_roles + perm_engines + perm_platforms + perm_consoles + perm_retro_games + perm_templates + perm_optimizer + perm_settings) as r FROM levels WHERE slug = ?");
    $stmt->execute([$level]);
    $rank = (int)$stmt->fetchColumn();
    return $rank > 0 ? $rank : -1;
}

function getUserRoleName() {
    return $_SESSION['admin_role_name'] ?? 'Sem cargo';
}

function canManageRole($targetLevel) {
    if (($_SESSION['admin_user_id'] ?? 0) === 1) return true;
    $currentRank = getSessionRank();
    $db = getDB();
    $stmt = $db->prepare("SELECT (perm_banners + perm_games + perm_blog + perm_testimonials + perm_faq + perm_team + perm_users + perm_roles + perm_engines + perm_platforms + perm_consoles + perm_retro_games + perm_templates + perm_optimizer + perm_settings) as r FROM levels WHERE slug = ?");
    $stmt->execute([$targetLevel]);
    $targetRank = (int)$stmt->fetchColumn();
    return $currentRank > $targetRank;
}

function canEditUser($targetId) {
    $currentId = (int)($_SESSION['admin_user_id'] ?? 0);
    if ($currentId === 1) return true;
    if ((int)$targetId === 1) return false;
    if ((int)$targetId === $currentId) return true;
    $currentRank = getSessionRank();
    if ($currentRank <= 0) return false;
    $db = getDB();
    $stmt = $db->prepare("SELECT l.id FROM users u LEFT JOIN roles r ON u.role_id = r.id LEFT JOIN levels l ON r.level_id = l.id WHERE u.id = ?");
    $stmt->execute([$targetId]);
    $targetLevelId = $stmt->fetchColumn();
    $targetRank = getLevelRank($targetLevelId);
    return $currentRank > $targetRank;
}

function isSmtpConfigured() {
    return defined('SMTP_PASS') && SMTP_PASS !== '';
}

function redirectOrError($msg, $detail) {
    if (file_exists(ROOT_PATH . '/install.php')) {
        header('Location: /install');
        exit;
    }
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>CMS — Erro</title><link rel="icon" href="<?= siteFaviconUrl() ?>" type="image/svg+xml">';
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
