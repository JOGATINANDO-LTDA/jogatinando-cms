<?php
require_once __DIR__ . '/../config.php';
requireLogin();

// Handle AI blog content generation (returns JSON, before header output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ai_generate_blog') {
    header('Content-Type: application/json');
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Token inválido.']);
        exit;
    }
    if (!can('perm_blog')) {
        echo json_encode(['error' => 'Permissão negada.']);
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');

    if ($title === '') {
        echo json_encode(['error' => 'Título do post é obrigatório para geração de conteúdo.']);
        exit;
    }

    try {
        require_once ROOT_PATH . '/includes/ai/client.php';
        $client = AIClient::getInstance();
        if (!$client->isAvailable()) {
            echo json_encode(['error' => 'Nenhum provider de IA configurado. Configure uma configuração em Configurações de IA.']);
            exit;
        }

        $prompt = "Escreva um artigo de blog de 300-500 palavras sobre \"$title\" em português do Brasil. "
            . ($excerpt ? "Use este resumo: \"$excerpt\". " : '')
            . "O conteúdo deve ser informativo, engajador, bem estruturado com subtítulos em HTML (use <h2>, <p>). "
            . "Foque em jogos, indústria de jogos ou cultura geek. Não inclua preâmbulos como 'Claro' ou 'Certamente'.";

        $result = $client->chat([
            ['role' => 'user', 'content' => $prompt],
        ], ['feature' => 'blog_content', 'max_tokens' => 1500, 'temperature' => 0.7]);

        echo json_encode(['content' => $result['content'] ?? '']);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

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

        $is_premium = isset($_POST['is_premium']) ? 1 : 0;

        if ($id > 0) {
            if (!$thumbnail_url) { $existing = dbQueryOne("SELECT thumbnail_url FROM blog_posts WHERE id = ?", [$id]); $thumbnail_url = $existing['thumbnail_url']; }
            dbExec("UPDATE blog_posts SET title=?, slug=?, content=?, thumbnail_url=?, external_url=?, active=?, is_premium=? WHERE id=?", [$title, $slug, $content, $thumbnail_url, $external_url, $active, $is_premium, $id]);
            flashMessage('success', 'Post atualizado!');
        } else {
            dbExec("INSERT INTO blog_posts (title, slug, content, thumbnail_url, external_url, active, is_premium) VALUES (?, ?, ?, ?, ?, ?, ?)", [$title, $slug, $content, $thumbnail_url, $external_url, $active, $is_premium]);
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
                <div style="display:flex; gap:8px; align-items:flex-start;">
                    <textarea id="content" name="content" rows="12" style="flex:1;"><?= e($post['content'] ?? '') ?></textarea>
                    <button type="button" id="aiGenBlogBtn" class="btn btn-outline btn-sm" style="align-self:flex-end; flex-shrink:0;" title="Gerar conteúdo com IA">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7v10c0 4.5 8 8 8 8s8-3.5 8-8V7l-10-5z"/><path d="M8 12l2 2 4-4"/></svg>
                        Gerar IA
                    </button>
                </div>
                <div id="aiGenBlogStatus" class="field-hint" style="display:none;"></div>
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
                    <input type="checkbox" id="is_premium" name="is_premium" <?= ($post['is_premium'] ?? 0) ? 'checked' : '' ?>>
                    <label for="is_premium">Conteúdo Premium</label>
                </div>
                <div class="field-hint">Conteúdo bloqueado para assinantes pagos</div>
            </div>
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
    <script src="<?= ADMIN_URL ?>/../assets/js/markdown-editor.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        initMarkdownEditor('content');

        // AI Blog Content Generation
        var aiBtn = document.getElementById('aiGenBlogBtn');
        var aiStatus = document.getElementById('aiGenBlogStatus');
        if (aiBtn) {
            aiBtn.addEventListener('click', function() {
                var title = document.getElementById('title').value.trim();
                var excerpt = document.getElementById('excerpt').value.trim();
                if (!title) {
                    aiStatus.textContent = 'Digite o título do post primeiro.';
                    aiStatus.style.display = 'block';
                    return;
                }
                aiStatus.textContent = 'Gerando conteúdo com IA...';
                aiStatus.style.display = 'block';
                aiBtn.disabled = true;
                aiBtn.textContent = 'Gerando...';

                var formData = new FormData();
                formData.append('csrf_token', '<?= getCSRFToken() ?>');
                formData.append('action', 'ai_generate_blog');
                formData.append('title', title);
                formData.append('excerpt', excerpt);

                fetch('<?= ADMIN_URL ?>/blog?action=edit&id=<?= $id ?>', {
                    method: 'POST',
                    body: formData,
                })
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        aiStatus.textContent = 'Erro: ' + data.error;
                    } else if (data.content) {
                        document.getElementById('content').value = data.content;
                        aiStatus.textContent = 'Conteúdo gerado! Revise e ajuste se necessário.';
                    }
                })
                .catch(err => {
                    aiStatus.textContent = 'Erro de rede: ' + err.message;
                })
                .finally(() => {
                    aiBtn.disabled = false;
                    aiBtn.textContent = 'Gerar IA';
                });
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
        <?php if (!empty($pager['error'])): ?>
            <?= renderDbErrorCard($pager['error']) ?>
        <?php elseif (empty($posts)): ?>
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
