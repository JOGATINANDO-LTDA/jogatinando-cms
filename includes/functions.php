<?php
/**
 * Utility functions
 */

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[àáâãäå]/', 'a', $text);
    $text = preg_replace('/[èéêë]/', 'e', $text);
    $text = preg_replace('/[ìíîï]/', 'i', $text);
    $text = preg_replace('/[òóôõö]/', 'o', $text);
    $text = preg_replace('/[ùúûü]/', 'u', $text);
    $text = preg_replace('/[ç]/', 'c', $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

function uploadFile($file, $directory, $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp']) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Upload inválido.'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'message' => 'Arquivo muito grande. Máximo: ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB'];
        default:
            return ['success' => false, 'message' => 'Erro no upload (código: ' . $file['error'] . ').'];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'Arquivo excede o tamanho máximo.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        return ['success' => false, 'message' => 'Extensão não permitida: .' . $ext];
    }

    $uploadDir = UPLOAD_PATH . '/' . $directory;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = uniqid('upl_', true) . '.' . $ext;
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'message' => 'Falha ao salvar o arquivo.'];
    }

    return [
        'success' => true,
        'filename' => $filename,
        'url' => UPLOAD_URL . '/' . $directory . '/' . $filename,
        'path' => $destination
    ];
}

function deleteFile($path) {
    if (file_exists($path)) {
        return unlink($path);
    }
    return false;
}

function truncateText($text, $length = 150) {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}

function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' ano(s) atrás';
    if ($diff->m > 0) return $diff->m . ' mês(es) atrás';
    if ($diff->d > 0) return $diff->d . ' dia(s) atrás';
    if ($diff->h > 0) return $diff->h . ' hora(s) atrás';
    if ($diff->i > 0) return $diff->i . ' minuto(s) atrás';
    return 'agora mesmo';
}

function flashMessage($type = '', $message = '') {
    if ($type && $message) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return;
    }
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash() {
    $flash = flashMessage();
    if ($flash) {
        $class = $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'error' : 'info');
        return '<div class="flash flash-' . $class . '">' . e($flash['message']) . '</div>';
    }
    return '';
}

function getEngineIcon($engine) {
    $icons = [
        'GDevelop' => '🎮',
        'Godot' => '🤖',
        'RPG Maker' => '⚔️',
        'Unity' => '🔷',
        'Unreal Engine' => '🔶',
        'Construct' => '🏗️',
        'Defold' => '📦',
        'Game Maker' => '🎯',
        'Ren\'py' => '💬',
        'Pixel Game Maker MV' => '👾',
        'RPG Paper Maker' => '📜',
    ];
    return $icons[$engine] ?? '🎮';
}

function getEngineColor($engine) {
    $colors = [
        'GDevelop' => 'oklch(68% 0.16 220)',
        'Godot' => 'oklch(65% 0.18 145)',
        'RPG Maker' => 'oklch(72% 0.14 85)',
        'Unity' => 'oklch(55% 0.02 250)',
        'Unreal Engine' => 'oklch(35% 0.02 250)',
        'Construct' => 'oklch(70% 0.16 30)',
        'Defold' => 'oklch(60% 0.18 120)',
        'Game Maker' => 'oklch(65% 0.18 200)',
        'Ren\'py' => 'oklch(60% 0.18 340)',
        'Pixel Game Maker MV' => 'oklch(70% 0.16 160)',
        'RPG Paper Maker' => 'oklch(68% 0.14 70)',
    ];
    return $colors[$engine] ?? 'oklch(68% 0.16 220)';
}
