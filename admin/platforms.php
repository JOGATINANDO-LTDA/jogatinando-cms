<?php
ob_start();
$pageTitle = 'Plataformas';
$requiredPerm = 'perm_platforms';
require_once __DIR__ . '/../includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? $id);
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Token de segurança inválido.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/platforms');
        exit;
    }

    if ($_POST['action'] === 'save') {
        $saveId = (int)($_POST['id'] ?? 0);
        $existing = $saveId > 0 ? dbQueryOne("SELECT logo_path FROM store_platforms WHERE id = ?", [$saveId]) : null;
        $name = trim($_POST['name']);
        $slug = !empty(trim($_POST['slug'])) ? generateSlug(trim($_POST['slug'])) : generateSlug($name);
        $icon = trim($_POST['icon']);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        $useLogo = isset($_POST['use_logo']) ? 1 : 0;
        $logoPath = $existing['logo_path'] ?? '';

        if (empty($name)) {
            flashMessage('error', 'Nome é obrigatório.');
        } else {
            try {
                if ($useLogo && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = uploadFile($_FILES['logo'], 'platforms', ['png', 'jpg', 'jpeg', 'gif', 'webp']);
                    if ($uploadResult['success']) {
                        if ($logoPath) deleteFile(UPLOAD_PATH . '/' . $logoPath);
                        $logoPath = ltrim($uploadResult['url'], '/');
                    } else {
                        flashMessage('error', 'Erro no upload da logo: ' . $uploadResult['message']);
                    }
                } elseif (!$useLogo && $logoPath) {
                    deleteFile(UPLOAD_PATH . '/' . $logoPath);
                    $logoPath = '';
                }

                if ($saveId > 0) {
                    dbExec("UPDATE store_platforms SET name=?, slug=?, icon=?, use_logo=?, logo_path=?, sort_order=?, active=? WHERE id=?",
                        [$name, $slug, $icon, $useLogo, $logoPath, $sortOrder, $active, $saveId]);
                    flashMessage('success', 'Plataforma atualizada com sucesso.');
                } else {
                    dbExec("INSERT INTO store_platforms (name, slug, icon, use_logo, logo_path, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [$name, $slug, $icon, $useLogo, $logoPath, $sortOrder, $active]);
                    flashMessage('success', 'Plataforma criada com sucesso.');
                }
            } catch (Exception $ex) {
                flashMessage('error', 'Erro ao salvar: ' . $ex->getMessage());
            }
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/platforms');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $usedGames = dbQueryOne("SELECT COUNT(*) as cnt FROM game_links WHERE platform_id = ?", [$id]);
        $usedTemplates = dbQueryOne("SELECT COUNT(*) as cnt FROM template_links WHERE platform_id = ?", [$id]);
        if (($usedGames && $usedGames['cnt'] > 0) || ($usedTemplates && $usedTemplates['cnt'] > 0)) {
            flashMessage('error', 'Não é possível excluir: existem jogos ou templates vinculados a esta plataforma.');
        } else {
            $delPlatform = dbQueryOne("SELECT logo_path FROM store_platforms WHERE id = ?", [$id]);
            if ($delPlatform && !empty($delPlatform['logo_path'])) {
                deleteFile(UPLOAD_PATH . '/' . $delPlatform['logo_path']);
            }
            dbDelete('store_platforms', $id);
            flashMessage('success', 'Plataforma excluída.');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/platforms');
        exit;
    }

    if ($_POST['action'] === 'delete_selected') {
        $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            dbExec("DELETE FROM store_platforms WHERE id IN ($placeholders)", $ids);
            flashMessage('success', count($ids) . ' plataforma(s) removida(s).');
        }
        ob_end_clean(); header('Location: ' . ADMIN_URL . '/platforms'); exit;
    }

    if ($_POST['action'] === 'toggle') {
        $p = dbQueryOne("SELECT active FROM store_platforms WHERE id = ?", [$id]);
        if ($p) {
            dbExec("UPDATE store_platforms SET active = ? WHERE id = ?", [1 - $p['active'], $id]);
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/platforms');
        exit;
    }
}

if ($action === 'new' || $action === 'edit') {
    $platform = $id > 0 ? dbQueryOne("SELECT * FROM store_platforms WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$platform) {
        flashMessage('error', 'Plataforma não encontrada.');
        header('Location: ' . ADMIN_URL . '/platforms');
        exit;
    }
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $action === 'new' ? 'Nova Plataforma' : 'Editar Plataforma' ?></h2>
            <a href="platforms" class="btn btn-outline btn-sm">← Voltar</a>
        </div>
        <div class="card-body">
        <form method="POST" class="form-grid form-grid-limited" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">
            <?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
            <?= csrfField() ?>

            <h3 class="form-section-title">Identificação</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="name">Nome *</label>
                    <input type="text" id="name" name="name" value="<?= e($platform['name'] ?? '') ?>" required placeholder="Ex: Steam">
                </div>
                <div class="form-group">
                    <label for="slug">Slug (deixe em branco para gerar automático)</label>
                    <input type="text" id="slug" name="slug" value="<?= e($platform['slug'] ?? '') ?>" placeholder="Ex: steam">
                    <div class="field-hint">Identificador usado em URLs. Se ficar vazio, será gerado automaticamente.</div>
                </div>
            </div>

            <h3 class="form-section-title">Configurações</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="icon">Ícone (emoji)</label>
                    <input type="text" id="icon" name="icon" class="emoji-field" value="<?= e($platform['icon'] ?? '🛒') ?>" maxlength="10">
                    <div class="field-hint">Clique no seletor para escolher um emoji</div>
                </div>
                <div class="form-group">
                    <label for="sort_order">Ordem</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= (int)($platform['sort_order'] ?? 0) ?>">
                    <div class="field-hint">Ordem de exibição nos links de jogos.</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <div class="toggle-group">
                        <input type="checkbox" id="use_logo" name="use_logo" <?= ($platform['use_logo'] ?? 0) ? 'checked' : '' ?>>
                        <label for="use_logo">Usar logo da marca</label>
                    </div>
                    <div class="field-hint">Se marcado, substitui o emoji pela logo enviada</div>
                </div>
            </div>

            <h3 class="form-section-title">Logo</h3>

            <div id="logoUploadRow" class="<?= ($platform['use_logo'] ?? 0) ? '' : 'hidden' ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label for="logo">Logo</label>
                        <div class="file-upload">
                            <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/gif,image/webp">
                            <div class="upload-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
                            <div class="upload-text">Upload da logo da marca</div>
                            <div class="upload-hint">PNG, JPG, WebP ou GIF. Máximo: 500KB.</div>
                        </div>
                    </div>
                </div>
                <?php if (!empty($platform['logo_path'])): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Logo atual</label>
                        <img src="<?= logoImgSrc($platform['logo_path']) ?>" class="preview-img" alt="Logo">
                    </div>
                </div>
                <?php endif; ?>
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
    <script>
    (function() {
        var useLogo = document.getElementById('use_logo');
        var logoRow = document.getElementById('logoUploadRow');
        if (useLogo && logoRow) {
            useLogo.addEventListener('change', function() {
                logoRow.classList.toggle('hidden', !this.checked);
            });
        }
    })();
    </script>
    <?php
} else {
    $pager = paginateQuery('SELECT COUNT(*) as c FROM store_platforms', 'SELECT p.*, (SELECT COUNT(*) FROM game_links WHERE platform_id = p.id) as link_count FROM store_platforms p ORDER BY p.active DESC, p.sort_order ASC, p.name ASC');
    $platforms = $pager['items'];
    $totalItems = $pager['total'];
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Plataformas de Distribuição (<?= $totalItems ?>)</h2>
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
            <form method="POST" id="bulkForm"><?= csrfField() ?><input type="hidden" name="action" value="delete_selected"></form>
            <div class="bulk-bar" id="bulkBar"><span class="bulk-count" id="bulkCount">0 selecionados</span><button type="button" class="btn btn-danger btn-sm" id="bulkDeleteBtn" disabled>Excluir Selecionados</button></div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
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
                            <td><input type="checkbox" class="row-select" value="<?= (int)$p['id'] ?>"></td>
                            <td>
                                <span class="platform-name">
                                    <?php if (!empty($p['use_logo']) && !empty($p['logo_path'])): ?>
                                        <span class="platform-thumb">
                                            <img src="<?= logoImgSrc($p['logo_path']) ?>" alt="<?= e($p['name']) ?>">
                                        </span>
                                    <?php else: ?>
                                        <span class="platform-thumb"><span class="emoji"><?= e($p['icon'] ?? '🛒') ?></span></span>
                                    <?php endif; ?>
                                    <span><?= e($p['name']) ?></span>
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
                                <form method="POST">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-outline btn-sm btn-icon" title="<?= $p['active'] ? 'Desativar' : 'Ativar' ?>"><?= $p['active'] ? '🔴' : '🟢' ?></button>
                                </form>
                                <a href="platforms?action=edit&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                                <form method="POST" onsubmit="return confirm('Excluir plataforma <?= e($p['name']) ?>?')">
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
            <?= renderPagination($pager['page'], $pager['pages']) ?>
        <?php endif; ?>
    </div>
    <?php
}

require_once __DIR__ . '/../includes/footer.php';
