<?php
ob_start();
$pageTitle = 'Cargos';
$requiredPerm = 'perm_roles';
require_once __DIR__ . '/../includes/header.php';

$userId = $_SESSION['admin_user_id'] ?? 0;
$db = getDB();

$levelsList = $db->query("SELECT * FROM levels ORDER BY (perm_banners + perm_games + perm_blog + perm_testimonials + perm_faq + perm_team + perm_users + perm_roles + perm_engines + perm_platforms + perm_consoles + perm_retro_games + perm_templates + perm_optimizer + perm_settings) DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/roles'); exit; }

    if ($_POST['action'] === 'create' || $_POST['action'] === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $levelId = (int)($_POST['level_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $editId = (int)($_POST['id'] ?? 0);

        if ($name === '' || $levelId === 0) {
            flashMessage('error', 'Nome e nível são obrigatórios.');
        } elseif ($userId !== 1 && getLevelRank($levelId) >= getSessionRank()) {
            flashMessage('error', 'Você não pode criar cargos de nível igual ou superior ao seu.');
        } else {
            if ($_POST['action'] === 'create') {
                try {
                    $stmt = $db->prepare("INSERT INTO roles (name, level_id, level, description) VALUES (?, ?, (SELECT slug FROM levels WHERE id = ?), ?)");
                    $stmt->execute([$name, $levelId, $levelId, $description]);
                    flashMessage('success', "Cargo '$name' criado com sucesso!");
                } catch (Exception $e) {
                    flashMessage('error', 'Erro ao criar cargo: ' . $e->getMessage());
                }
            } else {
                if ($editId === 1) {
                    flashMessage('error', 'O cargo CEO Administrador não pode ser modificado.');
                } else {
                    try {
                        $stmt = $db->prepare("UPDATE roles SET name = ?, level_id = ?, level = (SELECT slug FROM levels WHERE id = ?), description = ? WHERE id = ?");
                        $stmt->execute([$name, $levelId, $levelId, $description, $editId]);
                        flashMessage('success', "Cargo '$name' atualizado!");
                    } catch (Exception $e) {
                        flashMessage('error', 'Erro ao atualizar cargo: ' . $e->getMessage());
                    }
                }
            }
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/roles');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        if ($id === 1) {
            flashMessage('error', 'O cargo CEO Administrador não pode ser excluído.');
        } else {
            $count = $db->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
            $count->execute([$id]);
            $userCount = (int)$count->fetchColumn();

            if ($userCount > 0) {
                flashMessage('error', 'Este cargo possui usuários vinculados e não pode ser excluído.');
            } else {
                $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
                $stmt->execute([$id]);
                flashMessage('success', 'Cargo excluído.');
            }
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/roles');
        exit;
    }
}

$roles = $db->query("
    SELECT r.*, l.name AS level_name,
           (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count
    FROM roles r
    LEFT JOIN levels l ON r.level_id = l.id
    ORDER BY (SELECT (perm_banners + perm_games + perm_blog + perm_testimonials + perm_faq + perm_team + perm_users + perm_roles + perm_engines + perm_platforms + perm_consoles + perm_retro_games + perm_templates + perm_optimizer + perm_settings) FROM levels WHERE id = r.level_id) DESC, r.name ASC
")->fetchAll();

$editRole = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT r.*, l.name AS level_name, l.slug AS level_slug FROM roles r LEFT JOIN levels l ON r.level_id = l.id WHERE r.id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRole = $stmt->fetch();
}
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Cargos do Sistema</h2>
        <button class="btn btn-gold btn-sm" onclick="document.getElementById('newRoleForm').classList.toggle('hidden')">+ Novo Cargo</button>
        <a href="levels" class="btn btn-outline btn-sm" style="margin-left:8px;">Gerenciar Níveis</a>
    </div>
    <div class="card-body">
        <form id="newRoleForm" method="POST" class="hidden" style="margin-bottom: 24px; padding: 16px; background: oklch(16% 0.035 265); border: 1px solid var(--border); border-radius: 8px;">
            <input type="hidden" name="action" value="create">
            <?= csrfField() ?>
            <div class="form-row" style="margin-bottom: 0;">
                <div class="form-group">
                    <label for="name">Nome do Cargo *</label>
                    <input type="text" id="name" name="name" required placeholder="Ex: CEO Administrador">
                </div>
                <div class="form-group">
                    <label for="level_id">Nível de Permissão *</label>
                    <select id="level_id" name="level_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($levelsList as $lvl):
                            $lvlRank = 0;
                            foreach ($levelsList[0] ?? [] as $k => $v) { if (strpos($k, 'perm_') === 0 && $lvl[$k]) $lvlRank++; }
                            if ($userId === 1 || $lvlRank < getSessionRank()):
                        ?>
                        <option value="<?= $lvl['id'] ?>"><?= e($lvl['name']) ?> (rank <?= $lvlRank ?>)</option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-top: 12px;">
                <label for="description">Descrição</label>
                <textarea id="description" name="description" rows="2" placeholder="Descrição do cargo..." style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:6px;background:oklch(12% 0.02 260);color:var(--fg);font-size:14px;resize:vertical;"></textarea>
            </div>
            <div class="form-actions" style="margin-top: 12px;">
                <button type="submit" class="btn btn-gold btn-sm">Criar</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="this.closest('form').classList.add('hidden')">Cancelar</button>
            </div>
        </form>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>ID</th><th>Nome</th><th>Nível</th><th>Usuários</th><th>Descrição</th><th>Ações</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><strong style="color:var(--fg)"><?= e($r['name']) ?></strong></td>
                        <td><span class="badge badge-featured"><?= e($r['level_name'] ?? $r['level'] ?? '—') ?></span></td>
                        <td><?= $r['user_count'] ?></td>
                        <td style="color:var(--fg-muted);font-size:13px;"><?= e($r['description']) ?></td>
                        <td class="actions">
                            <?php if ($r['id'] != 1): ?>
                            <a href="?edit=<?= $r['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Excluir cargo <?= e($r['name']) ?>?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Excluir" <?= $r['user_count'] > 0 ? 'disabled' : '' ?>><?= $r['user_count'] > 0 ? '🔒' : '🗑️' ?></button>
                            </form>
                            <?php else: ?>
                            <span style="color:var(--fg-muted);font-size:12px;">🔒 Protegido</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($editRole): ?>
<?php if ($editRole['id'] == 1): ?>
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <h2 class="card-title">Cargo Protegido</h2>
        <a href="roles" class="btn btn-outline btn-sm">Voltar</a>
    </div>
    <div class="card-body">
        <p style="color:var(--fg-muted);">O cargo <strong><?= e($editRole['name']) ?></strong> é o cargo mestre do sistema e não pode ser modificado ou excluído.</p>
    </div>
</div>
<?php else: ?>
<?php $editLevelRank = 0;
if ($editRole['level_id']) { $el = $db->prepare("SELECT * FROM levels WHERE id = ?"); $el->execute([$editRole['level_id']]); $ed = $el->fetch(); if ($ed) { foreach ($ed as $k => $v) { if (strpos($k, 'perm_') === 0 && $v) $editLevelRank++; } } } ?>
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <h2 class="card-title">Editar Cargo: <?= e($editRole['name']) ?></h2>
        <a href="roles" class="btn btn-outline btn-sm">Cancelar</a>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= $editRole['id'] ?>">
            <?= csrfField() ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_name">Nome do Cargo *</label>
                    <input type="text" id="edit_name" name="name" required value="<?= e($editRole['name']) ?>">
                </div>
                <div class="form-group">
                    <label for="edit_level_id">Nível de Permissão *</label>
                    <select id="edit_level_id" name="level_id" required>
                        <?php foreach ($levelsList as $lvl):
                            $lvlRank = 0;
                            foreach ($lvl as $k => $v) { if (strpos($k, 'perm_') === 0 && $v) $lvlRank++; }
                            $canAssign = $userId === 1 || $lvlRank < getSessionRank();
                            if ($canAssign || (int)$lvl['id'] === (int)$editRole['level_id']):
                        ?>
                        <option value="<?= $lvl['id'] ?>" <?= (int)$lvl['id'] === (int)$editRole['level_id'] ? 'selected' : '' ?> <?= !$canAssign ? 'disabled' : '' ?>><?= e($lvl['name']) ?> (rank <?= $lvlRank ?>)<?= !$canAssign ? ' (acima do seu nível)' : '' ?></option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="edit_description">Descrição</label>
                <textarea id="edit_description" name="description" rows="2" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:6px;background:oklch(12% 0.02 260);color:var(--fg);font-size:14px;resize:vertical;"><?= e($editRole['description']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-gold btn-sm">Salvar</button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<style>
.hidden { display: none; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
