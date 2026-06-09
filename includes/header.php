<?php
require_once dirname(__DIR__) . '/config.php';
requireLogin();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$siteName = getSetting('site_name', 'CMS de Jogos');
$initial = strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1));
$avatarUrl = $_SESSION['admin_avatar_url'] ?? '';
if (isset($requiredPerm) && !can($requiredPerm)) {
    header('Location: ' . ADMIN_URL . '/dashboard');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= e($siteName) ?></title>
    <link rel="icon" href="<?= siteFaviconUrl() ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= assetUrl('/assets/css/admin.css') ?>">
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar Overlay (mobile) -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <aside class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-logo">
                <div class="logo-shield">
                    <img src="<?= siteLogoUrl() ?>" alt="<?= e($siteName) ?>">
                </div>
                <div>
                    <span class="logo-text"><?= e($siteName) ?></span>
                    <span class="logo-sub">Painel Admin</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section-label">Principal</div>
                <a href="<?= ADMIN_URL ?>/dashboard" class="nav-item <?= ($currentPage === 'dashboard' || $currentPage === 'index') ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span>
                    <span class="nav-label">Dashboard</span>
                </a>
                <?php if (can('perm_banners')): ?>
                <a href="<?= ADMIN_URL ?>/banners" class="nav-item <?= $currentPage === 'banners' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
                    <span class="nav-label">Banners</span>
                </a>
                <?php endif; ?>
                <?php if (can('perm_games')): ?>
                <a href="<?= ADMIN_URL ?>/games" class="nav-item <?= $currentPage === 'games' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M6 11h4M8 9v4"/><circle cx="15" cy="10.5" r="0.5" fill="currentColor" stroke="none"/><circle cx="17" cy="12.5" r="0.5" fill="currentColor" stroke="none"/><rect x="2" y="6" width="20" height="12" rx="4"/></svg></span>
                    <span class="nav-label">Jogos</span>
                </a>
                <?php endif; ?>
                <?php if (can('perm_blog')): ?>
                <a href="<?= ADMIN_URL ?>/blog" class="nav-item <?= $currentPage === 'blog' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></span>
                    <span class="nav-label">Blog</span>
                </a>
                <?php endif; ?>

                <div class="nav-section-label">Conteúdo</div>
                <?php if (can('perm_testimonials')): ?>
                <a href="<?= ADMIN_URL ?>/testimonials" class="nav-item <?= $currentPage === 'testimonials' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></span>
                    <span class="nav-label">Depoimentos</span>
                </a>
                <?php endif; ?>
                <?php if (can('perm_faq')): ?>
                <a href="<?= ADMIN_URL ?>/faq" class="nav-item <?= $currentPage === 'faq' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
                    <span class="nav-label">FAQ</span>
                </a>
                <?php endif; ?>
                <?php if (can('perm_team')): ?>
                <a href="<?= ADMIN_URL ?>/team" class="nav-item <?= $currentPage === 'team' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></span>
                    <span class="nav-label">Equipe</span>
                </a>
                <?php endif; ?>

                <div class="nav-section-label">Sistema</div>
                <?php if (can('perm_users')): ?>
                <a href="<?= ADMIN_URL ?>/users" class="nav-item <?= $currentPage === 'users' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></span>
                    <span class="nav-label">Usuários</span>
                </a>
                <?php endif; ?>
                <?php if (can('perm_roles')): ?>
                <a href="<?= ADMIN_URL ?>/roles" class="nav-item <?= $currentPage === 'roles' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></span>
                    <span class="nav-label">Cargos</span>
                </a>
                <a href="<?= ADMIN_URL ?>/levels" class="nav-item <?= $currentPage === 'levels' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></span>
                    <span class="nav-label">Níveis</span>
                </a>
                <?php endif; ?>
                <?php if (can('perm_engines')): ?>
                <a href="<?= ADMIN_URL ?>/engines" class="nav-item <?= $currentPage === 'engines' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
                    <span class="nav-label">Engines</span>
                </a>
                <?php endif; ?>
                <?php if (can('perm_platforms')): ?>
                <a href="<?= ADMIN_URL ?>/platforms" class="nav-item <?= $currentPage === 'platforms' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg></span>
                    <span class="nav-label">Plataformas</span>
                </a>
                <?php endif; ?>
                <?php if (can('perm_consoles')): ?>
                <a href="<?= ADMIN_URL ?>/consoles" class="nav-item <?= $currentPage === 'consoles' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><path d="M8 9h8"/></svg></span>
                    <span class="nav-label">Emuladores</span>
                </a>
                <?php endif; ?>
                <?php if (can('perm_retro_games')): ?>
                <a href="<?= ADMIN_URL ?>/retro-games" class="nav-item <?= $currentPage === 'retro-games' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M7 7h10v10H7z"/><path d="M4 12h3M17 12h3M12 4v3M12 17v3"/></svg></span>
                    <span class="nav-label">Jogos Retro</span>
                </a>
                <?php endif; ?>
                <?php if (can('perm_templates')): ?>
                <a href="<?= ADMIN_URL ?>/templates" class="nav-item <?= $currentPage === 'templates' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></span>
                    <span class="nav-label">Templates</span>
                </a>
                <?php endif; ?>
                <?php if (can('perm_optimizer')): ?>
                <a href="<?= ADMIN_URL ?>/optimize" class="nav-item <?= $currentPage === 'optimize' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg></span>
                    <span class="nav-label">Otimizador</span>
                </a>
                <?php endif; ?>
                <?php if (can('perm_settings')): ?>
                <a href="<?= ADMIN_URL ?>/settings" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg></span>
                    <span class="nav-label">Configurações</span>
                </a>
                <?php endif; ?>
                <?php if (($_SESSION['admin_user_id'] ?? 0) === 1): ?>
                <a href="<?= ADMIN_URL ?>/repair" class="nav-item <?= $currentPage === 'repair' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg></span>
                    <span class="nav-label">Diagnóstico</span>
                </a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <a href="<?= SITE_URL ?>" target="_blank" class="nav-item">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></span>
                    <span class="nav-label">Ver Site</span>
                </a>
                <a href="<?= ADMIN_URL ?>/logout" class="nav-item logout">
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
                    <div class="user-avatar">
                        <?php if ($avatarUrl): ?>
                            <img src="<?= e($avatarUrl) ?>" alt="<?= e($_SESSION['admin_username']) ?>">
                        <?php else: ?>
                            <?= $initial ?>
                        <?php endif; ?>
                    </div>
                    <span class="user-name"><?= e($_SESSION['admin_username']) ?></span>
                </div>
            </header>

            <div class="admin-content">
                <?= renderFlash() ?>
