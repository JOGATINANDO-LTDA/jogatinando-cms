<?php
ob_start();
$pageTitle = 'Templates';
require_once __DIR__ . '/../includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? $id);
    if (empty($_POST) && empty($_FILES)) {
        $serverLimit = @ini_get('post_max_size') ?: '30M';
        flashMessage('error', "Arquivo excede o limite do servidor ($serverLimit).");
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/templates');
        exit;
    }

    if (isset($_FILES['template_archive']) && $_FILES['template_archive']['error'] !== UPLOAD_ERR_OK && $_FILES['template_archive']['error'] !== UPLOAD_ERR_NO_FILE) {
        $err = $_FILES['template_archive']['error'];
        $serverLimit = @ini_get('post_max_size') ?: '30M';
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            flashMessage('error', "Arquivo muito grande. Limite: $serverLimit.");
        } else {
            flashMessage('error', 'Erro no upload do arquivo.');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/templates');
        exit;
    }

    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Token de segurança inválido.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/templates');
        exit;
    }

    if ($_POST['action'] === 'save') {
        $title = trim($_POST['title']);
        $engine = trim($_POST['engine']);
        $description = trim($_POST['description']);
        $language = trim($_POST['language'] ?? '');
        $language_version = trim($_POST['language_version'] ?? '');
        $store_url = trim($_POST['store_url'] ?? '');
        $features = trim($_POST['features'] ?? '');
        $requirements = trim($_POST['requirements'] ?? '');
        $featured = isset($_POST['featured']) ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        $thumbnail_url = '';
        $game_path = '';
        $slug = generateSlug($title);

        // Handle thumbnail upload
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['thumbnail'], 'thumbnails', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($result['success']) {
                $thumbnail_url = $result['url'];
            } else {
                flashMessage('error', $result['message']);
                ob_end_clean();
                header('Location: ' . ADMIN_URL . '/templates?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
                exit;
            }
        }

        // Handle template archive upload (zip only)
        if (isset($_FILES['template_archive']) && $_FILES['template_archive']['error'] === UPLOAD_ERR_OK) {
            $result = uploadAndExtractGame($_FILES['template_archive'], $engine, $title);
            if ($result['success']) {
                $game_path = $result['game_path'];
            } else {
                flashMessage('error', $result['message']);
                ob_end_clean();
                header('Location: ' . ADMIN_URL . '/templates?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
                exit;
            }
        }

        if (empty($title)) {
            flashMessage('error', 'Título é obrigatório.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/templates?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
            exit;
        }

        try {
            if ($id > 0) {
                if (!$game_path) {
                    $existing = dbQueryOne("SELECT game_path FROM game_templates WHERE id = ?", [$id]);
                    $game_path = $existing['game_path'] ?? '';
                }
                if (!$thumbnail_url) {
                    $existing = dbQueryOne("SELECT thumbnail_url FROM game_templates WHERE id = ?", [$id]);
                    $thumbnail_url = $existing['thumbnail_url'] ?? '';
                }

                dbExec("UPDATE game_templates SET title=?, slug=?, engine=?, description=?, language=?, language_version=?, store_url=?, game_path=?, thumbnail_url=?, features=?, requirements=?, featured=?, sort_order=?, active=?, updated_at=CURRENT_TIMESTAMP WHERE id=?",
                    [$title, $slug, $engine, $description, $language, $language_version, $store_url, $game_path, $thumbnail_url, $features, $requirements, $featured, $sort_order, $active, $id]);
                flashMessage('success', 'Template atualizado!');
            } else {
                $id = dbExec("INSERT INTO game_templates (title, slug, engine, description, language, language_version, store_url, game_path, thumbnail_url, features, requirements, featured, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$title, $slug, $engine, $description, $language, $language_version, $store_url, $game_path, $thumbnail_url, $features, $requirements, $featured, $sort_order, $active]);
                flashMessage('success', 'Template criado!');
            }
        } catch (Exception $ex) {
            flashMessage('error', 'Erro ao salvar: ' . $ex->getMessage());
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/templates');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $template = dbQueryOne("SELECT * FROM game_templates WHERE id = ?", [$id]);
        if ($template) {
            if (!empty($template['game_path'])) {
                deleteGameDir($template['game_path']);
            }
            dbDelete('game_templates', $id);
            flashMessage('success', 'Template excluído.');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/templates');
        exit;
    }

    if ($_POST['action'] === 'toggle') {
        $template = dbQueryOne("SELECT active FROM game_templates WHERE id = ?", [$id]);
        if ($template) {
            dbExec("UPDATE game_templates SET active = ? WHERE id = ?", [1 - $template['active'], $id]);
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/templates');
        exit;
    }
}

if ($action === 'new' || $action === 'edit') {
    $template = $id > 0 ? dbQueryOne("SELECT * FROM game_templates WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$template) {
        flashMessage('error', 'Template não encontrado.');
        header('Location: ' . ADMIN_URL . '/templates');
        exit;
    }
    $postMax = @ini_get('post_max_size');
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $action === 'new' ? 'Novo Template' : 'Editar Template' ?></h2>
            <a href="templates" class="btn btn-outline btn-sm">← Voltar</a>
        </div>
        <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_UPLOAD_SIZE ?>">
            <?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
            <?= csrfField() ?>

            <h3 class="form-section-title">Informações Básicas</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="title">Título do Template *</label>
                    <input type="text" id="title" name="title" value="<?= e($template['title'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="engine">Engine *</label>
                    <select id="engine" name="engine" required>
                        <option value="">Selecione...</option>
                        <?php
                        $allEngines = getEngines();
                        $currentEngine = $template['engine'] ?? '';
                        foreach ($allEngines as $eng) {
                            $label = e($eng['icon'] ?? '') . ' ' . e($eng['name']);
                            if (!$eng['active']) $label .= ' (inativa)';
                            echo '<option value="' . e($eng['name']) . '" ' . ($currentEngine === $eng['name'] ? 'selected' : '') . '>'
                                . $label . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Descrição</label>
                <textarea id="description" name="description" rows="4"><?= e($template['description'] ?? '') ?></textarea>
            </div>

            <h3 class="form-section-title">Linguagem</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="language">Linguagem / Framework</label>
                    <input type="text" id="language" name="language" value="<?= e($template['language'] ?? '') ?>" placeholder="Ex: GDScript, C#, Lua...">
                </div>
                <div class="form-group">
                    <label for="language_version">Versão</label>
                    <input type="text" id="language_version" name="language_version" value="<?= e($template['language_version'] ?? '') ?>" placeholder="Ex: 4.2, 2021.3...">
                </div>
            </div>

            <h3 class="form-section-title">Mídia</h3>

            <div class="form-row">
                <div class="form-group">
                    <label>Thumbnail</label>
                    <div class="file-upload">
                        <input type="file" name="thumbnail" accept="image/*">
                        <div class="upload-icon">
                            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </div>
                        <div class="upload-text">Clique ou arraste uma imagem</div>
                        <div class="upload-hint">JPG, PNG, WebP — máx <?= e($postMax ?: '30M') ?></div>
                    </div>
                    <?php if (!empty($template['thumbnail_url'])): ?>
                        <img src="<?= e($template['thumbnail_url']) ?>" class="preview-img" alt="Thumbnail">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Arquivo do Template</label>
                    <div class="file-upload">
                        <input type="file" name="template_archive" accept=".zip">
                        <div class="upload-icon">
                            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </div>
                        <div class="upload-text">Upload do template</div>
                        <div class="upload-hint">ZIP — máx <?= e($postMax ?: '30M') ?></div>
                    </div>
                    <?php if (!empty($template['game_path'])): ?>
                        <p style="margin-top:8px;font-size:13px;color:var(--muted)">📎 <?= e($template['game_path']) ?> (envie outro para substituir)</p>
                    <?php endif; ?>
                </div>
            </div>

            <h3 class="form-section-title">Links e Detalhes</h3>

            <div class="form-group">
                <label for="store_url">URL da Loja / Marketplace</label>
                <input type="url" id="store_url" name="store_url" value="<?= e($template['store_url'] ?? '') ?>" placeholder="https://...">
                <div class="field-hint">Link para onde o template pode ser adquirido (Asset Store, itch.io, etc.)</div>
            </div>

            <div class="form-group">
                <label for="features">Recursos / Features</label>
                <textarea id="features" name="features" rows="3" placeholder="Um recurso por linha"><?= e($template['features'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="requirements">Requisitos</label>
                <textarea id="requirements" name="requirements" rows="3" placeholder="Ex: GDevelop 5, Godot 4.2+"><?= e($template['requirements'] ?? '') ?></textarea>
            </div>

            <h3 class="form-section-title">Configurações</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="sort_order">Ordem</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= (int)($template['sort_order'] ?? 0) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <div class="toggle-group" style="margin-top:28px">
                        <input type="checkbox" id="featured" name="featured" <?= ($template['featured'] ?? 0) ? 'checked' : '' ?>>
                        <label for="featured">Destaque no site</label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="toggle-group" style="margin-top:28px">
                        <input type="checkbox" id="active" name="active" <?= ($template['active'] ?? 1) ? 'checked' : '' ?>>
                        <label for="active">Ativo</label>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-gold">Salvar Template</button>
                <a href="templates" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
        </div>
    </div>
    <?php
} else {
    $templates = dbQuery("SELECT * FROM game_templates ORDER BY sort_order ASC, id DESC");
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Todos os Templates</h2>
            <a href="templates?action=new" class="btn btn-gold btn-sm">+ Novo Template</a>
        </div>
        <?php if (empty($templates)): ?>
            <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon">📦</div>
                <p>Nenhum template cadastrado ainda.</p>
            </div>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Engine</th>
                            <th class="hide-tablet">Linguagem</th>
                            <th class="hide-tablet">Loja</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $t): ?>
                        <tr>
                            <td><strong style="color:var(--fg)"><?= e($t['title']) ?></strong></td>
                            <td><span class="game-engine-badge" style="background:<?= getEngineColor($t['engine']) ?>"><?= getEngineIcon($t['engine']) ?> <?= e($t['engine']) ?></span></td>
                            <td class="hide-tablet"><?= e($t['language'] ?: '—') ?></td>
                            <td class="hide-tablet"><?= $t['store_url'] ? '<a href="' . e($t['store_url']) . '" target="_blank" rel="noopener" style="color:var(--gold)">🔗 Link</a>' : '—' ?></td>
                            <td>
                                <?php if ($t['active']): ?>
                                    <span class="badge badge-active">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Inativo</span>
                                <?php endif; ?>
                                <?php if ($t['featured']): ?>
                                    <span class="badge badge-featured">Destaque</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-outline btn-sm btn-icon" title="<?= $t['active'] ? 'Desativar' : 'Ativar' ?>"><?= $t['active'] ? '🔴' : '🟢' ?></button>
                                </form>
                                <a href="templates?action=edit&id=<?= $t['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este template?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
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
