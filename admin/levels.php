<?php
ob_start();
$pageTitle = 'Níveis de Permissão';
$requiredPerm = 'perm_roles';
require_once __DIR__ . '/../includes/header.php';

$userId = $_SESSION['admin_user_id'] ?? 0;
$db = getDB();

$PERM_LABELS = [
    'perm_banners' => 'Banners',
    'perm_games' => 'Jogos',
    'perm_blog' => 'Blog',
    'perm_testimonials' => 'Depoimentos',
    'perm_faq' => 'FAQ',
    'perm_team' => 'Equipe',
    'perm_users' => 'Usuários',
    'perm_roles' => 'Cargos',
    'perm_engines' => 'Engines',
    'perm_platforms' => 'Plataformas',
    'perm_consoles' => 'Emuladores',
    'perm_retro_games' => 'Jogos Retro',
    'perm_templates' => 'Templates',
    'perm_optimizer' => 'Otimizador',
    'perm_settings' => 'Configurações',
];

function getPermRank($row) {
    $count = 0;
    foreach ($row as $k => $v) {
        if (strpos($k, 'perm_') === 0 && $v) $count++;
    }
    return $count;
}

$levels = $db->query("SELECT * FROM levels ORDER BY (perm_banners + perm_games + perm_blog + perm_testimonials + perm_faq + perm_team + perm_users + perm_roles + perm_engines + perm_platforms + perm_consoles + perm_retro_games + perm_templates + perm_optimizer + perm_settings) DESC, name ASC")->fetchAll();

