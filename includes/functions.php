<?php

require_once __DIR__ . '/storage.php';

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function assetUrl($path) {
    $file = ROOT_PATH . $path;
    $mtime = file_exists($file) ? filemtime($file) : CMS_VERSION;
    return SITE_URL . $path . '?v=' . $mtime;
}

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
    if (!$fp) {
        error_log("SMTP: Connection to $host:$port failed: $errstr");
        return false;
    }

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

    $banner = $read($fp);
    $write($fp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $ehloResp = $read($fp);
    $write($fp, "STARTTLS");
    $starttlsResp = $read($fp);
    stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    $write($fp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $ehlo2Resp = $read($fp);
    $write($fp, "AUTH LOGIN");
    $authLoginResp = $read($fp);
    $write($fp, base64_encode($user));
    $userResp = $read($fp);
    $write($fp, base64_encode($pass));
    $passResp = $read($fp);
    if (strpos($passResp, '235') === false) {
        error_log("SMTP: falha de autenticação");
        fclose($fp);
        return false;
    }

    $write($fp, "MAIL FROM: <$fromAddr>");
    $mailFromResp = $read($fp);
    $write($fp, "RCPT TO: <$to>");
    $rcptToResp = $read($fp);
    $write($fp, "DATA");
    $dataResp = $read($fp);

    $headers = "From: $fromLbl <$fromAddr>\r\n";
    $headers .= "Reply-To: $to\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: CMS de Jogos\r\n";

    $messageData = "Subject: $subject\r\n$headers\r\n$body\r\n.\r\n";
    fwrite($fp, $messageData);
    $messageResp = $read($fp);
    $write($fp, "QUIT");
    $quitResp = $read($fp);
    fclose($fp);

    $success = strpos($messageResp, '250') !== false;
    if (!$success) {
        error_log("SMTP: falha ao enviar mensagem");
    }
    return $success;
}

function sendVerificationEmail($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || empty($user['email'])) return false;

    $token = bin2hex(random_bytes(32));
    $stmt = $db->prepare("UPDATE users SET email_verification_token = ? WHERE id = ?");
    $stmt->execute([$token, $userId]);

    $verifyLink = ADMIN_URL . '/verify-email?token=' . urlencode($token);
    $subject = 'Verificação de Email';
    $noreplyEmail = getSetting('noreply_email', 'noreply@seudominio.com');
    $noreplyName = getSetting('noreply_name', 'No Reply');
    $body = "Olá {$user['username']},\n\n"
          . "Confirme seu email clicando no link abaixo:\n\n"
          . "$verifyLink\n\n"
          . "Se você não criou uma conta, ignore este email.\n"
          . SITE_NAME;

    return sendSmtpMail($user['email'], $subject, $body, $noreplyEmail, $noreplyName);
}

function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[àáâãäå]/', 'a', $text);
    $text = preg_replace('/[èéêë]/', 'e', $text);
    $text = preg_replace('/[ìíîï]/', 'i', $text);
    $text = preg_replace('/[òóôõö]/', 'o', $text);
    $text = preg_replace('/[ùúûü]/', 'u', $text);
    $text = preg_replace('/[ç]/', 'c', $text);
    $text = preg_replace("/['\"`´‘’ʻ`]/", '', $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

function getFileMimeType($path) {
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = @finfo_file($finfo, $path);
            @finfo_close($finfo);
            if ($mime !== false && $mime !== '') return $mime;
        }
    }
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($path);
        if ($mime !== false && $mime !== '') return $mime;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $map = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon', 'zip' => 'application/zip',
    ];
    return $map[$ext] ?? 'application/octet-stream';
}

