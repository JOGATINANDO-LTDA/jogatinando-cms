<?php
/**
 * HTML5 Game Optimizer
 * Minifies JS/CSS, compresses images, removes bloat, generates .gz assets
 */

function isHtml5Game($gameDir) {
    return file_exists($gameDir . '/index.html') || file_exists($gameDir . '/index.htm');
}

function scanGameFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

function removeBloatFiles($gameDir) {
    $bloatPatterns = [
        '*.map', '*.log', '*.bak', '*.orig', '*.tmp',
        'Thumbs.db', '.DS_Store', '.gitignore', '.gitattributes',
        'README.md', 'README.txt', 'CHANGELOG.md', 'LICENSE',
        'package.json', 'package-lock.json', 'yarn.lock',
        'tsconfig.json', '.eslintrc*', '.prettierrc*',
        'webpack.config.*', 'vite.config.*', 'rollup.config.*',
        '.env', '.env.*', 'composer.json', 'composer.lock',
    ];
    $removed = [];
    $files = scanGameFiles($gameDir);
    foreach ($files as $file) {
        $basename = basename($file);
        foreach ($bloatPatterns as $pattern) {
            if (fnmatch($pattern, $basename)) {
                if (@unlink($file)) {
                    $removed[] = $file;
                }
                break;
            }
        }
        // Remove .git directories
        if (strpos($file, '/.git/') !== false || $basename === '.git') {
            deleteDirRecursive($file);
            $removed[] = $file;
        }
        // Remove node_modules
        if (strpos($file, '/node_modules/') !== false || $basename === 'node_modules') {
            deleteDirRecursive($file);
            $removed[] = $file;
        }
    }
    return $removed;
}

function deleteDirRecursive($path) {
    if (is_dir($path)) {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($path);
    }
}

function minifyJs($content) {
    // Remove single-line comments (not URLs)
    $content = preg_replace('#(?<!:)//[^\n]*#', '', $content);
    // Remove multi-line comments
    $content = preg_replace('#/\*.*?\*/#s', '', $content);
    // Remove leading/trailing whitespace per line
    $content = preg_replace('/^[ \t]+/m', '', $content);
    $content = preg_replace('/[ \t]+$/m', '', $content);
    // Remove empty lines
    $content = preg_replace('/^\s*\n/m', '', $content);
    // Collapse multiple spaces (not in strings)
    $content = preg_replace('/  +/', ' ', $content);
    return trim($content);
}

function minifyCss($content) {
    // Remove comments
    $content = preg_replace('#/\*.*?\*/#s', '', $content);
    // Remove whitespace around symbols
    $content = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $content);
    // Remove trailing semicolons before }
    $content = preg_replace('/;}/', '}', $content);
    // Remove leading zeros
    $content = preg_replace('/(:|\s)0\.(\d)/', '$1.$2', $content);
    // Remove units for zero values
    $content = preg_replace('/(:|\s)0(px|em|rem|%|vh|vw|deg)/', '$10', $content);
    // Collapse multiple spaces
    $content = preg_replace('/  +/', ' ', $content);
    return trim($content);
}

function minifyHtml($content) {
    // Remove HTML comments (not IE conditionals)
    $content = preg_replace('/<!--(?!\[)(?:(?!-->).)*-->/s', '', $content);
    // Remove whitespace between tags
    $content = preg_replace('/>\s+</s', '><', $content);
    // Remove leading/trailing whitespace per line
    $content = preg_replace('/^[ \t]+/m', '', $content);
    $content = preg_replace('/[ \t]+$/m', '', $content);
    // Remove empty lines
    $content = preg_replace('/^\s*\n/m', '', $content);
    return trim($content);
}

