<?php
ob_start();
$pageTitle = 'Templates';
$requiredPerm = 'perm_templates';
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
        $has_free_file = isset($_POST['has_free_file']) ? 1 : 0;
        $thumbnail_url = '';
        $game_path = '';
        $slug = generateSlug($title);

        if (empty($title)) {
            flashMessage('error', 'Título é obrigatório.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/templates?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
            exit;
        }

        // Handle thumbnail upload
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $oldThumb = $id > 0 ? dbQueryOne("SELECT thumbnail_url FROM game_templates WHERE id = ?", [$id])['thumbnail_url'] ?? '' : '';
            $result = uploadFile($_FILES['thumbnail'], 'thumbnails', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($result['success']) {
                $thumbnail_url = $result['url'];
                if (!empty($oldThumb)) {
                    $thumbPath = UPLOAD_PATH . str_replace('/uploads', '', $oldThumb);
                    if (file_exists($thumbPath)) @unlink($thumbPath);
                }
            } else {
                flashMessage('error', $result['message']);
                ob_end_clean();
                header('Location: ' . ADMIN_URL . '/templates?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
                exit;
            }
        }

        // Load existing values on edit
        if ($id > 0) {
            $existing = dbQueryOne("SELECT * FROM game_templates WHERE id = ?", [$id]);
            if (!$thumbnail_url) {
                $thumbnail_url = $existing['thumbnail_url'] ?? '';
            }
            $gallery = json_decode($existing['gallery'] ?? '[]', true) ?: [];
        } else {
            $gallery = [];
        }

        // Handle gallery image removals
        $removeGallery = $_POST['remove_gallery'] ?? [];
        foreach ($removeGallery as $imgUrl) {
            $key = array_search($imgUrl, $gallery);
            if ($key !== false) {
                $filePath = UPLOAD_PATH . str_replace('/uploads', '', $imgUrl);
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
                array_splice($gallery, $key, 1);
            }
        }

        // Handle gallery image uploads
        $newUploadCount = 0;
        if (isset($_FILES['gallery']) && is_array($_FILES['gallery']['name'])) {
            $fileCount = count($_FILES['gallery']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['gallery']['error'][$i] === UPLOAD_ERR_OK) {
                    $newUploadCount++;
                }
            }
        }

        $finalCount = count($gallery) + $newUploadCount;
        if ($finalCount > 5) {
            $remainingSlots = 5 - count($gallery);
            flashMessage('error', "Máximo de 5 imagens na galeria. Você pode adicionar mais " . max(0, $remainingSlots) . ".");
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/templates?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
            exit;
        }

        if (isset($_FILES['gallery']) && is_array($_FILES['gallery']['name'])) {
            $fileCount = count($_FILES['gallery']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['gallery']['error'][$i] === UPLOAD_ERR_OK) {
                    $singleFile = [
                        'name' => $_FILES['gallery']['name'][$i],
                        'type' => $_FILES['gallery']['type'][$i],
                        'tmp_name' => $_FILES['gallery']['tmp_name'][$i],
                        'error' => $_FILES['gallery']['error'][$i],
                        'size' => $_FILES['gallery']['size'][$i],
                    ];
                    $result = uploadFile($singleFile, 'templates', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    if ($result['success']) {
                        $gallery[] = $result['url'];
                    }
                }
            }
        }

        $gallery_json = json_encode($gallery);

        // Handle template archive upload (zip only)
        if ($has_free_file && isset($_FILES['template_archive']) && $_FILES['template_archive']['error'] === UPLOAD_ERR_OK) {
            // Delete old archive if exists
            if ($id > 0 && !empty($existing['game_path'])) {
                $oldFile = UPLOAD_PATH . str_replace('/uploads', '', $existing['game_path']);
                if (file_exists($oldFile)) {
                    @unlink($oldFile);
                }
            }
            $result = uploadFile($_FILES['template_archive'], 'templates', ['zip']);
            if ($result['success']) {
                $game_path = $result['url'];
            } else {
                flashMessage('error', $result['message']);
                ob_end_clean();
                header('Location: ' . ADMIN_URL . '/templates?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
                exit;
            }
        } elseif ($id > 0 && !$has_free_file && !empty($existing['game_path'])) {
            // has_free_file was unchecked — delete old archive
            $oldFile = UPLOAD_PATH . str_replace('/uploads', '', $existing['game_path']);
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        } elseif ($id > 0 && !empty($existing['game_path'])) {
            $game_path = $existing['game_path'];
        }

        try {
            if ($id > 0) {
                dbExec("UPDATE game_templates SET title=?, slug=?, engine=?, description=?, language=?, language_version=?, store_url=?, game_path=?, thumbnail_url=?, gallery=?, features=?, requirements=?, has_free_file=?, featured=?, sort_order=?, active=?, updated_at=CURRENT_TIMESTAMP WHERE id=?",
                    [$title, $slug, $engine, $description, $language, $language_version, $store_url, $game_path, $thumbnail_url, $gallery_json, $features, $requirements, $has_free_file, $featured, $sort_order, $active, $id]);
            } else {
                $id = dbExec("INSERT INTO game_templates (title, slug, engine, description, language, language_version, store_url, game_path, thumbnail_url, gallery, features, requirements, has_free_file, featured, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$title, $slug, $engine, $description, $language, $language_version, $store_url, $game_path, $thumbnail_url, $gallery_json, $features, $requirements, $has_free_file, $featured, $sort_order, $active]);
            }

            // Save template_links
            $db = getDB();
            $db->prepare("DELETE FROM template_links WHERE template_id = ?")->execute([$id]);
            if (isset($_POST['link_platform']) && is_array($_POST['link_platform'])) {
                $stmt = $db->prepare("INSERT INTO template_links (template_id, platform_id, url, sort_order) VALUES (?, ?, ?, ?)");
                $order = 0;
                foreach ($_POST['link_platform'] as $i => $platformId) {
                    $url = trim($_POST['link_url'][$i] ?? '');
                    if ($platformId > 0 && !empty($url)) {
                        $stmt->execute([$id, (int)$platformId, $url, $order++]);
                    }
                }
            }

            flashMessage('success', $id > 0 ? 'Template atualizado!' : 'Template criado!');
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
            if (!empty($template['thumbnail_url'])) {
                $thumbPath = UPLOAD_PATH . str_replace('/uploads', '', $template['thumbnail_url']);
                if (file_exists($thumbPath)) {
                    @unlink($thumbPath);
                }
            }
            if (!empty($template['game_path'])) {
                $archiveFile = UPLOAD_PATH . str_replace('/uploads', '', $template['game_path']);
                if (file_exists($archiveFile)) {
                    @unlink($archiveFile);
                }
            }
            $gallery = json_decode($template['gallery'] ?? '[]', true) ?: [];
            foreach ($gallery as $imgUrl) {
                $filePath = UPLOAD_PATH . str_replace('/uploads', '', $imgUrl);
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
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
    $templateLinks = $id > 0 ? dbQuery("SELECT tl.*, p.name as platform_name, p.icon as platform_icon, p.use_logo, p.logo_path FROM template_links tl JOIN store_platforms p ON tl.platform_id = p.id WHERE tl.template_id = ? ORDER BY tl.sort_order", [$id]) : [];
    $platforms = dbQuery("SELECT id, name, icon, use_logo, logo_path FROM store_platforms WHERE active = 1 ORDER BY sort_order ASC, name ASC");
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
                <div class="toggle-group" style="margin-top:8px">
                    <input type="checkbox" id="has_free_file" name="has_free_file" <?= ($template['has_free_file'] ?? 0) ? 'checked' : '' ?>>
                    <label for="has_free_file">Possui arquivo gratuito para download</label>
                </div>
            </div>

            <div id="archive-upload-section" style="display:<?= ($template['has_free_file'] ?? 0) ? 'block' : 'none' ?>">
                <div class="form-group">
                    <label>Arquivo do Template (ZIP)</label>
                    <div class="file-upload">
                        <input type="file" name="template_archive" accept=".zip">
                        <div class="upload-icon">
                            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </div>
                        <div class="upload-text">Upload do template</div>
                        <div class="upload-hint">ZIP — máx <?= e($postMax ?: '30M') ?></div>
                    </div>
                    <?php if (!empty($template['game_path'])): ?>
                        <p style="margin-top:8px;font-size:13px;color:var(--muted)">📎 <?= e(basename($template['game_path'])) ?> (envie outro para substituir)</p>
                    <?php endif; ?>
                </div>
            </div>

            <h3 class="form-section-title">Galeria de Imagens</h3>

            <div class="form-group" id="gallery-section">
                <?php
                $galleryImages = json_decode($template['gallery'] ?? '[]', true) ?: [];
                $galleryCount = count($galleryImages);
                ?>
                <label>Imagens da Galeria <span style="font-weight:400;color:var(--muted)">(máx 5)</span></label>

                <?php if (!empty($galleryImages)): ?>
                <div id="gallery-existing" style="display:flex;flex-wrap:wrap;gap:12px;margin-top:8px;margin-bottom:12px">
                    <?php foreach ($galleryImages as $img): ?>
                    <div class="gallery-existing-item" style="position:relative;width:130px;border-radius:8px;overflow:hidden;border:2px solid var(--border)">
                        <img src="<?= e($img) ?>" style="width:100%;height:85px;object-fit:cover;display:block">
                        <button type="button" onclick="removeGalleryImg(this, '<?= e($img) ?>')" style="position:absolute;top:4px;right:4px;border:none;background:oklch(10% 0.03 260 / 0.7);color:#fff;border-radius:4px;cursor:pointer;padding:2px 8px;font-size:12px;line-height:1">✕</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div id="gallery-slots"></div>

                <input type="file" id="gallery-multiple" name="gallery[]" accept="image/*" multiple style="display:none">

                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:8px">
                    <button type="button" id="gallery-add-btn" class="btn btn-outline btn-sm">📁 Selecionar Imagens</button>
                    <button type="button" id="gallery-slot-btn" class="btn btn-outline btn-sm" title="Adicionar 1 imagem">➕</button>
                    <span id="gallery-counter" style="font-size:13px;color:var(--muted)"><?= $galleryCount ?>/5 imagens</span>
                </div>

                <div id="gallery-previews" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px"></div>

                <script>
                var MAX = 5;
                var existingTotal = <?= $galleryCount ?>;
                var multipleInput = document.getElementById('gallery-multiple');
                var previews = document.getElementById('gallery-previews');
                var counter = document.getElementById('gallery-counter');
                var slots = document.getElementById('gallery-slots');
                var addBtn = document.getElementById('gallery-add-btn');
                var slotBtn = document.getElementById('gallery-slot-btn');

                function countRemoved() {
                    return document.querySelectorAll('#gallery-section input[name="remove_gallery[]"]').length;
                }

                function countNew() {
                    var n = 0;
                    if (multipleInput.files) n += multipleInput.files.length;
                    slots.querySelectorAll('.gallery-slot input[type=file]').forEach(function(i) {
                        if (i.files && i.files.length > 0) n++;
                    });
                    return n;
                }

                function countTotal() {
                    return existingTotal - countRemoved() + countNew();
                }

                function refresh() {
                    var c = countTotal();
                    if (c > MAX) c = MAX;
                    counter.textContent = c + '/' + MAX + ' imagens';
                    var full = c >= MAX;
                    addBtn.disabled = full;
                    slotBtn.disabled = full;
                    addBtn.style.opacity = full ? '0.4' : '';
                    slotBtn.style.opacity = full ? '0.4' : '';

                    previews.innerHTML = '';
                    slots.querySelectorAll('.gallery-slot').forEach(function(slot) {
                        var f = slot.querySelector('input[type=file]').files;
                        if (f && f[0]) {
                            var box = document.createElement('div');
                            box.style.cssText = 'position:relative;width:100px;border-radius:6px;overflow:hidden;border:1px solid var(--border)';
                            var img = document.createElement('img');
                            img.style.cssText = 'width:100%;height:70px;object-fit:cover;display:block';
                            var r = new FileReader();
                            r.onload = function(e) { img.src = e.target.result; };
                            r.readAsDataURL(f[0]);
                            box.appendChild(img);
                            previews.appendChild(box);
                        }
                    });
                    if (multipleInput.files) {
                        for (var i = 0; i < multipleInput.files.length; i++) {
                            (function(f) {
                                var box = document.createElement('div');
                                box.style.cssText = 'position:relative;width:100px;border-radius:6px;overflow:hidden;border:1px solid var(--border)';
                                var img = document.createElement('img');
                                img.style.cssText = 'width:100%;height:70px;object-fit:cover;display:block';
                                var r = new FileReader();
                                r.onload = function(e) { img.src = e.target.result; };
                                r.readAsDataURL(f);
                                box.appendChild(img);
                                previews.appendChild(box);
                            })(multipleInput.files[i]);
                        }
                    }
                }

                function removeGalleryImg(btn, url) {
                    btn.parentElement.style.display = 'none';
                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'remove_gallery[]';
                    hidden.value = url;
                    document.getElementById('gallery-section').appendChild(hidden);
                    refresh();
                }

                addBtn.addEventListener('click', function() {
                    if (countTotal() >= MAX) { alert('Máximo de ' + MAX + ' imagens.'); return; }
                    multipleInput.click();
                });

                multipleInput.addEventListener('change', function() {
                    if (countTotal() > MAX) {
                        alert('Máximo de ' + MAX + ' imagens.');
                        this.value = '';
                        return;
                    }
                    refresh();
                });

                slotBtn.addEventListener('click', function() {
                    if (countTotal() >= MAX) {
                        alert('Máximo de ' + MAX + ' imagens.');
                        return;
                    }
                    var wrap = document.createElement('div');
                    wrap.className = 'gallery-slot';
                    wrap.style.cssText = 'display:flex;align-items:center;gap:6px;margin-top:6px';
                    var inp = document.createElement('input');
                    inp.type = 'file';
                    inp.name = 'gallery[]';
                    inp.accept = 'image/*';
                    inp.style.cssText = 'font-size:13px;flex:1';
                    var rm = document.createElement('button');
                    rm.type = 'button';
                    rm.textContent = '✕';
                    rm.style.cssText = 'border:none;background:oklch(55% 0.20 25 / 0.15);color:oklch(55% 0.20 25);border-radius:4px;cursor:pointer;padding:2px 8px;font-size:12px';
                    rm.addEventListener('click', function() { wrap.remove(); refresh(); });
                    inp.addEventListener('change', refresh);
                    wrap.appendChild(inp);
                    wrap.appendChild(rm);
                    slots.appendChild(wrap);
                    refresh();
                });

                refresh();
                </script>
            </div>

            <h3 class="form-section-title">Links de Distribuição</h3>
            <div id="templateLinksContainer">
                <div class="field-hint" style="margin-bottom:12px">Links para lojas onde o template pode ser adquirido.</div>
                <div style="display:flex;gap:8px;margin-bottom:8px;font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase">
                    <div style="flex:0 0 30%">Plataforma</div>
                    <div style="flex:0 0 50%">URL do Link</div>
                    <div style="flex:0 0 20%;text-align:center">Ação</div>
                </div>
                <div id="templateLinksList">
                    <?php if (!empty($templateLinks)): ?>
                        <?php foreach ($templateLinks as $tl): ?>
                        <div class="template-link-row" style="display:flex;gap:8px;align-items:flex-end;margin-bottom:8px">
                            <div class="form-group" style="flex:0 0 30%;margin-bottom:0">
                                <div style="display:flex;align-items:center;gap:6px">
                                    <?php if (!empty($tl['use_logo']) && !empty($tl['logo_path'])): ?>
                                        <img src="/<?= e($tl['logo_path']) ?>" alt="" class="platform-thumb" style="height:18px;width:auto;flex-shrink:0">
                                    <?php else: ?>
                                        <span class="platform-thumb" style="font-size:18px;flex-shrink:0"><?= e($tl['platform_icon'] ?? '🛒') ?></span>
                                    <?php endif; ?>
                                    <select name="link_platform[]" style="width:100%">
                                        <option value="">Selecione...</option>
                                        <?php foreach ($platforms as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= $tl['platform_id'] == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group" style="flex:0 0 50%;margin-bottom:0">
                                <input type="url" name="link_url[]" value="<?= e($tl['url']) ?>" placeholder="https://..." style="width:100%">
                            </div>
                            <div style="flex:0 0 20%;text-align:center;padding-bottom:2px">
                                <button type="button" class="btn btn-danger btn-sm template-link-remove" title="Remover link">🗑️ Excluir</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-outline btn-sm" id="addTemplateLink">+ Adicionar Link</button>
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
            <script>
            (function() {
                var checkbox = document.getElementById('has_free_file');
                var section = document.getElementById('archive-upload-section');
                if (checkbox && section) {
                    checkbox.addEventListener('change', function() {
                        section.style.display = this.checked ? 'block' : 'none';
                    });
                }
            })();
            </script>
            <script>
            (function() {
                var list = document.getElementById('templateLinksList');
                var addBtn = document.getElementById('addTemplateLink');
                if (!list || !addBtn) return;

                const platforms = <?= json_encode(array_map(function($p) {
                    return ['id' => $p['id'], 'name' => $p['name'], 'icon' => $p['icon'] ?? '', 'use_logo' => !empty($p['use_logo']) ? 1 : 0, 'logo_path' => $p['logo_path'] ?? ''];
                }, $platforms ?? [])) ?>;

                function createLinkRow(platformId, url) {
                    let selectHtml = '<select name="link_platform[]"><option value="">Selecione...</option>';
                    let thumbHtml = '<span class="platform-thumb" style="font-size:18px;flex-shrink:0">🛒</span>';
                    platforms.forEach(function(p) {
                        selectHtml += '<option value="' + p.id + '" ' + (p.id == platformId ? 'selected' : '') + '>' + p.name + '</option>';
                        if (p.id == platformId) {
                            thumbHtml = p.use_logo && p.logo_path
                                ? '<img class="platform-thumb" src="/' + p.logo_path + '" alt="" style="height:18px;width:auto;flex-shrink:0">'
                                : '<span class="platform-thumb" style="font-size:18px;flex-shrink:0">' + p.icon + '</span>';
                        }
                    });
                    selectHtml += '</select>';
                    return '<div class="template-link-row" style="display:flex;gap:8px;align-items:flex-end;margin-bottom:8px">' +
                        '<div class="form-group" style="flex:0 0 30%;margin-bottom:0">' +
                            '<div style="display:flex;align-items:center;gap:6px">' + thumbHtml + selectHtml + '</div>' +
                        '</div>' +
                        '<div class="form-group" style="flex:0 0 50%;margin-bottom:0"><input type="url" name="link_url[]" value="' + url + '" placeholder="https://..." style="width:100%"></div>' +
                        '<div style="flex:0 0 20%;text-align:center;padding-bottom:2px"><button type="button" class="btn btn-danger btn-sm template-link-remove" title="Remover link">🗑️ Excluir</button></div>' +
                    '</div>';
                }

                addBtn.addEventListener('click', function() {
                    var div = document.createElement('div');
                    div.innerHTML = createLinkRow(0, '');
                    list.appendChild(div.firstElementChild);
                });

                list.addEventListener('click', function(e) {
                    if (e.target.classList.contains('template-link-remove')) {
                        e.target.closest('.template-link-row').remove();
                    }
                });

                list.addEventListener('change', function(e) {
                    if (e.target.matches('select[name="link_platform[]"]')) {
                        var row = e.target.closest('.template-link-row');
                        var thumb = row.querySelector('.platform-thumb');
                        var selected = platforms.find(function(p) { return p.id == e.target.value; });
                        if (selected) {
                            if (selected.use_logo && selected.logo_path) {
                                thumb.outerHTML = '<img class="platform-thumb" src="/' + selected.logo_path + '" alt="" style="height:18px;width:auto;flex-shrink:0">';
                            } else {
                                thumb.outerHTML = '<span class="platform-thumb" style="font-size:18px;flex-shrink:0">' + selected.icon + '</span>';
                            }
                        } else {
                            thumb.outerHTML = '<span class="platform-thumb" style="font-size:18px;flex-shrink:0">🛒</span>';
                        }
                    }
                });
            })();
            </script>
        </form>
        </div>
    </div>
    <?php
} else {
    $templates = dbQuery("SELECT gt.*, (SELECT GROUP_CONCAT(p.name, '||') FROM template_links tl JOIN store_platforms p ON tl.platform_id = p.id WHERE tl.template_id = gt.id) as platform_names FROM game_templates gt ORDER BY gt.sort_order ASC, gt.id DESC");
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
                            <th class="hide-tablet">Download</th>
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
                            <td class="hide-tablet"><?php
                                $pnames = $t['platform_names'] ?? '';
                                if ($pnames) {
                                    $parts = explode('||', $pnames);
                                    $parts = array_unique(array_filter($parts));
                                    foreach ($parts as $pn) {
                                        echo '<span class="badge" style="background:var(--gold);color:var(--bg-dark);padding:2px 8px;border-radius:4px;font-size:11px;margin-right:4px">' . e(trim($pn)) . '</span>';
                                    }
                                } else {
                                    echo '—';
                                }
                            ?></td>
                            <td class="hide-tablet"><?= $t['has_free_file'] ? '<span style="color:var(--green,#4ade80)">✅ Grátis</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
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
