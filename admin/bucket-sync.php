<?php
ob_start();
$pageTitle = 'Sincronizar com S3 (R2)';
$requiredPerm = 'perm_settings';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

function validateTableSchema($table, $column) {
    $allowed = [
        'games' => ['thumbnail_url'],
        'blog_posts' => ['thumbnail_url'],
        'banners' => ['image_url'],
        'team_members' => ['avatar_url'],
        'testimonials' => ['avatar_url'],
        'users' => ['avatar_url'],
        'retro_games' => ['rom_path', 'thumbnail_url'],
        'retro_consoles' => ['thumbnail_url'],
        'store_platforms' => ['logo_path'],
    ];
    if (!isset($allowed[$table]) || !in_array($column, $allowed[$table], true)) {
        throw new InvalidArgumentException("Tabela/coluna não permitida: {$table}.{$column}");
    }
}

function enqueueFile($localPath, $s3Name, $refTable = '', $refColumn = '', $refId = null) {
    if (!file_exists($localPath)) return false;
    try {
        $existing = dbQueryOne("SELECT id, status FROM sync_queue WHERE s3_name = ?", [$s3Name]);
        if ($existing) {
            if ($existing['status'] === 'failed') {
                dbExec("UPDATE sync_queue SET status='pending', attempts=0, last_error='' WHERE id = ?", [$existing['id']]);
            }
            return true;
        }
        dbExec("INSERT INTO sync_queue (local_path, s3_name, ref_table, ref_column, ref_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', ?)",
            [$localPath, $s3Name, $refTable, $refColumn, $refId, date('Y-m-d H:i:s')]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function enqueueDir($localDir, $s3Prefix) {
    if (!is_dir($localDir)) return 0;
    $count = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($localDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $relPath = substr($file->getPathname(), strlen(rtrim($localDir, '/\\')) + 1);
        $s3Name = rtrim($s3Prefix, '/') . '/' . str_replace('\\', '/', $relPath);
        if (enqueueFile($file->getPathname(), $s3Name)) $count++;
    }
    return $count;
}

function syncQueueStats() {
    try {
        $row = dbQueryOne("SELECT COUNT(*) as total, SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as done, SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending FROM sync_queue");
        return $row ? $row : ['total' => 0, 'done' => 0, 'failed' => 0, 'pending' => 0];
    } catch (Exception $e) {
        return ['total' => 0, 'done' => 0, 'failed' => 0, 'pending' => 0];
    }
}

function getLocalUploadFiles($dir) {
    $files = [];
    $base = UPLOAD_PATH;
    $fullDir = $base . '/' . ltrim($dir, '/');
    if (!is_dir($fullDir)) return $files;
    $items = scandir($fullDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $fullDir . '/' . $item;
        if (is_file($path)) {
            $s3Name = 'uploads/' . $dir . '/' . $item;
            $files[] = ['local' => $path, 's3name' => $s3Name, 'name' => $item];
        }
    }
    return $files;
}

function s3SyncFile($localPath, $s3Name) {
    if (S3::upload($localPath, $s3Name)) return 'uploaded';
    return 'failed: ' . S3::getLastUploadError();
}

function syncDirToS3($localDir, $s3Prefix) {
    if (!is_dir($localDir)) return [];
    $results = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($localDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $relPath = substr($file->getPathname(), strlen($localDir) + 1);
        $s3Name = rtrim($s3Prefix, '/') . '/' . str_replace('\\', '/', $relPath);
        $result = s3SyncFile($file->getPathname(), $s3Name);
        if ($result === 'uploaded') $results[] = "⬆ {$s3Name}";
        elseif (str_starts_with($result, 'failed')) {
            $reason = substr($result, 7);
            $results[] = "❌ {$s3Name} — {$reason}";
        }
    }
    return $results;
}

$isAjax = ($_POST['ajax'] ?? '') === '1' || ($_GET['ajax'] ?? '') === '1';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        if ($isAjax) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'CSRF inválido. Recarregue a página.']);
            exit;
        }
        flashMessage('error', 'Token inválido.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/bucket-sync');
        exit;
    }

    if ($_POST['action'] === 'toggle_auto_sync') {
        setSetting('s3_auto_sync', $_POST['value'] ?? '0');
        flashMessage('success', 'Auto-sync ' . (($_POST['value'] ?? '0') === '1' ? 'ativado' : 'desativado') . '.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/bucket-sync');
        exit;
    }

    if ($_POST['action'] === 'toggle_serve_media') {
        setSetting('s3_serve_media', $_POST['value'] ?? '0');
        flashMessage('success', 'Servir mídia do S3 ' . (($_POST['value'] ?? '0') === '1' ? 'ativado' : 'desativado') . '.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/bucket-sync');
        exit;
    }

    if ($_POST['action'] === 'restore_from_s3') {
        ob_implicit_flush(true);
        echo "<p style='color:var(--gold);'>Restaurando arquivos do S3...</p>\n";
        flush();
        $tables = [
            ['table' => 'games', 'column' => 'thumbnail_url'],
            ['table' => 'blog_posts', 'column' => 'thumbnail_url'],
            ['table' => 'banners', 'column' => 'image_url'],
            ['table' => 'team_members', 'column' => 'avatar_url'],
            ['table' => 'testimonials', 'column' => 'avatar_url'],
            ['table' => 'users', 'column' => 'avatar_url'],
            ['table' => 'retro_games', 'column' => 'rom_path'],
            ['table' => 'retro_games', 'column' => 'thumbnail_url'],
            ['table' => 'retro_consoles', 'column' => 'thumbnail_url'],
            ['table' => 'store_platforms', 'column' => 'logo_path'],
        ];
        $restored = 0; $skipped = 0; $failed = 0;
        $count = 0;
        $seen = [];
        foreach ($tables as $t) {
            validateTableSchema($t['table'], $t['column']);
            $rows = dbQuery("SELECT id, {$t['column']} FROM {$t['table']} WHERE {$t['column']} != '' AND {$t['column']} IS NOT NULL");
            foreach ($rows as $row) {
                $url = $row[$t['column']];
                if (str_starts_with($url, 'http')) {
                    $parts = explode('/uploads/', $url, 2);
                    if (count($parts) < 2) continue;
                    $suffix = $parts[1];
                } elseif (str_starts_with($url, '/uploads/')) {
                    $suffix = substr($url, 9);
                } else {
                    continue;
                }
                $s3Key = 'uploads/' . $suffix;
                if (isset($seen[$s3Key])) continue;
                $seen[$s3Key] = true;
                $localPath = UPLOAD_PATH . '/' . $suffix;
                if (file_exists($localPath)) { $skipped++; continue; }
                $dir = dirname($localPath);
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                if (Storage::downloadFromS3($s3Key, $localPath)) {
                    $results[] = "⬇ {$s3Key}";
                    $restored++;
                } else {
                    $results[] = "❌ {$s3Key} — Erro de download";
                    $failed++;
                }
                $count++;
                if ($count % 5 === 0) {
                    echo "<script>document.getElementById('restore-progress').textContent = '{$restored} restaurados, {$skipped} ignorados, {$failed} falhas';</script>\n";
                    flush();
                }
            }
        }
        foreach (['site_logo_url', 'site_favicon_url'] as $key) {
            $val = getSetting($key, '');
            if ($val === '') continue;
            $parts = explode('/uploads/', $val, 2);
            if (count($parts) < 2) continue;
            $suffix = $parts[1];
            $s3Key = 'uploads/' . $suffix;
            if (isset($seen[$s3Key])) continue;
            $seen[$s3Key] = true;
            $localPath = UPLOAD_PATH . '/' . $suffix;
            if (file_exists($localPath)) { $skipped++; continue; }
            $dir = dirname($localPath);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            if (Storage::downloadFromS3($s3Key, $localPath)) {
                $results[] = "⬇ {$s3Key}";
                $restored++;
            } else {
                $err = Storage::getS3DownloadError();
                $results[] = "❌ {$s3Key} — {$err}";
                $failed++;
            }
        }
        // Restore game dirs from S3
        $glist = dbQuery("SELECT id, game_path, engine, title FROM games WHERE game_path IS NOT NULL AND game_path != ''");
        foreach ($glist as $g) {
            $gameDir = UPLOAD_PATH . '/games/' . $g['game_path'];
            if (is_dir($gameDir)) { $skipped++; continue; }
            $s3Prefix = 'uploads/games/' . $g['game_path'] . '/';
            $s3Files = S3::listFiles($s3Prefix);
            if (empty($s3Files)) { $failed++; continue; }
            foreach ($s3Files as $sf) {
                $rel = substr($sf['key'], strlen($s3Prefix));
                $localFile = $gameDir . '/' . $rel;
                $dir = dirname($localFile);
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                if (Storage::downloadFromS3($sf['key'], $localFile)) {
                    $results[] = "⬇ {$sf['key']}";
                    $restored++;
                } else {
                    $results[] = "❌ {$sf['key']} — Erro de download";
                    $failed++;
                }
                $count++;
                if ($count % 5 === 0) {
                    echo "<script>document.getElementById('restore-progress').textContent = '{$restored} restaurados, {$skipped} ignorados, {$failed} falhas';</script>\n";
                    flush();
                }
            }
        }
        $results[] = "Restaurados: {$restored}, ignorados (já existem): {$skipped}, falhas: {$failed}.";
        echo "<p style='color:oklch(75% .15 85);'>Restauração concluída. Recarregando...</p>\n";
        flush();
        flashMessage('success', "{$restored} arquivos restaurados do S3.");
        ob_end_flush();
    }

    if ($_POST['action'] === 'sync_images') {
        $directories = ['thumbnails', 'banners', 'blog', 'avatars', 'platforms'];
        foreach ($directories as $dir) {
            $localDir = UPLOAD_PATH . '/' . $dir;
            if (!is_dir($localDir)) continue;
            $s3Prefix = 'uploads/' . $dir;
            $r = syncDirToS3($localDir, $s3Prefix);
            array_push($results, ...$r);
            if (empty($r)) $results[] = "⏭ {$dir} (sem arquivos)";
        }
        flashMessage('success', 'Imagens sincronizadas.');
    }

    if ($_POST['action'] === 'sync_games') {
        $games = dbQuery("SELECT id, game_path, engine, title FROM games WHERE game_path IS NOT NULL AND game_path != ''");
        foreach ($games as $game) {
            $gameDir = UPLOAD_PATH . '/games/' . $game['game_path'];
            if (!is_dir($gameDir)) continue;
            $s3Prefix = 'uploads/games/' . $game['game_path'];
            $r = syncDirToS3($gameDir, $s3Prefix);
            array_push($results, ...$r);
            if (empty($r)) $results[] = "⏭ games/{$game['game_path']} (sem arquivos)";
        }
        flashMessage('success', 'Jogos sincronizados.');
    }

    if ($_POST['action'] === 'sync_roms') {
        $retroBase = UPLOAD_PATH . '/retro';
        if (is_dir($retroBase)) {
            $consoles = scandir($retroBase);
            foreach ($consoles as $console) {
                if ($console === '.' || $console === '..') continue;
                foreach (['rom', 'rommod'] as $type) {
                    $typeDir = $retroBase . '/' . $console . '/' . $type;
                    if (!is_dir($typeDir)) continue;
                    $files = scandir($typeDir);
                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..') continue;
                        $localPath = $typeDir . '/' . $file;
                        if (!is_file($localPath)) continue;
                        $s3Name = 'uploads/retro/' . $console . '/' . $type . '/' . $file;
                        $result = s3SyncFile($localPath, $s3Name);
                        if ($result === 'uploaded') $results[] = "✅ {$s3Name}";
                        elseif (str_starts_with($result, 'failed')) {
                            $reason = substr($result, 7);
                            $results[] = "❌ {$s3Name} — {$reason}";
                        }
                    }
                }
            }
        }
        flashMessage('success', 'ROMs sincronizadas.');
    }

    if ($_POST['action'] === 'sync_all') {
        try {
            $directories = ['thumbnails', 'banners', 'blog', 'avatars', 'platforms'];
            $total = 0;
            foreach ($directories as $dir) {
                $files = getLocalUploadFiles($dir);
                foreach ($files as $f) {
                    if (enqueueFile($f['local'], $f['s3name'])) $total++;
                }
            }
            $games = dbQuery("SELECT id, game_path, engine, title FROM games WHERE game_path IS NOT NULL AND game_path != ''");
            foreach ($games as $game) {
                $gameDir = UPLOAD_PATH . '/games/' . $game['game_path'];
                if (!is_dir($gameDir)) continue;
                $s3Prefix = 'uploads/games/' . $game['game_path'];
                $total += enqueueDir($gameDir, $s3Prefix);
            }
            $retroBase = UPLOAD_PATH . '/retro';
            if (is_dir($retroBase)) {
                $consoles = scandir($retroBase);
                foreach ($consoles as $console) {
                    if ($console === '.' || $console === '..') continue;
                    foreach (['rom', 'rommod'] as $type) {
                        $typeDir = $retroBase . '/' . $console . '/' . $type;
                        if (!is_dir($typeDir)) continue;
                        $s3Prefix = 'uploads/retro/' . $console . '/' . $type;
                        $total += enqueueDir($typeDir, $s3Prefix);
                    }
                }
            }
            $results[] = "{$total} arquivos enfileirados para sincronização.";
            if ($isAjax) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'total' => $total, 'message' => "{$total} arquivos enfileirados."]);
                exit;
            }
            flashMessage('success', "{$total} arquivos enfileirados.");
        } catch (Exception $e) {
            if ($isAjax) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            throw $e;
        }
    }

    if ($_POST['action'] === 'update_db_urls') {
        $cfg = S3::getResolvedConfig();
        $publicUrl = $cfg['public_url'];
        $bucket = $cfg['bucket'];
        $endpoint = $cfg['endpoint'];

        $baseUrl = $publicUrl !== '' ? rtrim($publicUrl, '/') : '';
        if ($baseUrl === '' && $endpoint !== '' && $bucket !== '') {
            $baseUrl = rtrim($endpoint, '/') . '/' . $bucket;
        }
        if ($baseUrl === '') {
            flashMessage('error', 'Não foi possível determinar a URL do S3.');
        } else {
            $tables = [
                ['table' => 'games', 'column' => 'thumbnail_url'],
                ['table' => 'blog_posts', 'column' => 'thumbnail_url'],
                ['table' => 'banners', 'column' => 'image_url'],
                ['table' => 'team_members', 'column' => 'avatar_url'],
                ['table' => 'testimonials', 'column' => 'avatar_url'],
                ['table' => 'users', 'column' => 'avatar_url'],
                ['table' => 'retro_games', 'column' => 'rom_path'],
                ['table' => 'retro_games', 'column' => 'thumbnail_url'],
                ['table' => 'retro_consoles', 'column' => 'thumbnail_url'],
                ['table' => 'store_platforms', 'column' => 'logo_path'],
            ];
            $updated = 0;
            foreach ($tables as $t) {
                validateTableSchema($t['table'], $t['column']);
                $rows = dbQuery("SELECT id, {$t['column']} FROM {$t['table']} WHERE {$t['column']} LIKE '/uploads/%'");
                foreach ($rows as $row) {
                    $oldUrl = $row[$t['column']];
                    if (empty($oldUrl)) continue;
                    $s3Name = 'uploads' . substr($oldUrl, 8);
                    $newUrl = rtrim($baseUrl, '/') . '/' . ltrim($s3Name, '/');
                    dbExec("UPDATE {$t['table']} SET {$t['column']} = ? WHERE id = ?", [$newUrl, $row['id']]);
                    $updated++;
                }
            }
            $results[] = "URLs atualizadas: {$updated} registros.";
            flashMessage('success', "{$updated} registros atualizados com URLs do S3.");
        }
    }

    if ($_POST['action'] === 'revert_db_urls') {
        $cfg = S3::getResolvedConfig();
        $publicUrl = rtrim(getSetting('s3_public_url', ''), '/');
        if ($publicUrl === '') $publicUrl = rtrim($cfg['public_url'], '/');
        $downloaded = 0; $skipped = 0; $failed = 0;

        if ($publicUrl !== '') {
            $tables = [
                ['table' => 'games', 'column' => 'thumbnail_url'],
                ['table' => 'blog_posts', 'column' => 'thumbnail_url'],
                ['table' => 'banners', 'column' => 'image_url'],
                ['table' => 'team_members', 'column' => 'avatar_url'],
                ['table' => 'testimonials', 'column' => 'avatar_url'],
                ['table' => 'users', 'column' => 'avatar_url'],
                ['table' => 'retro_games', 'column' => 'rom_path'],
                ['table' => 'retro_games', 'column' => 'thumbnail_url'],
                ['table' => 'retro_consoles', 'column' => 'thumbnail_url'],
                ['table' => 'store_platforms', 'column' => 'logo_path'],
            ];
            foreach ($tables as $t) {
                validateTableSchema($t['table'], $t['column']);
                $rows = dbQuery("SELECT id, {$t['column']} FROM {$t['table']} WHERE {$t['column']} LIKE ?", [$publicUrl . '%']);
                foreach ($rows as $row) {
                    $url = $row[$t['column']];
                    $parts = explode('/uploads/', $url, 2);
                    if (!isset($parts[1])) { $skipped++; continue; }
                    $s3Name = 'uploads/' . $parts[1];
                    $localPath = UPLOAD_PATH . '/' . $parts[1];
                    if (file_exists($localPath)) { $skipped++; continue; }
                    if (Storage::downloadFromS3($s3Name, $localPath)) {
                        $downloaded++;
                        $results[] = "⬇ {$s3Name}";
                    } else {
                        $failed++;
                        $err = Storage::getS3DownloadError();
                        $results[] = "❌ Falha ao baixar: {$s3Name}" . ($err ? " — {$err}" : '');
                    }
                }
            }
        }

        $count = revertS3Urls();
        $results[] = "Baixados: {$downloaded}, ignorados (já existem): {$skipped}, falhas: {$failed}.";
        $results[] = "URLs revertidas: {$count} registros.";
        flashMessage('success', "{$downloaded} arquivos baixados do S3, {$count} URLs revertidas para local.");
    }

    if ($_POST['action'] === 'fix_broken_urls') {
        $tables = [
            ['table' => 'games', 'column' => 'thumbnail_url'],
            ['table' => 'blog_posts', 'column' => 'thumbnail_url'],
            ['table' => 'banners', 'column' => 'image_url'],
            ['table' => 'team_members', 'column' => 'avatar_url'],
            ['table' => 'testimonials', 'column' => 'avatar_url'],
            ['table' => 'users', 'column' => 'avatar_url'],
            ['table' => 'retro_games', 'column' => 'rom_path'],
            ['table' => 'retro_games', 'column' => 'thumbnail_url'],
            ['table' => 'retro_consoles', 'column' => 'thumbnail_url'],
            ['table' => 'store_platforms', 'column' => 'logo_path'],
        ];
        $fixed = 0;
        foreach ($tables as $t) {
            validateTableSchema($t['table'], $t['column']);
            $rows = dbQuery("SELECT id, {$t['column']} FROM {$t['table']} WHERE {$t['column']} LIKE 'http%' OR {$t['column']} LIKE '/uploads/%'");
            foreach ($rows as $row) {
                $old = $row[$t['column']];
                if (empty($old)) continue;
                $new = $old;
                $new = preg_replace('#^https:(?!/)#', 'https://', $new);
                $new = preg_replace('#^http:(?!/)#', 'http://', $new);
                $new = preg_replace('#^http//#', 'http://', $new);
                $new = preg_replace('#(://[^/]+)//uploads/#', '$1/uploads/', $new);
                $new = preg_replace('#(?<!:)/{3,}#', '//', $new);
                $new = preg_replace('#(?<=[^:])/{2,}#', '/', $new);
                $new = preg_replace('#^(https?://)\1+#', '$1', $new);
                $new = preg_replace('#[ \t]+#', '%20', $new);
                if ($new !== $old) {
                    dbExec("UPDATE {$t['table']} SET {$t['column']} = ? WHERE id = ?", [$new, $row['id']]);
                    $fixed++;
                    $results[] = "🔧 {$t['table']}.{$t['column']}: $old → $new";
                }
            }
        }
        $results[] = "URLs corrigidas: {$fixed} registros.";
        flashMessage('success', "{$fixed} URLs corrompidas foram corrigidas.");
    }

    if ($_POST['action'] === 'process_sync_queue') {
        try {
            $batchSize = 5;
            $rows = dbQuery("SELECT * FROM sync_queue WHERE status='pending' ORDER BY id ASC LIMIT ?", [$batchSize]);
            $processed = 0; $failed = 0; $errors = [];
            $currentFile = '';
            foreach ($rows as $row) {
                $currentFile = $row['s3_name'];
                if (!file_exists($row['local_path'])) {
                    dbExec("DELETE FROM sync_queue WHERE id = ?", [$row['id']]);
                    $processed++;
                    continue;
                }
                if (S3::fileExists($row['s3_name'])) {
                    dbExec("UPDATE sync_queue SET status='success', last_error='' WHERE id = ?", [$row['id']]);
                    $processed++;
                    continue;
                }
                $newAttempts = intval($row['attempts']) + 1;
                if (S3::upload($row['local_path'], $row['s3_name'])) {
                    dbExec("UPDATE sync_queue SET status='success', attempts=?, last_error='' WHERE id = ?", [$newAttempts, $row['id']]);
                    $processed++;
                } else {
                    $err = S3::getLastUploadError();
                    if ($newAttempts >= 3) {
                        dbExec("UPDATE sync_queue SET status='failed', attempts=?, last_error=? WHERE id = ?", [$newAttempts, $err, $row['id']]);
                    } else {
                        dbExec("UPDATE sync_queue SET attempts=?, last_error=? WHERE id = ?", [$newAttempts, $err, $row['id']]);
                    }
                    $failed++;
                    $errors[] = "{$row['s3_name']}: {$err}";
                }
            }
            $stats = syncQueueStats();
            if ($isAjax) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'processed' => $processed,
                    'failed' => $failed,
                    'remaining' => intval($stats['pending']),
                    'total' => intval($stats['total']),
                    'done' => intval($stats['done']),
                    'current_file' => $currentFile,
                    'errors' => $errors,
                ]);
                exit;
            }
            $results[] = "Batch: {$processed} sincronizados, {$failed} falhas. Restam {$stats['pending']} pendentes.";
            flashMessage('success', "Batch processado: {$processed} arquivos sincronizados.");
        } catch (Exception $e) {
            if ($isAjax) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            throw $e;
        }
    }

    if ($_POST['action'] === 'sync_status') {
        try {
            $stats = syncQueueStats();
            if ($isAjax) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'total' => intval($stats['total']),
                    'done' => intval($stats['done']),
                    'failed' => intval($stats['failed']),
                    'pending' => intval($stats['pending']),
                ]);
                exit;
            }
            $results[] = "Status: {$stats['done']} concluídos, {$stats['pending']} pendentes, {$stats['failed']} falhas.";
        } catch (Exception $e) {
            if ($isAjax) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            throw $e;
        }
    }

    if ($_POST['action'] === 'retry_failed') {
        try {
            dbExec("UPDATE sync_queue SET status='pending', attempts=0, last_error='' WHERE status='failed'");
            $stats = syncQueueStats();
            if ($isAjax) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'total' => intval($stats['total']),
                    'pending' => intval($stats['pending']),
                    'done' => intval($stats['done']),
                ]);
                exit;
            }
            $results[] = "Falhas reenfileiradas.";
            flashMessage('success', 'Falhas reenfileiradas.');
        } catch (Exception $e) {
            if ($isAjax) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            throw $e;
        }
    }

    if ($_POST['action'] === 'clean_images') {
        $dirs = ['thumbnails', 'banners', 'blog', 'avatars', 'platforms'];
        $total = 0; $deleted = 0; $skipped = 0;
        foreach ($dirs as $dir) {
            $files = getLocalUploadFiles($dir);
            foreach ($files as $f) {
                $total++;
                if (S3::fileExists($f['s3name'])) {
                    if (unlink($f['local'])) {
                        $results[] = "🗑 {$f['s3name']}";
                        $deleted++;
                    } else {
                        $results[] = "❌ Falha ao deletar {$f['local']}";
                    }
                } else {
                    $skipped++;
                }
            }
        }
        $results[] = "Resumo: {$deleted} deletados, {$skipped} ignorados (não existem no S3), {$total} verificados.";
        flashMessage('success', "{$deleted} arquivos locais removidos.");
    }

    if ($_POST['action'] === 'clean_roms') {
        $retroBase = UPLOAD_PATH . '/retro';
        $total = 0; $deleted = 0; $skipped = 0;
        if (is_dir($retroBase)) {
            $consoles = scandir($retroBase);
            foreach ($consoles as $console) {
                if ($console === '.' || $console === '..') continue;
                foreach (['rom', 'rommod'] as $type) {
                    $typeDir = $retroBase . '/' . $console . '/' . $type;
                    if (!is_dir($typeDir)) continue;
                    $files = scandir($typeDir);
                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..') continue;
                        $localPath = $typeDir . '/' . $file;
                        if (!is_file($localPath)) continue;
                        $total++;
                        $s3Name = 'uploads/retro/' . $console . '/' . $type . '/' . $file;
                        if (S3::fileExists($s3Name)) {
                            if (unlink($localPath)) {
                                $results[] = "🗑 {$s3Name}";
                                $deleted++;
                            } else {
                                $results[] = "❌ Falha ao deletar {$localPath}";
                            }
                        } else {
                            $skipped++;
                        }
                    }
                }
            }
        }
        $results[] = "Resumo: {$deleted} deletados, {$skipped} ignorados, {$total} verificados.";
        flashMessage('success', "{$deleted} ROMs locais removidas.");
    }

    if ($_POST['action'] === 'clean_games') {
        $games = dbQuery("SELECT id, game_path, engine, title FROM games WHERE game_path IS NOT NULL AND game_path != ''");
        $total = 0; $deleted = 0; $skipped = 0;
        foreach ($games as $game) {
            $gameDir = UPLOAD_PATH . '/games/' . $game['game_path'];
            if (!is_dir($gameDir)) { $skipped++; continue; }
            $total++;
            $s3Prefix = 'uploads/games/' . $game['game_path'] . '/';
            if (!empty(S3::listFiles($s3Prefix))) {
                $it = new RecursiveDirectoryIterator($gameDir, RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($files as $f) {
                    if ($f->isDir()) { @rmdir($f->getRealPath()); }
                    else { @unlink($f->getRealPath()); }
                }
                @rmdir($gameDir);
                if (!is_dir($gameDir)) {
                    $results[] = "🗑 games/{$game['game_path']}";
                    $deleted++;
                } else {
                    $results[] = "❌ Falha ao remover diretório {$game['game_path']}";
                }
            } else {
                $results[] = "⏭ {$game['title']} (diretório não encontrado no S3)";
                $skipped++;
            }
        }
        $results[] = "Resumo: {$deleted} diretórios removidos, {$skipped} ignorados.";
        flashMessage('success', "{$deleted} diretórios de jogos removidos.");
    }

    if ($_POST['action'] === 'check_integrity') {
        $tables = [
            ['table' => 'games', 'column' => 'thumbnail_url'],
            ['table' => 'blog_posts', 'column' => 'thumbnail_url'],
            ['table' => 'banners', 'column' => 'image_url'],
            ['table' => 'team_members', 'column' => 'avatar_url'],
            ['table' => 'testimonials', 'column' => 'avatar_url'],
            ['table' => 'users', 'column' => 'avatar_url'],
            ['table' => 'retro_games', 'column' => 'rom_path'],
            ['table' => 'retro_games', 'column' => 'thumbnail_url'],
            ['table' => 'retro_consoles', 'column' => 'thumbnail_url'],
            ['table' => 'store_platforms', 'column' => 'logo_path'],
        ];
        $prefixes = ['uploads/thumbnails/', 'uploads/banners/', 'uploads/blog/', 'uploads/avatars/', 'uploads/platforms/', 'uploads/retro/', 'uploads/games/'];
        $s3Files = [];
        foreach ($prefixes as $prefix) {
            $files = S3::listFiles($prefix);
            foreach ($files as $f) {
                $s3Files[$f['key']] = $f;
            }
        }

        $referenced = [];
        foreach ($tables as $t) {
            validateTableSchema($t['table'], $t['column']);
            $rows = dbQuery("SELECT id, {$t['column']} FROM {$t['table']} WHERE {$t['column']} LIKE 'http%' OR {$t['column']} LIKE '/uploads/%'");
            foreach ($rows as $row) {
                $parts = explode('/uploads/', $row[$t['column']], 2);
                if (isset($parts[1])) {
                    $referenced['uploads/' . $parts[1]] = true;
                }
            }
        }
        foreach (['site_logo_url', 'site_favicon_url'] as $key) {
            $val = getSetting($key, '');
            if ($val === '') continue;
            $parts = explode('/uploads/', $val, 2);
            if (isset($parts[1])) {
                $referenced['uploads/' . $parts[1]] = true;
            }
        }

        $orphans = [];
        $broken = [];
        $orphanSize = 0;
        foreach ($s3Files as $key => $info) {
            if (!isset($referenced[$key])) {
                $orphans[] = ['key' => $key, 'size' => $info['size']];
                $orphanSize += $info['size'];
            }
        }
        foreach ($referenced as $key => $v) {
            if (!isset($s3Files[$key])) {
                $broken[] = $key;
            }
        }

        $results[] = "📋 Verificação de integridade:";
        $results[] = "Total de objetos no S3: " . count($s3Files);
        $results[] = "Total de referências no BD: " . count($referenced);
        $results[] = "Órfãos no S3 (sem referência no BD): " . count($orphans) . " (~" . number_format($orphanSize / 1024 / 1024, 1) . " MB)";
        foreach (array_slice($orphans, 0, 50) as $o) {
            $results[] = "  🟠 {$o['key']} (" . number_format($o['size']) . " B)";
        }
        if (count($orphans) > 50) $results[] = "  ... e mais " . (count($orphans) - 50) . " órfãos.";

        $results[] = "Referências quebradas (no BD mas sem objeto no S3): " . count($broken);
        foreach (array_slice($broken, 0, 50) as $k) {
            $results[] = "  🔴 {$k}";
        }
        if (count($broken) > 50) $results[] = "  ... e mais " . (count($broken) - 50) . " quebradas.";
        flashMessage('success', "Verificação concluída: " . count($orphans) . " órfãos no S3, " . count($broken) . " referências quebradas.");
    }

    if ($_POST['action'] === 'clean_orphans_s3') {
        $tables = [
            ['table' => 'games', 'column' => 'thumbnail_url'],
            ['table' => 'blog_posts', 'column' => 'thumbnail_url'],
            ['table' => 'banners', 'column' => 'image_url'],
            ['table' => 'team_members', 'column' => 'avatar_url'],
            ['table' => 'testimonials', 'column' => 'avatar_url'],
            ['table' => 'users', 'column' => 'avatar_url'],
            ['table' => 'retro_games', 'column' => 'rom_path'],
            ['table' => 'retro_games', 'column' => 'thumbnail_url'],
            ['table' => 'retro_consoles', 'column' => 'thumbnail_url'],
            ['table' => 'store_platforms', 'column' => 'logo_path'],
        ];
        $prefixes = ['uploads/thumbnails/', 'uploads/banners/', 'uploads/blog/', 'uploads/avatars/', 'uploads/platforms/', 'uploads/retro/', 'uploads/games/'];
        $s3Files = [];
        foreach ($prefixes as $prefix) {
            $files = S3::listFiles($prefix);
            foreach ($files as $f) {
                $s3Files[$f['key']] = $f;
            }
        }

        $referenced = [];
        foreach ($tables as $t) {
            validateTableSchema($t['table'], $t['column']);
            $rows = dbQuery("SELECT id, {$t['column']} FROM {$t['table']} WHERE {$t['column']} LIKE 'http%' OR {$t['column']} LIKE '/uploads/%'");
            foreach ($rows as $row) {
                $parts = explode('/uploads/', $row[$t['column']], 2);
                if (isset($parts[1])) {
                    $referenced['uploads/' . $parts[1]] = true;
                }
            }
        }
        foreach (['site_logo_url', 'site_favicon_url'] as $key) {
            $val = getSetting($key, '');
            if ($val === '') continue;
            $parts = explode('/uploads/', $val, 2);
            if (isset($parts[1])) {
                $referenced['uploads/' . $parts[1]] = true;
            }
        }

        $deleted = 0; $failed = 0; $skipped = 0;
        foreach ($s3Files as $key => $info) {
            if (!isset($referenced[$key])) {
                if (S3::delete($key)) {
                    $results[] = "🗑 {$key}";
                    $deleted++;
                } else {
                    $results[] = "❌ Falha ao deletar: {$key}";
                    $failed++;
                }
            } else {
                $skipped++;
            }
        }
        $results[] = "Limpeza concluída: {$deleted} deletados, {$failed} falhas, {$skipped} ignorados (referenciados).";
        flashMessage('success', "{$deleted} objetos órfãos removidos do S3.");
    }
}

