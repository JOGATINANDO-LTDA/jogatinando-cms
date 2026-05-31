<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$gameCount = $db->query("SELECT COUNT(*) FROM games WHERE active = 1")->fetchColumn();
$bannerCount = $db->query("SELECT COUNT(*) FROM banners WHERE active = 1")->fetchColumn();
$postCount = $db->query("SELECT COUNT(*) FROM blog_posts WHERE active = 1")->fetchColumn();
$testimonialCount = $db->query("SELECT COUNT(*) FROM testimonials WHERE active = 1")->fetchColumn();
$faqCount = $db->query("SELECT COUNT(*) FROM faq_items WHERE active = 1")->fetchColumn();
$teamCount = $db->query("SELECT COUNT(*) FROM team_members WHERE active = 1")->fetchColumn();

// Recent games
$recentGames = dbQuery("SELECT * FROM games ORDER BY created_at DESC LIMIT 5");
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24"><path d="M6 11h4M8 9v4"/><circle cx="15" cy="10.5" r="0.5" fill="currentColor" stroke="none"/><circle cx="17" cy="12.5" r="0.5" fill="currentColor" stroke="none"/><rect x="2" y="6" width="20" height="12" rx="4"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-number"><?= $gameCount ?></div>
            <div class="stat-label">Jogos Ativos</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-number"><?= $bannerCount ?></div>
            <div class="stat-label">Banners Ativos</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-number"><?= $postCount ?></div>
            <div class="stat-label">Posts Blog</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-number"><?= $testimonialCount ?></div>
            <div class="stat-label">Depoimentos</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Acesso Rápido</h2>
    </div>
    <div class="card-body">
        <div class="quick-links">
            <a href="games?action=new" class="quick-link">
                <div class="quick-link-icon">
                    <svg viewBox="0 0 24 24"><path d="M6 11h4M8 9v4"/><circle cx="15" cy="10.5" r="0.5" fill="currentColor" stroke="none"/><circle cx="17" cy="12.5" r="0.5" fill="currentColor" stroke="none"/><rect x="2" y="6" width="20" height="12" rx="4"/></svg>
                </div>
                <div class="quick-link-text">
                    <h3>Novo Jogo</h3>
                    <p>Adicionar jogo ao portfólio</p>
                </div>
            </a>
            <a href="banners?action=new" class="quick-link">
                <div class="quick-link-icon">
                    <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                </div>
                <div class="quick-link-text">
                    <h3>Novo Banner</h3>
                    <p>Adicionar slide ao carousel</p>
                </div>
            </a>
            <a href="blog?action=new" class="quick-link">
                <div class="quick-link-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                </div>
                <div class="quick-link-text">
                    <h3>Novo Post</h3>
                    <p>Publicar artigo no blog</p>
                </div>
            </a>
            <a href="testimonials?action=new" class="quick-link">
                <div class="quick-link-icon">
                    <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                </div>
                <div class="quick-link-text">
                    <h3>Novo Depoimento</h3>
                    <p>Adicionar depoimento de cliente</p>
                </div>
            </a>
            <a href="faq?action=new" class="quick-link">
                <div class="quick-link-icon">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div class="quick-link-text">
                    <h3>Nova FAQ</h3>
                    <p>Adicionar pergunta frequente</p>
                </div>
            </a>
            <a href="team?action=new" class="quick-link">
                <div class="quick-link-icon">
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                </div>
                <div class="quick-link-text">
                    <h3>Novo Membro</h3>
                    <p>Adicionar membro da equipe</p>
                </div>
            </a>
            <a href="optimize" class="quick-link">
                <div class="quick-link-icon">
                    <svg viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>
                </div>
                <div class="quick-link-text">
                    <h3>Otimizar Jogos</h3>
                    <p>Minificar e comprimir jogos HTML5</p>
                </div>
            </a>
        </div>
    </div>
</div>

<?php
$pendingUsers = $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
$canManageUsers = can('perm_users');
?>
<?php if ($pendingUsers > 0 && $canManageUsers): ?>
<div style="margin-bottom: 24px; padding: 16px 20px; background: oklch(68% 0.16 220 / 0.1); border: 1px solid oklch(68% 0.16 220 / 0.3); border-radius: 8px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
    <div style="display:flex;align-items:center;gap:12px;">
        <span style="font-size:24px;">⏳</span>
        <div>
            <strong style="color:var(--fg);font-size:15px;"><?= $pendingUsers ?> usuário(s) pendente(s)</strong>
            <p style="color:var(--fg-muted);font-size:13px;">Aguardando ativação via email.</p>
        </div>
    </div>
    <a href="users" class="btn btn-outline btn-sm" style="padding:8px 20px;">Gerenciar</a>
</div>
<?php endif; ?>

<?php if (!empty($recentGames)): ?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Jogos Recentes</h2>
        <a href="games" class="btn btn-outline btn-sm">Ver Todos</a>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Engine</th>
                    <th>Status</th>
                    <th>Criado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentGames as $game): ?>
                <tr>
                    <td><strong style="color:var(--fg)"><?= e($game['title']) ?></strong></td>
                    <td><?= e($game['engine']) ?></td>
                    <td>
                        <?php if ($game['active']): ?>
                            <span class="badge badge-active">Ativo</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">Inativo</span>
                        <?php endif; ?>
                        <?php if ($game['featured']): ?>
                            <span class="badge badge-featured">Destaque</span>
                        <?php endif; ?>
                    </td>
                    <td><?= timeAgo($game['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
