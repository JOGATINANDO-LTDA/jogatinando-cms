<?php
ob_start();
$pageTitle = 'Engines';
$requiredPerm = 'perm_engines';
require_once __DIR__ . '/../includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? $id);
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Token de segurança inválido.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/engines');
        exit;
    }

    if ($_POST['action'] === 'save') {
        $name = trim($_POST['name']);
        $slug = !empty(trim($_POST['slug'])) ? generateSlug(trim($_POST['slug'])) : generateSlug($name);
        $icon = trim($_POST['icon']);
        $color = trim($_POST['color']);
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($name)) {
            flashMessage('error', 'Nome é obrigatório.');
        } else {
            try {
                if ($id > 0) {
                    dbExec("UPDATE engines SET name=?, slug=?, icon=?, color=?, active=? WHERE id=?",
                        [$name, $slug, $icon, $color, $active, $id]);
                    flashMessage('success', 'Engine atualizada com sucesso.');
                } else {
                    dbExec("INSERT INTO engines (name, slug, icon, color, active) VALUES (?, ?, ?, ?, ?)",
                        [$name, $slug, $icon, $color, $active]);
                    flashMessage('success', 'Engine criada com sucesso.');
                }
            } catch (Exception $ex) {
                flashMessage('error', 'Erro ao salvar: ' . $ex->getMessage());
            }
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/engines');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $usedGames = dbQueryOne("SELECT COUNT(*) as cnt FROM games WHERE engine = (SELECT name FROM engines WHERE id = ?)", [$id]);
        $usedTemplates = dbQueryOne("SELECT COUNT(*) as cnt FROM game_templates WHERE engine = (SELECT name FROM engines WHERE id = ?)", [$id]);
        if (($usedGames && $usedGames['cnt'] > 0) || ($usedTemplates && $usedTemplates['cnt'] > 0)) {
            flashMessage('error', 'Não é possível excluir: existem jogos ou templates usando esta engine.');
        } else {
            dbDelete('engines', $id);
            flashMessage('success', 'Engine excluída.');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/engines');
        exit;
    }

    if ($_POST['action'] === 'delete_selected') {
        $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            dbExec("DELETE FROM engines WHERE id IN ($placeholders)", $ids);
            flashMessage('success', count($ids) . ' engine(s) removida(s).');
        }
        ob_end_clean(); header('Location: ' . ADMIN_URL . '/engines'); exit;
    }

    if ($_POST['action'] === 'toggle') {
        $engine = dbQueryOne("SELECT active FROM engines WHERE id = ?", [$id]);
        if ($engine) {
            dbExec("UPDATE engines SET active = ? WHERE id = ?", [1 - $engine['active'], $id]);
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/engines');
        exit;
    }
}

