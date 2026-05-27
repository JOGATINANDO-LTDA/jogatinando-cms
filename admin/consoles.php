<?php
ob_start();
$pageTitle = 'Emuladores';
require_once __DIR__ . '/../includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Token de segurança inválido.');
        ob_end_clean();
        header('Location: consoles');
        exit;
    }

    if ($_POST['action'] === 'save') {
        $name = trim($_POST['name']);
        $slug = !empty(trim($_POST['slug'])) ? generateSlug(trim($_POST['slug'])) : generateSlug($name);
        $icon = trim($_POST['icon']);
        $emulatorCore = trim($_POST['emulator_core']);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;

        if ($name === '') {
            flashMessage('error', 'Nome é obrigatório.');
        } else {
            try {
                if ($id > 0) {
                    dbExec("UPDATE retro_consoles SET name=?, slug=?, icon=?, emulator_core=?, sort_order=?, active=? WHERE id=?",
                        [$name, $slug, $icon, $emulatorCore, $sortOrder, $active, $id]);
                    flashMessage('success', 'Emulador atualizado com sucesso.');
                } else {
                    dbExec("INSERT INTO retro_consoles (name, slug, icon, emulator_core, sort_order, active) VALUES (?, ?, ?, ?, ?, ?)",
                        [$name, $slug, $icon, $emulatorCore, $sortOrder, $active]);
                    flashMessage('success', 'Emulador criado com sucesso.');
                }
            } catch (Exception $ex) {
                flashMessage('error', 'Erro ao salvar: ' . $ex->getMessage());
            }
        }
        ob_end_clean();
        header('Location: consoles');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $used = dbQueryOne("SELECT COUNT(*) as cnt FROM retro_games WHERE console = (SELECT slug FROM retro_consoles WHERE id = ?)", [$id]);
        if ($used && $used['cnt'] > 0) {
            flashMessage('error', 'Não é possível excluir: existem jogos vinculados a este emulador.');
        } else {
            dbDelete('retro_consoles', $id);
            flashMessage('success', 'Emulador excluído.');
        }
        ob_end_clean();
        header('Location: consoles');
        exit;
    }

    if ($_POST['action'] === 'toggle') {
        $console = dbQueryOne("SELECT active FROM retro_consoles WHERE id = ?", [$id]);
        if ($console) {
            dbExec("UPDATE retro_consoles SET active = ? WHERE id = ?", [1 - $console['active'], $id]);
        }
        ob_end_clean();
        header('Location: consoles');
        exit;
    }
}

if ($action === 'new' || $action === 'edit') {
    $console = $id > 0 ? dbQueryOne("SELECT * FROM retro_consoles WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$console) {
        flashMessage('error', 'Emulador não encontrado.');
        header('Location: consoles');
        exit;
    }
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $action === 'new' ? 'Novo Emulador' : 'Editar Emulador' ?></h2>
            <a href="consoles" class="btn btn-outline btn-sm">← Voltar</a>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
                <?= csrfField() ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Nome *</label>
                        <input type="text" id="name" name="name" value="<?= e($console['name'] ?? '') ?>" required placeholder="Ex: SNES">
                    </div>
                    <div class="form-group">
                        <label for="slug">Slug</label>
                        <input type="text" id="slug" name="slug" value="<?= e($console['slug'] ?? '') ?>" placeholder="Ex: snes">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="icon">Ícone (emoji)</label>
                        <input type="text" id="icon" name="icon" value="<?= e($console['icon'] ?? '🎮') ?>" maxlength="10">
                    </div>
                    <div class="form-group">
                        <label for="emulator_core">Core EmulatorJS</label>
                        <input type="text" id="emulator_core" name="emulator_core" value="<?= e($console['emulator_core'] ?? 'snes9x') ?>" placeholder="Ex: snes9x, genesis_plus_gx, pcsx_rearmed">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="sort_order">Ordem</label>
                        <input type="number" id="sort_order" name="sort_order" value="<?= (int)($console['sort_order'] ?? 0) ?>">
                    </div>
                    <div class="form-group">
                        <div class="toggle-group" style="margin-top:28px">
                            <input type="checkbox" id="active" name="active" <?= ($console['active'] ?? 1) ? 'checked' : '' ?>>
                            <label for="active">Ativo</label>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-gold">Salvar</button>
                    <a href="consoles" class="btn btn-outline">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <?php
} else {
    $consoles = dbQuery("SELECT c.*, (SELECT COUNT(*) FROM retro_games WHERE console = c.slug) as game_count FROM retro_consoles c ORDER BY c.active DESC, c.sort_order ASC, c.name ASC");
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Emuladores</h2>
            <a href="consoles?action=new" class="btn btn-gold btn-sm">+ Novo Emulador</a>
        </div>
        <?php if (empty($consoles)): ?>
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-icon">🕹️</div>
                    <p>Nenhum emulador cadastrado.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Emulador</th>
                            <th>Slug</th>
                            <th class="hide-tablet">Core</th>
                            <th class="hide-tablet">Jogos</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consoles as $c): ?>
                        <tr>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:8px">
                                    <span style="font-size:20px"><?= e($c['icon'] ?? '🎮') ?></span>
                                    <span style="font-weight:600;color:var(--fg)"><?= e($c['name']) ?></span>
                                </span>
                            </td>
                            <td><code><?= e($c['slug']) ?></code></td>
                            <td class="hide-tablet"><code><?= e($c['emulator_core']) ?></code></td>
                            <td class="hide-tablet"><?= (int)$c['game_count'] ?></td>
                            <td>
                                <?php if ($c['active']): ?>
                                    <span class="badge badge-active">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-outline btn-sm btn-icon" title="<?= $c['active'] ? 'Desativar' : 'Ativar' ?>"><?= $c['active'] ? '🔴' : '🟢' ?></button>
                                </form>
                                <a href="consoles?action=edit&id=<?= $c['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir emulador <?= e($c['name']) ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
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
