<?php
ob_start();
$pageTitle = 'Cargos';
$requiredPerm = 'perm_roles';
require_once __DIR__ . '/../includes/header.php';

$userId = $_SESSION['admin_user_id'] ?? 0;
$db = getDB();

$levelsList = $db->query("SELECT * FROM levels ORDER BY (perm_banners + perm_games + perm_blog + perm_testimonials + perm_faq + perm_team + perm_users + perm_roles + perm_engines + perm_platforms + perm_consoles + perm_retro_games + perm_settings) DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/roles'); exit; }

    if ($_POST['action'] === 'create' || $_POST['action'] === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $levelId = (int)($_POST['level_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $editId = (int)($_POST['id'] ?? 0);

        if ($name === '') {
            flashMessage('error', 'Nome é obrigatório.');
        } elseif ($_POST['action'] === 'edit' && $editId === 1) {
            if ($userId !== 1) {
                flashMessage('error', 'Apenas o master pode editar o cargo CEO Administrador.');
            } else {
                try {
                    $stmt = $db->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $editId]);
                    flashMessage('success', "Cargo '$name' atualizado!");
                } catch (Exception $e) {
                    flashMessage('error', 'Erro ao atualizar cargo: ' . $e->getMessage());
                }
            }
        } elseif ($levelId === 0) {
            flashMessage('error', 'Nível é obrigatório.');
        } elseif ($userId !== 1 && getLevelRank($levelId) >= getSessionRank()) {
            flashMessage('error', 'Você não pode criar cargos de nível igual ou superior ao seu.');
        } else {
            if ($_POST['action'] === 'create') {
                try {
                    $stmt = $db->prepare("INSERT INTO roles (name, level_id, description) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $levelId, $description]);
                    flashMessage('success', "Cargo '$name' criado com sucesso!");
                } catch (Exception $e) {
                    flashMessage('error', 'Erro ao criar cargo: ' . $e->getMessage());
                }
            } else {
                try {
                    $stmt = $db->prepare("UPDATE roles SET name = ?, level_id = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $levelId, $description, $editId]);
                    flashMessage('success', "Cargo '$name' atualizado!");
                } catch (Exception $e) {
                    flashMessage('error', 'Erro ao atualizar cargo: ' . $e->getMessage());
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
    ORDER BY (SELECT (perm_banners + perm_games + perm_blog + perm_testimonials + perm_faq + perm_team + perm_users + perm_roles + perm_engines + perm_platforms + perm_consoles + perm_retro_games + perm_settings) FROM levels WHERE id = r.level_id) DESC, r.name ASC
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
        <form id="newRoleForm" method="POST" class="hidden form-card">
            <input type="hidden" name="action" value="create">
            <?= csrfField() ?>
            <div class="form-row" style="margin-bottom:0;">
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
            <div class="form-group">
                <label for="description">Descrição</label>
                <textarea id="description" name="description" rows="2" placeholder="Descrição do cargo..."></textarea>
            </div>
            <div class="form-actions">
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
                        <td><strong class="text-fg"><?= e($r['name']) ?></strong></td>
                        <td><span class="badge badge-featured"><?= e($r['level_name'] ?? $r['level'] ?? '—') ?></span></td>
                        <td><?= $r['user_count'] ?></td>
                        <td class="text-muted text-small"><?= e($r['description']) ?></td>
                        <td class="actions">
                            <?php if ($r['id'] == 1): ?>
                                <?php if ($userId === 1): ?>
                                <a href="?edit=1" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                                <?php endif; ?>
                                <button class="btn btn-danger btn-sm btn-icon" disabled title="Cargo mestre não pode ser excluído">🔒</button>
                            <?php else: ?>
                            <a href="?edit=<?= $r['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                            <form method="POST" class="form-inline" onsubmit="return confirm('Excluir cargo <?= e($r['name']) ?>?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Excluir" <?= $r['user_count'] > 0 ? 'disabled' : '' ?>><?= $r['user_count'] > 0 ? '🔒' : '🗑️' ?></button>
                            </form>
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
    <?php if ($userId !== 1): ?>
<div class="card card-spaced">
    <div class="card-header">
        <h2 class="card-title">Cargo Protegido</h2>
        <a href="roles" class="btn btn-outline btn-sm">Voltar</a>
    </div>
    <div class="card-body">
        <p class="text-muted">O cargo <strong class="text-fg"><?= e($editRole['name']) ?></strong> é o cargo mestre do sistema e só pode ser editado pelo master.</p>
    </div>
</div>
    <?php else: ?>
<div class="card card-spaced">
    <div class="card-header">
        <h2 class="card-title">Editar Cargo: <?= e($editRole['name']) ?></h2>
        <a href="roles" class="btn btn-outline btn-sm">Cancelar</a>
    </div>
    <div class="card-body">
        <div class="alert-static alert-static-gold">🔒 Cargo mestre — apenas nome e descrição podem ser alterados.</div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="1">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="edit_name">Nome do Cargo *</label>
                <input type="text" id="edit_name" name="name" required value="<?= e($editRole['name']) ?>">
            </div>
            <div class="form-group">
                <label for="edit_description">Descrição</label>
                <textarea id="edit_description" name="description" rows="2"><?= e($editRole['description']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-gold btn-sm">Salvar</button>
        </form>
    </div>
</div>
    <?php endif; ?>
<?php else: ?>
<?php $editLevelRank = 0;
if ($editRole['level_id']) { $el = $db->prepare("SELECT * FROM levels WHERE id = ?"); $el->execute([$editRole['level_id']]); $ed = $el->fetch(); if ($ed) { foreach ($ed as $k => $v) { if (strpos($k, 'perm_') === 0 && $v) $editLevelRank++; } } } ?>
<div class="card card-spaced">
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
                <textarea id="edit_description" name="description" rows="2"><?= e($editRole['description']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-gold btn-sm">Salvar</button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
