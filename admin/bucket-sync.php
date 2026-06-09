<?php
ob_start();
$pageTitle = 'Sincronizar com S3 (R2)';
$requiredPerm = 'perm_settings';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

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

$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
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
        $restored = 0; $skipped = 0; $failed = 0;
        $seen = [];
        foreach ($tables as $t) {
            $rows = dbQuery("SELECT id, {$t['column']} FROM {$t['table']} WHERE {$t['column']} != '' AND {$t['column']} IS NOT NULL");
            foreach ($rows as $row) {
                $url = $row[$t['column']];
                if (str_starts_with($url, 'http')) {
                    $pos = strpos($url, '/uploads/');
                    if ($pos === false) continue;
                    $relPath = substr($url, $pos + 1);
                } elseif (str_starts_with($url, '/uploads/')) {
                    $relPath = substr($url, 1);
                } else {
                    continue;
                }
                if (isset($seen[$relPath])) continue;
                $seen[$relPath] = true;
                $localPath = UPLOAD_PATH . '/' . $relPath;
                if (file_exists($localPath)) { $skipped++; continue; }
                $dir = dirname($localPath);
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                if (Storage::downloadFromS3($relPath, $localPath)) {
                    $results[] = "⬇ {$relPath}";
                    $restored++;
                } else {
                    $err = Storage::getS3DownloadError();
                    $results[] = "❌ {$relPath} — {$err}";
                    $failed++;
                }
            }
        }
        foreach (['site_logo_url', 'site_favicon_url'] as $key) {
            $val = getSetting($key, '');
            if ($val === '') continue;
            if (!str_starts_with($val, '/uploads/') && !str_starts_with($val, 'http')) continue;
            $relPath = ltrim($val, '/');
            if (isset($seen[$relPath])) continue;
            $seen[$relPath] = true;
            $localPath = UPLOAD_PATH . '/' . $relPath;
            if (file_exists($localPath)) { $skipped++; continue; }
            $dir = dirname($localPath);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            if (Storage::downloadFromS3($relPath, $localPath)) {
                $results[] = "⬇ {$relPath}";
                $restored++;
            } else {
                $err = Storage::getS3DownloadError();
                $results[] = "❌ {$relPath} — {$err}";
                $failed++;
            }
        }
        // Restore game extraction dirs from S3 zips
        $games = dbQuery("SELECT id, game_path, engine, title FROM games WHERE game_path IS NOT NULL AND game_path != ''");
        foreach ($games as $game) {
            $gameDir = UPLOAD_PATH . '/games/' . $game['game_path'];
            if (is_dir($gameDir)) { $skipped++; continue; }
            $engineSlug = explode('/', $game['game_path'])[0];
            $gameSlug = explode('/', $game['game_path'])[1] ?? $game['game_path'];
            $zipS3Name = 'zips/' . $engineSlug . '/' . $gameSlug . '.zip';
            if (!Storage::s3FileExists($zipS3Name)) { $failed++; continue; }
            if (Storage::extractFromS3Zip($zipS3Name, 'games/' . $game['game_path'])) {
                $results[] = "⬇ games/{$game['game_path']} (extraído do ZIP)";
                $restored++;
            } else {
                $results[] = "❌ games/{$game['game_path']} — falha ao extrair ZIP";
                $failed++;
            }
        }
        $results[] = "Restaurados: {$restored}, ignorados (já existem): {$skipped}, falhas: {$failed}.";
        flashMessage('success', "{$restored} arquivos restaurados do S3.");
    }

    if ($_POST['action'] === 'sync_games') {
        $games = dbQuery("SELECT id, game_path, engine, title FROM games WHERE game_path IS NOT NULL AND game_path != ''");
        foreach ($games as $game) {
            $gameDir = UPLOAD_PATH . '/games/' . $game['game_path'];
            if (!is_dir($gameDir)) continue;
            $engineSlug = explode('/', $game['game_path'])[0];
            $gameSlug = explode('/', $game['game_path'])[1] ?? $game['game_path'];
            $zipS3Name = 'zips/' . $engineSlug . '/' . $gameSlug . '.zip';
            if (!S3::fileExists($zipS3Name)) {
                $tmpZip = UPLOAD_PATH . '/_b2tmp/' . $gameSlug . '.zip';
                if (!is_dir(dirname($tmpZip))) mkdir(dirname($tmpZip), 0755, true);
                $zip = new ZipArchive();
                if ($zip->open($tmpZip, ZipArchive::CREATE) === true) {
                    $items = scandir($gameDir);
                    foreach ($items as $item) {
                        if ($item === '.' || $item === '..') continue;
                        $itemPath = $gameDir . '/' . $item;
                        if (is_file($itemPath)) {
                            $zip->addFile($itemPath, $item);
                        }
                    }
                    $zip->close();
                    if (S3::upload($tmpZip, $zipS3Name)) {
                        $results[] = "✅ {$zipS3Name}";
                    } else {
                        $err = S3::getLastUploadError();
                        $results[] = "❌ {$zipS3Name} — {$err}";
                    }
                    unlink($tmpZip);
                }
            } else {
                $results[] = "⏭ {$zipS3Name} (já existe)";
            }
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
        $directories = ['thumbnails', 'banners', 'blog', 'avatars', 'platforms'];
        foreach ($directories as $dir) {
            $files = getLocalUploadFiles($dir);
            foreach ($files as $f) {
                $result = s3SyncFile($f['local'], $f['s3name']);
                if ($result === 'uploaded') $results[] = "⬆ {$f['s3name']}";
                elseif (str_starts_with($result, 'failed')) {
                    $reason = substr($result, 7);
                    $results[] = "❌ {$f['s3name']} — {$reason}";
                }
            }
        }

        $games = dbQuery("SELECT id, game_path, engine, title FROM games WHERE game_path IS NOT NULL AND game_path != ''");
        foreach ($games as $game) {
            $gameDir = UPLOAD_PATH . '/games/' . $game['game_path'];
            if (!is_dir($gameDir)) continue;
            $engineSlug = explode('/', $game['game_path'])[0];
            $gameSlug = explode('/', $game['game_path'])[1] ?? $game['game_path'];
            $zipS3Name = 'zips/' . $engineSlug . '/' . $gameSlug . '.zip';
            if (!S3::fileExists($zipS3Name)) {
                $tmpZip = UPLOAD_PATH . '/_b2tmp/' . $gameSlug . '.zip';
                if (!is_dir(dirname($tmpZip))) mkdir(dirname($tmpZip), 0755, true);
                $zip = new ZipArchive();
                if ($zip->open($tmpZip, ZipArchive::CREATE) === true) {
                    $items = scandir($gameDir);
                    foreach ($items as $item) {
                        if ($item === '.' || $item === '..') continue;
                        $itemPath = $gameDir . '/' . $item;
                        if (is_file($itemPath)) { $zip->addFile($itemPath, $item); }
                    }
                    $zip->close();
                    if (S3::upload($tmpZip, $zipS3Name)) {
                        $results[] = "⬆ {$zipS3Name}";
                    } else {
                        $err = S3::getLastUploadError();
                        $results[] = "❌ {$zipS3Name} — {$err}";
                    }
                    unlink($tmpZip);
                }
            } else {
                $results[] = "⏭ {$zipS3Name} (já existe)";
            }
        }

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
                        if ($result === 'uploaded') $results[] = "⬆ {$s3Name}";
                        elseif (str_starts_with($result, 'failed')) {
                            $reason = substr($result, 7);
                            $results[] = "❌ {$s3Name} — {$reason}";
                        }
                    }
                }
            }
        }

        $cfg = S3::getResolvedConfig();
        $publicUrl = $cfg['public_url'];
        $bucket = $cfg['bucket'];
        $endpoint = $cfg['endpoint'];
        $baseUrl = $publicUrl !== '' ? rtrim($publicUrl, '/') : '';
        if ($baseUrl === '' && $endpoint !== '' && $bucket !== '') {
            $baseUrl = rtrim($endpoint, '/') . '/' . $bucket;
        }
        if ($baseUrl !== '') {
            $urlTables = [
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
            $updated = 0;
            foreach ($urlTables as $t) {
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
        }
        flashMessage('success', 'Sincronização completa finalizada.');
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
                ['table' => 'game_templates', 'column' => 'thumbnail_url'],
                ['table' => 'retro_consoles', 'column' => 'thumbnail_url'],
                ['table' => 'store_platforms', 'column' => 'logo_path'],
            ];
            $updated = 0;
            foreach ($tables as $t) {
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
                ['table' => 'game_templates', 'column' => 'thumbnail_url'],
                ['table' => 'retro_consoles', 'column' => 'thumbnail_url'],
                ['table' => 'store_platforms', 'column' => 'logo_path'],
            ];
            foreach ($tables as $t) {
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
            ['table' => 'game_templates', 'column' => 'thumbnail_url'],
            ['table' => 'retro_consoles', 'column' => 'thumbnail_url'],
            ['table' => 'store_platforms', 'column' => 'logo_path'],
        ];
        $fixed = 0;
        foreach ($tables as $t) {
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
        $rows = dbQuery("SELECT * FROM sync_queue ORDER BY id ASC");
        $processed = 0; $failed = 0;
        foreach ($rows as $row) {
            if (!file_exists($row['local_path'])) {
                dbExec("DELETE FROM sync_queue WHERE id = ?", [$row['id']]);
                continue;
            }
            if (S3::fileExists($row['s3_name'])) {
                dbExec("DELETE FROM sync_queue WHERE id = ?", [$row['id']]);
                $results[] = "⏭ Já existe no S3: {$row['s3_name']}";
                continue;
            }
            if (S3::upload($row['local_path'], $row['s3_name'])) {
                dbExec("DELETE FROM sync_queue WHERE id = ?", [$row['id']]);
                $processed++;
                $results[] = "✅ {$row['s3_name']}";
            } else {
                $failed++;
                $err = S3::getLastUploadError();
                $results[] = "❌ Falha: {$row['s3_name']} — {$err}";
            }
        }
        $queueCount = getSyncQueueCount();
        $results[] = "Fila processada: {$processed} sincronizados, {$failed} falhas. Restam {$queueCount} pendentes.";
        flashMessage('success', "Fila processada: {$processed} arquivos sincronizados.");
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
            $engineSlug = explode('/', $game['game_path'])[0];
            $gameSlug = explode('/', $game['game_path'])[1] ?? $game['game_path'];
            $zipS3Name = 'zips/' . $engineSlug . '/' . $gameSlug . '.zip';
            if (S3::fileExists($zipS3Name)) {
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
                $results[] = "⏭ {$game['title']} (ZIP não encontrado no S3)";
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
            ['table' => 'game_templates', 'column' => 'thumbnail_url'],
            ['table' => 'retro_consoles', 'column' => 'thumbnail_url'],
            ['table' => 'store_platforms', 'column' => 'logo_path'],
        ];
        $prefixes = ['uploads/thumbnails/', 'uploads/banners/', 'uploads/blog/', 'uploads/avatars/', 'uploads/platforms/', 'uploads/retro/', 'zips/'];
        $s3Files = [];
        foreach ($prefixes as $prefix) {
            $files = S3::listFiles($prefix);
            foreach ($files as $f) {
                $s3Files[$f['key']] = $f;
            }
        }

        $referenced = [];
        foreach ($tables as $t) {
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

        $brokenNoZips = array_filter($broken, fn($k) => !str_starts_with($k, 'zips/'));
        $results[] = "Referências quebradas (no BD mas sem objeto no S3): " . count($brokenNoZips);
        foreach (array_slice($brokenNoZips, 0, 50) as $k) {
            $results[] = "  🔴 {$k}";
        }
        if (count($brokenNoZips) > 50) $results[] = "  ... e mais " . (count($brokenNoZips) - 50) . " quebradas.";
        flashMessage('success', "Verificação concluída: " . count($orphans) . " órfãos no S3, " . count($brokenNoZips) . " referências quebradas.");
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
            ['table' => 'game_templates', 'column' => 'thumbnail_url'],
            ['table' => 'retro_consoles', 'column' => 'thumbnail_url'],
            ['table' => 'store_platforms', 'column' => 'logo_path'],
        ];
        $prefixes = ['uploads/thumbnails/', 'uploads/banners/', 'uploads/blog/', 'uploads/avatars/', 'uploads/platforms/', 'uploads/retro/', 'zips/'];
        $s3Files = [];
        foreach ($prefixes as $prefix) {
            $files = S3::listFiles($prefix);
            foreach ($files as $f) {
                $s3Files[$f['key']] = $f;
            }
        }

        $referenced = [];
        foreach ($tables as $t) {
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

$s3Images = 0; $s3Roms = 0; $s3Zips = 0;
if ($s3Configured) {
    $s3Images = count(S3::listFiles('uploads/thumbnails/')) + count(S3::listFiles('uploads/banners/')) + count(S3::listFiles('uploads/blog/')) + count(S3::listFiles('uploads/avatars/')) + count(S3::listFiles('uploads/platforms/'));
    $s3Roms = count(S3::listFiles('uploads/retro/'));
    $s3Zips = count(S3::listFiles('zips/'));
}

$syncQueueCount = getSyncQueueCount();
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
                <div style="font-size: 13px; color: var(--muted);">Jogos (ZIPs)</div>
                <div style="font-size: 12px; color: oklch(65% 0.18 145);"><?= $s3Zips ?> no S3</div>
            </div>
            <div class="stat-box" style="background: oklch(12% 0.02 260); padding: 16px; border-radius: 8px; border: 1px solid var(--border); text-align:center;">
                <div style="font-size: 28px; font-weight: 800; color: var(--gold);"><?= $localRoms ?></div>
                <div style="font-size: 13px; color: var(--muted);">ROMs (local)</div>
                <div style="font-size: 12px; color: oklch(65% 0.18 145);"><?= $s3Roms ?> no S3</div>
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

<?php if ($syncQueueCount > 0): ?>
<div class="card" style="margin-bottom: 24px; border-color: oklch(55% 0.12 85 / 0.3);">
    <div class="card-body" style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
        <div>
            <strong style="color: var(--gold); font-size: 15px;">Fila de Sincronização</strong>
            <div style="font-size: 13px; color: var(--muted); margin-top: 4px;">
                <?= $syncQueueCount ?> arquivo(s) aguardando sincronização com o S3.
            </div>
        </div>
        <form method="POST" id="sync-queue-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="process_sync_queue">
            <button type="submit" class="btn btn-gold" style="white-space: nowrap;">Processar Fila</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px;">
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="sync_all">
        <button type="submit" class="btn btn-gold" style="font-weight:700;"> Sincronizar Tudo</button>
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
        <button type="submit" class="btn btn-outline" style="color: oklch(65% 0.20 35); border-color: oklch(55% 0.20 35);"> Limpar rfaos do S3</button>
    </form>
</div>

<details style="margin-bottom: 24px;">
    <summary style="cursor: pointer; color: var(--muted); font-size: 13px;">Avanado</summary>
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

<div id="sync-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;flex-direction:column;">
    <div style="width:48px;height:48px;border:4px solid oklch(75% 0.15 85);border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;margin-bottom:16px;"></div>
    <div style="color:#fff;font-size:18px;font-weight:600;">Sincronizando...</div>
    <div style="color:oklch(70% 0.01 250);font-size:14px;margin-top:8px;">Isso pode levar alguns minutos. Não feche a página.</div>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
var confirmMsgs = {
    sync_all: 'Executar sincronizao completa?\n\n1. ⬆ Enviar imagens\n2. ⬆ Enviar ZIPs dos jogos\n3. ⬆ Enviar ROMs\n\nIsso pode levar alguns minutos.',
    sync_images: 'Enviar imagens para o S3 (R2)?',
    sync_games: 'Recriar e enviar ZIPs dos jogos para o S3?',
    sync_roms: 'Enviar ROMs para o S3?',
    restore_from_s3: 'Restaurar arquivos do S3?\n\nBaixa do S3 todos os arquivos referenciados no banco que no existem localmente. Jogos so re-extraos dos ZIPs.',
    fix_broken_urls: 'Corrigir URLs mal formadas no banco de dados?',
    update_db_urls: 'Atualizar todas as URLs do BD para apontar para o S3?',
    revert_db_urls: 'Baixar TODOS os arquivos do S3 e reverter URLs para local (/uploads/...)?\nArquivos que j existem localmente sero ignorados.',
    clean_images: 'Remover imagens locais que j existem no S3?',
    clean_games: 'Remover diretrios de jogos extraos que j possuem ZIP no S3?\nEles sero re-extraos automaticamente ao jogar.',
    clean_roms: 'Remover ROMs locais que j existem no S3?',
    process_sync_queue: 'Enviar arquivos pendentes da fila para o S3?',
    check_integrity: 'Verificar integridade das referncias entre BD e S3?\n\nLista objetos no S3, compara com URLs do BD, mostra rfaos e quebrados.',
    clean_orphans_s3: 'Remover objetos rfaos do S3?\n\nIsso deletar TODOS os objetos do bucket que no tm referncia no banco de dados. Recomendado executar "Verificar Integridade" antes.',
};
document.querySelectorAll('form[method="POST"]').forEach(function(f) {
    f.addEventListener('submit', function(e) {
        var inp = this.querySelector('input[name="action"]');
        if (!inp) return;
        var msg = confirmMsgs[inp.value] || '';
        if (msg && !confirm(msg)) {
            e.preventDefault();
            return;
        }
        document.getElementById('sync-overlay').style.display = 'flex';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