function mediaUrl($path) {
    if (empty($path)) return '';
    if (str_starts_with($path, 'http')) return $path;
    $relPath = ltrim($path, '/');
    if (function_exists('getSetting') && getSetting('s3_serve_media', '0') === '1') {
        $cfg = S3::getResolvedConfig();
        $baseUrl = $cfg['public_url'];
        if ($baseUrl === '' && $cfg['endpoint'] !== '' && $cfg['bucket'] !== '') {
            $baseUrl = rtrim($cfg['endpoint'], '/') . '/' . $cfg['bucket'];
        }
        if ($baseUrl !== '') {
            return rtrim($baseUrl, '/') . '/' . $relPath;
        }
    }
    return '/' . $relPath;
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
            error_log('uploadFile: código de erro ' . $file['error'] . ' para ' . ($file['name'] ?? 'unknown'));
            return ['success' => false, 'message' => 'Erro no upload (código: ' . $file['error'] . ').'];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        error_log('uploadFile: tamanho excedido ' . $file['size'] . ' > ' . MAX_UPLOAD_SIZE);
        return ['success' => false, 'message' => 'Arquivo excede o tamanho máximo.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        error_log('uploadFile: extensão rejeitada .' . $ext);
        return ['success' => false, 'message' => 'Extensão não permitida: .' . $ext];
    }

    $mime = getFileMimeType($file['tmp_name']);
    $allowedMimes = [
        'image/jpeg','image/png','image/gif','image/webp',
    ];
    if (!in_array($mime, $allowedMimes)) {
        error_log('uploadFile: MIME rejeitado ' . $mime . ' para ' . ($file['name'] ?? 'unknown'));
        return ['success' => false, 'message' => 'Tipo de arquivo não permitido.'];
    }

    $filename = uniqid('upl_', true) . '.' . $ext;
    $relPath = $directory . '/' . $filename;
    $s3Name = 'uploads/' . $relPath;

    if (!Storage::upload($file['tmp_name'], $relPath)) {
        error_log('uploadFile: Storage::upload falhou para ' . $relPath);
        return ['success' => false, 'message' => 'Falha ao salvar o arquivo.'];
    }

    if (getSetting('s3_auto_sync', '0') === '1' && Storage::isS3Configured()) {
        $localPath = UPLOAD_PATH . '/' . $relPath;
        if (!Storage::mirrorToS3($localPath, $s3Name)) {
            error_log("S3 mirror failed: {$s3Name}");
            enqueueSync($localPath, $s3Name);
        }
    }

    return [
        'success' => true,
        'filename' => $filename,
        'url' => '/uploads/' . $directory . '/' . $filename,
        'path' => UPLOAD_PATH . '/' . $relPath
    ];
}

function uploadRetroRom($file, $consoleSlug, $gameSlug, $type = 'original', $allowedExtensions = null) {
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
    $allowedExtensions = $allowedExtensions ?? ['sfc', 'smc', 'fig', 'bs', 'gb', 'gbc', 'gba', 'nes', 'fds', 'z64', 'n64', 'v64', 'md', 'gen', 'bin', 'cue', 'chd', 'iso', 'zip'];
    if (!in_array($ext, $allowedExtensions)) {
        return ['success' => false, 'message' => 'Extensão não permitida: .' . $ext];
    }

    $mime = getFileMimeType($file['tmp_name']);
    $blockedMimes = ['text/html', 'text/php', 'application/x-php', 'application/x-httpd-php', 'application/x-javascript', 'text/javascript', 'application/json'];
    if (in_array($mime, $blockedMimes)) {
        return ['success' => false, 'message' => 'Tipo de arquivo não permitido.'];
    }

    $consoleSlug = generateSlug($consoleSlug);
    $gameSlug = generateSlug($gameSlug);
    $typeDir = $type === 'modified' ? 'rommod' : 'rom';
    $uploadDir = UPLOAD_PATH . '/retro/' . $consoleSlug . '/' . $typeDir;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = $gameSlug . '.' . $ext;
    $destination = $uploadDir . '/' . $filename;

    if (file_exists($destination)) {
        @unlink($destination);
    }

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'message' => 'Falha ao salvar o arquivo.'];
    }

    $s3Name = 'uploads/retro/' . $consoleSlug . '/' . $typeDir . '/' . $filename;
    if (getSetting('s3_auto_sync', '0') === '1' && Storage::isS3Configured()) {
        if (!Storage::mirrorToS3($destination, $s3Name)) {
            error_log("S3 mirror failed: {$s3Name}");
            enqueueSync($destination, $s3Name);
        }
    }

    return [
        'success' => true,
        'filename' => $filename,
        'url' => '/uploads/retro/' . $consoleSlug . '/' . $typeDir . '/' . $filename,
        'path' => $destination,
        'rel_path' => 'retro/' . $consoleSlug . '/' . $typeDir . '/' . $filename,
        'type_dir' => $typeDir,
    ];
}

