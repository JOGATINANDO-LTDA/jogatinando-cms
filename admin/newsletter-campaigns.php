<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$db = getDB();
$success = '';
$error = '';
$action = $_GET['action'] ?? '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token inválido.';
    } else {
        $postAction = $_POST['action'] ?? '';

        if ($postAction === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM newsletter_campaigns WHERE id = ?")->execute([$id]);
                $db->prepare("DELETE FROM newsletter_campaign_recipients WHERE campaign_id = ?")->execute([$id]);
                $success = 'Campanha removida.';
            }
        }

        if ($postAction === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $sender_name = trim($_POST['sender_name'] ?? '');
            $sender_email = trim($_POST['sender_email'] ?? '');
            $scheduled_at = trim($_POST['scheduled_at'] ?? '');

            if ($title === '' || $subject === '' || $content === '') {
                $error = 'Título, assunto e conteúdo são obrigatórios.';
            } else {
                $now = date('Y-m-d H:i:s');
                $newStatus = $scheduled_at !== '' ? 'scheduled' : 'draft';
                if ($id > 0) {
                    $cur = $db->query("SELECT status FROM newsletter_campaigns WHERE id = $id")->fetch();
                    if ($cur && $cur['status'] === 'sent') {
                        $newStatus = 'sent';
                    }
                    $db->prepare("UPDATE newsletter_campaigns SET title = ?, subject = ?, content = ?, sender_name = ?, sender_email = ?, scheduled_at = ?, status = ?, updated_at = ? WHERE id = ?")->execute([$title, $subject, $content, $sender_name, $sender_email, $scheduled_at, $newStatus, $now, $id]);
                } else {
                    $db->prepare("INSERT INTO newsletter_campaigns (title, subject, content, sender_name, sender_email, scheduled_at, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([$title, $subject, $content, $sender_name, $sender_email, $scheduled_at, $newStatus, $now, $now]);
                }
                $success = $newStatus === 'scheduled' ? 'Campanha agendada.' : 'Campanha salva.';
            }
        }

        if ($postAction === 'send_test') {
            $id = (int)($_POST['id'] ?? 0);
            $test_email = trim($_POST['test_email'] ?? '');
            if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
                $campaign = $db->query("SELECT * FROM newsletter_campaigns WHERE id = $id")->fetch();
                if ($campaign) {
                    $unsubUrl = SITE_URL . '/unsubscribe?token=EXEMPLO';
                    $body = buildCampaignBody($campaign['content'], 'Leitor', $unsubUrl);
                    $headers = "From: " . ($campaign['sender_name'] ?: 'CMS') . " <" . ($campaign['sender_email'] ?: 'noreply@localhost') . ">\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    $mail = mail($test_email, '[TESTE] ' . $campaign['subject'], $body, $headers);
                    $success = $mail ? 'E-mail de teste enviado para ' . $test_email : 'Falha ao enviar e-mail de teste.';
                }
            } else {
                $error = 'E-mail de teste inválido.';
            }
        }

        if ($postAction === 'send') {
            $id = (int)($_POST['id'] ?? 0);
            $campaign = $db->query("SELECT * FROM newsletter_campaigns WHERE id = $id")->fetch();
            if (!$campaign) {
                $error = 'Campanha não encontrada.';
            } else {
                $result = sendCampaign($id);
                $success = "Campanha enviada para {$result['sent']}/{$result['total']} destinatários.";
            }
        }

        if ($success || $error) {
            header('Location: ' . ADMIN_URL . '/newsletter-campaigns' . ($error ? '?err=1' : '?ok=1'));
            exit;
        }
    }
    header('Location: ' . ADMIN_URL . '/newsletter-campaigns');
    exit;
}

// Edit mode
$editItem = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0 && $action === 'edit') {
    $editItem = $db->query("SELECT * FROM newsletter_campaigns WHERE id = $editId")->fetch();
    if (!$editItem) $editId = 0;
}

// List campaigns
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;

$total = (int)$db->query("SELECT COUNT(*) FROM newsletter_campaigns")->fetchColumn();
$totalPages = (int)ceil($total / $limit);

$campaigns = $db->query("SELECT * FROM newsletter_campaigns ORDER BY id DESC LIMIT $limit OFFSET $offset")->fetchAll();

$activeSubs = (int)$db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE is_active = 1")->fetchColumn();
$ok = isset($_GET['ok']);
$err = isset($_GET['err']);

$pageTitle = 'Campanhas';
$requiredPerm = 'perm_settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-page-header">
    <div>
        <h2>Campanhas de Newsletter</h2>
        <p class="subtitle"><?= $total ?> campanhas · <span class="text-gold"><?= $activeSubs ?></span> inscritos ativos</p>
    </div>
    <a class="btn btn-gold btn-sm" href="?action=new">+ Nova Campanha</a>
