<?php
$pageTitle = 'Blog';
require_once __DIR__ . '/../includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Token de segurança inválido.');
        header('Location: blog.php');
        exit;
    }

    if ($_POST['action'] === 'save') {
        $title = trim($_POST['title']);
        $slug = trim($_POST['slug']) ?: generateSlug($title);
        $content = trim($_POST['content']);
        $external_url = trim($_POST['external_url']);
        $active = isset($_POST['active']) ? 1 : 0;
        $thumbnail_url = '';

        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['thumbnail'], 'blog', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($result['success']) $thumbnail_url = $result['url'];
            else { flashMessage('error', $result['message']); header('Location: blog.php?action=' . ($id > 0 ? "edit&id=$id" : 'new')); exit; }
        }

        if (empty($title)) { flashMessage('error', 'Título é obrigatório.'); header('Location: blog.php?action=' . ($id > 0 ? "edit&id=$id" : 'new')); exit; }

        if ($id > 0) {
            if (!$thumbnail_url) { $existing = dbQueryOne("SELECT thumbnail_url FROM blog_posts WHERE id = ?", [$id]); $thumbnail_url = $existing['thumbnail_url']; }
            dbExec("UPDATE blog_posts SET title=?, slug=?, content=?, thumbnail_url=?, external_url=?, active=? WHERE id=?", [$title, $slug, $content, $thumbnail_url, $external_url, $active, $id]);
            flashMessage('success', 'Post atualizado!');
        } else {
            dbExec("INSERT INTO blog_posts (title, slug, content, thumbnail_url, external_url, active) VALUES (?, ?, ?, ?, ?, ?)", [$title, $slug, $content, $thumbnail_url, $external_url, $active]);
            flashMessage('success', 'Post criado!');
        }
        header('Location: blog.php'); exit;
    }

    if ($_POST['action'] === 'delete') { dbDelete('blog_posts', $id); flashMessage('success', 'Post excluído.'); header('Location: blog.php'); exit; }
    if ($_POST['action'] === 'toggle') { $r = dbQueryOne("SELECT active FROM blog_posts WHERE id = ?", [$id]); if ($r) dbExec("UPDATE blog_posts SET active = ? WHERE id = ?", [1 - $r['active'], $id]); header('Location: blog.php'); exit; }
}

if ($action === 'new' || $action === 'edit') {
    $post = $id > 0 ? dbQueryOne("SELECT * FROM blog_posts WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$post) { flashMessage('error', 'Post não encontrado.'); header('Location: blog.php'); exit; }
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $action === 'new' ? 'Novo Post' : 'Editar Post' ?></h2>
            <a href="blog.php" class="btn btn-outline btn-sm">← Voltar</a>
        </div>
        <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">
            <?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
            <?= csrfField() ?>

            <h3 class="form-section-title">Conteúdo</h3>

            <div class="form-group">
                <label for="title">Título *</label>
                <input type="text" id="title" name="title" value="<?= e($post['title'] ?? '') ?>" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="slug">Slug (URL)</label>
                    <input type="text" id="slug" name="slug" value="<?= e($post['slug'] ?? '') ?>">
                    <p class="hint">Gerado automaticamente do título</p>
                </div>
                <div class="form-group">
                    <label for="external_url">URL Externa (opcional)</label>
                    <input type="url" id="external_url" name="external_url" value="<?= e($post['external_url'] ?? '') ?>" placeholder="https://gamenews.xo.je/...">
                    <p class="hint">Se preenchido, o link aponta para URL externa</p>
                </div>
            </div>
            <div class="form-group">
                <label for="content">Conteúdo</label>
                <textarea id="content" name="content" rows="12"><?= e($post['content'] ?? '') ?></textarea>
                <p class="hint">Use HTML para formatação</p>
            </div>

            <h3 class="form-section-title">Thumbnail</h3>

            <div class="form-group">
                <div class="file-upload">
                    <input type="file" name="thumbnail" accept="image/*">
                    <div class="upload-icon">
                        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    </div>
                    <div class="upload-text">Clique ou arraste uma imagem</div>
                </div>
                <?php if (!empty($post['thumbnail_url'])): ?><img src="<?= e($post['thumbnail_url']) ?>" class="preview-img" alt="Thumbnail"><?php endif; ?>
            </div>

            <h3 class="form-section-title">Publicação</h3>

            <div class="form-group">
                <div class="toggle-group">
                    <input type="checkbox" id="active" name="active" <?= ($post['active'] ?? 1) ? 'checked' : '' ?>>
                    <label for="active">Publicado</label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-gold">Salvar Post</button>
                <a href="blog.php" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
        </div>
    </div>
    <?php
} else {
    $posts = dbQuery("SELECT * FROM blog_posts ORDER BY created_at DESC");
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Posts do Blog</h2>
            <a href="blog.php?action=new" class="btn btn-gold btn-sm">+ Novo Post</a>
        </div>
        <?php if (empty($posts)): ?>
            <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                </div>
                <p>Nenhum post cadastrado.</p>
            </div>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Título</th><th>Slug</th><th>Externa</th><th>Status</th><th>Data</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php foreach ($posts as $p): ?>
                        <tr>
                            <td><strong style="color:var(--fg)"><?= e($p['title']) ?></strong></td>
                            <td><code style="font-size:12px;color:var(--muted)"><?= e($p['slug']) ?></code></td>
                            <td><?= $p['external_url'] ? '🔗 Externo' : '📄 Interno' ?></td>
                            <td><?= $p['active'] ? '<span class="badge badge-active">Publicado</span>' : '<span class="badge badge-inactive">Rascunho</span>' ?></td>
                            <td><?= timeAgo($p['created_at']) ?></td>
                            <td class="actions">
                                <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-outline btn-sm btn-icon"><?= $p['active'] ? '🔴' : '🟢' ?></button></form>
                                <a href="blog.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este post?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-danger btn-sm btn-icon" title="Excluir">🗑️</button></form>
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