function deleteFile($path) {
    $localDeleted = false;
    if (file_exists($path)) {
        $localDeleted = unlink($path);
    }

    if (Storage::isS3Configured()) {
        $s3Name = urlToS3Name($path);
        if ($s3Name) {
            Storage::deleteFromS3($s3Name);
        }
    }

    return $localDeleted;
}

function enqueueSync($localPath, $s3Name, $refTable = '', $refColumn = '', $refId = null) {
    if (empty($localPath) || empty($s3Name)) return;
    try {
        $existing = dbQueryOne("SELECT id FROM sync_queue WHERE s3_name = ?", [$s3Name]);
        if ($existing) return;
        dbExec("INSERT INTO sync_queue (local_path, s3_name, ref_table, ref_column, ref_id, created_at) VALUES (?, ?, ?, ?, ?, ?)",
            [$localPath, $s3Name, $refTable, $refColumn, $refId, date('Y-m-d H:i:s')]);
    } catch (Exception $e) {}
}

function getSyncQueueCount() {
    try {
        $row = dbQueryOne("SELECT COUNT(*) as cnt FROM sync_queue");
        return $row ? (int)$row['cnt'] : 0;
    } catch (Exception $e) {
        return 0;
    }
}

function urlToS3Name($url) {
    if (str_starts_with($url, '/uploads/')) {
        return 'uploads' . substr($url, 8);
    }

    $publicUrl = getSetting('s3_public_url', '');
    if ($publicUrl !== '' && str_starts_with($url, rtrim($publicUrl, '/'))) {
        $after = substr($url, strlen(rtrim($publicUrl, '/')));
        $after = ltrim($after, '/');
        if (str_starts_with($after, 'uploads/')) {
            return $after;
        }
    }

    $endpoint = getSetting('s3_endpoint', '');
    $bucket = getSetting('s3_bucket', '');
    if ($endpoint !== '' && $bucket !== '' && str_contains($url, rtrim($endpoint, '/'))) {
        $parts = explode(rtrim($endpoint, '/') . '/' . $bucket . '/', $url, 2);
        if (isset($parts[1])) return $parts[1];
    }

    if (str_contains($url, '.backblazeb2.com/') && str_contains($url, '/uploads/')) {
        $parts = explode('/uploads/', $url, 2);
        if (isset($parts[1])) return 'uploads/' . $parts[1];
    }

    if (str_contains($url, '/uploads/')) {
        $parts = explode('/uploads/', $url, 2);
        if (isset($parts[1])) return 'uploads/' . $parts[1];
    }

    return null;
}

