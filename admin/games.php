<?php
ob_start();
$pageTitle = 'Jogos';
require_once __DIR__ . '/../includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Detect oversized upload (POST empty due to exceeding post_max_size)
    if (empty($_POST) && empty($_FILES)) {
        $serverLimit = @ini_get('post_max_size') ?: '30M';
        flashMessage('error', "Arquivo excede o limite do servidor ($serverLimit). Contate a hospedagem para aumentar post_max_size.");
        ob_end_clean();
        header('Location: games');
        exit;
    }

    // Check for upload errors before processing
    if (isset($_FILES['game_archive']) && $_FILES['game_archive']['error'] !== UPLOAD_ERR_OK && $_FILES['game_archive']['error'] !== UPLOAD_ERR_NO_FILE) {
        $err = $_FILES['game_archive']['error'];
        $serverLimit = @ini_get('post_max_size') ?: '30M';
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            flashMessage('error', "Arquivo muito grande. Limite do servidor: $serverLimit.");
        } else {
            flashMessage('error', 'Erro no upload do arquivo.');
        }
        ob_end_clean();
        header('Location: games');
        exit;
    }

    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Token de segurança inválido.');
        ob_end_clean();
        header('Location: games');
        exit;
    }

    if ($_POST['action'] === 'save') {
        $title = trim($_POST['title']);
        $engine = trim($_POST['engine']);
        $description = trim($_POST['description']);
        $game_type = in_array($_POST['game_type'] ?? '', ['autoral', 'cliente']) ? $_POST['game_type'] : 'autoral';
        $is_web_playable = isset($_POST['is_web_playable']) ? 1 : 0;
        $featured = isset($_POST['featured']) ? 1 : 0;
        $orientation = in_array($_POST['orientation'] ?? '', ['auto', 'landscape', 'portrait']) ? $_POST['orientation'] : 'auto';
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
                header('Location: games?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
                exit;
            }
        }

        // Handle game archive upload (zip only)
        if (isset($_FILES['game_archive']) && $_FILES['game_archive']['error'] === UPLOAD_ERR_OK) {
            $result = uploadAndExtractGame($_FILES['game_archive'], $engine, $title);
            if ($result['success']) {
                $game_path = $result['game_path'];
                // Clear optimization status on re-upload
                if ($id > 0) {
                    dbExec("UPDATE games SET optimized_at = NULL WHERE id = ?", [$id]);
                }
            } else {
                flashMessage('error', $result['message']);
                ob_end_clean();
                header('Location: games?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
                exit;
            }
        }

        if (empty($title)) {
            flashMessage('error', 'Título é obrigatório.');
            ob_end_clean();
            header('Location: games?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
            exit;
        }

        try {
            if ($id > 0) {
                // Get existing game_path if not uploading new one
                if (!$game_path) {
                    $existing = dbQueryOne("SELECT game_path FROM games WHERE id = ?", [$id]);
                    $game_path = $existing['game_path'] ?? '';
                }
                // Get existing thumbnail if not uploading new one
                if (!$thumbnail_url) {
                    $existing = dbQueryOne("SELECT thumbnail_url FROM games WHERE id = ?", [$id]);
                    $thumbnail_url = $existing['thumbnail_url'] ?? '';
                }

                dbExec("UPDATE games SET title=?, slug=?, engine=?, description=?, thumbnail_url=?, game_path=?, game_type=?, is_web_playable=?, featured=?, orientation=?, sort_order=?, active=?, updated_at=CURRENT_TIMESTAMP WHERE id=?",
                    [$title, $slug, $engine, $description, $thumbnail_url, $game_path, $game_type, $is_web_playable, $featured, $orientation, $sort_order, $active, $id]);
                flashMessage('success', 'Jogo atualizado com sucesso!' . ($game_path ? ' (' . $game_path . ')' : ''));
            } else {
                $id = dbExec("INSERT INTO games (title, slug, engine, description, thumbnail_url, game_path, game_type, is_web_playable, featured, orientation, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$title, $slug, $engine, $description, $thumbnail_url, $game_path, $game_type, $is_web_playable, $featured, $orientation, $sort_order, $active]);
                flashMessage('success', 'Jogo criado com sucesso!' . ($game_path ? ' (' . $game_path . ')' : ''));
            }

            // Save game links (distribution platforms)
            if ($id > 0) {
                $db = getDB();
                $db->prepare("DELETE FROM game_links WHERE game_id = ?")->execute([$id]);
                if (isset($_POST['link_platform']) && is_array($_POST['link_platform'])) {
                    $stmt = $db->prepare("INSERT INTO game_links (game_id, platform_id, url, sort_order) VALUES (?, ?, ?, ?)");
                    $order = 0;
                    foreach ($_POST['link_platform'] as $i => $platformId) {
                        $url = trim($_POST['link_url'][$i] ?? '');
                        if ($platformId > 0 && !empty($url)) {
                            $stmt->execute([$id, (int)$platformId, $url, $order++]);
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            flashMessage('error', 'Erro ao salvar: ' . $ex->getMessage());
        }
        ob_end_clean();
        header('Location: games');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $game = dbQueryOne("SELECT * FROM games WHERE id = ?", [$id]);
        if ($game) {
            if ($game['game_path']) {
                deleteGameDir($game['game_path']);
            }
            dbDelete('games', $id);
            flashMessage('success', 'Jogo excluído.');
        }
        ob_end_clean();
        header('Location: games');
        exit;
    }

    if ($_POST['action'] === 'toggle') {
        $game = dbQueryOne("SELECT g.active, COALESCE(e.active, 0) as engine_active FROM games g LEFT JOIN engines e ON g.engine = e.name WHERE g.id = ?", [$id]);
        if ($game) {
            if (!$game['engine_active']) {
                flashMessage('error', 'Engine inativa — ative a engine antes de alterar o status do jogo.');
            } else {
                dbExec("UPDATE games SET active = ? WHERE id = ?", [1 - $game['active'], $id]);
            }
        }
        ob_end_clean();
        header('Location: games');
        exit;
    }
}

if ($action === 'new' || $action === 'edit') {
    $game = $id > 0 ? dbQueryOne("SELECT * FROM games WHERE id = ?", [$id]) : null;
    $gameLinks = [];
    if ($id > 0) {
        $gameLinks = dbQuery("SELECT gl.*, p.name as platform_name, p.icon as platform_icon FROM game_links gl JOIN store_platforms p ON gl.platform_id = p.id WHERE gl.game_id = ? ORDER BY gl.sort_order", [$id]);
    }
    if ($action === 'edit' && !$game) {
        flashMessage('error', 'Jogo não encontrado.');
        header('Location: games');
        exit;
    }
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $action === 'new' ? 'Novo Jogo' : 'Editar Jogo' ?></h2>
            <a href="games" class="btn btn-outline btn-sm">← Voltar</a>
        </div>
        <div class="card-body">
        <!-- Server capabilities -->
        <?php try { ?>
        <div class="server-status">
            <span class="status-item <?= class_exists('ZipArchive') ? 'ok' : 'fail' ?>">
                <?= class_exists('ZipArchive') ? '✅' : '❌' ?> ZIP
            </span>
            <?php
            $postMax = @ini_get('post_max_size');
            $postMaxBytes = 0;
            if (is_string($postMax)) {
                $val = trim($postMax);
                $multiplier = strtolower(substr($val, -1));
                $num = (int)$val;
                if ($multiplier === 'g') $postMaxBytes = $num * 1073741824;
                elseif ($multiplier === 'm') $postMaxBytes = $num * 1048576;
                elseif ($multiplier === 'k') $postMaxBytes = $num * 1024;
                else $postMaxBytes = $num;
            }
            ?>
            <span class="status-item <?= $postMaxBytes >= 104857600 ? 'ok' : 'warn' ?>">
                <?= $postMaxBytes >= 104857600 ? '✅' : '⚠️' ?> Limite: <?= e($postMax ?: 'N/A') ?>
            </span>
        </div>
        <?php } catch (Throwable $e) { /* silently skip server status */ } ?>

        <form method="POST" enctype="multipart/form-data" id="gameForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_UPLOAD_SIZE ?>">
            <input type="hidden" id="serverLimitBytes" value="<?= $postMaxBytes ?>" data-limit="<?= e($postMax ?: '30M') ?>">
            <?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
            <?= csrfField() ?>

            <!-- Upload progress overlay -->
            <div class="upload-progress-overlay" id="uploadProgress">
                <div class="upload-progress-content">
                    <div class="upload-spinner"></div>
                    <h3>Processando jogo...</h3>
                    <p id="uploadStatus">Enviando arquivo...</p>
                </div>
            </div>

            <h3 class="form-section-title">Informações Básicas</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="title">Título do Jogo *</label>
                    <input type="text" id="title" name="title" value="<?= e($game['title'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="engine">Engine *</label>
                    <select id="engine" name="engine" required>
                        <option value="">Selecione...</option>
                        <?php
                        $allEngines = getEngines();
                        $currentEngine = $game['engine'] ?? '';
                        $hasCurrent = false;
                        foreach ($allEngines as $eng) {
                            if ($eng['name'] === $currentEngine) $hasCurrent = true;
                            if (!$eng['active'] && $eng['name'] !== $currentEngine) continue;
                            echo '<option value="' . e($eng['name']) . '" ' . ($currentEngine === $eng['name'] ? 'selected' : '') . '>'
                                . e($eng['icon'] ?? '') . ' ' . e($eng['name']) . '</option>';
                        }
                        if ($currentEngine && !$hasCurrent && $currentEngine !== 'Outra') {
                            echo '<option value="' . e($currentEngine) . '" selected>' . e($currentEngine) . ' (inativa)</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Descrição</label>
                <textarea id="description" name="description" rows="4"><?= e($game['description'] ?? '') ?></textarea>
            </div>

            <h3 class="form-section-title">Mídia</h3>

            <div class="form-row">
                <div class="form-group">
                    <label>Thumbnail do Jogo</label>
                    <div class="file-upload">
                        <input type="file" name="thumbnail" accept="image/*">
                        <div class="upload-icon">
                            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </div>
                        <div class="upload-text">Clique ou arraste uma imagem</div>
                        <div class="upload-hint">JPG, PNG, WebP — máx <?= e($postMax ?: '30M') ?></div>
                    </div>
                    <?php if (!empty($game['thumbnail_url'])): ?>
                        <img src="<?= e($game['thumbnail_url']) ?>" class="preview-img" alt="Thumbnail">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Arquivo do Jogo</label>
                    <div class="file-upload" id="gameArchiveDrop">
                        <input type="file" name="game_archive" accept=".zip" id="gameArchiveInput">
                        <div class="upload-icon">
                            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </div>
                        <div class="upload-text">Upload do arquivo do jogo</div>
                        <div class="upload-hint">ZIP — HTML exportado — máx <?= e($postMax ?: '30M') ?></div>
                    </div>
                    <div class="file-selected" id="gameArchiveInfo" style="display:none">
                        <span class="file-name" id="gameArchiveName"></span>
                        <span class="file-size" id="gameArchiveSize"></span>
                        <button type="button" class="file-remove" id="gameArchiveRemove">✕</button>
                    </div>
                    <?php if (!empty($game['game_path'])): ?>
                        <p style="margin-top:8px;font-size:13px;color:var(--muted)">📎 <?= e($game['game_path']) ?> (envie outro para substituir)</p>
                    <?php endif; ?>
                </div>
            </div>

            <h3 class="form-section-title">Configurações</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="sort_order">Ordem</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= (int)($game['sort_order'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label for="orientation">Orientação Mobile</label>
                    <select id="orientation" name="orientation">
                        <option value="auto" <?= ($game['orientation'] ?? 'auto') === 'auto' ? 'selected' : '' ?>>Automático</option>
                        <option value="landscape" <?= ($game['orientation'] ?? '') === 'landscape' ? 'selected' : '' ?>>Paisagem (Landscape)</option>
                        <option value="portrait" <?= ($game['orientation'] ?? '') === 'portrait' ? 'selected' : '' ?>>Retrato (Portrait)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="game_type">Tipo</label>
                    <select id="game_type" name="game_type">
                        <option value="autoral" <?= ($game['game_type'] ?? 'autoral') === 'autoral' ? 'selected' : '' ?>>Autoral</option>
                        <option value="cliente" <?= ($game['game_type'] ?? '') === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                    </select>
                </div>
                <div class="form-group">
                    <div class="toggle-group" style="margin-top:28px">
                        <input type="checkbox" id="is_web_playable" name="is_web_playable" <?= ($game['is_web_playable'] ?? 1) ? 'checked' : '' ?>>
                        <label for="is_web_playable">Jogável no navegador</label>
                    </div>
                    <div class="field-hint" style="margin-top:4px">Desmarque para jogos com link de loja (Steam, etc.)</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <div class="toggle-group" style="margin-top:28px">
                        <input type="checkbox" id="featured" name="featured" <?= ($game['featured'] ?? 0) ? 'checked' : '' ?>>
                        <label for="featured">Destaque no site</label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="toggle-group" style="margin-top:28px">
                        <input type="checkbox" id="active" name="active" <?= ($game['active'] ?? 1) ? 'checked' : '' ?>>
                        <label for="active">Ativo</label>
                    </div>
                </div>
            </div>

            <h3 class="form-section-title" id="gameLinksSection" style="<?= ($game['is_web_playable'] ?? 1) ? 'display:none' : '' ?>">Links de Distribuição</h3>
            <div id="gameLinksContainer" style="<?= ($game['is_web_playable'] ?? 1) ? 'display:none' : '' ?>">
                <div class="field-hint" style="margin-bottom:12px">Links para lojas onde o jogo pode ser adquirido.</div>
                <div id="gameLinksList">
                    <?php
                    $platforms = dbQuery("SELECT id, name, icon FROM store_platforms WHERE active = 1 ORDER BY sort_order ASC, name ASC");
                    if (!empty($gameLinks)):
                        foreach ($gameLinks as $gl):
                    ?>
                    <div class="form-row game-link-row">
                        <div class="form-group" style="flex:0 0 200px">
                            <select name="link_platform[]">
                                <option value="">Selecione...</option>
                                <?php foreach ($platforms as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $gl['platform_id'] == $p['id'] ? 'selected' : '' ?>><?= e($p['icon'] ?? '') ?> <?= e($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex:1">
                            <input type="url" name="link_url[]" value="<?= e($gl['url']) ?>" placeholder="https://...">
                        </div>
                        <button type="button" class="btn btn-danger btn-sm btn-icon game-link-remove" style="margin-top:24px;flex:0 0 36px" title="Remover">✕</button>
                    </div>
                    <?php
                        endforeach;
                    endif;
                    ?>
                </div>
                <button type="button" class="btn btn-outline btn-sm" id="addGameLink">+ Adicionar Link</button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-gold" id="submitBtn">Salvar Jogo</button>
                <a href="games" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('gameForm');
        const input = document.getElementById('gameArchiveInput');
        const info = document.getElementById('gameArchiveInfo');
        const nameEl = document.getElementById('gameArchiveName');
        const sizeEl = document.getElementById('gameArchiveSize');
        const removeBtn = document.getElementById('gameArchiveRemove');
        const dropZone = document.getElementById('gameArchiveDrop');
        const progress = document.getElementById('uploadProgress');
        const submitBtn = document.getElementById('submitBtn');

        function formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function showFileInfo(file) {
            nameEl.textContent = file.name;
            sizeEl.textContent = formatSize(file.size);
            info.style.display = 'flex';
            dropZone.style.display = 'none';
        }

        function clearFile() {
            input.value = '';
            info.style.display = 'none';
            dropZone.style.display = '';
        }

        input.addEventListener('change', () => {
            if (input.files.length > 0) showFileInfo(input.files[0]);
        });

        removeBtn.addEventListener('click', clearFile);

        // Drag and drop
        if (dropZone) {
            ['dragenter', 'dragover'].forEach(evt => {
                dropZone.addEventListener(evt, e => {
                    e.preventDefault();
                    dropZone.style.borderColor = 'var(--gold)';
                    dropZone.style.background = 'var(--gold-subtle)';
                });
            });
            ['dragleave', 'drop'].forEach(evt => {
                dropZone.addEventListener(evt, e => {
                    e.preventDefault();
                    dropZone.style.borderColor = '';
                    dropZone.style.background = '';
                });
            });
            dropZone.addEventListener('drop', e => {
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    input.files = files;
                    showFileInfo(files[0]);
                }
            });
        }

        // Submit with progress overlay
        form.addEventListener('submit', (e) => {
            if (input.files.length > 0) {
                const serverLimit = parseInt(document.getElementById('serverLimitBytes')?.value || 0);
                const fileSize = input.files[0].size;
                if (serverLimit > 0 && fileSize > serverLimit * 0.9) {
                    e.preventDefault();
                    alert(`Arquivo muito grande!\n\nTamanho: ${(fileSize / 1048576).toFixed(1)}MB\nLimite do servidor: ${document.getElementById('serverLimitBytes').dataset.limit || '30M'}\n\nContate sua hospedagem para aumentar o limite.`);
                    return;
                }
                submitBtn.disabled = true;
                submitBtn.textContent = 'Enviando...';
                progress.style.display = 'flex';
                setTimeout(() => {
                    const statusEl = document.getElementById('uploadStatus');
                    if (statusEl && progress.style.display === 'flex') {
                        statusEl.textContent = 'Processando arquivo grande... aguarde';
                    }
                }, 15000);
            }
        });
    });

    // Game links add/remove and toggle
    const container = document.getElementById('gameLinksContainer');
    const sectionTitle = document.getElementById('gameLinksSection');
    const list = document.getElementById('gameLinksList');
    const addBtn = document.getElementById('addGameLink');
    const isWebPlayable = document.getElementById('is_web_playable');

    function toggleGameLinks() {
        const show = !isWebPlayable.checked;
        if (container) container.style.display = show ? '' : 'none';
        if (sectionTitle) sectionTitle.style.display = show ? '' : 'none';
    }

    if (isWebPlayable) {
        isWebPlayable.addEventListener('change', toggleGameLinks);
    }

    function createLinkRow(platformId, url) {
        const platforms = <?= json_encode(array_map(function($p) {
            return ['id' => $p['id'], 'name' => $p['name'], 'icon' => $p['icon'] ?? ''];
        }, $platforms ?? [])) ?>;
        let selectHtml = '<select name="link_platform[]"><option value="">Selecione...</option>';
        platforms.forEach(p => {
            selectHtml += `<option value="${p.id}" ${p.id == platformId ? 'selected' : ''}>${p.icon} ${p.name}</option>`;
        });
        selectHtml += '</select>';
        return `<div class="form-row game-link-row">
            <div class="form-group" style="flex:0 0 200px">${selectHtml}</div>
            <div class="form-group" style="flex:1"><input type="url" name="link_url[]" value="${url}" placeholder="https://..."></div>
            <button type="button" class="btn btn-danger btn-sm btn-icon game-link-remove" style="margin-top:24px;flex:0 0 36px" title="Remover">✕</button>
        </div>`;
    }

    if (addBtn) {
        addBtn.addEventListener('click', () => {
            const div = document.createElement('div');
            div.innerHTML = createLinkRow(0, '');
            list.appendChild(div.firstElementChild);
        });
    }

    list.addEventListener('click', (e) => {
        if (e.target.classList.contains('game-link-remove')) {
            e.target.closest('.game-link-row').remove();
        }
    });
    </script>
    <?php
} else {
    $games = dbQuery("SELECT g.*, COALESCE(e.active, 0) as engine_active FROM games g LEFT JOIN engines e ON g.engine = e.name ORDER BY g.sort_order ASC, g.id DESC");
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Todos os Jogos</h2>
            <a href="games?action=new" class="btn btn-gold btn-sm">+ Novo Jogo</a>
        </div>
        <?php if (empty($games)): ?>
            <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon">
                    <svg viewBox="0 0 24 24"><path d="M6 11h4M8 9v4"/><circle cx="15" cy="10.5" r="0.5" fill="currentColor" stroke="none"/><circle cx="17" cy="12.5" r="0.5" fill="currentColor" stroke="none"/><rect x="2" y="6" width="20" height="12" rx="4"/></svg>
                </div>
                <p>Nenhum jogo cadastrado ainda.</p>
            </div>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th>Engine</th>
                            <th class="hide-tablet">ZIP</th>
                            <th>Status</th>
                            <th class="hide-mobile">Otimização</th>
                            <th class="hide-mobile">Criado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($games as $g): ?>
                        <tr>
                            <td><strong style="color:var(--fg)"><?= e($g['title']) ?></strong></td>
                            <td>
                                <?php if (($g['game_type'] ?? 'autoral') === 'cliente'): ?>
                                    <span class="badge badge-client">Cliente</span>
                                <?php else: ?>
                                    <span class="badge badge-autoral">Autoral</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="game-engine-badge" style="background:<?= getEngineColor($g['engine']) ?>"><?= getEngineIcon($g['engine']) ?> <?= e($g['engine']) ?></span></td>
                            <td class="hide-tablet"><?= $g['game_path'] ? '📦 ' . e($g['game_path']) : '—' ?></td>
                            <td>
                                <?php if (!$g['engine_active']): ?>
                                    <span class="badge badge-inactive">Engine Inativa</span>
                                <?php elseif ($g['active']): ?>
                                    <span class="badge badge-active">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Inativo</span>
                                <?php endif; ?>
                                <?php if ($g['featured']): ?>
                                    <span class="badge badge-featured">Destaque</span>
                                <?php endif; ?>
                            </td>
                            <td class="hide-mobile">
                                <?php if ($g['game_path']): ?>
                                    <?php if (!empty($g['optimized_at'])): ?>
                                        <span class="badge badge-optimized">✅ <?= date('d/m/Y', strtotime($g['optimized_at'])) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">⏳ Pendente</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="hide-mobile"><?= timeAgo($g['created_at']) ?></td>
                            <td class="actions">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-outline btn-sm btn-icon" title="<?= $g['engine_active'] ? ($g['active'] ? 'Desativar' : 'Ativar') : 'Engine inativa — use Engines para ativar' ?>" <?= $g['engine_active'] ? '' : 'disabled style="opacity:0.4;cursor:not-allowed"' ?>><?= $g['active'] && $g['engine_active'] ? '🔴' : '🟢' ?></button>
                                </form>
                                <a href="games?action=edit&id=<?= $g['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este jogo? O arquivo ZIP também será removido.')">
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
