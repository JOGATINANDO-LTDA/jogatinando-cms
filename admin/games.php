<?php
$pageTitle = 'Jogos';
require_once __DIR__ . '/../includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

$engines = ['GDevelop', 'Godot', 'RPG Maker', 'Unity', 'Unreal Engine', 'Construct', 'Defold', 'Game Maker', 'Ren\'py', 'Pixel Game Maker MV', 'RPG Paper Maker', 'Outra'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Token de segurança inválido.');
        header('Location: games.php');
        exit;
    }

    if ($_POST['action'] === 'save') {
        $title = trim($_POST['title']);
        $engine = trim($_POST['engine']);
        $description = trim($_POST['description']);
        $featured = isset($_POST['featured']) ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        $thumbnail_url = '';
        $zip_filename = '';

        // Handle thumbnail upload
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['thumbnail'], 'thumbnails', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($result['success']) {
                $thumbnail_url = $result['url'];
            } else {
                flashMessage('error', $result['message']);
                header('Location: games.php?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
                exit;
            }
        }

        // Handle game zip upload
        if (isset($_FILES['game_zip']) && $_FILES['game_zip']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['game_zip'], 'games', ['zip']);
            if ($result['success']) {
                $zip_filename = $result['filename'];
            } else {
                flashMessage('error', $result['message']);
                header('Location: games.php?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
                exit;
            }
        }

        if (empty($title)) {
            flashMessage('error', 'Título é obrigatório.');
            header('Location: games.php?action=' . ($id > 0 ? "edit&id=$id" : 'new'));
            exit;
        }

        if ($id > 0) {
            // Get existing zip filename if not uploading new one
            if (!$zip_filename) {
                $existing = dbQueryOne("SELECT zip_filename FROM games WHERE id = ?", [$id]);
                $zip_filename = $existing['zip_filename'];
            }
            // Get existing thumbnail if not uploading new one
            if (!$thumbnail_url) {
                $existing = dbQueryOne("SELECT thumbnail_url FROM games WHERE id = ?", [$id]);
                $thumbnail_url = $existing['thumbnail_url'];
            }

            dbExec("UPDATE games SET title=?, engine=?, description=?, thumbnail_url=?, zip_filename=?, featured=?, sort_order=?, active=?, updated_at=CURRENT_TIMESTAMP WHERE id=?",
                [$title, $engine, $description, $thumbnail_url, $zip_filename, $featured, $sort_order, $active, $id]);
            flashMessage('success', 'Jogo atualizado com sucesso!');
        } else {
            dbExec("INSERT INTO games (title, engine, description, thumbnail_url, zip_filename, featured, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$title, $engine, $description, $thumbnail_url, $zip_filename, $featured, $sort_order, $active]);
            flashMessage('success', 'Jogo criado com sucesso!');
        }
        header('Location: games.php');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $game = dbQueryOne("SELECT * FROM games WHERE id = ?", [$id]);
        if ($game) {
            // Delete zip file if exists
            if ($game['zip_filename']) {
                deleteFile(UPLOAD_PATH . '/games/' . $game['zip_filename']);
            }
            dbDelete('games', $id);
            flashMessage('success', 'Jogo excluído.');
        }
        header('Location: games.php');
        exit;
    }

    if ($_POST['action'] === 'toggle') {
        $game = dbQueryOne("SELECT active FROM games WHERE id = ?", [$id]);
        if ($game) {
            dbExec("UPDATE games SET active = ? WHERE id = ?", [1 - $game['active'], $id]);
        }
        header('Location: games.php');
        exit;
    }
}

if ($action === 'new' || $action === 'edit') {
    $game = $id > 0 ? dbQueryOne("SELECT * FROM games WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$game) {
        flashMessage('error', 'Jogo não encontrado.');
        header('Location: games.php');
        exit;
    }
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $action === 'new' ? 'Novo Jogo' : 'Editar Jogo' ?></h2>
            <a href="games.php" class="btn btn-outline btn-sm">← Voltar</a>
        </div>
        <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">
            <?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
            <?= csrfField() ?>

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
                        <?php foreach ($engines as $eng): ?>
                            <option value="<?= e($eng) ?>" <?= ($game['engine'] ?? '') === $eng ? 'selected' : '' ?>><?= e($eng) ?></option>
                        <?php endforeach; ?>
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
                        <div class="upload-hint">JPG, PNG, WebP — máx 10MB</div>
                    </div>
                    <?php if (!empty($game['thumbnail_url'])): ?>
                        <img src="<?= e($game['thumbnail_url']) ?>" class="preview-img" alt="Thumbnail">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Arquivo do Jogo (ZIP)</label>
                    <div class="file-upload">
                        <input type="file" name="game_zip" accept=".zip">
                        <div class="upload-icon">
                            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </div>
                        <div class="upload-text">Upload do ZIP do jogo</div>
                        <div class="upload-hint">HTML exportado em ZIP — máx 100MB</div>
                    </div>
                    <?php if (!empty($game['zip_filename'])): ?>
                        <p style="margin-top:8px;font-size:13px;color:var(--muted)">📎 <?= e($game['zip_filename']) ?> (envie outro para substituir)</p>
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
                    <div class="toggle-group" style="margin-top:28px">
                        <input type="checkbox" id="featured" name="featured" <?= ($game['featured'] ?? 0) ? 'checked' : '' ?>>
                        <label for="featured">Destaque no site</label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="toggle-group">
                    <input type="checkbox" id="active" name="active" <?= ($game['active'] ?? 1) ? 'checked' : '' ?>>
                    <label for="active">Ativo</label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-gold">Salvar Jogo</button>
                <a href="games.php" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
        </div>
    </div>
    <?php
} else {
    $games = dbQuery("SELECT * FROM games ORDER BY sort_order ASC, id DESC");
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Todos os Jogos</h2>
            <a href="games.php?action=new" class="btn btn-gold btn-sm">+ Novo Jogo</a>
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
                            <th>Engine</th>
                            <th>ZIP</th>
                            <th>Status</th>
                            <th>Criado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($games as $g): ?>
                        <tr>
                            <td><strong style="color:var(--fg)"><?= e($g['title']) ?></strong></td>
                            <td><?= e($g['engine']) ?></td>
                            <td><?= $g['zip_filename'] ? '📦 Sim' : '—' ?></td>
                            <td>
                                <?php if ($g['active']): ?>
                                    <span class="badge badge-active">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Inativo</span>
                                <?php endif; ?>
                                <?php if ($g['featured']): ?>
                                    <span class="badge badge-featured">Destaque</span>
                                <?php endif; ?>
                            </td>
                            <td><?= timeAgo($g['created_at']) ?></td>
                            <td class="actions">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-outline btn-sm btn-icon" title="<?= $g['active'] ? 'Desativar' : 'Ativar' ?>"><?= $g['active'] ? '🔴' : '🟢' ?></button>
                                </form>
                                <a href="games.php?action=edit&id=<?= $g['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
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