function compressImage($filePath) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $originalSize = filesize($filePath);

    // Try external tools first
    if ($ext === 'png' && is_executable('pngquant')) {
        $tmp = $filePath . '.tmp';
        exec("pngquant --quality=60-80 --skip-if-larger --force --output=\"$tmp\" \"$filePath\" 2>/dev/null", $output, $return);
        if ($return === 0 && file_exists($tmp) && filesize($tmp) < $originalSize) {
            rename($tmp, $filePath);
            return $originalSize - filesize($filePath);
        }
        @unlink($tmp);
    }

    if ($ext === 'jpg' || $ext === 'jpeg') {
        if (is_executable('jpegoptim')) {
            exec("jpegoptim --strip-all --quiet \"$filePath\" 2>/dev/null", $output, $return);
            $newSize = filesize($filePath);
            if ($newSize < $originalSize) return $originalSize - $newSize;
        }
        // Fallback: PHP GD recompress
        if (function_exists('imagecreatefromjpeg')) {
            $img = @imagecreatefromjpeg($filePath);
            if ($img) {
                imagejpeg($img, $filePath, 80);
                imagedestroy($img);
                $newSize = filesize($filePath);
                if ($newSize < $originalSize) return $originalSize - $newSize;
            }
        }
    }

    if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
        $img = @imagecreatefromwebp($filePath);
        if ($img) {
            imagewebp($img, $filePath, 80);
            imagedestroy($img);
            $newSize = filesize($filePath);
            if ($newSize < $originalSize) return $originalSize - $newSize;
        }
    }

    return 0;
}

function generateGz($filePath) {
    $content = file_get_contents($filePath);
    if ($content === false) return false;
    $gzPath = $filePath . '.gz';
    $compressed = gzencode($content, 9);
    if (file_put_contents($gzPath, $compressed) !== false) {
        return strlen($content) - strlen($compressed);
    }
    return false;
}

function optimizeGame($gamePath) {
    $gameDir = UPLOAD_PATH . '/games/' . $gamePath;
    if (!is_dir($gameDir)) {
        return ['success' => false, 'message' => 'Diretório não encontrado'];
    }
    if (!isHtml5Game($gameDir)) {
        return ['success' => false, 'message' => 'Não é um jogo HTML5'];
    }

    $report = [
        'success' => true,
        'game' => $gamePath,
        'bloat_removed' => [],
        'images_compressed' => [],
        'gz_generated' => [],
        'total_saved' => 0,
        'original_size' => 0,
        'final_size' => 0,
    ];

    // Calculate original size
    $files = scanGameFiles($gameDir);
    foreach ($files as $f) {
        $report['original_size'] += filesize($f);
    }

    // 1. Remove bloat
    $report['bloat_removed'] = removeBloatFiles($gameDir);

    // 2. Compress images + generate .gz (NO JS/CSS/HTML minification — game engines break)
    $files = scanGameFiles($gameDir);
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $content = @file_get_contents($file);
        if ($content === false) continue;

        // Compress images
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
            $saved = compressImage($file);
            if ($saved > 0) {
                $report['images_compressed'][] = [
                    'file' => str_replace($gameDir . '/', '', $file),
                    'saved' => $saved,
                ];
                $report['total_saved'] += $saved;
            }
        }

        // Generate .gz for text-based assets (skip binary files)
        if (in_array($ext, ['js', 'css', 'html', 'htm', 'json', 'xml', 'svg', 'wasm', 'ttf', 'woff', 'woff2'])) {
            $saved = generateGz($file);
            if ($saved !== false && $saved > 0) {
                $report['gz_generated'][] = str_replace($gameDir . '/', '', $file) . '.gz';
                $report['total_saved'] += $saved;
            }
        }
    }

    // Calculate final size
    $files = scanGameFiles($gameDir);
    foreach ($files as $f) {
        $report['final_size'] += filesize($f);
    }

    return $report;
}

function optimizeAllGames() {
    $games = dbQuery("SELECT id, title, game_path, engine FROM games WHERE game_path IS NOT NULL AND game_path != '' AND active = 1");
    $results = [];
    foreach ($games as $game) {
        $isHtml5 = isHtml5Game(UPLOAD_PATH . '/games/' . $game['game_path']);
        if ($isHtml5) {
            $results[] = optimizeGame($game['game_path']);
        }
    }
    return $results;
}

function getTotalGameSize($gamePath) {
    $gameDir = UPLOAD_PATH . '/games/' . $gamePath;
    if (!is_dir($gameDir)) return 0;
    $size = 0;
    $files = scanGameFiles($gameDir);
    foreach ($files as $f) {
        $size += filesize($f);
    }
    return $size;
}

function formatBytes($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' KB';
    return number_format($bytes / 1048576, 2) . ' MB';
}
