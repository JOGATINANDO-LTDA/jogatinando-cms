<?php
ob_start();
$pageTitle = 'Banners';
$requiredPerm = 'perm_banners';
require_once __DIR__ . '/../includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? $id);
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Token de segurança inválido.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/banners');
        exit;
    }

    if ($_POST['action'] === 'save') {
        $title = trim($_POST['title']);
        $subtitle = trim($_POST['subtitle']);
        $description = trim($_POST['description']);
        $cta_text = trim($_POST['cta_text']);
        $cta_url = trim($_POST['cta_url']);
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        $image_url = '';

        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $oldImage = $id > 0 ? dbQueryOne("SELECT image_url FROM banners WHERE id = ?", [$id])['image_url'] ?? '' : '';
            $result = uploadFile($_FILES['image'], 'banners', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($result['success']) {
                $image_url = $result['url'];
                if (!empty($oldImage)) {
                    $oldPath = UPLOAD_PATH . str_replace('/uploads', '', $oldImage);
                    if (file_exists($oldPath)) @unlink($oldPath);
                }
            } else {
                flashMessage('error', $result['message']);
                ob_end_clean();
                header('Location: ' . ADMIN_URL . '/banners?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
                exit;
            }
        }

        if (empty($title)) {
            flashMessage('error', 'Título é obrigatório.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/banners?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
            exit;
        }

        if ($id > 0) {
            // Update
            if ($image_url) {
                dbExec("UPDATE banners SET title=?, subtitle=?, description=?, image_url=?, cta_text=?, cta_url=?, sort_order=?, active=? WHERE id=?",
                    [$title, $subtitle, $description, $image_url, $cta_text, $cta_url, $sort_order, $active, $id]);
            } else {
                dbExec("UPDATE banners SET title=?, subtitle=?, description=?, cta_text=?, cta_url=?, sort_order=?, active=? WHERE id=?",
                    [$title, $subtitle, $description, $cta_text, $cta_url, $sort_order, $active, $id]);
            }
            flashMessage('success', 'Banner atualizado com sucesso!');
        } else {
            // Insert
            dbExec("INSERT INTO banners (title, subtitle, description, image_url, cta_text, cta_url, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$title, $subtitle, $description, $image_url, $cta_text, $cta_url, $sort_order, $active]);
            flashMessage('success', 'Banner criado com sucesso!');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/banners');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $banner = dbQueryOne("SELECT * FROM banners WHERE id = ?", [$id]);
        if ($banner) {
            if (!empty($banner['image_url'])) {
                $imgPath = UPLOAD_PATH . str_replace('/uploads', '', $banner['image_url']);
                if (file_exists($imgPath)) @unlink($imgPath);
            }
            dbDelete('banners', $id);
            flashMessage('success', 'Banner excluído.');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/banners');
        exit;
    }

    if ($_POST['action'] === 'toggle') {
        $banner = dbQueryOne("SELECT active FROM banners WHERE id = ?", [$id]);
        if ($banner) {
            dbExec("UPDATE banners SET active = ? WHERE id = ?", [1 - $banner['active'], $id]);
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/banners');
        exit;
    }
}

if ($action === 'new' || $action === 'edit') {
    $banner = $id > 0 ? dbQueryOne("SELECT * FROM banners WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$banner) {
        flashMessage('error', 'Banner não encontrado.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/banners');
        exit;
    }
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $action === 'new' ? 'Novo Banner' : 'Editar Banner' ?></h2>
            <a href="banners" class="btn btn-outline btn-sm">← Voltar</a>
        </div>
        <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">
            <?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
            <?= csrfField() ?>

            <h3 class="form-section-title">Conteúdo</h3>

            <div class="form-group">
                <label for="title">Título *</label>
                <input type="text" id="title" name="title" value="<?= e($banner['title'] ?? '') ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="subtitle">Subtítulo</label>
                    <input type="text" id="subtitle" name="subtitle" value="<?= e($banner['subtitle'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="description">Descrição</label>
                <textarea id="description" name="description" rows="3"><?= e($banner['description'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="cta_text">Texto do Botão</label>
                    <input type="text" id="cta_text" name="cta_text" value="<?= e($banner['cta_text'] ?? 'Saiba Mais') ?>">
                </div>
                <div class="form-group">
                    <label for="cta_url">URL do Botão</label>
                    <input type="text" id="cta_url" name="cta_url" value="<?= e($banner['cta_url'] ?? '#') ?>">
                </div>
            </div>

            <h3 class="form-section-title">Imagem de Fundo</h3>

            <div class="form-group">
                <div class="file-upload">
                    <input type="file" name="image" accept="image/*">
                    <div class="upload-icon">
                        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    </div>
                    <div class="upload-text">Clique ou arraste uma imagem</div>
                    <div class="upload-hint">JPG, PNG, WebP — máx 10MB</div>
                </div>
                <?php if (!empty($banner['image_url'])): ?>
                    <img src="<?= e($banner['image_url']) ?>" class="preview-img" alt="Current banner image">
                    <p class="hint">Imagem atual. Envie outra para substituir.</p>
                <?php endif; ?>
            </div>

            <h3 class="form-section-title">Configurações</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="sort_order">Ordem</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= (int)($banner['sort_order'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <div class="toggle-group" style="margin-top:28px">
                        <input type="checkbox" id="active" name="active" <?= ($banner['active'] ?? 1) ? 'checked' : '' ?>>
                        <label for="active">Ativo</label>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-gold">Salvar Banner</button>
                <a href="banners" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
        </div>
    </div>
    <?php
} else {
    $banners = dbQuery("SELECT * FROM banners ORDER BY sort_order ASC, id DESC");
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Todos os Banners</h2>
            <a href="banners?action=new" class="btn btn-gold btn-sm">+ Novo Banner</a>
        </div>
        <?php if (empty($banners)): ?>
            <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon">
                    <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                </div>
                <p>Nenhum banner cadastrado ainda.</p>
            </div>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Ordem</th>
                            <th>Título</th>
                            <th>Subtítulo</th>
                            <th>CTA</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($banners as $b): ?>
                        <tr>
                            <td><?= (int)$b['sort_order'] ?></td>
                            <td><strong style="color:var(--fg)"><?= e($b['title']) ?></strong></td>
                            <td><?= e(truncateText($b['subtitle'], 40)) ?></td>
                            <td><?= e($b['cta_text']) ?></td>
                            <td>
                                <?php if ($b['active']): ?>
                                    <span class="badge badge-active">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-outline btn-sm btn-icon" title="<?= $b['active'] ? 'Desativar' : 'Ativar' ?>"><?= $b['active'] ? '🔴' : '🟢' ?></button>
                                </form>
                                <a href="banners?action=edit&id=<?= $b['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este banner?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
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