$s3Configured = Storage::isS3Configured();

$localImages = 0;
$localRoms = 0;
$localGames = 0;
foreach (['thumbnails', 'banners', 'blog', 'avatars', 'platforms'] as $dir) {
    $localImages += count(getLocalUploadFiles($dir));
}
$retroBase = UPLOAD_PATH . '/retro';
if (is_dir($retroBase)) {
    $consoles = scandir($retroBase);
    foreach ($consoles as $console) {
        if ($console === '.' || $console === '..') continue;
        foreach (['rom', 'rommod'] as $type) {
            $typeDir = $retroBase . '/' . $console . '/' . $type;
            if (!is_dir($typeDir)) continue;
            $localRoms += count(array_filter(scandir($typeDir), fn($f) => $f !== '.' && $f !== '..' && is_file($typeDir . '/' . $f)));
        }
    }
}
$localGames = count(dbQuery("SELECT id FROM games WHERE game_path IS NOT NULL AND game_path != ''"));

$s3Images = 0; $s3Roms = 0; $s3Games = 0;
if ($s3Configured) {
    $s3Images = count(S3::listFiles('uploads/thumbnails/')) + count(S3::listFiles('uploads/banners/')) + count(S3::listFiles('uploads/blog/')) + count(S3::listFiles('uploads/avatars/')) + count(S3::listFiles('uploads/platforms/'));
    $s3Roms = count(S3::listFiles('uploads/retro/'));
    $s3Games = count(S3::listFiles('uploads/games/'));
}

