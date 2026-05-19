<?php
require_once dirname(__DIR__) . '/config.php';
requireLogin();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$initial = strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — Jogatinando CMS</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 36 36' fill='none'><path d='M18 2L32 8V20C32 28 26 33 18 35C10 33 4 28 4 20V8L18 2Z' fill='oklch(75%25 0.15 85 / 0.15)' stroke='oklch(75%25 0.15 85)' stroke-width='1.5'/><path d='M18 6L28 10V20C28 26 24 30 18 32C12 30 8 26 8 20V10L18 6Z' fill='oklch(75%25 0.15 85 / 0.1)' stroke='oklch(75%25 0.15 85 / 0.5)' stroke-width='1'/><text x='18' y='19' text-anchor='middle' dominant-baseline='central' font-family='Cinzel, serif' font-size='7' font-weight='800' fill='oklch(75%25 0.15 85)'>JTN</text></svg>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar Overlay (mobile) -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <aside class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-logo">
                <div class="logo-shield">
                    <svg viewBox="0 0 36 36" fill="none">
                        <path d="M18 2L32 8V20C32 28 26 33 18 35C10 33 4 28 4 20V8L18 2Z" fill="oklch(75% 0.15 85 / 0.15)" stroke="oklch(75% 0.15 85)" stroke-width="1.5"/>
                        <path d="M18 6L28 10V20C28 26 24 30 18 32C12 30 8 26 8 20V10L18 6Z" fill="oklch(75% 0.15 85 / 0.1)" stroke="oklch(75% 0.15 85 / 0.5)" stroke-width="1"/>
                        <text x="18" y="19" text-anchor="middle" dominant-baseline="central" font-family="Cinzel, serif" font-size="7" font-weight="800" fill="oklch(75% 0.15 85)">JTN</text>
                    </svg>
                </div>
                <div>
                    <span class="logo-text">Jogatinando</span>
                    <span class="logo-sub">Painel Admin</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section-label">Principal</div>
                <a href="<?= ADMIN_URL ?>/index.php" class="nav-item <?= $currentPage === 'index' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span>
                    <span class="nav-label">Dashboard</span>
                </a>
                <a href="<?= ADMIN_URL ?>/banners.php" class="nav-item <?= $currentPage === 'banners' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
                    <span class="nav-label">Banners</span>
                </a>
                <a href="<?= ADMIN_URL ?>/games.php" class="nav-item <?= $currentPage === 'games' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M6 11h4M8 9v4"/><circle cx="15" cy="10.5" r="0.5" fill="currentColor" stroke="none"/><circle cx="17" cy="12.5" r="0.5" fill="currentColor" stroke="none"/><rect x="2" y="6" width="20" height="12" rx="4"/></svg></span>
                    <span class="nav-label">Jogos</span>
                </a>
                <a href="<?= ADMIN_URL ?>/blog.php" class="nav-item <?= $currentPage === 'blog' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></span>
                    <span class="nav-label">Blog</span>
                </a>

                <div class="nav-section-label">Conteúdo</div>
                <a href="<?= ADMIN_URL ?>/testimonials.php" class="nav-item <?= $currentPage === 'testimonials' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></span>
                    <span class="nav-label">Depoimentos</span>
                </a>
                <a href="<?= ADMIN_URL ?>/faq.php" class="nav-item <?= $currentPage === 'faq' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
                    <span class="nav-label">FAQ</span>
                </a>
                <a href="<?= ADMIN_URL ?>/team.php" class="nav-item <?= $currentPage === 'team' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></span>
                    <span class="nav-label">Equipe</span>
                </a>

                <div class="nav-section-label">Sistema</div>
                <a href="<?= ADMIN_URL ?>/settings.php" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg></span>
                    <span class="nav-label">Configurações</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="<?= SITE_URL ?>" target="_blank" class="nav-item">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></span>
                    <span class="nav-label">Ver Site</span>
                </a>
                <a href="<?= ADMIN_URL ?>/logout.php" class="nav-item logout">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
                    <span class="nav-label">Sair</span>
                </a>
            </div>
        </aside>

        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Menu">
                        <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                    <h1 class="admin-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>
                </div>
                <div class="admin-user">
                    <div class="user-avatar"><?= $initial ?></div>
                    <span class="user-name"><?= e($_SESSION['admin_username']) ?></span>
                </div>
            </header>

            <div class="admin-content">
                <?= renderFlash() ?>
