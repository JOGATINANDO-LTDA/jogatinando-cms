<?php
ob_start();
$pageTitle = 'Plataformas';
require_once __DIR__ . '/../includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? $id);
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Token de segurança inválido.');
        ob_end_clean();
        header('Location: platforms');
        exit;
    }

    if ($_POST['action'] === 'save') {
        $name = trim($_POST['name']);
        $slug = !empty(trim($_POST['slug'])) ? generateSlug(trim($_POST['slug'])) : generateSlug($name);
        $icon = trim($_POST['icon']);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($name)) {
            flashMessage('error', 'Nome é obrigatório.');
        } else {
            try {
                if ($id > 0) {
                    dbExec("UPDATE store_platforms SET name=?, slug=?, icon=?, sort_order=?, active=? WHERE id=?",
                        [$name, $slug, $icon, $sortOrder, $active, $id]);
                    flashMessage('success', 'Plataforma atualizada com sucesso.');
                } else {
                    dbExec("INSERT INTO store_platforms (name, slug, icon, sort_order, active) VALUES (?, ?, ?, ?, ?)",
                        [$name, $slug, $icon, $sortOrder, $active]);
                    flashMessage('success', 'Plataforma criada com sucesso.');
                }
            } catch (Exception $ex) {
                flashMessage('error', 'Erro ao salvar: ' . $ex->getMessage());
            }
        }
        ob_end_clean();
        header('Location: platforms');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $used = dbQueryOne("SELECT COUNT(*) as cnt FROM game_links WHERE platform_id = ?", [$id]);
        if ($used && $used['cnt'] > 0) {
            flashMessage('error', 'Não é possível excluir: existem jogos vinculados a esta plataforma.');
        } else {
            dbDelete('store_platforms', $id);
            flashMessage('success', 'Plataforma excluída.');
        }
        ob_end_clean();
        header('Location: platforms');
        exit;
    }

    if ($_POST['action'] === 'toggle') {
        $p = dbQueryOne("SELECT active FROM store_platforms WHERE id = ?", [$id]);
        if ($p) {
            dbExec("UPDATE store_platforms SET active = ? WHERE id = ?", [1 - $p['active'], $id]);
        }
        ob_end_clean();
        header('Location: platforms');
        exit;
    }
}

if ($action === 'new' || $action === 'edit') {
    $platform = $id > 0 ? dbQueryOne("SELECT * FROM store_platforms WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$platform) {
        flashMessage('error', 'Plataforma não encontrada.');
        header('Location: platforms');
        exit;
    }
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $action === 'new' ? 'Nova Plataforma' : 'Editar Plataforma' ?></h2>
            <a href="platforms" class="btn btn-outline btn-sm">← Voltar</a>
        </div>
        <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
            <?= csrfField() ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="name">Nome *</label>
                    <input type="text" id="name" name="name" value="<?= e($platform['name'] ?? '') ?>" required placeholder="Ex: Steam">
                </div>
                <div class="form-group">
                    <label for="slug">Slug (deixe em branco para gerar automático)</label>
                    <input type="text" id="slug" name="slug" value="<?= e($platform['slug'] ?? '') ?>" placeholder="Ex: steam">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="icon">Ícone (emoji)</label>
                    <input type="text" id="icon" name="icon" value="<?= e($platform['icon'] ?? '🛒') ?>" maxlength="10">
                    <div class="field-hint">Clique no seletor para escolher um emoji</div>
                </div>
                <div class="form-group">
                    <label for="sort_order">Ordem</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= (int)($platform['sort_order'] ?? 0) ?>">
                </div>
            </div>

            <div class="form-group">
                <div class="toggle-group">
                    <input type="checkbox" id="active" name="active" <?= ($platform['active'] ?? 1) ? 'checked' : '' ?>>
                    <label for="active">Ativa (aparece nos links de jogos)</label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-gold">Salvar</button>
                <a href="platforms" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
        </div>
    </div>
    <?php
} else {
    $platforms = dbQuery("SELECT p.*, (SELECT COUNT(*) FROM game_links WHERE platform_id = p.id) as link_count FROM store_platforms p ORDER BY p.active DESC, p.sort_order ASC, p.name ASC");
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Plataformas de Distribuição</h2>
            <a href="platforms?action=new" class="btn btn-gold btn-sm">+ Nova Plataforma</a>
        </div>
        <?php if (empty($platforms)): ?>
            <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon">🛒</div>
                <p>Nenhuma plataforma cadastrada.</p>
            </div>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Plataforma</th>
                            <th>Slug</th>
                            <th class="hide-tablet">Links</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($platforms as $p): ?>
                        <tr>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:8px">
                                    <span style="font-size:20px"><?= e($p['icon'] ?? '🛒') ?></span>
                                    <span style="font-weight:600;color:var(--fg)"><?= e($p['name']) ?></span>
                                </span>
                            </td>
                            <td><code><?= e($p['slug']) ?></code></td>
                            <td class="hide-tablet"><?= (int)$p['link_count'] ?></td>
                            <td>
                                <?php if ($p['active']): ?>
                                    <span class="badge badge-active">Ativa</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Inativa</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-outline btn-sm btn-icon" title="<?= $p['active'] ? 'Desativar' : 'Ativar' ?>"><?= $p['active'] ? '🔴' : '🟢' ?></button>
                                </form>
                                <a href="platforms?action=edit&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir plataforma <?= e($p['name']) ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Excluir">🗑️</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

require_once __DIR__ . '/../includes/footer.php';