$protectedLevelId = null;
$maxRank = -1;
foreach ($levels as $l) {
    $rank = getPermRank($l);
    if ($rank > $maxRank || ($rank === $maxRank && $protectedLevelId !== null && $l['id'] < $protectedLevelId)) {
        $maxRank = $rank;
        $protectedLevelId = (int)$l['id'];
    } elseif ($maxRank === -1) {
        $maxRank = $rank;
        $protectedLevelId = (int)$l['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/levels'); exit; }

    if ($_POST['action'] === 'toggle_protect') {
        $id = (int)$_POST['id'];
        if ($id === $protectedLevelId) {
            flashMessage('error', 'O nível de maior rank não pode ser desprotegido.');
        } else {
            $stmt = $db->prepare("SELECT is_protected FROM levels WHERE id = ?");
            $stmt->execute([$id]);
            $level = $stmt->fetch();
            if ($level) {
                $newVal = $level['is_protected'] ? 0 : 1;
                $stmt = $db->prepare("UPDATE levels SET is_protected = ? WHERE id = ?");
                $stmt->execute([$newVal, $id]);
                flashMessage('success', 'Proteção do nível alterada.');
            }
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/levels');
        exit;
    }

    if ($_POST['action'] === 'create' || $_POST['action'] === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $editId = (int)($_POST['id'] ?? 0);

        if ($name === '') {
            flashMessage('error', 'Nome é obrigatório.');
        } elseif ($_POST['action'] === 'create' && $slug === '') {
            flashMessage('error', 'Slug é obrigatório.');
        } else {
            $perms = [];
            $permColumns = array_keys($PERM_LABELS);
            foreach ($permColumns as $col) {
                $perms[$col] = isset($_POST[$col]) ? 1 : 0;
            }

            $isProtected = $userId === 1 && isset($_POST['is_protected']) ? 1 : 0;

            if ($_POST['action'] === 'create') {
                try {
                    $cols = implode(', ', array_merge(['name', 'slug', 'is_protected'], $permColumns));
                    $placeholders = implode(', ', array_fill(0, count($perms) + 3, '?'));
                    $vals = array_merge([$name, $slug, $isProtected], array_values($perms));
                    $stmt = $db->prepare("INSERT INTO levels ($cols) VALUES ($placeholders)");
                    $stmt->execute($vals);
                    flashMessage('success', "Nível '$name' criado com sucesso!");
                } catch (Exception $e) {
                    flashMessage('error', 'Erro ao criar nível: ' . $e->getMessage());
                }
            } else {
                if ($editId === 0) {
                    flashMessage('error', 'ID inválido.');
                } else {
                    try {
                        if ($userId === 1) {
                            $sets = 'name = ?, slug = ?, is_protected = ?';
                            $vals = [$name, $slug, $isProtected];
                        } else {
                            $sets = 'name = ?, slug = ?';
                            $vals = [$name, $slug];
                        }
                        foreach ($permColumns as $col) {
                            $sets .= ", $col = ?";
                            $vals[] = $perms[$col];
                        }
                        $vals[] = $editId;
                        $stmt = $db->prepare("UPDATE levels SET $sets WHERE id = ?");
                        $stmt->execute($vals);
                        flashMessage('success', "Nível '$name' atualizado!");
                    } catch (Exception $e) {
                        flashMessage('error', 'Erro ao atualizar nível: ' . $e->getMessage());
                    }
                }
            }
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/levels');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("SELECT is_protected, name FROM levels WHERE id = ?");
        $stmt->execute([$id]);
        $level = $stmt->fetch();
        if (!$level) {
            flashMessage('error', 'Nível não encontrado.');
        } elseif ($level['is_protected']) {
            flashMessage('error', 'Níveis protegidos não podem ser excluídos.');
        } else {
            $count = $db->prepare("SELECT COUNT(*) FROM roles WHERE level_id = ?");
            $count->execute([$id]);
            $roleCount = (int)$count->fetchColumn();
            if ($roleCount > 0) {
                flashMessage('error', 'Este nível possui ' . $roleCount . ' cargo(s) vinculado(s). Remova os vínculos antes de excluir.');
            } else {
                $stmt = $db->prepare("DELETE FROM levels WHERE id = ?");
                $stmt->execute([$id]);
                flashMessage('success', "Nível '{$level['name']}' excluído.");
            }
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/levels');
        exit;
    }
}

$editLevel = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM levels WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editLevel = $stmt->fetch();
}
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Níveis de Permissão</h2>
        <button class="btn btn-gold btn-sm" onclick="document.getElementById('newLevelForm').classList.toggle('hidden')">+ Novo Nível</button>
    </div>
    <div class="card-body">
        <form id="newLevelForm" method="POST" class="hidden" style="margin-bottom: 24px; padding: 16px; background: oklch(16% 0.035 265); border: 1px solid var(--border); border-radius: 8px;">
            <input type="hidden" name="action" value="create">
            <?= csrfField() ?>
            <div class="form-row" style="margin-bottom: 0;">
                <div class="form-group">
                    <label for="name">Nome do Nível *</label>
                    <input type="text" id="name" name="name" required placeholder="Ex: Administrador">
                </div>
                <div class="form-group">
                    <label for="slug">Slug (identificador único) *</label>
                    <input type="text" id="slug" name="slug" required placeholder="Ex: admin">
                </div>
            </div>
            <?php if ($userId === 1): ?>
            <div class="form-group" style="margin-top: 8px;">
                <label><input type="checkbox" name="is_protected" value="1"> Nível protegido</label>
            </div>
            <?php endif; ?>
            <div style="margin-top: 16px; display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px;">
                <?php foreach ($PERM_LABELS as $col => $label): ?>
                <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="checkbox" name="<?= $col ?>" value="1" checked> <?= $label ?>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="form-actions" style="margin-top: 16px;">
                <button type="submit" class="btn btn-gold btn-sm">Criar Nível</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="this.closest('form').classList.add('hidden')">Cancelar</button>
            </div>
        </form>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>ID</th><th>Nome</th><th>Slug</th><th>Rank</th><th>Permissões</th><th>Protegido</th><th>Ações</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($levels as $l): ?>
                    <?php $rank = getPermRank($l); ?>
                    <tr>
                        <td><?= $l['id'] ?></td>
                        <td><strong style="color:var(--fg)"><?= e($l['name']) ?></strong></td>
                        <td style="color:var(--fg-muted);font-size:13px;"><?= e($l['slug']) ?></td>
                        <td><span class="badge badge-featured" style="font-size:12px;"><?= $rank ?></span></td>
                        <td style="max-width:300px;">
                            <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                <?php foreach ($PERM_LABELS as $col => $label): ?>
                                    <?php if ($l[$col]): ?>
                                    <span style="font-size:11px;padding:2px 6px;border-radius:4px;background:oklch(25% 0.06 145 / 0.3);color:oklch(70% 0.12 145);"><?= $label ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ((int)$l['id'] === $protectedLevelId): ?>
                            <span style="color:var(--gold);cursor:not-allowed;" title="Nível de maior rank — proteção permanente">🔒</span>
                            <?php else: ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('<?= $l['is_protected'] ? 'Desproteger' : 'Proteger' ?> o nível <?= e($l['name']) ?>?')">
                                <input type="hidden" name="action" value="toggle_protect">
                                <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                <?= csrfField() ?>
                                <button type="submit" class="btn-icon-toggle" title="<?= $l['is_protected'] ? 'Desproteger' : 'Proteger' ?>" style="background:none;border:none;cursor:pointer;font-size:18px;padding:2px;">
                                    <?= $l['is_protected'] ? '🔒' : '🔓' ?>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <a href="?edit=<?= $l['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Excluir nível <?= e($l['name']) ?>?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Excluir" <?= $l['is_protected'] ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : '' ?>>
                                    🗑️
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($editLevel): ?>
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <h2 class="card-title">Editar Nível: <?= e($editLevel['name']) ?></h2>
        <a href="levels" class="btn btn-outline btn-sm">Cancelar</a>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= $editLevel['id'] ?>">
            <?= csrfField() ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_name">Nome do Nível *</label>
                    <input type="text" id="edit_name" name="name" required value="<?= e($editLevel['name']) ?>">
                </div>
                <div class="form-group">
                    <label for="edit_slug">Slug *</label>
                    <input type="text" id="edit_slug" name="slug" required value="<?= e($editLevel['slug']) ?>">
                </div>
            </div>
            <?php if ($userId === 1): ?>
            <div class="form-group" style="margin-top: 8px;">
                <label><input type="checkbox" name="is_protected" value="1" <?= $editLevel['is_protected'] ? 'checked' : '' ?>> Nível protegido</label>
            </div>
            <?php endif; ?>
            <div style="margin-top: 16px; display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px;">
                <?php foreach ($PERM_LABELS as $col => $label): ?>
                <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="checkbox" name="<?= $col ?>" value="1" <?= $editLevel[$col] ? 'checked' : '' ?>> <?= $label ?>
                </label>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-gold btn-sm" style="margin-top:16px;">Salvar</button>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
.hidden { display: none; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
