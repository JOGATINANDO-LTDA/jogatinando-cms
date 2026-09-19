#!/usr/bin/env php
<?php
/**
 * HTML5 Game Optimizer — CLI
 * 
 * Usage:
 *   php scripts/optimize.php                    # Optimize all games
 *   php scripts/optimize.php --game <slug>      # Optimize single game
 *   php scripts/optimize.php --dry-run          # Show what would be done
 *   php scripts/optimize.php --list             # List HTML5 games
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/optimizer.php';

$argv = $argv ?? [];
$opts = [];
foreach ($argv as $i => $arg) {
    if ($i === 0) continue;
    if ($arg === '--dry-run') { $opts['dry_run'] = true; }
    elseif ($arg === '--list') { $opts['list'] = true; }
    elseif ($arg === '--game' && isset($argv[$i + 1])) { $opts['game'] = $argv[$i + 1]; }
}

$db = getDB();
if (!$db) {
    fwrite(STDERR, "ERRO: Banco de dados não disponível.\n");
    exit(1);
}

$games = dbQuery("SELECT id, title, engine, game_path, optimized_at FROM games WHERE game_path IS NOT NULL AND game_path != '' ORDER BY title");

if (empty($games)) {
    echo "Nenhum jogo encontrado.\n";
    exit(0);
}

$html5Games = [];
foreach ($games as $g) {
    $gameDir = UPLOAD_PATH . '/games/' . $g['game_path'];
    if (is_dir($gameDir) && isHtml5Game($gameDir)) {
        $html5Games[] = [
            'title' => $g['title'],
            'engine' => $g['engine'],
            'game_path' => $g['game_path'],
            'optimized_at' => $g['optimized_at'] ?? null,
        ];
    }
}

if (!empty($opts['list'])) {
    echo sprintf("%-40s %-15s %-12s %s\n", "Título", "Engine", "Tamanho", "Otimizado");
    echo str_repeat("-", 80) . "\n";
    foreach ($html5Games as $g) {
        $size = getTotalGameSize($g['game_path']);
        $optimized = $g['optimized_at'] ? date('d/m/Y', strtotime($g['optimized_at'])) : 'Pendente';
        echo sprintf("%-40s %-15s %-12s %s\n", 
            mb_substr($g['title'], 0, 38), 
            $g['engine'], 
            formatBytes($size), 
            $optimized
        );
    }
    echo "\nTotal: " . count($html5Games) . " jogos HTML5\n";
    exit(0);
}

if (!empty($opts['game'])) {
    $found = false;
    foreach ($html5Games as $g) {
        if ($g['game_path'] === $opts['game'] || mb_strtolower($g['title']) === mb_strtolower($opts['game'])) {
            $found = true;
            if (!empty($opts['dry_run'])) {
                echo "[DRY-RUN] Otimizaria: {$g['title']} ({$g['game_path']})\n";
            } else {
                echo "Otimizando: {$g['title']}...\n";
                $result = optimizeGame($g['game_path']);
                printReport([$result]);
            }
            break;
        }
    }
    if (!$found) {
        fwrite(STDERR, "ERRO: Jogo '{$opts['game']}' não encontrado.\n");
        exit(1);
    }
    exit(0);
}

// Optimize all
echo "Jogos HTML5 encontrados: " . count($html5Games) . "\n\n";

if (!empty($opts['dry_run'])) {
    foreach ($html5Games as $g) {
        $size = getTotalGameSize($g['game_path']);
        echo "[DRY-RUN] Otimizaria: {$g['title']} (" . formatBytes($size) . ")\n";
    }
    echo "\nTotal: " . count($html5Games) . " jogos seriam otimizados.\n";
    exit(0);
}

$results = [];
foreach ($html5Games as $g) {
    echo "Otimizando: {$g['title']}...\n";
    $results[] = optimizeGame($g['game_path']);
}

echo "\n";
printReport($results);

function printReport($results) {
    $totalSaved = 0;
    $success = 0;
    $failed = 0;
    foreach ($results as $r) {
        if ($r['success']) {
            $success++;
            $totalSaved += $r['total_saved'];
            $status = $r['total_saved'] > 0 ? "-" . formatBytes($r['total_saved']) : "já otimizado";
            echo "  ✓ {$r['game']}: {$status}\n";
        } else {
            $failed++;
            echo "  ✗ {$r['game']}: {$r['message']}\n";
        }
    }
    echo "\nResumo: {$success} sucesso, {$failed} falhas, " . formatBytes($totalSaved) . " total economizado\n";
}
