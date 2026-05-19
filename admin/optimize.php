<?php
$pageTitle = 'Otimizador de Jogos';
require_once '../config.php';
require_once __DIR__ . '/../includes/optimizer.php';
requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? 'index';
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'optimize_all') {
        $results = optimizeAllGames();
        $message = ['type' => 'success', 'results' => $results];
    } elseif ($action === 'optimize_single') {
        $gamePath = $_POST['game_path'] ?? '';
        if ($gamePath) {
            $results = [optimizeGame($gamePath)];
            $message = ['type' => 'success', 'results' => $results];
        }
    }
}

$games = dbQuery("SELECT id, title, engine, game_path, active FROM games WHERE game_path IS NOT NULL AND game_path != '' ORDER BY title");
$html5Games = [];
$totalSize = 0;
foreach ($games as $g) {
    $gameDir = UPLOAD_PATH . '/games/' . $g['game_path'];
    if (is_dir($gameDir)) {
        $size = getTotalGameSize($g['game_path']);
        $totalSize += $size;
        $isHtml5 = isHtml5Game($gameDir);
        $fileCount = count(scanGameFiles($gameDir));
        $hasGz = false;
        $files = scanGameFiles($gameDir);
        foreach ($files as $f) {
            if (substr($f, -3) === '.gz') {
                $hasGz = true;
                break;
            }
        }
        $html5Games[] = [
            'id' => $g['id'],
            'title' => $g['title'],
            'engine' => $g['engine'],
            'game_path' => $g['game_path'],
            'is_html5' => $isHtml5,
            'size' => $size,
            'file_count' => $fileCount,
            'has_gz' => $hasGz,
        ];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">🔧 Otimizador de Jogos HTML5</h2>
        <a href="games.php" class="btn btn-outline btn-sm">← Voltar aos Jogos</a>
    </div>
    <div class="card-body">
        <div class="optimizer-summary">
            <div class="summary-card">
                <span class="summary-value"><?= count($html5Games) ?></span>
                <span class="summary-label">Jogos HTML5</span>
            </div>
            <div class="summary-card">
                <span class="summary-value"><?= formatBytes($totalSize) ?></span>
                <span class="summary-label">Tamanho Total</span>
            </div>
            <div class="summary-card">
                <span class="summary-value"><?= count(array_filter($html5Games, fn($g) => $g['has_gz'])) ?></span>
                <span class="summary-label">Otimizados</span>
            </div>
        </div>

        <?php if ($message && $message['type'] === 'success'): ?>
        <div class="optimization-report" id="optimizationReport">
            <h3>Relatório de Otimização</h3>
            <?php foreach ($message['results'] as $report): ?>
            <?php if ($report['success']): ?>
            <div class="report-game">
                <div class="report-header">
                    <strong><?= e($report['game']) ?></strong>
                    <span class="report-saved">
                        -<?= formatBytes($report['total_saved']) ?> economizado
                    </span>
                </div>
                <div class="report-details">
                    <?php if (!empty($report['bloat_removed'])): ?>
                    <div class="report-item">
                        <span class="report-badge badge-bloat"><?= count($report['bloat_removed']) ?> arquivos removidos</span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($report['images_compressed'])): ?>
                    <div class="report-item">
                        <span class="report-badge badge-img"><?= count($report['images_compressed']) ?> imagens comprimidas</span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($report['gz_generated'])): ?>
                    <div class="report-item">
                        <span class="report-badge badge-gz"><?= count($report['gz_generated']) ?> arquivos .gz gerados</span>
                    </div>
                    <?php endif; ?>
                    <div class="report-sizes">
                        <?= formatBytes($report['original_size']) ?> → <?= formatBytes($report['final_size']) ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="report-game report-error">
                <strong><?= e($report['game']) ?></strong>
                <span><?= e($report['message']) ?></span>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="optimizer-actions">
            <form method="POST" id="optimizeAllForm">
                <input type="hidden" name="action" value="optimize_all">
                <button type="submit" class="btn btn-gold btn-lg" id="optimizeAllBtn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>
                    Otimizar Todos os Jogos
                </button>
            </form>
            <p class="optimizer-hint">Remove arquivos desnecessários, comprime imagens e gera versões .gz para entrega rápida. JS/CSS/HTML não são modificados para preservar compatibilidade com engines de jogo.</p>
        </div>

        <h3 class="games-list-title">Jogos Detectados</h3>
        <div class="optimizer-games-list">
            <?php foreach ($html5Games as $game): ?>
            <div class="optimizer-game-card <?= $game['is_html5'] ? 'is-html5' : '' ?>">
                <div class="game-info">
                    <strong><?= e($game['title']) ?></strong>
                    <span class="game-engine"><?= e($game['engine']) ?></span>
                    <span class="game-path"><?= e($game['game_path']) ?></span>
                </div>
                <div class="game-meta">
                    <span class="meta-size"><?= formatBytes($game['size']) ?></span>
                    <span class="meta-files"><?= $game['file_count'] ?> arquivos</span>
                    <?php if ($game['has_gz']): ?>
                    <span class="meta-optimized">✅ Otimizado</span>
                    <?php else: ?>
                    <span class="meta-pending">⏳ Pendente</span>
                    <?php endif; ?>
                </div>
                <form method="POST" class="game-optimize-form">
                    <input type="hidden" name="action" value="optimize_single">
                    <input type="hidden" name="game_path" value="<?= e($game['game_path']) ?>">
                    <button type="submit" class="btn btn-outline btn-sm">Otimizar</button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php if (empty($html5Games)): ?>
            <div class="empty-state">
                <p>Nenhum jogo com arquivo encontrado.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('optimizeAllForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('optimizeAllBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="spinner" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg> Otimizando...';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
