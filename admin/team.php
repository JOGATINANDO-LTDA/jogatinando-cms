<?php
ob_start();
$pageTitle = 'Equipe';
require_once __DIR__ . '/../includes/header.php';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? $id);
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: team'); exit; }
    if ($_POST['action'] === 'save') {
        $name = trim($_POST['name']); $role = trim($_POST['role']); $bio = trim($_POST['bio']);
        $social_youtube = trim($_POST['social_youtube']); $social_twitch = trim($_POST['social_twitch']); $social_linkedin = trim($_POST['social_linkedin']);
        $sort_order = (int)($_POST['sort_order'] ?? 0); $active = isset($_POST['active']) ? 1 : 0;
        $avatar_url = '';
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['avatar'], 'avatars', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($result['success']) $avatar_url = $result['url'];
            else { flashMessage('error', $result['message']); ob_end_clean(); header('Location: team?action=' . ($id > 0 ? "edit&id=$id" : 'new')); exit; }
        }
        if (empty($name) || empty($role)) { flashMessage('error', 'Nome e cargo são obrigatórios.'); ob_end_clean(); header('Location: team?action=' . ($id > 0 ? "edit&id=$id" : 'new')); exit; }
        if ($id > 0) {
            if (!$avatar_url) { $existing = dbQueryOne("SELECT avatar_url FROM team_members WHERE id = ?", [$id]); $avatar_url = $existing['avatar_url']; }
            dbExec("UPDATE team_members SET name=?, role=?, bio=?, avatar_url=?, social_youtube=?, social_twitch=?, social_linkedin=?, sort_order=?, active=? WHERE id=?", [$name, $role, $bio, $avatar_url, $social_youtube, $social_twitch, $social_linkedin, $sort_order, $active, $id]);
            flashMessage('success', 'Membro atualizado!');
        } else {
            dbExec("INSERT INTO team_members (name, role, bio, avatar_url, social_youtube, social_twitch, social_linkedin, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [$name, $role, $bio, $avatar_url, $social_youtube, $social_twitch, $social_linkedin, $sort_order, $active]);
            flashMessage('success', 'Membro criado!');
        }
        ob_end_clean();
        header('Location: team'); exit;
    }
    if ($_POST['action'] === 'delete') { dbDelete('team_members', $id); flashMessage('success', 'Membro excluído.'); ob_end_clean(); header('Location: team'); exit; }
    if ($_POST['action'] === 'toggle') { $r = dbQueryOne("SELECT active FROM team_members WHERE id = ?", [$id]); if ($r) dbExec("UPDATE team_members SET active = ? WHERE id = ?", [1 - $r['active'], $id]); ob_end_clean(); header('Location: team'); exit; }
}

if ($action === 'new' || $action === 'edit') {
    $member = $id > 0 ? dbQueryOne("SELECT * FROM team_members WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$member) { flashMessage('error', 'Não encontrado.'); ob_end_clean(); header('Location: team'); exit; }
    ?>
    <div class="card">
        <div class="card-header"><h2 class="card-title"><?= $action === 'new' ? 'Novo Membro' : 'Editar Membro' ?></h2><a href="team" class="btn btn-outline btn-sm">← Voltar</a></div>
        <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save"><?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?><?= csrfField() ?>

            <h3 class="form-section-title">Informações Pessoais</h3>

            <div class="form-row">
                <div class="form-group"><label for="name">Nome *</label><input type="text" id="name" name="name" value="<?= e($member['name'] ?? '') ?>" required></div>
                <div class="form-group"><label for="role">Cargo *</label>
                    <select id="role" name="role" required>
                        <option value="">Selecione...</option>
                        <?php foreach (dbQuery("SELECT name FROM roles WHERE id != 1 ORDER BY level DESC, name ASC") as $r): ?>
                        <option value="<?= e($r['name']) ?>" <?= ($member['role'] ?? '') === $r['name'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <a href="roles?action=new" style="display:block;margin-top:4px;font-size:13px;color:var(--gold);">+ Criar novo cargo</a>
                </div>
            </div>
            <div class="form-group"><label for="bio">Bio</label><textarea id="bio" name="bio" rows="4"><?= e($member['bio'] ?? '') ?></textarea></div>

            <h3 class="form-section-title">Avatar</h3>

            <div class="form-group">
                <div class="file-upload"><input type="file" name="avatar" accept="image/*">
                <div class="upload-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                <div class="upload-text">Upload de foto</div></div>
                <?php if (!empty($member['avatar_url'])): ?><img src="<?= e($member['avatar_url']) ?>" class="preview-img" alt="Avatar"><?php endif; ?>
            </div>

            <h3 class="form-section-title">Redes Sociais</h3>

            <div class="form-row">
                <div class="form-group"><label for="social_youtube">YouTube URL</label><input type="url" id="social_youtube" name="social_youtube" value="<?= e($member['social_youtube'] ?? '') ?>" placeholder="https://youtube.com/..."></div>
                <div class="form-group"><label for="social_twitch">Twitch URL</label><input type="url" id="social_twitch" name="social_twitch" value="<?= e($member['social_twitch'] ?? '') ?>" placeholder="https://twitch.tv/..."></div>
            </div>
            <div class="form-group"><label for="social_linkedin">LinkedIn URL</label><input type="url" id="social_linkedin" name="social_linkedin" value="<?= e($member['social_linkedin'] ?? '') ?>" placeholder="https://linkedin.com/in/..."></div>

            <h3 class="form-section-title">Configurações</h3>

            <div class="form-row">
                <div class="form-group"><label for="sort_order">Ordem</label><input type="number" id="sort_order" name="sort_order" value="<?= (int)($member['sort_order'] ?? 0) ?>"></div>
                <div class="form-group"><div class="toggle-group" style="margin-top:28px"><input type="checkbox" id="active" name="active" <?= ($member['active'] ?? 1) ? 'checked' : '' ?>><label for="active">Ativo</label></div></div>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-gold">Salvar</button><a href="team" class="btn btn-outline">Cancelar</a></div>
        </form>
        </div>
    </div>
    <?php
} else {
    $members = dbQuery("SELECT * FROM team_members ORDER BY sort_order ASC");
    ?>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Equipe</h2><a href="team?action=new" class="btn btn-gold btn-sm">+ Novo Membro</a></div>
        <?php if (empty($members)): ?><div class="card-body"><div class="empty-state"><div class="empty-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div><p>Nenhum membro cadastrado.</p></div></div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Nome</th><th>Cargo</th><th>YouTube</th><th>Twitch</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php foreach ($members as $m): ?>
                        <tr>
                            <td><strong style="color:var(--fg)"><?= e($m['name']) ?></strong></td>
                            <td><?= e($m['role']) ?></td>
                            <td><?= $m['social_youtube'] ? '🔗' : '—' ?></td>
                            <td><?= $m['social_twitch'] ? '🔗' : '—' ?></td>
                            <td><?= $m['active'] ? '<span class="badge badge-active">Ativo</span>' : '<span class="badge badge-inactive">Inativo</span>' ?></td>
                            <td class="actions">
                                <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $m['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-outline btn-sm btn-icon"><?= $m['active'] ? '🔴' : '🟢' ?></button></form>
                                <a href="team?action=edit&id=<?= $m['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $m['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-danger btn-sm btn-icon" title="Excluir">🗑️</button></form>
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
