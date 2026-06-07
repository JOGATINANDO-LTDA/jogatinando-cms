<?php
http_response_code(404);
require_once __DIR__ . '/config.php';
$siteName = defined('SITE_NAME') ? SITE_NAME : 'Jogatinando';
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página não encontrada — <?= e($siteName) ?></title>
    <meta name="description" content="Página não encontrada">
    <link rel="icon" href="<?= siteFaviconUrl() ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .error-container { display:flex;align-items:center;justify-content:center;min-height:80vh;text-align:center;padding:24px }
        .error-code { font-family:'Cinzel',serif;font-size:120px;font-weight:900;background:linear-gradient(135deg,var(--gold),#e8c84a);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1;margin:0 }
        .error-text { color:var(--muted);font-size:18px;margin:16px 0 32px }
        .error-link { display:inline-block }
    </style>
</head>
<body>
    <div class="cosmic-bg"></div>
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="/" class="navbar-brand">
                <div class="logo-shield"><img src="<?= siteLogoUrl() ?>" alt="Logo"></div>
                <?= e($siteName) ?>
            </a>
        </div>
    </nav>
    <div class="error-container">
        <div>
            <p class="error-code">404</p>
            <p class="error-text">O conteúdo que você procura não foi encontrado.</p>
            <a href="/" class="btn btn-gold error-link">← Voltar ao Início</a>
        </div>
    </div>
</body>
</html>