</div>

<?php if ($ok): ?>
    <div class="alert alert-success">Operação realizada com sucesso!</div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert alert-error">Ocorreu um erro. Verifique os campos.</div>
<?php endif; ?>

<?php if ($action === 'new' || $editId > 0): ?>
<div class="admin-card">
    <h3><?= $editItem ? 'Editar Campanha' : 'Nova Campanha' ?></h3>
    <form method="POST" class="form-grid">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)($editItem['id'] ?? 0) ?>">

        <div class="form-group">
            <label for="title">Título</label>
            <input type="text" id="title" name="title" value="<?= e($editItem['title'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label for="subject">Assunto do E-mail</label>
            <input type="text" id="subject" name="subject" value="<?= e($editItem['subject'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label for="sender_name">Remetente (nome)</label>
            <input type="text" id="sender_name" name="sender_name" value="<?= e($editItem['sender_name'] ?? getSetting('site_name')) ?>">
        </div>

        <div class="form-group">
            <label for="sender_email">Remetente (e-mail)</label>
            <input type="email" id="sender_email" name="sender_email" value="<?= e($editItem['sender_email'] ?? getSetting('admin_email', '')) ?>">
        </div>

        <div class="form-group">
            <label for="scheduled_at">Agendar envio</label>
            <input type="datetime-local" id="scheduled_at" name="scheduled_at" value="<?= e($editItem['scheduled_at'] ?? '') ?>">
            <div class="field-hint">Preencha para agendar o envio automático (status "Agendada"). Deixe em branco para salvar como rascunho.</div>
        </div>

        <div class="form-group" style="grid-column: 1 / -1;">
            <label for="content">Conteúdo do E-mail (HTML)</label>
            <div class="field-hint">Placeholders: {name} (nome do destinatário) e {unsubscribe_url} (link de descadastro). Se não usar {unsubscribe_url}, um rodapé de descadastro é adicionado automaticamente.</div>
            <textarea id="content" name="content" rows="20" style="font-family:monospace"><?= e($editItem['content'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button class="btn btn-gold" type="submit">Salvar Campanha</button>
            <a class="btn btn-outline" href="?">Cancelar</a>
        </div>
    </form>

    <?php if ($editItem && ($editItem['status'] === 'draft' || $editItem['status'] === 'scheduled')): ?>
    <form method="POST" style="display:flex; gap:8px; align-items:center; margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="send_test">
        <input type="hidden" name="id" value="<?= (int)($editItem['id'] ?? 0) ?>">
        <label style="white-space:nowrap;">Enviar teste:</label>
        <input type="email" name="test_email" placeholder="seu@email.com" style="flex:1;" required>
        <button type="submit" class="btn btn-outline btn-sm">Enviar</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<form method="POST" id="sendCampaignForm">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="send">
    <input type="hidden" name="id" id="send_campaign_id">
</form>

<div class="admin-card">
    <h3>Campanhas (<?= $total ?>)</h3>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Título</th>
                    <th>Assunto</th>
                    <th>Status</th>
                    <th>Agendado</th>
                    <th>Enviados</th>
                    <th>Criado em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $c): ?>
                <tr>
                    <td><?= (int)$c['id'] ?></td>
                    <td><?= e($c['title']) ?></td>
                    <td><?= e($c['subject']) ?></td>
                    <td>
                        <span class="badge badge-<?= $c['status'] === 'sent' ? 'success' : ($c['status'] === 'scheduled' ? 'gold' : 'muted') ?>">
                            <?= e(ucfirst($c['status'])) ?>
                        </span>
                    </td>
                    <td><?= $c['scheduled_at'] ? e($c['scheduled_at']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= (int)$c['sent_count'] ?>/<?= (int)$c['total_recipients'] ?: $activeSubs ?></td>
                    <td><?= e($c['created_at']) ?></td>
                    <td class="actions">
                        <?php if ($c['status'] === 'draft' || $c['status'] === 'scheduled'): ?>
                            <button class="btn btn-gold btn-sm" onclick="document.getElementById('send_campaign_id').value='<?= (int)$c['id'] ?>'; if(confirm('Enviar esta campanha para <?= $activeSubs ?> inscritos?')) document.getElementById('sendCampaignForm').submit();">Enviar</button>
                        <?php endif; ?>
                        <a class="btn btn-outline btn-sm" href="?action=edit&edit=<?= (int)$c['id'] ?>">Editar</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Remover esta campanha?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button class="btn btn-danger btn-sm">Excluir</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($campaigns)): ?>
                    <tr><td colspan="7" class="text-muted">Nenhuma campanha encontrada.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="btn btn-outline btn-sm" href="?page=<?= $i ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