function revertS3Urls() {
    $tables = [
        ['table' => 'games', 'column' => 'thumbnail_url'],
        ['table' => 'blog_posts', 'column' => 'thumbnail_url'],
        ['table' => 'banners', 'column' => 'image_url'],
        ['table' => 'team_members', 'column' => 'avatar_url'],
        ['table' => 'testimonials', 'column' => 'avatar_url'],
        ['table' => 'users', 'column' => 'avatar_url'],
        ['table' => 'retro_games', 'column' => 'rom_path'],
        ['table' => 'retro_games', 'column' => 'thumbnail_url'],
        ['table' => 'game_templates', 'column' => 'thumbnail_url'],
        ['table' => 'retro_consoles', 'column' => 'thumbnail_url'],
        ['table' => 'store_platforms', 'column' => 'logo_path'],
    ];

    $s3Bases = _revertS3BaseUrls();

    $updated = 0;

    foreach (['site_logo_url', 'site_favicon_url'] as $key) {
        $val = getSetting($key, '');
        if ($val === '') continue;
        $new = _revertSingleUrl($val, $s3Bases);
        if ($new !== null) {
            setSetting($key, $new);
            $updated++;
        }
    }

    foreach ($tables as $t) {
        $rows = dbQuery("SELECT id, {$t['column']} FROM {$t['table']} WHERE {$t['column']} LIKE 'http%'");
        $seen = [];
        foreach ($rows as $row) {
            $id = $row['id'];
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $new = _revertSingleUrl($row[$t['column']], $s3Bases, $t['table'], $t['column']);
            if ($new !== null) {
                dbExec("UPDATE {$t['table']} SET {$t['column']} = ? WHERE id = ?", [$new, $id]);
                $updated++;
            }
        }
    }
    return $updated;
}

function _revertS3BaseUrls() {
    $bases = [];
    $pub = getSetting('s3_public_url', '');
    if ($pub !== '') $bases[] = rtrim($pub, '/');
    if (defined('S3_ENDPOINT') && S3_ENDPOINT) $bases[] = rtrim(S3_ENDPOINT, '/');
    return $bases;
}

function _revertSingleUrl($url, $s3Bases = [], $table = null, $column = null) {
    if ($url === '' || $url === null) return null;
    if (str_starts_with($url, '/')) return null;

    $parts = explode('/uploads/', $url, 2);
    if (isset($parts[1])) {
        if ($table === 'retro_games' && $column === 'rom_path') return $parts[1];
        return '/uploads/' . $parts[1];
    }

    foreach ($s3Bases as $base) {
        $prefix = $base . '/';
        if (str_starts_with($url, $prefix)) {
            $rest = substr($url, strlen($prefix));
            if ($table === 'retro_games' && $column === 'rom_path') return $rest;
            if (str_starts_with($rest, 'uploads/')) return '/' . $rest;
            return '/uploads/' . $rest;
        }
    }

    if (preg_match('#/uploads/[^?#\s]+#', $url, $m)) {
        $path = ltrim($m[0], '/');
        if ($table === 'retro_games' && $column === 'rom_path') return $path;
        return '/' . $path;
    }

    $host = @parse_url($url, PHP_URL_HOST);
    $path = @parse_url($url, PHP_URL_PATH);
    if ($host && $path && $path !== '/') {
        $isS3 = str_contains($host, 'r2.dev')
             || str_contains($host, 'backblaze')
             || str_starts_with($host, 's3.')
             || str_contains($host, '.s3.')
             || str_contains($host, 'cloudfront');
        if ($isS3) {
            $rest = ltrim($path, '/');
            if ($table === 'retro_games' && $column === 'rom_path') return $rest;
            if (str_starts_with($rest, 'uploads/')) return '/' . $rest;
            return '/uploads/' . $rest;
        }
    }

    return null;
}