$syncQueueStats = syncQueueStats();
?>

<div class="page-header">
    <h1>⬆ Sincronizar com Cloudflare R2 (S3)</h1>
    <p style="color: var(--muted);">Gerencia arquivos entre o servidor local e o bucket S3 (Cloudflare R2).</p>
</div>

<?php if (!$s3Configured): ?>
<div class="status error" style="margin-bottom: 24px;">
    <strong>S3 não configurado.</strong> Configure pelo painel de <a href="/admin/settings" style="color:oklch(75% 0.15 85);">Configurações → Cloudflare R2</a> ou adicione as constantes em <code>data/config.local.php</code>:
    <pre style="margin-top: 8px; background: oklch(12% 0.02 260); padding: 12px; border-radius: 6px; font-size: 13px;">define('S3_ACCESS_KEY', 'seu-access-key');
define('S3_SECRET_KEY', 'sua-secret-key');
define('S3_ENDPOINT', 'https://seu-account-id.r2.cloudflarestorage.com');
define('S3_REGION', 'auto');
define('S3_BUCKET', 'jogatinando-cms');
define('S3_PUBLIC_URL', 'https://pub-xxxxx.r2.dev');</pre>
</div>
<?php else: ?>
<?= renderFlash() ?>

<?php
$autoSync = getSetting('s3_auto_sync', '0');
$serveMedia = getSetting('s3_serve_media', '0');
?>

