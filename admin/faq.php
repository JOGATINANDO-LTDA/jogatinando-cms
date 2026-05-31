<?php
ob_start();
$pageTitle = 'FAQ';
require_once __DIR__ . '/../includes/header.php';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? $id);
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/faq'); exit; }
    if ($_POST['action'] === 'save') {
        $question = trim($_POST['question']); $answer = trim($_POST['answer']);
        $sort_order = (int)($_POST['sort_order'] ?? 0); $active = isset($_POST['active']) ? 1 : 0;
        if (empty($question) || empty($answer)) { flashMessage('error', 'Pergunta e resposta são obrigatórias.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/faq?action=' . ($id > 0 ? "edit&id=$id" : 'new')); exit; }
        if ($id > 0) {
            dbExec("UPDATE faq_items SET question=?, answer=?, sort_order=?, active=? WHERE id=?", [$question, $answer, $sort_order, $active, $id]);
            flashMessage('success', 'FAQ atualizada!');
        } else {
            dbExec("INSERT INTO faq_items (question, answer, sort_order, active) VALUES (?, ?, ?, ?)", [$question, $answer, $sort_order, $active]);
            flashMessage('success', 'FAQ criada!');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/faq'); exit;
    }
    if ($_POST['action'] === 'delete') { dbDelete('faq_items', $id); flashMessage('success', 'FAQ excluída.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/faq'); exit; }
    if ($_POST['action'] === 'toggle') { $r = dbQueryOne("SELECT active FROM faq_items WHERE id = ?", [$id]); if ($r) dbExec("UPDATE faq_items SET active = ? WHERE id = ?", [1 - $r['active'], $id]); ob_end_clean(); header('Location: ' . ADMIN_URL . '/faq'); exit; }
}

if ($action === 'new' || $action === 'edit') {
    $item = $id > 0 ? dbQueryOne("SELECT * FROM faq_items WHERE id = ?", [$id]) : null;
    if ($action === 'edit' && !$item) { flashMessage('error', 'Não encontrada.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/faq'); exit; }
    ?>
    <div class="card">
        <div class="card-header"><h2 class="card-title"><?= $action === 'new' ? 'Nova FAQ' : 'Editar FAQ' ?></h2><a href="faq" class="btn btn-outline btn-sm">← Voltar</a></div>
        <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save"><?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?><?= csrfField() ?>

            <h3 class="form-section-title">Conteúdo</h3>

            <div class="form-group"><label for="question">Pergunta *</label><input type="text" id="question" name="question" value="<?= e($item['question'] ?? '') ?>" required></div>
            <div class="form-group"><label for="answer">Resposta *</label><textarea id="answer" name="answer" rows="6" required><?= e($item['answer'] ?? '') ?></textarea><p class="hint">Use HTML para formatação</p></div>

            <h3 class="form-section-title">Configurações</h3>

            <div class="form-row">
                <div class="form-group"><label for="sort_order">Ordem</label><input type="number" id="sort_order" name="sort_order" value="<?= (int)($item['sort_order'] ?? 0) ?>"></div>
                <div class="form-group"><div class="toggle-group" style="margin-top:28px"><input type="checkbox" id="active" name="active" <?= ($item['active'] ?? 1) ? 'checked' : '' ?>><label for="active">Ativa</label></div></div>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-gold">Salvar</button><a href="faq" class="btn btn-outline">Cancelar</a></div>
        </form>
        </div>
    </div>
    <?php
} else {
    $items = dbQuery("SELECT * FROM faq_items ORDER BY sort_order ASC");
    ?>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Perguntas Frequentes</h2><a href="faq?action=new" class="btn btn-gold btn-sm">+ Nova FAQ</a></div>
        <?php if (empty($items)): ?><div class="card-body"><div class="empty-state"><div class="empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><p>Nenhuma FAQ cadastrada.</p></div></div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Ordem</th><th>Pergunta</th><th>Resposta</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $f): ?>
                        <tr>
                            <td><?= (int)$f['sort_order'] ?></td>
                            <td><strong style="color:var(--fg)"><?= e($f['question']) ?></strong></td>
                            <td><?= e(truncateText(strip_tags($f['answer']), 60)) ?></td>
                            <td><?= $f['active'] ? '<span class="badge badge-active">Ativa</span>' : '<span class="badge badge-inactive">Inativa</span>' ?></td>
                            <td class="actions">
                                <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $f['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-outline btn-sm btn-icon"><?= $f['active'] ? '🔴' : '🟢' ?></button></form>
                                <a href="faq?action=edit&id=<?= $f['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Editar">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Excluir?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $f['id'] ?>"><?= csrfField() ?><button type="submit" class="btn btn-danger btn-sm btn-icon" title="Excluir">🗑️</button></form>
                            </td>
                        </tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
require_once __DIR__ . '/../includes/footer.php';
