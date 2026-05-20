<?php
/**
 * Utility functions
 */

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Lightweight SMTP client using native PHP sockets.
 * Sends email via authenticated SMTP (Zoho, etc).
 */
function sendSmtpMail($to, $subject, $body, $from = null, $fromName = null) {
    $host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.zoho.com';
    $port = defined('SMTP_PORT') ? SMTP_PORT : 587;
    $user = defined('SMTP_USER') ? SMTP_USER : '';
    $pass = defined('SMTP_PASS') ? SMTP_PASS : '';
    $fromAddr = $from ?: (defined('SMTP_FROM') ? SMTP_FROM : $user);
    $fromLbl = $fromName ?: (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'CMS');

    if (empty($pass)) {
        error_log('SMTP: Password not configured.');
        return false;
    }

    $fp = @fsockopen($host, $port, $errno, $errstr, 30);
    if (!$fp) return false;

    $read = function($fp) {
        $out = '';
        while (($line = fgets($fp)) !== false) {
            $out .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return trim($out);
    };

    $write = function($fp, $cmd) {
        fwrite($fp, $cmd . "\r\n");
    };

    $read($fp); // Banner
    $write($fp, "EHLO " . $_SERVER['HTTP_HOST'] ?? 'localhost');
    $read($fp);
    $write($fp, "STARTTLS");
    $read($fp);
    stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    $write($fp, "EHLO " . $_SERVER['HTTP_HOST'] ?? 'localhost');
    $read($fp);
    $write($fp, "AUTH LOGIN");
    $read($fp);
    $write($fp, base64_encode($user));
    $read($fp);
    $write($fp, base64_encode($pass));
    $resp = $read($fp);
    if (strpos($resp, '235') === false) { fclose($fp); return false; }

    $write($fp, "MAIL FROM: <$fromAddr>");
    $read($fp);
    $write($fp, "RCPT TO: <$to>");
    $read($fp);
    $write($fp, "DATA");
    $read($fp);

    $headers = "From: $fromLbl <$fromAddr>\r\n";
    $headers .= "Reply-To: $to\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: Jogatinando CMS\r\n";

    fwrite($fp, "Subject: $subject\r\n$headers\r\n$body\r\n.\r\n");
    $resp = $read($fp);
    $write($fp, "QUIT");
    $read($fp);
    fclose($fp);

    return strpos($resp, '250') !== false;
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

/**
 * Upload and extract game archive to uploads/{engine-slug}/{game-slug}/
 * Handles nested folder structures automatically.
 */
function uploadAndExtractGame($file, $engine, $gameTitle) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Upload inválido.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo excede o limite do servidor (100MB).',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o limite do formulário (100MB).',
            UPLOAD_ERR_PARTIAL => 'Upload incompleto. Tente novamente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Sem diretório temporário no servidor.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever no disco.',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão PHP.',
        ];
        $msg = $messages[$file['error']] ?? 'Erro no upload (código: ' . $file['error'] . ').';
        return ['success' => false, 'message' => $msg];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'Arquivo muito grande. Máximo: ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_GAME_EXTENSIONS)) {
        return ['success' => false, 'message' => 'Formato não suportado. Use: ' . implode(', ', ALLOWED_GAME_EXTENSIONS)];
    }

    $engineSlug = generateSlug($engine);
    $gameSlug = generateSlug($gameTitle);
    $gameDir = UPLOAD_PATH . '/games/' . $engineSlug . '/' . $gameSlug;

    // If game already exists, delete old version first
    if (is_dir($gameDir)) {
        deleteGameDir($engineSlug . '/' . $gameSlug);
    }

    if (!is_dir($gameDir)) {
        mkdir($gameDir, 0755, true);
    }

    $tmpFile = $gameDir . '/_upload.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $tmpFile)) {
        return ['success' => false, 'message' => 'Falha ao salvar o arquivo.'];
    }

    $extracted = false;
    $extractError = '';

    if ($ext === 'zip') {
        $zip = new ZipArchive();
        $result = $zip->open($tmpFile);
        if ($result === true) {
            $zip->extractTo($gameDir);
            $zip->close();
            $extracted = true;
        } else {
            $reasons = [
                ZipArchive::ER_EXISTS => 'Arquivo já existe',
                ZipArchive::ER_INCONS => 'Arquivo ZIP inconsistente',
                ZipArchive::ER_MEMORY => 'Erro de memória',
                ZipArchive::ER_NOENT => 'Arquivo não encontrado',
                ZipArchive::ER_NOZIP => 'Não é um arquivo ZIP válido',
                ZipArchive::ER_OPEN => 'Não foi possível abrir o arquivo',
                ZipArchive::ER_READ => 'Erro de leitura',
                ZipArchive::ER_SEEK => 'Erro de seek',
            ];
            $extractError = 'ZIP inválido: ' . ($reasons[$result] ?? 'código ' . $result);
        }
    }

    if (!$extracted) {
        @unlink($tmpFile);
        return ['success' => false, 'message' => 'Falha ao extrair: ' . $extractError];
    }

    @unlink($tmpFile);

    // Detect and flatten nested folder structure
    $items = scandir($gameDir);
    $subdirs = [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (is_dir($gameDir . '/' . $item)) {
            $subdirs[] = $item;
        }
    }

    // If exactly one subdirectory and it contains index.html, flatten
    if (count($subdirs) === 1) {
        $onlyDir = $gameDir . '/' . $subdirs[0];
        if (file_exists($onlyDir . '/index.html')) {
            moveDirectoryContents($onlyDir, $gameDir);
            @rmdir($onlyDir);
        }
    }

    // Validate index.html exists
    if (!file_exists($gameDir . '/index.html')) {
        // Try to find index.html in a deeper subdirectory
        $found = findIndexHtml($gameDir);
        if ($found) {
            moveDirectoryContents(dirname($found), $gameDir);
            // Remove empty subdirectories
            cleanupEmptyDirs($gameDir);
        } else {
            return ['success' => false, 'message' => 'Arquivo index.html não encontrado no pacote. O jogo precisa ter um index.html na raiz.'];
        }
    }

    return [
        'success' => true,
        'game_path' => $engineSlug . '/' . $gameSlug,
        'engine_slug' => $engineSlug,
        'game_slug' => $gameSlug,
        'message' => 'Jogo extraído com sucesso!'
    ];
}