<div class="card" style="margin-bottom: 24px;">
    <div class="card-body">
        <h3 style="margin-bottom: 16px;">📊 Status</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px;">
            <div class="stat-box" style="background: oklch(12% 0.02 260); padding: 16px; border-radius: 8px; border: 1px solid var(--border); text-align:center;">
                <div style="font-size: 28px; font-weight: 800; color: var(--gold);"><?= $localImages ?></div>
                <div style="font-size: 13px; color: var(--muted);">Imagens (local)</div>
                <div style="font-size: 12px; color: oklch(65% 0.18 145);"><?= $s3Images ?> no S3</div>
            </div>
            <div class="stat-box" style="background: oklch(12% 0.02 260); padding: 16px; border-radius: 8px; border: 1px solid var(--border); text-align:center;">
                <div style="font-size: 28px; font-weight: 800; color: var(--gold);"><?= $localGames ?></div>
                <div style="font-size: 13px; color: var(--muted);">Jogos (local)</div>
                <div style="font-size: 12px; color: oklch(65% 0.18 145);"><?= $s3Games ?> no S3</div>
            </div>
            <div class="stat-box" style="background: oklch(12% 0.02 260); padding: 16px; border-radius: 8px; border: 1px solid var(--border); text-align:center;">
                <div style="font-size: 28px; font-weight: 800; color: var(--gold);"><?= $localRoms ?></div>
                <div style="font-size: 13px; color: var(--muted);">ROMs (local)</div>
                <div style="font-size: 12px; color: oklch(65% 0.18 145);"><?= $s3Roms ?> no S3</div>
            </div>
            <div class="stat-box sync-queue-stat" style="background: oklch(12% 0.02 260); padding: 16px; border-radius: 8px; border: 1px solid var(--border); text-align:center;">
                <div id="queue-total" style="font-size: 28px; font-weight: 800; color: var(--gold);"><?= $syncQueueStats['total'] ?></div>
                <div style="font-size: 13px; color: var(--muted);">Fila de Sync</div>
                <div id="queue-sub" style="font-size: 12px; color: oklch(65% 0.18 145);"><?= $syncQueueStats['done'] ?> ok, <?= $syncQueueStats['pending'] ?> pend, <?= $syncQueueStats['failed'] ?> falhas</div>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom: 24px; border-color: oklch(55% 0.12 85 / 0.3);">
    <div class="card-body">
        <h3 style="margin-bottom: 12px;">Configurações</h3>
        <div style="display: flex; gap: 32px; flex-wrap: wrap;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <form method="POST" id="form-auto-sync" style="display:contents;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_auto_sync">
                    <input type="hidden" name="value" value="<?= $autoSync === '1' ? '0' : '1' ?>">
                </form>
                <div onclick="document.getElementById('form-auto-sync').submit()" style="width: 44px; height: 24px; border-radius: 12px; background: <?= $autoSync === '1' ? 'oklch(75% 0.15 85)' : 'oklch(30% 0.02 260)' ?>; position: relative; transition: background 0.2s;">
                    <div style="width: 20px; height: 20px; border-radius: 50%; background: #fff; position: absolute; top: 2px; left: <?= $autoSync === '1' ? '22px' : '2px' ?>; transition: left 0.2s;"></div>
                </div>
                <span style="font-size: 14px;"><strong>Auto-sync</strong><br><span style="font-size: 12px; color: var(--muted);">Upload automático ao criar/editar</span></span>
            </label>
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <form method="POST" id="form-serve-media" style="display:contents;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_serve_media">
                    <input type="hidden" name="value" value="<?= $serveMedia === '1' ? '0' : '1' ?>">
                </form>
                <div onclick="document.getElementById('form-serve-media').submit()" style="width: 44px; height: 24px; border-radius: 12px; background: <?= $serveMedia === '1' ? 'oklch(75% 0.15 85)' : 'oklch(30% 0.02 260)' ?>; position: relative; transition: background 0.2s;">
                    <div style="width: 20px; height: 20px; border-radius: 50%; background: #fff; position: absolute; top: 2px; left: <?= $serveMedia === '1' ? '22px' : '2px' ?>; transition: left 0.2s;"></div>
                </div>
                <span style="font-size: 14px;"><strong>Servir do S3</strong><br><span style="font-size: 12px; color: var(--muted);">Frontend carrega mídia direto do S3</span></span>
            </label>
        </div>
    </div>
