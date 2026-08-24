<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$db = getDB();
$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token inválido.';
    } else {
        $postAction = $_POST['action'] ?? '';

        // Bulk actions
        if ($postAction === 'bulk_delete') {
            $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
            if ($ids) {
                $placeholders = implode(',', $ids);
                $db->exec("DELETE FROM newsletter_subscribers WHERE id IN ($placeholders)");
                $success = count($ids) . ' inscrito(s) removido(s).';
            }
        }

        if ($postAction === 'bulk_activate') {
            $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
            if ($ids) {
                $placeholders = implode(',', $ids);
                $db->exec("UPDATE newsletter_subscribers SET is_active = 1 WHERE id IN ($placeholders)");
                $success = count($ids) . ' inscrito(s) ativado(s).';
            }
        }

        if ($postAction === 'bulk_deactivate') {
            $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
            if ($ids) {
                $placeholders = implode(',', $ids);
                $db->exec("UPDATE newsletter_subscribers SET is_active = 0 WHERE id IN ($placeholders)");
                $success = count($ids) . ' inscrito(s) desativado(s).';
            }
        }

        if ($postAction === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM newsletter_subscribers WHERE id = ?")->execute([$id]);
                $success = 'Inscrito removido.';
            }
        }

        if ($postAction === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $active = (int)($_POST['active'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE newsletter_subscribers SET is_active = ? WHERE id = ?")->execute([$active, $id]);
                $success = $active ? 'Inscrito ativado.' : 'Inscrito desativado.';
            }
        }

        if ($postAction === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $tags = trim($_POST['tags'] ?? '');

            if ($email === '') {
                $error = 'E-mail é obrigatório.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'E-mail inválido.';
            } else {
                if ($id > 0) {
                    $db->prepare("UPDATE newsletter_subscribers SET name = ?, email = ?, tags = ? WHERE id = ?")->execute([$name, $email, $tags, $id]);
                } else {
                    $token = bin2hex(random_bytes(32));
                    $db->prepare("INSERT INTO newsletter_subscribers (email, name, tags, unsubscribe_token) VALUES (?, ?, ?, ?)")->execute([$email, $name, $tags, $token]);
                }
                $success = 'Inscrito salvo.';
            }
        }

        if ($success || $error) {
            header('Location: ' . ADMIN_URL . '/newsletter' . ($success ? '?ok=1' : ''));
            exit;
        }
    }
    header('Location: ' . ADMIN_URL . '/newsletter');
    exit;
}

// Edit mode
$editItem = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $editItem = $db->query("SELECT * FROM newsletter_subscribers WHERE id = $editId")->fetch();
    if (!$editItem) $editId = 0;
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$total = (int)$db->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();
$totalPages = (int)ceil($total / $limit);

$subscribers = $db->query("SELECT * FROM newsletter_subscribers ORDER BY id DESC LIMIT $limit OFFSET $offset")->fetchAll();

$ok = isset($_GET['ok']);
?>

<div class="admin-page-header">
    <div>
        <h2>Newsletter</h2>
        <p class="subtitle"><?= $total ?> inscritos</p>
    </div>
    <a class="btn btn-gold btn-sm" href="?edit=0">+ Novo Inscrito</a>
</div>

<?php if ($ok || $success): ?>
    <div class="alert alert-success"><?= e($success ?: 'Operação realizada com sucesso!') ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($editId > 0 || (isset($_GET['edit']) && $_GET['edit'] === '0')): ?>
<div class="admin-card">
    <h3><?= $editItem ? 'Editar Inscrito' : 'Novo Inscrito' ?></h3>
    <form method="POST" class="form-grid">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?= (int)($editItem['id'] ?? 0) ?>">

        <div class="form-group">
            <label for="email">E-mail *</label>
            <input type="email" id="email" name="email" value="<?= e($editItem['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="name">Nome</label>
            <input type="text" id="name" name="name" value="<?= e($editItem['name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="tags">Tags</label>
            <input type="text" id="tags" name="tags" value="<?= e($editItem['tags'] ?? '') ?>" placeholder="beta-tester, influenciador">
            <div class="field-hint">Separadas por vírgula</div>
        </div>

        <div class="form-actions">
            <button class="btn btn-gold" type="submit">Salvar</button>
            <a class="btn btn-outline" href="?">Cancelar</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="admin-card">
    <h3>Inscritos (<?= $total ?>)</h3>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>ID</th>
                    <th>E-mail</th>
                    <th>Nome</th>
                    <th>Tags</th>
                    <th>Status</th>
                    <th>Inscrito em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subscribers as $s): ?>
                <tr>
                    <td><input type="checkbox" class="row-select" value="<?= (int)$s['id'] ?>"></td>
                    <td><?= (int)$s['id'] ?></td>
                    <td><?= e($s['email']) ?></td>
                    <td><?= e($s['name'] ?: '—') ?></td>
                    <td><?= e($s['tags'] ?: '—') ?></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <input type="hidden" name="active" value="<?= $s['is_active'] ? 0 : 1 ?>">
                            <button type="submit" class="badge badge-<?= $s['is_active'] ? 'success' : 'muted' ?>"><?= $s['is_active'] ? 'Ativo' : 'Inativo' ?></button>
                        </form>
                    </td>
                    <td><?= e($s['subscribed_at']) ?></td>
                    <td class="actions">
                        <a class="btn btn-outline btn-sm" href="?edit=<?= (int)$s['id'] ?>">Editar</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Remover este inscrito?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <button class="btn btn-danger btn-sm">Excluir</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($subscribers)): ?>
                    <tr><td colspan="8" class="text-muted">Nenhum inscrito encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <form method="POST" id="bulkForm" class="bulk-form" data-bulk-group="newsletter">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="bulk_delete">
    </form>
    <div class="bulk-bar">
        <span class="bulk-count">0 selecionados</span>
        <button type="button" class="btn btn-danger btn-sm bulk-delete-btn" disabled>Excluir selecionados</button>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="btn btn-outline btn-sm" href="?page=<?= $i ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Bulk select
document.getElementById('select-all').addEventListener('click', function() {
    var checked = this.checked;
    document.querySelectorAll('.row-select').forEach(cb => cb.checked = checked);
    updateBulkBar();
});
document.querySelectorAll('.row-select').forEach(cb => {
    cb.addEventListener('change', updateBulkBar);
});

function updateBulkBar() {
    var count = document.querySelectorAll('.row-select:checked').length;
    document.querySelector('.bulk-count').textContent = count + ' selecionados';
    document.querySelector('.bulk-delete-btn').disabled = count === 0;
}

document.querySelector('.bulk-delete-btn').addEventListener('click', function() {
    if (!confirm('Remover ' + document.querySelectorAll('.row-select:checked').length + ' inscritos?')) return;
    var ids = Array.from(document.querySelectorAll('.row-select:checked')).map(cb => cb.value);
    var form = document.getElementById('bulkForm');
    ids.forEach(id => {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        form.appendChild(input);
    });
    form.submit();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
