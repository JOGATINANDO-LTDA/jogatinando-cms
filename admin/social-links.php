<?php
ob_start();
$pageTitle = 'Redes Sociais';
$requiredPerm = 'perm_settings';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

$ensureSocialLinkSchema = function() use ($db) {
    $type = getDbType();
    try {
        if ($type === 'mysql') {
            $cols = $db->query("SHOW COLUMNS FROM social_links LIKE 'image_path'")->fetch();
            if (!$cols) {
                $db->exec("ALTER TABLE social_links ADD COLUMN image_path VARCHAR(500) NOT NULL DEFAULT '' AFTER url");
            }
        } else {
            $cols = $db->query("PRAGMA table_info(social_links)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('image_path', $cols)) {
                $db->exec("ALTER TABLE social_links ADD COLUMN image_path TEXT NOT NULL DEFAULT ''");
            }
        }
    } catch (Exception $e) {}
};

$ensureSocialLinkSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Token inválido.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/social-links');
        exit;
    }

    $action = $_POST['action'] ?? 'save';
    if ($action === 'save') {
        $ensureSocialLinkSchema();
        $id = (int)($_POST['id'] ?? 0);
        $allowedScopes = ['site', 'footer', 'hero', 'header'];
        $allowedPlatforms = ['youtube', 'twitch', 'x', 'tiktok', 'facebook', 'instagram', 'linkedin', 'discord', 'kick', 'kwai', 'website'];
        $scope = trim($_POST['scope'] ?? 'site');
        $platformKey = trim($_POST['platform_key'] ?? 'website');
        $label = trim($_POST['label'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $imagePath = '';
        $customImage = !empty($_POST['custom_image']) ? 1 : 0;
        $active = !empty($_POST['active']) ? 1 : 0;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $existing = $id > 0 ? dbQueryOne('SELECT * FROM social_links WHERE id = ?', [$id]) : null;

        if (!in_array($scope, $allowedScopes, true)) {
            flashMessage('error', 'Escopo inválido.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/social-links');
            exit;
        }
        if (!in_array($platformKey, $allowedPlatforms, true)) {
            flashMessage('error', 'Plataforma inválida.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/social-links');
            exit;
        }
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            flashMessage('error', 'URL inválida.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/social-links');
            exit;
        }
        if ($label === '') {
            $preset = getSocialPlatformPreset($platformKey);
            $label = $preset['label'];
        }
        $shouldUploadImage = $platformKey === 'website' || $customImage;
        if ($platformKey === 'website') {
            $customImage = 1;
        }

        if ($shouldUploadImage && empty($_FILES['image_file']['name']) && empty($existing['image_path'] ?? '')) {
            flashMessage('error', 'Envie uma imagem para links do tipo website.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/social-links');
            exit;
        }
        if ($shouldUploadImage && isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['image_file'], 'social-links', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if (!$result['success']) {
                flashMessage('error', $result['message']);
                ob_end_clean();
                header('Location: ' . ADMIN_URL . '/social-links' . ($id > 0 ? '?edit=' . $id : ''));
                exit;
            }
            $imagePath = $result['url'];
        } elseif ($existing) {
            $imagePath = trim((string)($existing['image_path'] ?? ''));
        }

        if ($shouldUploadImage && $imagePath === '') {
            flashMessage('error', 'A imagem é obrigatória para links do tipo website.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/social-links');
            exit;
        }

        if ($id > 0) {
            if ($existing && !empty($existing['image_path'] ?? '') && $imagePath !== trim((string)($existing['image_path'] ?? ''))) {
                deleteFile($existing['image_path']);
            }
            $stmt = $db->prepare("UPDATE social_links SET scope = ?, platform_key = ?, label = ?, url = ?, image_path = ?, active = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$scope, $platformKey, $label, $url, $shouldUploadImage ? $imagePath : '', $active, $sortOrder, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO social_links (scope, platform_key, label, url, image_path, active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$scope, $platformKey, $label, $url, $shouldUploadImage ? $imagePath : '', $active, $sortOrder]);
        }
        flashMessage('success', 'Link social salvo.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/social-links');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM social_links WHERE id = ?');
        $stmt->execute([$id]);
        flashMessage('success', 'Link social removido.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/social-links');
        exit;
    }
}

$items = dbQuery('SELECT * FROM social_links ORDER BY scope ASC, sort_order ASC, id ASC');
$editId = (int)($_GET['edit'] ?? 0);
$editItem = $editId ? dbQueryOne('SELECT * FROM social_links WHERE id = ?', [$editId]) : null;
$presetKeys = ['youtube','twitch','x','tiktok','facebook','instagram','linkedin','discord','kick','kwai','website'];
?>

<div class="card">
    <div class="card-header"><h2 class="card-title">Gerenciar Redes Sociais</h2></div>
    <div class="card-body">
        <form method="POST" class="form-grid form-grid-limited" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= e((string)($editItem['id'] ?? 0)) ?>">
            <h3 class="form-section-title">Informações Básicas</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="scope">Escopo</label>
                    <select id="scope" name="scope">
                        <option value="site" <?= ($editItem['scope'] ?? 'site') === 'site' ? 'selected' : '' ?>>site</option>
                        <option value="footer" <?= ($editItem['scope'] ?? '') === 'footer' ? 'selected' : '' ?>>footer</option>
                        <option value="hero" <?= ($editItem['scope'] ?? '') === 'hero' ? 'selected' : '' ?>>hero</option>
                        <option value="header" <?= ($editItem['scope'] ?? '') === 'header' ? 'selected' : '' ?>>header</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="platform_key">Plataforma</label>
                    <select id="platform_key" name="platform_key">
                        <option value="website" <?= ($editItem['platform_key'] ?? 'website') === 'website' ? 'selected' : '' ?>>website</option>
                        <?php foreach ($presetKeys as $key): if ($key === 'website') continue; ?>
                            <option value="<?= e($key) ?>" <?= ($editItem['platform_key'] ?? '') === $key ? 'selected' : '' ?>><?= e($key) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="label">Label</label>
                    <input type="text" id="label" name="label" value="<?= e($editItem['label'] ?? '') ?>" placeholder="Ex: Canal oficial">
                </div>
                <div class="form-group">
                    <label for="url">URL</label>
                    <input type="url" id="url" name="url" value="<?= e($editItem['url'] ?? '') ?>" placeholder="https://...">
                </div>
            </div>

            <h3 class="form-section-title">Mídia</h3>
            <?php $isWebsite = ($editItem['platform_key'] ?? 'website') === 'website'; ?>
            <div class="form-group hidden" id="customImageRow">
                <div class="toggle-group">
                    <input type="checkbox" id="custom_image" name="custom_image" value="1" <?= !$isWebsite && !empty($editItem['image_path']) ? 'checked' : '' ?>>
                    <label for="custom_image">Usar imagem personalizada</label>
                </div>
                <div class="field-hint">Plataformas conhecidas usam Font Awesome por padrão. Marque para substituir com uma imagem própria.</div>
            </div>

            <div class="form-group <?= $isWebsite || !empty($editItem['image_path']) ? '' : 'hidden' ?>" id="socialImageGroup">
                <label for="image_file">Imagem</label>
                <div class="file-upload">
                    <input type="file" id="image_file" name="image_file" accept="image/png,image/jpeg,image/gif,image/webp">
                    <div class="upload-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
                    <div class="upload-text">Upload de imagem da marca</div>
                    <div class="upload-hint">JPG, PNG, WebP ou GIF. SVG e scripts não são aceitos.</div>
                </div>
                <?php if (!empty($editItem['image_path'] ?? '')): ?>
                    <img src="<?= e(mediaUrl($editItem['image_path'])) ?>" class="preview-img" alt="Imagem atual">
                <?php endif; ?>
                <div class="field-hint" id="socialImageHint"><?= ($editItem['platform_key'] ?? '') === 'website' ? 'Para `website`, o upload é obrigatório.' : 'Quando desmarcado, o ícone do Font Awesome será usado.' ?></div>
            </div>

            <h3 class="form-section-title">Configurações</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="sort_order">Ordem</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= e((string)($editItem['sort_order'] ?? 0)) ?>">
                </div>
                <div class="form-group">
                    <div class="toggle-group">
                        <input type="checkbox" id="active" name="active" value="1" <?= !empty($editItem['active']) ? 'checked' : '' ?>>
                        <label for="active">Ativo</label>
                    </div>
                </div>
            </div>
            <div class="form-actions"><button class="btn btn-gold" type="submit">Salvar</button></div>
        </form>
        <script>
        (function() {
            var platform = document.getElementById('platform_key');
            var group = document.getElementById('socialImageGroup');
            var hint = document.getElementById('socialImageHint');
            var customRow = document.getElementById('customImageRow');
            var custom = document.getElementById('custom_image');
            if (!platform || !group || !hint) return;
            function sync() {
                var show = platform.value === 'website';
                if (customRow) customRow.classList.toggle('hidden', show);
                if (custom) {
                    if (show) {
                        custom.checked = true;
                    }
                }
                if (group) group.classList.toggle('hidden', !(show || (custom && custom.checked)));
                hint.textContent = show ? 'Para `website`, o upload é obrigatório.' : (custom && custom.checked ? 'Upload ativo.' : 'Quando desmarcado, o ícone do Font Awesome será usado.');
            }
            platform.addEventListener('change', function() {
                if (custom) {
                    custom.checked = platform.value === 'website' ? true : false;
                }
                sync();
            });
            if (custom) custom.addEventListener('change', sync);
            sync();
        })();
        </script>
    </div>
</div>

<div class="card card-spaced">
    <div class="card-header"><h2 class="card-title">Itens</h2></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Escopo</th><th>Plataforma</th><th>URL</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['scope']) ?></td>
                    <td><?= e($item['platform_key']) ?></td>
                    <td><?= e($item['url']) ?></td>
                    <td><?= !empty($item['active']) ? '<span class="badge badge-active">Ativo</span>' : '<span class="badge badge-inactive">Inativo</span>' ?></td>
                    <td class="actions">
                        <a class="btn btn-outline btn-sm" href="?edit=<?= (int)$item['id'] ?>">Editar</a>
                        <form method="POST" onsubmit="return confirm('Remover link?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                            <button class="btn btn-outline btn-sm" type="submit">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