if ($action === 'new' || $action === 'edit') {
    $engine = $id > 0 ? dbQueryOne("SELECT * FROM engines WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$engine) {
        flashMessage('error', 'Engine não encontrada.');
        header('Location: ' . ADMIN_URL . '/engines');
        exit;
    }
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $action === 'new' ? 'Nova Engine' : 'Editar Engine' ?></h2>
            <a href="engines" class="btn btn-outline btn-sm">← Voltar</a>
        </div>
        <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
            <?= csrfField() ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="name">Nome da Engine *</label>
                    <input type="text" id="name" name="name" value="<?= e($engine['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="slug">Slug (deixe em branco para gerar automático)</label>
                    <input type="text" id="slug" name="slug" value="<?= e($engine['slug'] ?? '') ?>" placeholder="Ex: gdevelop">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="icon">Ícone (emoji)</label>
                    <input type="text" id="icon" name="icon" class="emoji-field" value="<?= e($engine['icon'] ?? '🎮') ?>" maxlength="10">
                    <div class="field-hint">Clique no seletor para escolher um emoji</div>
                </div>
                <div class="form-group">
                    <label for="color">Cor (OKLCH)</label>
                    <input type="text" id="color" name="color" value="<?= e($engine['color'] ?? 'oklch(68% 0.16 220)') ?>" placeholder="oklch(55% 0.15 145)">
                    <div class="field-hint">Formato: <code>oklch(L% C H)</code></div>
                </div>
                <div class="form-group" style="flex:0 0 120px">
                    <label for="active_preview">Preview</label>
                    <div style="padding:8px 12px;border-radius:var(--radius-pill);background:<?= e($engine['color'] ?? 'oklch(68% 0.16 220)') ?>;display:inline-block;font-size:13px;font-weight:600;text-transform:uppercase;color:#fff">
                        <span id="previewIcon"><?= $engine['icon'] ?? '🎮' ?></span>
                        <span id="previewName"><?= e($engine['name'] ?? 'Engine') ?></span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="toggle-group">
                    <input type="checkbox" id="active" name="active" <?= ($engine['active'] ?? 0) ? 'checked' : '' ?>>
                    <label for="active">Ativa (visível na plataforma)</label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-gold">Salvar</button>
                <a href="engines" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const nameInput = document.getElementById('name');
        const iconInput = document.getElementById('icon');
        const colorInput = document.getElementById('color');
        const previewIcon = document.getElementById('previewIcon');
        const previewName = document.getElementById('previewName');
        const activePreview = document.getElementById('active_preview');

        function updatePreview() {
            previewIcon.textContent = iconInput.value || '🎮';
            previewName.textContent = nameInput.value || 'Engine';
            const preview = previewIcon.parentElement;
            preview.style.background = colorInput.value || 'oklch(68% 0.16 220)';
        }

        nameInput.addEventListener('input', updatePreview);
        iconInput.addEventListener('input', updatePreview);
        colorInput.addEventListener('input', updatePreview);
    });
    </script>
    <?php
} else {
    $pager = paginateQuery('SELECT COUNT(*) as c FROM engines', 'SELECT e.*, (SELECT COUNT(*) FROM games WHERE engine = e.name) as game_count FROM engines e ORDER BY active DESC, name ASC');
    $engines = $pager['items'];
    $totalItems = $pager['total'];

    $allEngines = getEngines();
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Engines (<?= $totalItems ?>)</h2>
            <a href="engines?action=new" class="btn btn-gold btn-sm">+ Nova Engine</a>
        </div>
        <?php if (!empty($pager['error'])): ?>
            <?= renderDbErrorCard($pager['error']) ?>
        <?php elseif (empty($engines)): ?>
            <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon">🎮</div>
                <p>Nenhuma engine cadastrada.</p>
            </div>
            </div>
        <?php else: ?>
            <form method="POST" id="bulkForm"><?= csrfField() ?><input type="hidden" name="action" value="delete_selected"></form>
            <div class="bulk-bar" id="bulkBar"><span class="bulk-count" id="bulkCount">0 selecionados</span><button type="button" class="btn btn-danger btn-sm" id="bulkDeleteBtn" disabled>Excluir Selecionados</button></div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
                            <th>Engine</th>
                            <th>Slug</th>
                            <th class="hide-tablet">Jogos</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($engines as $e): ?>
                        <tr>
                            <td><input type="checkbox" class="row-select" value="<?= (int)$e['id'] ?>"></td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:8px">
                                    <span style="font-size:20px"><?= e($e['icon'] ?? '🎮') ?></span>
                                    <span style="font-weight:600;color:var(--fg)"><?= e($e['name']) ?></span>
                                </span>
                            </td>
                            <td><code><?= e($e['slug']) ?></code></td>
                            <td class="hide-tablet"><?= (int)$e['game_count'] ?></td>
                            <td>
                                <?php if ($e['active']): ?>
                                    <span class="badge badge-active">Ativa</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Inativa</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-outline btn-sm btn-icon" title="<?= $e['active'] ? 'Desativar' : 'Ativar' ?>"><?= $e['active'] ? '🔴' : '🟢' ?></button>
                                </form>
                                <a href="engines?action=edit&id=<?= $e['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir engine <?= e($e['name']) ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Excluir">🗑️</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= renderPagination($pager['page'], $pager['pages']) ?>
        <?php endif; ?>
    </div>
    <?php
}

require_once __DIR__ . '/../includes/footer.php';
