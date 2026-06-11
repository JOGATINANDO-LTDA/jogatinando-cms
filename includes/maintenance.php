<?php

function isMaintenanceActive() {
    // Parent dir check (survives CI/CD that wipes public_html/)
    $parentFile = defined('ROOT_PATH') ? dirname(ROOT_PATH) . '/.maintenance' : __DIR__ . '/../../.maintenance';
    if (file_exists($parentFile)) {
        return true;
    }
    // data/ check (primary for Docker, fast path)
    $maintenanceFile = defined('DATA_PATH') ? DATA_PATH . '/.maintenance' : __DIR__ . '/../data/.maintenance';
    if (file_exists($maintenanceFile)) {
        return true;
    }
    // DB-based check (persists across file overwrites)
    try {
        return getSetting('maintenance_mode') === '1';
    } catch (Exception $e) {
        return false;
    }
}

function renderMaintenancePage() {
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'Jogatinando';
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Retry-After: 3600');
    ?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Em Manutenção — <?= e($siteName) ?></title>
<link rel="icon" href="<?= siteFaviconUrl() ?>" type="image/svg+xml">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:oklch(8% 0.02 260);color:oklch(96% 0.003 250);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;overflow:hidden}
.maint-overlay{position:fixed;inset:0;z-index:0;background:radial-gradient(ellipse 80% 60% at 50% 30%,oklch(18% 0.08 280 / 0.6),transparent),radial-gradient(ellipse 50% 40% at 50% 70%,oklch(14% 0.06 300 / 0.4),transparent),oklch(8% 0.02 260)}
.maint-container{position:relative;z-index:1;text-align:center;max-width:420px;width:100%}
.maint-card{background:oklch(14% 0.03 265);border:1px solid oklch(55% 0.12 85);border-radius:16px;padding:48px 32px;box-shadow:0 0 60px oklch(75% 0.15 85 / 0.1)}
.maint-icon{width:80px;height:80px;margin:0 auto 24px;filter:drop-shadow(0 0 20px oklch(75% 0.15 85 / 0.4));animation:maintPulse 3s ease-in-out infinite}
.maint-icon svg{width:100%;height:100%}
@keyframes maintPulse{0%,100%{filter:drop-shadow(0 0 20px oklch(75% 0.15 85 / 0.4))}50%{filter:drop-shadow(0 0 40px oklch(75% 0.15 85 / 0.7))}}
.maint-card h1{font-family:'Cinzel',Georgia,serif;font-size:24px;font-weight:800;color:oklch(75% 0.15 85);letter-spacing:0.04em;text-transform:uppercase;margin-bottom:16px}
.maint-card p{font-size:15px;color:oklch(60% 0.012 250);line-height:1.7}
.maint-card .maint-line{width:60px;height:2px;background:linear-gradient(90deg,transparent,oklch(75% 0.15 85),transparent);margin:20px auto}
</style>
</head>
<body>
<div class="maint-overlay"></div>
<div class="maint-container">
<div class="maint-card">
<div class="maint-icon">
<svg viewBox="0 0 100 120" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M50 5L8 25v30c0 28 18.5 53.5 42 60 23.5-6.5 42-32 42-60V25L50 5z" fill="oklch(75% 0.15 85 / 0.12)" stroke="oklch(75% 0.15 85)" stroke-width="3"/>
<path d="M50 20L28 32v18c0 17 10 32 22 36 12-4 22-19 22-36V32L50 20z" fill="oklch(75% 0.15 85 / 0.2)" stroke="oklch(75% 0.15 85)" stroke-width="2"/>
<path d="M38 50l8 8 16-16" stroke="oklch(75% 0.15 85)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
</div>
<h1>Em Manutenção</h1>
<div class="maint-line"></div>
<p>Estamos trabalhando para trazer novidades em breve.</p>
</div>
</div>
</body>
</html><?php
    exit;
}
