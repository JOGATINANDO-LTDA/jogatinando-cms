<?php
ob_start();
$pageTitle = 'Blog';
$requiredPerm = 'perm_blog';
require_once __DIR__ . '/../includes/header.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? $id);
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Token de segurança inválido.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/blog');
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
            $oldThumb = $id > 0 ? dbQueryOne("SELECT thumbnail_url FROM blog_posts WHERE id = ?", [$id])['thumbnail_url'] ?? '' : '';
            $result = uploadFile($_FILES['thumbnail'], 'blog', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if ($result['success']) {
                $thumbnail_url = $result['url'];
                if (!empty($oldThumb)) deleteFile($oldThumb);
            }
            else { flashMessage('error', $result['message']); ob_end_clean(); header('Location: ' . ADMIN_URL . '/blog?action=' . ($id > 0 ? "edit&id=$id" : 'new')); exit; }
        }

        if (empty($title)) { flashMessage('error', 'Título é obrigatório.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/blog?action=' . ($id > 0 ? "edit&id=$id" : 'new')); exit; }

        if ($id > 0) {
            if (!$thumbnail_url) { $existing = dbQueryOne("SELECT thumbnail_url FROM blog_posts WHERE id = ?", [$id]); $thumbnail_url = $existing['thumbnail_url']; }
            dbExec("UPDATE blog_posts SET title=?, slug=?, content=?, thumbnail_url=?, external_url=?, active=? WHERE id=?", [$title, $slug, $content, $thumbnail_url, $external_url, $active, $id]);
            flashMessage('success', 'Post atualizado!');
        } else {
            dbExec("INSERT INTO blog_posts (title, slug, content, thumbnail_url, external_url, active) VALUES (?, ?, ?, ?, ?, ?)", [$title, $slug, $content, $thumbnail_url, $external_url, $active]);
            flashMessage('success', 'Post criado!');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/blog'); exit;
    }

    if ($_POST['action'] === 'delete') { $post = dbQueryOne("SELECT thumbnail_url FROM blog_posts WHERE id = ?", [$id]); if ($post && !empty($post['thumbnail_url'])) deleteFile($post['thumbnail_url']); dbDelete('blog_posts', $id); flashMessage('success', 'Post excluído.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/blog'); exit; }
    if ($_POST['action'] === 'toggle') { $r = dbQueryOne("SELECT active FROM blog_posts WHERE id = ?", [$id]); if ($r) dbExec("UPDATE blog_posts SET active = ? WHERE id = ?", [1 - $r['active'], $id]); ob_end_clean(); header('Location: ' . ADMIN_URL . '/blog'); exit; }
    if ($_POST['action'] === 'delete_selected') {
        $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
        if (!empty($ids)) {
            foreach ($ids as $bid) { $p = dbQueryOne("SELECT thumbnail_url FROM blog_posts WHERE id = ?", [$bid]); if ($p && !empty($p['thumbnail_url'])) deleteFile($p['thumbnail_url']); }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            dbExec("DELETE FROM blog_posts WHERE id IN ($placeholders)", $ids);
            flashMessage('success', count($ids) . ' post(s) removido(s).');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/blog');
        exit;
    }
}

if ($action === 'new' || $action === 'edit') {
    $post = $id > 0 ? dbQueryOne("SELECT * FROM blog_posts WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$post) { flashMessage('error', 'Post não encontrado.'); header('Location: ' . ADMIN_URL . '/blog'); exit; }
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $action === 'new' ? 'Novo Post' : 'Editar Post' ?></h2>
            <a href="blog" class="btn btn-outline btn-sm">← Voltar</a>
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
                    <div class="field-hint">Gerado automaticamente do título</div>
                </div>
                <div class="form-group">
                    <label for="external_url">URL Externa (opcional)</label>
                    <input type="url" id="external_url" name="external_url" value="<?= e($post['external_url'] ?? '') ?>" placeholder="https://gamenews.xo.je/...">
                    <div class="field-hint">Se preenchido, o link aponta para URL externa</div>
                </div>
            </div>
            <div class="form-group">
                <label for="content">Conteúdo</label>
                <textarea id="content" name="content" rows="12"><?= e($post['content'] ?? '') ?></textarea>
                <div class="field-hint">Use HTML para formatação</div>
            </div>

            <h3 class="form-section-title">Thumbnail</h3>

            <div class="form-group">
                <label for="thumbnail">Thumbnail</label>
                <div class="file-upload">
                    <input type="file" id="thumbnail" name="thumbnail" accept="image/png,image/jpeg,image/gif,image/webp">
                    <div class="upload-icon">
                        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    </div>
                    <div class="upload-text">Clique ou arraste uma imagem</div>
                    <div class="upload-hint">JPG, PNG, GIF ou WebP.</div>
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
                <a href="blog" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
    </div>

    <?php if ($action === 'new' || $action === 'edit'): ?>
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('content')) {
            tinymce.init({
                selector: '#content',
                height: 350,
                menubar: false,
                plugins: 'link lists code',
                toolbar: 'bold italic underline | bullist numlist | link | code',
                content_style: 'body { font-family: Inter, sans-serif; font-size: 14px; color: #e0e0e0; background: #1a1a2e; } a { color: #d4a853; }',
                skin: 'oxide-dark',
                content_css: false,
                branding: false,
                promotion: false,
                statusbar: true
            });
        }
    });
    </script>
    <?php endif; ?>

    <?php
} else {
    $pager = paginateQuery('SELECT COUNT(*) as c FROM blog_posts', 'SELECT * FROM blog_posts ORDER BY created_at DESC');
    $posts = $pager['items'];
    $totalItems = $pager['total'];
    ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Posts do Blog (<?= $totalItems ?>)</h2>
            <a href="blog?action=new" class="btn btn-gold btn-sm">+ Novo Post</a>
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
            <div class="card-body">
                <form method="POST" id="bulkForm"><?= csrfField() ?><input type="hidden" name="action" value="delete_selected"></form>
                <div class="bulk-bar" id="bulkBar">
                    <span class="bulk-count" id="bulkCount">0 selecionados</span>
                    <button type="button" class="btn btn-danger btn-sm" id="bulkDeleteBtn" disabled>Excluir Selecionados</button>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th><input type="checkbox" id="select-all"></th><th>Título</th><th>Slug</th><th>Externa</th><th>Status</th><th>Data</th><th>Ações</th></tr></thead>
                        <tbody>
                            <?php foreach ($posts as $p): ?>
                            <tr>
                                <td><input type="checkbox" class="row-select" value="<?= (int)$p['id'] ?>"></td>
                                <td><strong><?= e($p['title']) ?></strong></td>
                                <td><code class="slug-code"><?= e($p['slug']) ?></code></td>
                                <td><?= $p['external_url'] ? '🔗 Externo' : '📄 Interno' ?></td>
                                <td><?= $p['active'] ? '<span class="badge badge-active">Publicado</span>' : '<span class="badge badge-inactive">Rascunho</span>' ?></td>
                                <td><?= timeAgo($p['created_at']) ?></td>
                                <td class="actions">
                                    <form method="POST" class="inline-actions"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-outline btn-sm btn-icon"><?= $p['active'] ? '🔴' : '🟢' ?></button></form>
                                    <a href="blog?action=edit&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                                    <form method="POST" class="inline-actions" onsubmit="return confirm('Excluir este post?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-danger btn-sm btn-icon" title="Excluir">🗑️</button></form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?= renderPagination($pager['page'], $pager['pages']) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
require_once __DIR__ . '/../includes/footer.php';
