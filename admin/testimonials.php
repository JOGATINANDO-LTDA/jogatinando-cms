<?php
ob_start();
$pageTitle = 'Depoimentos';
$requiredPerm = 'perm_testimonials';
require_once __DIR__ . '/../includes/header.php';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? $id);
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/testimonials'); exit; }
    if ($_POST['action'] === 'save') {
        $name = trim($_POST['name']); $role = trim($_POST['role']); $quote = trim($_POST['quote']);
        $sort_order = (int)($_POST['sort_order'] ?? 0); $active = isset($_POST['active']) ? 1 : 0;
        $avatar_url = '';
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $oldAvatar = $id > 0 ? dbQueryOne("SELECT avatar_url FROM testimonials WHERE id = ?", [$id])['avatar_url'] ?? '' : '';
            $result = uploadFile($_FILES['avatar'], 'avatars', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($result['success']) {
                $avatar_url = $result['url'];
                if (!empty($oldAvatar)) deleteFile($oldAvatar);
            }
            else { flashMessage('error', $result['message']); ob_end_clean(); header('Location: ' . ADMIN_URL . '/testimonials?action=' . ($id > 0 ? "edit&id=$id" : 'new')); exit; }
        }
        if (empty($name) || empty($quote)) { flashMessage('error', 'Nome e depoimento são obrigatórios.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/testimonials?action=' . ($id > 0 ? "edit&id=$id" : 'new')); exit; }
        if ($id > 0) {
            if (!$avatar_url) { $existing = dbQueryOne("SELECT avatar_url FROM testimonials WHERE id = ?", [$id]); $avatar_url = $existing['avatar_url']; }
            dbExec("UPDATE testimonials SET name=?, role=?, quote=?, avatar_url=?, sort_order=?, active=? WHERE id=?", [$name, $role, $quote, $avatar_url, $sort_order, $active, $id]);
            flashMessage('success', 'Depoimento atualizado!');
        } else {
            dbExec("INSERT INTO testimonials (name, role, quote, avatar_url, sort_order, active) VALUES (?, ?, ?, ?, ?, ?)", [$name, $role, $quote, $avatar_url, $sort_order, $active]);
            flashMessage('success', 'Depoimento criado!');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/testimonials'); exit;
    }
    if ($_POST['action'] === 'delete') { $item = dbQueryOne("SELECT avatar_url FROM testimonials WHERE id = ?", [$id]); if ($item && !empty($item['avatar_url'])) deleteFile($item['avatar_url']); dbDelete('testimonials', $id); flashMessage('success', 'Depoimento excluído.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/testimonials'); exit; }
    if ($_POST['action'] === 'toggle') { $r = dbQueryOne("SELECT active FROM testimonials WHERE id = ?", [$id]); if ($r) dbExec("UPDATE testimonials SET active = ? WHERE id = ?", [1 - $r['active'], $id]); ob_end_clean(); header('Location: ' . ADMIN_URL . '/testimonials'); exit; }
}

if ($action === 'new' || $action === 'edit') {
    $item = $id > 0 ? dbQueryOne("SELECT * FROM testimonials WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$item) { flashMessage('error', 'Não encontrado.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/testimonials'); exit; }
    ?>
    <div class="card">
        <div class="card-header"><h2 class="card-title"><?= $action === 'new' ? 'Novo Depoimento' : 'Editar Depoimento' ?></h2><a href="testimonials" class="btn btn-outline btn-sm">← Voltar</a></div>
        <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save"><?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?><?= csrfField() ?>

            <h3 class="form-section-title">Informações</h3>

            <div class="form-row">
                <div class="form-group"><label for="name">Nome *</label><input type="text" id="name" name="name" value="<?= e($item['name'] ?? '') ?>" required></div>
                <div class="form-group"><label for="role">Cargo / Empresa</label><input type="text" id="role" name="role" value="<?= e($item['role'] ?? '') ?>"></div>
            </div>
            <div class="form-group"><label for="quote">Depoimento *</label><textarea id="quote" name="quote" rows="4" required><?= e($item['quote'] ?? '') ?></textarea></div>

            <h3 class="form-section-title">Avatar</h3>

            <div class="form-group">
                <div class="file-upload"><input type="file" name="avatar" accept="image/*">
                <div class="upload-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                <div class="upload-text">Upload de avatar</div></div>
                <?php if (!empty($item['avatar_url'])): ?><img src="<?= e($item['avatar_url']) ?>" class="preview-img" alt="Avatar"><?php endif; ?>
            </div>

            <h3 class="form-section-title">Configurações</h3>

            <div class="form-row">
                <div class="form-group"><label for="sort_order">Ordem</label><input type="number" id="sort_order" name="sort_order" value="<?= (int)($item['sort_order'] ?? 0) ?>"></div>
                <div class="form-group"><div class="toggle-group" style="margin-top:28px"><input type="checkbox" id="active" name="active" <?= ($item['active'] ?? 1) ? 'checked' : '' ?>><label for="active">Ativo</label></div></div>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-gold">Salvar</button><a href="testimonials" class="btn btn-outline">Cancelar</a></div>
        </form>
        </div>
    </div>
    <?php
} else {
    $items = dbQuery("SELECT * FROM testimonials ORDER BY sort_order ASC");
    ?>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Depoimentos</h2><a href="testimonials?action=new" class="btn btn-gold btn-sm">+ Novo</a></div>
        <?php if (empty($items)): ?><div class="card-body"><div class="empty-state"><div class="empty-icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div><p>Nenhum depoimento cadastrado.</p></div></div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Nome</th><th>Cargo</th><th>Depoimento</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $t): ?>
                        <tr>
                            <td><strong style="color:var(--fg)"><?= e($t['name']) ?></strong></td>
                            <td><?= e($t['role']) ?></td>
                            <td><?= e(truncateText($t['quote'], 60)) ?></td>
                            <td><?= $t['active'] ? '<span class="badge badge-active">Ativo</span>' : '<span class="badge badge-inactive">Inativo</span>' ?></td>
                            <td class="actions">
                                <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $t['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-outline btn-sm btn-icon"><?= $t['active'] ? '🔴' : '🟢' ?></button></form>
                                <a href="testimonials?action=edit&id=<?= $t['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-danger btn-sm btn-icon" title="Excluir">🗑️</button></form>
                            </td>
                        </tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
require_once __DIR__ . '/../includes/footer.php';