</div>

<?php if ($syncQueueStats['total'] > 0): ?>
<div class="card" id="queue-card" style="margin-bottom: 24px; border-color: oklch(55% 0.12 85 / 0.3);">
    <div class="card-body" style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
        <div>
            <strong style="color: var(--gold); font-size: 15px;">Fila de Sincronização</strong>
            <div style="font-size: 13px; color: var(--muted); margin-top: 4px;">
                <span id="queue-text"><?= $syncQueueStats['pending'] ?> pendente(s), <?= $syncQueueStats['done'] ?> concluído(s), <?= $syncQueueStats['failed'] ?> falha(s).</span>
            </div>
        </div>
        <div style="display: flex; gap: 8px;">
            <button id="process-queue-btn" class="btn btn-gold" style="white-space: nowrap;">Processar Fila</button>
            <?php if ($syncQueueStats['failed'] > 0): ?>
            <button id="retry-queue-btn" class="btn btn-outline" style="white-space: nowrap; color: oklch(75% 0.15 85); border-color: oklch(55% 0.12 85);">Reenfileirar Falhas</button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="csrf-data" style="display:none;" data-token="<?= getCSRFToken() ?>"></div>

<div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px;">
    <form method="POST" id="sync-all-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="sync_all">
        <input type="hidden" name="ajax" value="1">
        <button type="button" id="sync-all-btn" class="btn btn-gold" style="font-weight:700;"> Sincronizar Tudo</button>
    </form>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="restore_from_s3">
        <button type="submit" class="btn btn-gold" style="background: oklch(55% 0.18 195);"> Restaurar do S3</button>
    </form>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="check_integrity">
        <button type="submit" class="btn btn-outline" style="color: oklch(75% 0.18 195); border-color: oklch(55% 0.12 195);"> Verificar Integridade</button>
    </form>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="clean_orphans_s3">
        <button type="submit" class="btn btn-outline" style="color: oklch(65% 0.20 35); border-color: oklch(55% 0.20 35);"> Limpar órfãos do S3</button>
    </form>
