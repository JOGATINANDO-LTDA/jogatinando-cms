<?php
require_once 'config.php';

$id = (int)($_GET['id'] ?? 0);
$game = dbQueryOne("SELECT * FROM games WHERE id = ? AND active = 1", [$id]);

if (!$game || !$game['zip_filename']) {
    http_response_code(404);
    die('<h1 style="color:white;text-align:center;margin-top:40vh;font-family:sans-serif">Jogo não encontrado</h1>');
}

$zipPath = UPLOAD_PATH . '/games/' . $game['zip_filename'];
$extractPath = UPLOAD_PATH . '/games/' . pathinfo($game['zip_filename'], PATHINFO_FILENAME);
$indexHtml = $extractPath . '/index.html';

// Extract zip if not already done
if (!file_exists($indexHtml) && file_exists($zipPath)) {
    if (!is_dir($extractPath)) {
        mkdir($extractPath, 0755, true);
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) === true) {
        $zip->extractTo($extractPath);
        $zip->close();
    }
}

// Find the index.html (might be in a subfolder)
if (!file_exists($indexHtml)) {
    // Look for index.html in subdirectories
    $files = glob($extractPath . '/*/index.html');
    if (!empty($files)) {
        $indexHtml = $files[0];
    }
}

if (!file_exists($indexHtml)) {
    http_response_code(404);
    die('<h1 style="color:white;text-align:center;margin-top:40vh;font-family:sans-serif">Arquivo do jogo não encontrado. O ZIP deve conter um index.html.</h1>');
}

$gameUrl = UPLOAD_URL . '/games/' . pathinfo($game['zip_filename'], PATHINFO_FILENAME) . '/';
// Adjust if index.html is in a subfolder
if (dirname($indexHtml) !== $extractPath) {
    $subDir = basename(dirname($indexHtml));
    $gameUrl .= $subDir . '/';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($game['title']) ?> — <?= e(getSetting('site_name', 'Jogatinando')) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: oklch(8% 0.02 260); color: oklch(96% 0.003 250); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
        .game-header { padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; background: oklch(10% 0.025 260); border-bottom: 1px solid oklch(55% 0.12 85); }
        .game-header h1 { font-size: 16px; font-weight: 600; }
        .game-header h1 span { color: oklch(75% 0.15 85); }
        .game-header a { color: oklch(75% 0.15 85); font-size: 14px; text-decoration: none; }
        .game-header a:hover { color: oklch(85% 0.13 85); }
        .game-frame { flex: 1; width: 100%; border: none; background: #000; }
        .game-footer { padding: 8px 24px; text-align: center; font-size: 12px; color: oklch(55% 0.015 250); background: oklch(10% 0.025 260); border-top: 1px solid oklch(25% 0.03 260); }
    </style>
</head>
<body>
    <header class="game-header">
        <h1><span>🎮</span> <?= e($game['title']) ?> <span style="font-size:12px;color:oklch(60% 0.012 250);font-weight:400">— <?= e($game['engine']) ?></span></h1>
        <a href="admin/index.php">← Voltar ao site</a>
    </header>
    <iframe class="game-frame" src="<?= e($gameUrl) ?>" allowfullscreen></iframe>
    <footer class="game-footer">
        <?= e(getSetting('site_name', 'Jogatinando')) ?> — <?= e(getSetting('site_tagline', '')) ?>
    </footer>
</body>
</html>