function truncateText($text, $length = 150) {
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '...';
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

function getEngines($activeOnly = false) {
    static $all = null, $active = null;
    if ($all === null) {
        $db = getDB();
        $all = $db ? dbQuery("SELECT * FROM engines ORDER BY name") : [];
        $active = [];
        foreach ($all as $e) {
            if ($e['active']) $active[] = $e;
        }
    }
    return $activeOnly ? $active : $all;
}

function getEngineIcon($engine) {
    static $icons = null;
    if ($icons === null) {
        $engines = getEngines();
        $icons = [];
        foreach ($engines as $e) {
            $icons[$e['name']] = $e['icon'];
        }
    }
    return $icons[$engine] ?? '🎮';
}

function getEngineColor($engine) {
    static $colors = null;
    if ($colors === null) {
        $engines = getEngines();
        $colors = [];
        foreach ($engines as $e) {
            $colors[$e['name']] = $e['color'];
        }
    }
    return $colors[$engine] ?? 'oklch(68% 0.16 220)';
}

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
        error_log("uploadAndExtractGame: tamanho excedido {$file['size']}");
        return ['success' => false, 'message' => 'Arquivo muito grande. Máximo: ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_GAME_EXTENSIONS)) {
        error_log('uploadAndExtractGame: extensão rejeitada .' . $ext);
        return ['success' => false, 'message' => 'Formato não suportado. Use: ' . implode(', ', ALLOWED_GAME_EXTENSIONS)];
    }

    $mime = getFileMimeType($file['tmp_name']);
    $allowedMimes = ['application/zip', 'application/x-zip-compressed', 'application/gzip', 'application/x-gzip', 'application/x-tar'];
    if (!in_array($mime, $allowedMimes)) {
        error_log("uploadAndExtractGame: MIME rejeitado {$mime} para " . ($file['name'] ?? 'unknown'));
        return ['success' => false, 'message' => 'Tipo de arquivo não permitido. Apenas arquivos .zip compactados são aceitos.'];
    }

    $engineSlug = generateSlug($engine);
    $gameSlug = generateSlug($gameTitle);
    $gameRelDir = 'games/' . $engineSlug . '/' . $gameSlug;
    $gameDir = UPLOAD_PATH . '/' . $gameRelDir;

    if (is_dir($gameDir)) {
        Storage::delete($gameRelDir);
    }

    $tmpRelPath = $gameRelDir . '/_upload.' . $ext;
    if (!Storage::upload($file['tmp_name'], $tmpRelPath)) {
        error_log("uploadAndExtractGame: Storage::upload falhou para {$tmpRelPath}");
        return ['success' => false, 'message' => 'Falha ao salvar o arquivo.'];
    }

    $zipS3Name = 'zips/' . $engineSlug . '/' . $gameSlug . '.' . $ext;
    if (getSetting('s3_auto_sync', '0') === '1' && Storage::isS3Configured()) {
        $tmpFullPath = UPLOAD_PATH . '/' . $tmpRelPath;
        if (!Storage::mirrorToS3($tmpFullPath, $zipS3Name)) {
            error_log("S3 mirror failed: {$zipS3Name}");
            enqueueSync($tmpFullPath, $zipS3Name);
        }
    }

    $extracted = false;
    $extractError = '';

    if ($ext === 'zip') {
        if (Storage::extractZip($tmpRelPath, $gameRelDir)) {
            $extracted = true;
        } else {
            $extractError = 'Não é um arquivo ZIP válido';
        }
    }

    Storage::delete($tmpRelPath);

    if (!$extracted) {
        Storage::delete($gameRelDir);
        return ['success' => false, 'message' => 'Falha ao extrair: ' . $extractError];
    }

    $items = scandir($gameDir);
    $subdirs = [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (is_dir($gameDir . '/' . $item)) {
            $subdirs[] = $item;
        }
    }

    if (count($subdirs) === 1) {
        $onlyDir = $gameDir . '/' . $subdirs[0];
        if (file_exists($onlyDir . '/index.html')) {
            moveDirectoryContents($onlyDir, $gameDir);
            @rmdir($onlyDir);
        }
    }

    if (!file_exists($gameDir . '/index.html')) {
        $found = findIndexHtml($gameDir);
        if ($found) {
            moveDirectoryContents(dirname($found), $gameDir);
            cleanupEmptyDirs($gameDir);
        } else {
            Storage::delete($gameRelDir);
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

    $engineDir = dirname($fullPath);
    if (is_dir($engineDir) && count(scandir($engineDir)) === 2) {
        @rmdir($engineDir);
    }

    if (Storage::isS3Configured()) {
        $parts = explode('/', $gamePath, 2);
        if (count($parts) === 2) {
            $zipS3Name = 'zips/' . $parts[0] . '/' . $parts[1] . '.zip';
            Storage::deleteFromS3($zipS3Name);
        }
    }
}

// ──────────────────────────────────────────────
//  Logo / Favicon Helpers
// ──────────────────────────────────────────────

function logoImgSrc($path) {
    if (empty($path)) return '';
    return mediaUrl($path);
}

function siteLogoUrl() {
    static $cached = null;
    if ($cached !== null) return $cached;
    $url = getSetting('site_logo_url', '');
    $cached = $url !== '' ? mediaUrl($url) : '/assets/svg/logo.svg';
    return $cached;
}

function siteFaviconUrl() {
    static $cached = null;
    if ($cached !== null) return $cached;
    $url = getSetting('site_favicon_url', '');
    $cached = $url !== '' ? mediaUrl($url) : '/assets/svg/logo.svg';
    return $cached;
}

function resizeAndSaveLogo($tmpPath) {
    $info = @getimagesize($tmpPath);
    if (!$info) return false;

    $src = imageCreateFromFile($tmpPath);
    if (!$src) return false;

    $dst = imagescale($src, 256, 256);
    if (!$dst) { imagedestroy($src); return false; }

    $relPath = 'site/logo.png';
    $absPath = UPLOAD_PATH . '/' . $relPath;
    $dir = dirname($absPath);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $ok = imagepng($dst, $absPath);
    imagedestroy($src);
    imagedestroy($dst);
    if (!$ok) return false;

    if (getSetting('s3_auto_sync', '0') === '1' && Storage::isS3Configured()) {
        if (!Storage::mirrorToS3($absPath, 'uploads/' . $relPath)) {
            error_log("S3 mirror failed: uploads/{$relPath}");
        }
    }

    setSetting('site_logo_url', '/uploads/' . $relPath);
    return '/uploads/' . $relPath;
}

function generateFavicons($tmpPath) {
    $info = @getimagesize($tmpPath);
    if (!$info) return false;

    $src = imageCreateFromFile($tmpPath);
    if (!$src) return false;

    $sizes = [16, 32, 180];
    $urls = [];

    foreach ($sizes as $size) {
        $dst = imagescale($src, $size, $size);
        if (!$dst) continue;

        $relPath = "site/favicon-{$size}.png";
        $absPath = UPLOAD_PATH . '/' . $relPath;
        $dir = dirname($absPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        if (imagepng($dst, $absPath)) {
            if (getSetting('s3_auto_sync', '0') === '1' && Storage::isS3Configured()) {
                if (!Storage::mirrorToS3($absPath, 'uploads/' . $relPath)) {
                    error_log("S3 mirror failed: uploads/{$relPath}");
                }
            }
            $urls[$size] = '/uploads/' . $relPath;
        }
        imagedestroy($dst);
    }

    imagedestroy($src);

    if (!empty($urls)) {
        $favUrl = $urls[32] ?? $urls[16] ?? reset($urls);
        setSetting('site_favicon_url', $favUrl);
    }

    return !empty($urls) ? $urls : false;
}

function imageCreateFromFile($path) {
    $info = @getimagesize($path);
    if (!$info) return null;
    switch ($info[2]) {
        case IMAGETYPE_JPEG:  return @imagecreatefromjpeg($path);
        case IMAGETYPE_PNG:   return @imagecreatefrompng($path);
        case IMAGETYPE_GIF:   return @imagecreatefromgif($path);
        case IMAGETYPE_WEBP:  return @imagecreatefromwebp($path);
        default:              return null;
    }
}