</div>

<details style="margin-bottom: 24px;">
    <summary style="cursor: pointer; color: var(--muted); font-size: 13px;">Avançado</summary>
    <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px;">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="sync_images">
            <button type="submit" class="btn btn-outline"> Sync Imagens</button>
        </form>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="sync_games">
            <button type="submit" class="btn btn-outline"> Sync Jogos</button>
        </form>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="sync_roms">
            <button type="submit" class="btn btn-outline"> Sync ROMs</button>
        </form>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="fix_broken_urls">
            <button type="submit" class="btn btn-outline" style="color: oklch(75% 0.15 85); border-color: oklch(55% 0.12 85);"> Corrigir URLs</button>
        </form>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_db_urls">
            <button type="submit" class="btn btn-outline"> URLs para S3</button>
        </form>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="revert_db_urls">
            <button type="submit" class="btn btn-outline" style="color: oklch(55% 0.20 25); border-color: oklch(55% 0.20 25);"> URLs Local</button>
        </form>
    </div>
</details>

<details style="margin-bottom: 24px;">
    <summary style="cursor: pointer; color: var(--muted); font-size: 13px;">Limpar Arquivos Locais</summary>
    <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px;">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="clean_images">
            <button type="submit" class="btn btn-outline" style="color: oklch(65% 0.18 145); border-color: oklch(65% 0.18 145);"> Limpar Imagens</button>
        </form>
        <form method="POST" onsubmit="return confirm('Remover diretrios de jogos extraos que j possuem ZIP no S3? Eles sero re-extraos automaticamente ao jogar.')">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="clean_games">
            <button type="submit" class="btn btn-outline" style="color: oklch(65% 0.18 145); border-color: oklch(65% 0.18 145);"> Limpar Jogos</button>
        </form>
        <form method="POST" onsubmit="return confirm('Remover ROMs locais que j existem no S3?')">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="clean_roms">
            <button type="submit" class="btn btn-outline" style="color: oklch(65% 0.18 145); border-color: oklch(65% 0.18 145);"> Limpar ROMs</button>
        </form>
    </div>