function moveDirectoryContents($source, $dest) {
    $items = scandir($source);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $src = $source . '/' . $item;
        $dst = $dest . '/' . $item;
        if (is_dir($src)) {
            if (!is_dir($dst)) {
                mkdir($dst, 0755, true);
            }
            moveDirectoryContents($src, $dst);
            @rmdir($src);
        } else {
            rename($src, $dst);
        }
    }
}

function findIndexHtml($dir) {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            if (file_exists($path . '/index.html')) {
                return $path . '/index.html';
            }
            $found = findIndexHtml($path);
            if ($found) return $found;
        }
    }
    return null;
}

function cleanupEmptyDirs($dir) {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            cleanupEmptyDirs($path);
            if (count(scandir($path)) === 2) {
                @rmdir($path);
            }
        }
    }
}

function deleteGameDir($gamePath) {
    if (!$gamePath) return;
    $fullPath = UPLOAD_PATH . '/games/' . $gamePath;
    if (!is_dir($fullPath)) return;

    $items = scandir($fullPath);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $fullPath . '/' . $item;
        if (is_dir($path)) {
            deleteGameDir($path);
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($fullPath);

    // Clean up empty engine folder
    $engineDir = dirname($fullPath);
    if (is_dir($engineDir) && count(scandir($engineDir)) === 2) {
        @rmdir($engineDir);
    }
}
