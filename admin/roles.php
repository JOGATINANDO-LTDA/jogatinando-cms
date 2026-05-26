<?php
ob_start();
$pageTitle = 'Cargos';
require_once __DIR__ . '/../includes/header.php';

$userId = $_SESSION['admin_user_id'] ?? 0;
$userLevel = $_SESSION['admin_role_level'] ?? 'moderator';
$isMasterCeo = ($userId === 1 && $userLevel === 'ceo');

requireRole('chief');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: roles'); exit; }

    if ($_POST['action'] === 'create' || $_POST['action'] === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $level = $_POST['level'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $editId = (int)($_POST['id'] ?? 0);

        if ($name === '' || $level === '') {
            flashMessage('error', 'Nome e nível são obrigatórios.');
        } elseif (!in_array($level, ['ceo', 'chief', 'moderator'])) {
            flashMessage('error', 'Nível inválido.');
        } elseif (!$isMasterCeo && $level === 'ceo') {
            flashMessage('error', 'Apenas o CEO Administrador pode criar cargos de nível CEO.');
        } elseif ($userId !== 1 && getRoleLevelRank($level) >= getRoleLevelRank($userLevel)) {
            flashMessage('error', 'Você não pode criar cargos de nível igual ou superior ao seu.');
        } else {
            if ($_POST['action'] === 'create') {
                try {
                    $stmt = $db->prepare("INSERT INTO roles (name, level, description) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $level, $description]);
                    flashMessage('success', "Cargo '$name' criado com sucesso!");
                } catch (Exception $e) {
                    flashMessage('error', 'Erro ao criar cargo: ' . $e->getMessage());
                }
            } else {
                try {
                    $stmt = $db->prepare("UPDATE roles SET name = ?, level = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $level, $description, $editId]);
                    flashMessage('success', "Cargo '$name' atualizado!");
                } catch (Exception $e) {
                    flashMessage('error', 'Erro ao atualizar cargo: ' . $e->getMessage());
                }
            }
        }
        ob_end_clean();
        header('Location: roles');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $count = $db->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?")->execute([$id]);
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
        $stmt->execute([$id]);
        $userCount = (int)$stmt->fetchColumn();

        if ($userCount > 0) {
            flashMessage('error', 'Este cargo possui usuários vinculados e não pode ser excluído.');
        } else {
            $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->execute([$id]);
            flashMessage('success', 'Cargo excluído.');
        }
        ob_end_clean();
        header('Location: roles');
        exit;
    }
}

$roles = $db->query("
    SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count
    FROM roles r ORDER BY
        CASE r.level WHEN 'ceo' THEN 0 WHEN 'chief' THEN 1 WHEN 'moderator' THEN 2 ELSE 3 END,
        r.name ASC
")->fetchAll();

$editRole = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRole = $stmt->fetch();
}
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Cargos do Sistema</h2>
        <button class="btn btn-gold btn-sm" onclick="document.getElementById('newRoleForm').classList.toggle('hidden')">+ Novo Cargo</button>
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
                    <label for="level">Nível de Permissão *</label>
                    <select id="level" name="level" required>
                        <option value="">Selecione...</option>
                        <?php if ($isMasterCeo): ?>
                        <option value="ceo">CEO</option>
                        <?php endif; ?>
                        <?php if ($isMasterCeo || $userLevel === 'ceo'): ?>
                        <option value="chief">Chief</option>
                        <?php endif; ?>
                        <option value="moderator" selected>Moderator</option>
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
                        <td>
                            <?php if ($r['level'] === 'ceo'): ?>
                                <span class="badge badge-featured">CEO</span>
                            <?php elseif ($r['level'] === 'chief'): ?>
                                <span class="badge badge-active">Chief</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">Moderator</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $r['user_count'] ?></td>
                        <td style="color:var(--fg-muted);font-size:13px;"><?= e($r['description']) ?></td>
                        <td class="actions">
                            <a href="?edit=<?= $r['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Excluir cargo <?= e($r['name']) ?>?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Excluir" <?= $r['user_count'] > 0 ? 'disabled' : '' ?>><?= $r['user_count'] > 0 ? '🔒' : '🗑️' ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($editRole): ?>
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
                    <label for="edit_level">Nível de Permissão *</label>
                    <select id="edit_level" name="level" required>
                        <?php if ($isMasterCeo): ?>
                        <option value="ceo" <?= $editRole['level'] === 'ceo' ? 'selected' : '' ?>>CEO</option>
                        <?php endif; ?>
                        <?php if ($isMasterCeo || $userLevel === 'ceo'): ?>
                        <option value="chief" <?= $editRole['level'] === 'chief' ? 'selected' : '' ?>>Chief</option>
                        <?php endif; ?>
                        <option value="moderator" <?= $editRole['level'] === 'moderator' ? 'selected' : '' ?>>Moderator</option>
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

<style>
.hidden { display: none; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