</details>
<?php endif; ?>

<?php if (!empty($results)): ?>
<div class="card">
    <div class="card-body">
        <h3 style="margin-bottom: 12px;">Resultados</h3>
        <div style="max-height: 400px; overflow-y: auto; font-size: 13px; line-height: 1.8;">
            <?php foreach ($results as $r): ?>
            <div><?= $r ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="sync-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;flex-direction:column;padding:24px;">
    <div style="background:oklch(15% 0.02 260);border:1px solid var(--border);border-radius:12px;padding:32px;max-width:560px;width:100%;">
        <h3 style="margin-bottom:16px;text-align:center;">Sincronizando com S3</h3>
        <div id="sync-progress-bar" style="width:100%;height:10px;background:oklch(25% 0.02 260);border-radius:5px;overflow:hidden;margin-bottom:12px;">
            <div id="sync-progress-fill" style="width:0%;height:100%;background:oklch(75% 0.15 85);border-radius:5px;transition:width 0.3s;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--muted);margin-bottom:8px;">
            <span id="sync-progress-text">0 / 0</span>
            <span id="sync-file-text">—</span>
        </div>
        <div id="sync-counts" style="display:flex;gap:16px;justify-content:center;font-size:13px;margin-bottom:12px;">
            <span>✅ <span id="sync-done">0</span></span>
            <span>⏳ <span id="sync-pending">0</span></span>
            <span>❌ <span id="sync-failed-count">0</span></span>
        </div>
        <div id="sync-errors" style="max-height:120px;overflow-y:auto;font-size:12px;color:oklch(65% 0.20 35);margin-bottom:12px;display:none;"></div>
        <div style="display:flex;gap:8px;justify-content:center;">
            <button id="sync-stop-btn" class="btn btn-outline" style="font-size:13px;">Fechar</button>
            <button id="sync-retry-btn" class="btn btn-gold" style="font-size:13px;display:none;">Reenfileirar Falhas</button>
        </div>
    </div>
</div>

<div id="sync-badge" style="display:none;position:fixed;bottom:24px;right:24px;z-index:9998;background:oklch(15% 0.02 260);border:1px solid oklch(55% 0.12 85);border-radius:12px;padding:12px 16px;cursor:pointer;box-shadow:0 4px 20px rgba(0,0,0,0.5);min-width:200px;">
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:12px;height:12px;border:2px solid oklch(75% 0.15 85);border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;flex-shrink:0;"></div>
        <div style="flex:1;">
            <div style="font-size:13px;font-weight:600;color:oklch(75% 0.15 85);">Sincronizando</div>
            <div id="sync-badge-text" style="font-size:11px;color:var(--muted);">0 / 0</div>
        </div>
    </div>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
var csrfToken = document.getElementById('csrf-data') ? document.getElementById('csrf-data').getAttribute('data-token') : '';
var syncRunning = false;
var syncStopped = false;
var syncCompleted = false;
var badgeTimer = null;

function closeOverlay() {
    syncRunning = false;
    syncCompleted = true;
    hideBadge();
    document.getElementById('sync-overlay').style.display = 'none';
}

function updateBadgeStats() {
    var totalEl = document.getElementById('queue-total');
    var subEl = document.getElementById('queue-sub');
    if (totalEl && subEl) {
        showBadge(subEl.textContent);
    }
}

function showBadge(text) {
    var badge = document.getElementById('sync-badge');
    var badgeText = document.getElementById('sync-badge-text');
    if (!badge) return;
    if (text && badgeText) badgeText.textContent = text;
    badge.style.display = 'block';
}

function hideBadge() {
    var badge = document.getElementById('sync-badge');
    if (!badge) return;
    badge.style.display = 'none';
    if (badgeTimer) { clearTimeout(badgeTimer); badgeTimer = null; }
}

function parseJSON(r) {
    var ct = r.headers.get('content-type') || '';
    if (ct.indexOf('application/json') === -1) {
        return r.text().then(function(text) {
            var preview = text.substring(0, 200);
            throw new Error('Resposta inesperada (HTML). Preview: ' + preview);
        });
    }
    return r.json();
}

function updateQueueUI(stats) {
    var el = document.getElementById('queue-total');
    if (el) el.textContent = stats.total;
    var sub = document.getElementById('queue-sub');
    if (sub) sub.textContent = stats.done + ' ok, ' + stats.pending + ' pend, ' + stats.failed + ' falhas';
    var txt = document.getElementById('queue-text');
    if (txt) txt.textContent = stats.pending + ' pendente(s), ' + stats.done + ' concluído(s), ' + stats.failed + ' falha(s).';
    document.getElementById('sync-done').textContent = stats.done;
    document.getElementById('sync-pending').textContent = stats.pending;
    document.getElementById('sync-failed-count').textContent = stats.failed;
    var total = parseInt(stats.total) || 0;
    var done = parseInt(stats.done) || 0;
    var failed = parseInt(stats.failed) || 0;
    if (total > 0) {
        var pct = Math.round((done + failed) / total * 100);
        document.getElementById('sync-progress-fill').style.width = pct + '%';
        document.getElementById('sync-progress-text').textContent = (done + failed) + ' / ' + total;
    } else {
        document.getElementById('sync-progress-fill').style.width = '100%';
        document.getElementById('sync-progress-text').textContent = '0 / 0';
    }
}

function fetchQueueStatus() {
    var fd = new FormData();
    fd.append('action', 'sync_status');
    fd.append('csrf_token', csrfToken);
    fd.append('ajax', '1');
    return fetch(window.location.href, { method: 'POST', body: fd }).then(parseJSON);
}

function processQueueBatch() {
    if (syncStopped) {
        syncRunning = false;
        return Promise.resolve();
    }
    var fd = new FormData();
    fd.append('action', 'process_sync_queue');
    fd.append('csrf_token', csrfToken);
    fd.append('ajax', '1');
    return fetch(window.location.href, { method: 'POST', body: fd }).then(parseJSON).then(function(data) {
        if (data.current_file) {
            document.getElementById('sync-file-text').textContent = data.current_file.split('/').pop();
        }
        if (data.errors && data.errors.length > 0) {
            var errDiv = document.getElementById('sync-errors');
            errDiv.style.display = 'block';
            data.errors.forEach(function(e) {
                var d = document.createElement('div');
                d.textContent = '❌ ' + e;
                errDiv.appendChild(d);
            });
        }
        return fetchQueueStatus().then(function(stats) {
            updateQueueUI(stats);
            if (parseInt(stats.pending) > 0 && !syncStopped) {
                return processQueueBatch();
            } else {
                var totalDone = parseInt(stats.total) || 0;
                document.getElementById('sync-file-text').textContent = '✅ Completo — ' + totalDone + ' arquivo(s)';
                var failedCount = parseInt(stats.failed) || 0;
                updateBadgeStats();
                if (failedCount > 0) {
                    var btn = document.getElementById('sync-stop-btn');
                    if (btn) {
                        var retryBtn = document.getElementById('sync-retry-btn');
                        btn.textContent = 'Fechar';
                        btn.classList.remove('btn-outline');
                        btn.classList.add('btn-gold');
                        if (retryBtn) retryBtn.style.display = 'inline-block';
                    }
                } else {
                    setTimeout(closeOverlay, 1500);
                }
                syncRunning = false;
                syncCompleted = true;
                return stats;
            }
        });
    }).catch(function(err) {
        syncRunning = false;
        document.getElementById('sync-file-text').textContent = 'Erro: ' + err.message;
        setTimeout(closeOverlay, 2000);
        return null;
    });
}

