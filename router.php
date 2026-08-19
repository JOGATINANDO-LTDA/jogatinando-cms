<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');
$uri = $uri ?: '/';

// ---- Public routes (before install check) ----

if ($uri === '/admin/setup-password') {
    require __DIR__ . '/admin/setup-password.php';
    exit;
}



if ($uri === '/install') {
    $isReconfigure = isset($_GET['reconfigure']) && $_GET['reconfigure'] === '1';
    if (!$isReconfigure) {
        $dataDir = __DIR__ . '/data';
        if (file_exists($dataDir . '/config.local.php') || file_exists(dirname(__DIR__) . '/config.local.php')) {
            header('Location: /');
            exit;
        }
    }
    $_SERVER['PHP_SELF'] = '/install.php';
    require __DIR__ . '/install.php';
    exit;
}

if ($uri === '/login') {
    header('Location: /admin/login');
    exit;
}

if ($uri === '/logout') {
    header('Location: /admin/logout');
    exit;
}

require_once __DIR__ . '/config.php';

// ---- Admin Routes ----
if ($uri === '/admin' || strpos($uri, '/admin/') === 0) {
    $relPath = ltrim(substr($uri, 6), '/');
    $adminDir = __DIR__ . '/admin';
    $oldCwd = getcwd();
    chdir($adminDir);

    $requireAdmin = function ($file) use ($oldCwd) {
        $_SERVER['PHP_SELF'] = '/admin/' . $file;
        require $file;
        chdir($oldCwd);
        exit;
    };

    if ($relPath === '') {
        $requireAdmin('index.php');
    }

    if ($relPath === 'index') {
        header('Location: /admin/dashboard', true, 301);
        exit;
    }

    if ($relPath === 'dashboard') {
        $_SERVER['PHP_SELF'] = '/admin/dashboard';
        require $adminDir . '/index.php';
        chdir($oldCwd);
        exit;
    }

    if (strpos($relPath, '..') !== false) {
        http_response_code(404);
        exit;
    }

    $target = $adminDir . '/' . $relPath;

    if (substr($relPath, -4) === '.php') {
        if (is_file($target)) {
            $requireAdmin($relPath);
        }
        http_response_code(404);
        $requireAdmin('index.php');
    }

    if (is_file($target . '.php')) {
        $requireAdmin($relPath . '.php');
    }

    if (is_dir($target) && is_file($target . '/index.php')) {
        $requireAdmin($relPath . '/index.php');
    }

    http_response_code(404);
    $requireAdmin('index.php');
}

// ---- New Frontend Routes (before generic game player) ----

if ($uri === '/autoral') {
    $_GET['type'] = 'autoral';
    require __DIR__ . '/catalogo.php';
    exit;
}

if ($uri === '/cliente') {
    $_GET['type'] = 'cliente';
    require __DIR__ . '/catalogo.php';
    exit;
}

if ($uri === '/externo') {
    $_GET['type'] = 'externo';
    require __DIR__ . '/catalogo.php';
    exit;
}

if ($uri === '/catalogo') {
    require __DIR__ . '/catalogo.php';
    exit;
}

if ($uri === '/retro') {
    require __DIR__ . '/retro.php';
    exit;
}

if (preg_match('#^/retro/([a-zA-Z0-9-]+)$#', $uri, $matches)) {
    $_GET['console'] = $matches[1];
    require __DIR__ . '/retro-console.php';
    exit;
}

if (preg_match('#^/retro/([a-zA-Z0-9-]+)/([a-zA-Z0-9-]+)$#', $uri, $matches)) {
    $_GET['console'] = $matches[1];
    $_GET['slug'] = $matches[2];
    require __DIR__ . '/retro-play.php';
    exit;
}

// ---- Game Player Routes ----
if (preg_match('#^/([a-zA-Z0-9-]+)/([a-zA-Z0-9-]+)$#', $uri, $matches)) {
    $_GET['engine'] = $matches[1];
    $_GET['slug'] = $matches[2];
    require __DIR__ . '/game.php';
    exit;
}

// ---- 404 ----
http_response_code(404);
require __DIR__ . '/404.php';
