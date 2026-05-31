<?php
ob_start();
$pageTitle = 'Jogos Retro';
require_once __DIR__ . '/../includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? $id);
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Token de segurança inválido.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/retro-games');
        exit;
    }

    if ($_POST['action'] === 'save') {
        $title = trim($_POST['title']);
        $slug = trim($_POST['slug']) ?: generateSlug($title);
        $console = trim($_POST['console']);
        $type = in_array($_POST['type'] ?? 'original', ['original', 'modified'], true) ? $_POST['type'] : 'original';
        $modificationDescription = trim(mb_substr($_POST['modification_description'] ?? '', 0, 60));
        $description = trim($_POST['description']);
        $featured = isset($_POST['featured']) ? 1 : 0;
        $active = isset($_POST['active']) ? 1 : 0;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $emulatorCore = trim($_POST['emulator_core']);
        $isNew = $id <= 0;
        $thumbnailUrl = '';
        $romPath = '';

        if (empty($title) || empty($console)) {
            flashMessage('error', 'Título e console são obrigatórios.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/retro-games?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
            exit;
        }

        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['thumbnail'], 'thumbnails', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($result['success']) {
                $thumbnailUrl = $result['url'];
            } else {
                flashMessage('error', $result['message']);
                ob_end_clean();
                header('Location: ' . ADMIN_URL . '/retro-games?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
                exit;
            }
        }

        if (isset($_FILES['rom']) && $_FILES['rom']['error'] === UPLOAD_ERR_OK) {
            $result = uploadRetroRom($_FILES['rom'], $console, $slug, $type);
            if ($result['success']) {
                $romPath = $result['rel_path'];
            } else {
                flashMessage('error', $result['message']);
                ob_end_clean();
                header('Location: ' . ADMIN_URL . '/retro-games?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
                exit;
            }
        }

        if ($isNew && $sortOrder === 0) {
            $max = dbQueryOne("SELECT COALESCE(MAX(sort_order), 0) + 1 as next FROM retro_games WHERE console = ?", [$console]);
            $sortOrder = (int)($max['next'] ?? 1);
        }

        try {
            if ($id > 0) {
                $existing = dbQueryOne("SELECT * FROM retro_games WHERE id = ?", [$id]);
                if (!$thumbnailUrl) $thumbnailUrl = $existing['thumbnail_url'] ?? '';
                if (!$romPath) $romPath = $existing['rom_path'] ?? '';

                dbExec("UPDATE retro_games SET title=?, slug=?, console=?, type=?, modification_description=?, rom_path=?, description=?, thumbnail_url=?, emulator_core=?, active=?, featured=?, sort_order=?, updated_at=CURRENT_TIMESTAMP WHERE id=?",
                    [$title, $slug, $console, $type, $modificationDescription, $romPath, $description, $thumbnailUrl, $emulatorCore, $active, $featured, $sortOrder, $id]);
                flashMessage('success', 'Jogo retro atualizado com sucesso.');
            } else {
                dbExec("INSERT INTO retro_games (title, slug, console, type, modification_description, rom_path, description, thumbnail_url, emulator_core, active, featured, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$title, $slug, $console, $type, $modificationDescription, $romPath, $description, $thumbnailUrl, $emulatorCore, $active, $featured, $sortOrder]);
                flashMessage('success', 'Jogo retro criado com sucesso.');
            }
        } catch (Exception $ex) {
            flashMessage('error', 'Erro ao salvar: ' . $ex->getMessage());
        }

        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/retro-games');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $game = dbQueryOne("SELECT * FROM retro_games WHERE id = ?", [$id]);
        if ($game) {
            if (!empty($game['rom_path'])) {
                deleteFile(ROOT_PATH . '/uploads/' . ltrim($game['rom_path'], '/'));
            }
            if (!empty($game['thumbnail_url'])) {
                $thumbPath = parse_url($game['thumbnail_url'], PHP_URL_PATH);
                deleteFile(ROOT_PATH . $thumbPath);
            }
            dbDelete('retro_games', $id);
            flashMessage('success', 'Jogo retro excluído.');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/retro-games');
        exit;
    }

    if ($_POST['action'] === 'toggle') {
        $game = dbQueryOne("SELECT active FROM retro_games WHERE id = ?", [$id]);
        if ($game) {
            dbExec("UPDATE retro_games SET active = ? WHERE id = ?", [1 - $game['active'], $id]);
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/retro-games');
        exit;
    }
}

if ($action === 'new' || $action === 'edit') {
    $game = $id > 0 ? dbQueryOne("SELECT * FROM retro_games WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$game) {
        flashMessage('error', 'Jogo retro não encontrado.');
        header('Location: ' . ADMIN_URL . '/retro-games');
        exit;
    }
    $consoles = dbQuery("SELECT * FROM retro_consoles WHERE active = 1 ORDER BY sort_order ASC, name ASC");
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $action === 'new' ? 'Novo Jogo Retro' : 'Editar Jogo Retro' ?></h2>
            <a href="retro-games" class="btn btn-outline btn-sm">← Voltar</a>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
                <?= csrfField() ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Título *</label>
                        <input type="text" id="title" name="title" value="<?= e($game['title'] ?? '') ?>" required placeholder="Ex: Joe & Mac 2">
                    </div>
                    <div class="form-group">
                        <label for="slug">Slug</label>
                        <input type="text" id="slug" name="slug" value="<?= e($game['slug'] ?? '') ?>" placeholder="Ex: joeemac2">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="console">Console *</label>
                        <select id="console" name="console" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($consoles as $console): ?>
                                <option value="<?= e($console['slug']) ?>" <?= ($game['console'] ?? '') === $console['slug'] ? 'selected' : '' ?>><?= e($console['icon'] ?? '') ?> <?= e($console['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="type">Tipo</label>
                        <select id="type" name="type">
                            <option value="original" <?= ($game['type'] ?? 'original') === 'original' ? 'selected' : '' ?>>Original</option>
                            <option value="modified" <?= ($game['type'] ?? '') === 'modified' ? 'selected' : '' ?>>Modificado</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" id="modDescRow" style="display:<?= ($game['type'] ?? 'original') === 'modified' ? 'flex' : 'none' ?>">
                    <div class="form-group" style="flex:1">
                        <label for="modification_description">Descrição da Modificação</label>
                        <input type="text" id="modification_description" name="modification_description" value="<?= e($game['modification_description'] ?? '') ?>" maxlength="60" placeholder="Ex: Tradução PT-BR, Novo jogo, Hack de levels">
                        <small class="field-hint">Máximo de 60 caracteres. Descreva resumidamente o que foi modificado.</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="emulator_core">Core EmulatorJS</label>
                        <input type="text" id="emulator_core" name="emulator_core" value="<?= e($game['emulator_core'] ?? '') ?>" placeholder="Deixe em branco para usar o core do console">
                    </div>
                    <div class="form-group">
                        <label for="sort_order">Ordem</label>
                        <input type="number" id="sort_order" name="sort_order" value="<?= (int)($game['sort_order'] ?? 0) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Descrição</label>
                    <textarea id="description" name="description" rows="4"><?= e($game['description'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Thumbnail</label>
                        <div class="file-upload">
                            <input type="file" name="thumbnail" accept="image/*">
                            <div class="upload-icon">
                                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            </div>
                            <div class="upload-text">Clique ou arraste uma imagem</div>
                        </div>
                        <?php if (!empty($game['thumbnail_url'])): ?><img src="<?= e($game['thumbnail_url']) ?>" class="preview-img" alt="Thumbnail"><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>ROM</label>
                        <div class="file-upload">
                            <input type="file" name="rom" accept=".sfc,.smc,.zip,.nes,.gb,.gba,.bin,.iso,.cue,.chd">
                            <div class="upload-icon">
                                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            </div>
                            <div class="upload-text">Envie a ROM</div>
                        </div>
                        <?php if (!empty($game['rom_path'])): ?><p class="hint">Arquivo atual: <?= e($game['rom_path']) ?></p><?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <div class="toggle-group" style="margin-top:28px">
                            <input type="checkbox" id="featured" name="featured" <?= ($game['featured'] ?? 0) ? 'checked' : '' ?>>
                            <label for="featured">Destaque</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="toggle-group" style="margin-top:28px">
                            <input type="checkbox" id="active" name="active" <?= ($game['active'] ?? 1) ? 'checked' : '' ?>>
                            <label for="active">Ativo</label>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-gold">Salvar</button>
                    <a href="retro-games" class="btn btn-outline">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var typeSelect = document.getElementById('type');
        var modDescRow = document.getElementById('modDescRow');
        if (typeSelect && modDescRow) {
            typeSelect.addEventListener('change', function() {
                modDescRow.style.display = this.value === 'modified' ? 'flex' : 'none';
            });
        }
    });
    </script>
    <?php
} else {
    $allConsoles = dbQuery("SELECT * FROM retro_consoles ORDER BY sort_order ASC, name ASC");
    $where = "1=1";
    $params = [];
    if (!empty($_GET['console'])) {
        $where .= " AND r.console = ?";
        $params[] = $_GET['console'];
    }
    if (!empty($_GET['search'])) {
        $where .= " AND r.title LIKE ?";
        $params[] = '%' . $_GET['search'] . '%';
    }
    $games = dbQuery("SELECT r.*, c.name as console_name, c.icon as console_icon FROM retro_games r LEFT JOIN retro_consoles c ON c.slug = r.console WHERE $where ORDER BY r.active DESC, r.sort_order ASC, r.created_at DESC", $params);
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Jogos Retro</h2>
            <a href="retro-games?action=new" class="btn btn-gold btn-sm">+ Novo Jogo Retro</a>
        </div>
        <div class="card-body">
            <form method="GET" class="filters-form" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-bottom:16px">
                <div class="form-group" style="margin-bottom:0;min-width:180px">
                    <label for="filter-console" style="font-size:12px;text-transform:uppercase;letter-spacing:0.04em;color:var(--muted);margin-bottom:4px;display:block">Console</label>
                    <select id="filter-console" name="console" onchange="this.form.submit()" style="min-height:40px;padding:0 12px;border-radius:var(--radius-md);border:1px solid var(--border);background:var(--bg-input);color:var(--fg)">
                        <option value="">Todos</option>
                        <?php foreach ($allConsoles as $ac): ?>
                            <option value="<?= e($ac['slug']) ?>" <?= (isset($_GET['console']) && $_GET['console'] === $ac['slug']) ? 'selected' : '' ?>><?= e($ac['icon'] ?? '') ?> <?= e($ac['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;min-width:200px">
                    <label for="filter-search" style="font-size:12px;text-transform:uppercase;letter-spacing:0.04em;color:var(--muted);margin-bottom:4px;display:block">Buscar</label>
                    <div style="display:flex;gap:8px">
                        <input type="search" id="filter-search" name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Buscar por título..." style="min-height:40px;padding:0 12px;border-radius:var(--radius-md);border:1px solid var(--border);background:var(--bg-input);color:var(--fg);flex:1;min-width:120px">
                        <button type="submit" class="btn btn-outline btn-sm" style="min-height:40px">OK</button>
                        <?php if (!empty($_GET['console']) || !empty($_GET['search'])): ?>
                            <a href="retro-games" class="btn btn-outline btn-sm" style="min-height:40px">Limpar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        <?php if (empty($games)): ?>
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-icon">🕹️</div>
                    <p>Nenhum jogo retro encontrado.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Console</th>
                            <th class="hide-tablet">Tipo</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($games as $g): ?>
                        <tr>
                            <td><strong style="color:var(--fg)"><?= e($g['title']) ?></strong></td>
                            <td><span style="display:inline-flex;align-items:center;gap:8px"><span style="font-size:20px"><?= e($g['console_icon'] ?? '🎮') ?></span><span><?= e($g['console_name'] ?? $g['console']) ?></span></span></td>
                            <td class="hide-tablet"><?= e($g['type'] === 'modified' ? ($g['modification_description'] ?: 'Modificado') : 'Original') ?></td>
                            <td>
                                <?php if ($g['active']): ?>
                                    <span class="badge badge-active">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-outline btn-sm btn-icon" title="<?= $g['active'] ? 'Desativar' : 'Ativar' ?>"><?= $g['active'] ? '🔴' : '🟢' ?></button>
                                </form>
                                <a href="retro-games?action=edit&id=<?= $g['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este jogo retro?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $g['id'] ?>">
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