function resetOverlay(actionTitle) {
    var titleEl = document.querySelector('#sync-overlay h3');
    if (titleEl) titleEl.textContent = actionTitle || 'Sincronizando com S3';
    document.getElementById('sync-overlay').style.display = 'flex';
    document.getElementById('sync-errors').style.display = 'none';
    document.getElementById('sync-errors').innerHTML = '';
    document.getElementById('sync-retry-btn').style.display = 'none';
    document.getElementById('sync-file-text').textContent = 'Enfileirando...';
    var stopBtn = document.getElementById('sync-stop-btn');
    if (stopBtn) {
        stopBtn.textContent = 'Fechar';
        stopBtn.classList.remove('btn-gold');
        stopBtn.classList.add('btn-outline');
    }
}

function startSync() {
    syncRunning = true;
    syncStopped = false;
    syncCompleted = false;
    hideBadge();
    resetOverlay('Sincronizando com S3');

    var fd = new FormData();
    fd.append('action', 'sync_all');
    fd.append('csrf_token', csrfToken);
    fd.append('ajax', '1');
    fetch(window.location.href, { method: 'POST', body: fd }).then(parseJSON).then(function(data) {
        if (data.success) {
            document.getElementById('sync-file-text').textContent = data.total + ' arquivos enfileirados.';
            return fetchQueueStatus();
        } else {
            syncRunning = false;
            var msg = data.error || 'Erro ao enfileirar.';
            document.getElementById('sync-file-text').textContent = msg;
            var errDiv = document.getElementById('sync-errors');
            errDiv.style.display = 'block';
            errDiv.textContent = '❌ ' + msg;
            setTimeout(closeOverlay, 2000);
        }
    }).then(function(stats) {
        if (stats) {
            updateQueueUI(stats);
            return processQueueBatch();
        }
    }).catch(function(err) {
        syncRunning = false;
        document.getElementById('sync-file-text').textContent = 'Erro: ' + err.message;
        var errDiv = document.getElementById('sync-errors');
        errDiv.style.display = 'block';
        errDiv.textContent = '❌ ' + err.message;
        setTimeout(closeOverlay, 2000);
    });
}

function retryFailed() {
    var fd = new FormData();
    fd.append('action', 'retry_failed');
    fd.append('csrf_token', csrfToken);
    fd.append('ajax', '1');
    document.getElementById('sync-retry-btn').style.display = 'none';
    document.getElementById('sync-errors').innerHTML = '';
    document.getElementById('sync-errors').style.display = 'none';
    fetch(window.location.href, { method: 'POST', body: fd }).then(parseJSON).then(function(data) {
        if (data.success) {
        return fetchQueueStatus().then(function(stats) {
            updateQueueUI(stats);
                if (parseInt(stats.pending) > 0) {
                    processQueueBatch();
                }
            });
        } else {
            var errDiv = document.getElementById('sync-errors');
            errDiv.style.display = 'block';
            errDiv.textContent = '❌ ' + (data.error || 'Erro ao reenfileirar.');
        }
    }).catch(function(err) {
        var errDiv = document.getElementById('sync-errors');
        errDiv.style.display = 'block';
        errDiv.textContent = '❌ ' + err.message;
    });
}

// Button handlers
var syncAllBtn = document.getElementById('sync-all-btn');
if (syncAllBtn) {
    syncAllBtn.addEventListener('click', function() {
        if (syncRunning) return;
        if (!confirm('Enfileirar todos os arquivos locais para sincronização?\n\nIsso irá enfileirar imagens, jogos e ROMs para upload ao S3.')) return;
        startSync();
    });
}

var processQueueBtn = document.getElementById('process-queue-btn');
if (processQueueBtn) {
    processQueueBtn.addEventListener('click', function() {
        if (syncRunning) return;
        syncStopped = false;
        syncRunning = true;
        resetOverlay('Processando fila...');
        processQueueBatch();
    });
}

var retryBtn = document.getElementById('retry-queue-btn');
if (retryBtn) {
    retryBtn.addEventListener('click', function() {
        if (syncRunning) return;
        retryFailed();
    });
}

var syncRetryBtn = document.getElementById('sync-retry-btn');
if (syncRetryBtn) {
    syncRetryBtn.addEventListener('click', function() {
        retryFailed();
    });
}

var syncStopBtn = document.getElementById('sync-stop-btn');
if (syncStopBtn) {
    syncStopBtn.addEventListener('click', function() {
        document.getElementById('sync-overlay').style.display = 'none';
        updateBadgeStats();
    });
}

var syncBadge = document.getElementById('sync-badge');
if (syncBadge) {
    syncBadge.addEventListener('click', function() {
        document.getElementById('sync-overlay').style.display = 'flex';
        hideBadge();
    });
}

// Confirm dialogs for non-AJAX forms
var confirmMsgs = {
    sync_all: 'Executar sincronização completa?\n\nIsso pode levar alguns minutos.',
    sync_images: 'Enviar imagens para o S3 (R2)?',
    sync_games: 'Enviar jogos para o S3?',
    sync_roms: 'Enviar ROMs para o S3?',
    restore_from_s3: 'Restaurar arquivos do S3?\n\nBaixa do S3 todos os arquivos referenciados no banco que não existem localmente.',
    fix_broken_urls: 'Corrigir URLs mal formadas no banco de dados?',
    update_db_urls: 'Atualizar todas as URLs do BD para apontar para o S3?',
    revert_db_urls: 'Baixar TODOS os arquivos do S3 e reverter URLs para local (/uploads/...)?\nArquivos que já existem localmente serão ignorados.',
    clean_images: 'Remover imagens locais que já existem no S3?',
    clean_games: 'Remover diretórios de jogos extraídos que já estão no S3?',
    clean_roms: 'Remover ROMs locais que já existem no S3?',
    process_sync_queue: 'Enviar arquivos pendentes da fila para o S3?',
    check_integrity: 'Verificar integridade das referências entre BD e S3?\n\nLista objetos no S3, compara com URLs do BD, mostra órfãos e quebrados.',
    clean_orphans_s3: 'Remover objetos órfãos do S3?\n\nIsso deletará TODOS os objetos do bucket que não têm referência no banco de dados.',
};
var overlayTitles = {
    sync_images: 'Sincronizando imagens...',
    sync_games: 'Sincronizando jogos...',
    sync_roms: 'Sincronizando ROMs...',
    restore_from_s3: 'Restaurando do S3...',
    fix_broken_urls: 'Corrigindo URLs...',
    update_db_urls: 'Atualizando URLs...',
    revert_db_urls: 'Revertendo URLs...',
    clean_images: 'Limpando imagens locais...',
    clean_games: 'Limpando jogos locais...',
    clean_roms: 'Limpando ROMs locais...',
    process_sync_queue: 'Processando fila...',
    check_integrity: 'Verificando integridade...',
    clean_orphans_s3: 'Limpando objetos órfãos...',
};
document.querySelectorAll('form[method="POST"]').forEach(function(f) {
    f.addEventListener('submit', function(e) {
        var inp = this.querySelector('input[name="action"]');
        if (!inp) return;
        if (inp.value === 'sync_all') return; // handled by AJAX
        var msg = confirmMsgs[inp.value] || '';
        if (msg && !confirm(msg)) {
            e.preventDefault();
            return;
        }
        resetOverlay(overlayTitles[inp.value] || 'Processando...');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
