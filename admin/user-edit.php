<?php
ob_start();
$pageTitle = 'Editar Usuário';
$requiredPerm = 'perm_users';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$currentUserId = $_SESSION['admin_user_id'] ?? 0;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flashMessage('error', 'Usuário inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/users'); exit; }

$user = dbQueryOne("
    SELECT u.*, r.name AS role_name,
           l.name AS level_name, l.slug AS level_slug
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN levels l ON r.level_id = l.id
    WHERE u.id = ?
", [$id]);
if (!$user) { flashMessage('error', 'Usuário não encontrado.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/users'); exit; }

$isSelf = ((int)$id === $currentUserId);
$isEditingMaster = ((int)$id === 1);

if (!$isSelf && $currentUserId !== 1) {
    $stmt = $db->prepare("SELECT l.id FROM users u LEFT JOIN roles r ON u.role_id = r.id LEFT JOIN levels l ON r.level_id = l.id WHERE u.id = ?");
    $stmt->execute([$id]);
    $targetLevelId = $stmt->fetchColumn();
    $targetRank = getLevelRank($targetLevelId);
    $currentRank = getSessionRank();
    if ($currentRank <= $targetRank) {
        flashMessage('error', 'Você não pode editar usuários de nível igual ou superior ao seu.');
        ob_end_clean(); header('Location: ' . ADMIN_URL . '/users'); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/user-edit?id=' . $id); exit; }

    if ($_POST['action'] === 'save') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if ($username === '') { flashMessage('error', 'Usuário é obrigatório.'); }
        else {
            $existing = dbQueryOne("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $id]);
            if ($existing) { flashMessage('error', 'Este nome de usuário já existe.'); }
            else {
                $changePassword = isset($_POST['change_password']) && $_POST['new_password'] !== '';
                $roleChanged = false;

                if (!$isEditingMaster && $roleId > 0) {
                    $roleCheck = $db->prepare("SELECT r.level_id FROM roles r WHERE r.id = ?");
                    $roleCheck->execute([$roleId]);
                    $targetLevelId = $roleCheck->fetchColumn();
                    if ($targetLevelId && ($currentUserId === 1 || getLevelRank((int)$targetLevelId) < getSessionRank())) {
                        $db->prepare("UPDATE users SET role_id = ? WHERE id = ?")->execute([$roleId, $id]);
                        $roleChanged = true;
                    }
                }

                if ($isEditingMaster) {
                    $db->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?")->execute([$username, $email, $id]);
                    if ($changePassword) {
                        $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $id]);
                    }
                    if ($email !== $user['email']) {
                        $db->prepare("UPDATE users SET email_verified_at = NULL, email_verification_token = NULL WHERE id = ?")->execute([$id]);
                        sendVerificationEmail($id);
                        flashMessage('success', 'Email alterado! Um link de confirmação foi enviado para o novo email.');
                    } elseif ($changePassword) {
                        flashMessage('success', 'Senha alterada com sucesso!');
                    } else {
                        flashMessage('success', 'Dados atualizados!');
                    }
                } else {
                    $db->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?")->execute([$username, $email, $id]);
                    if ($changePassword) {
                        $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $id]);
                    }
                    if (!$isSelf) {
                        $db->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$status, $id]);
                    }
                    if ($email !== $user['email']) {
                        $db->prepare("UPDATE users SET email_verified_at = NULL, email_verification_token = NULL WHERE id = ?")->execute([$id]);
                        sendVerificationEmail($id);
                        flashMessage('success', 'Email alterado! Link de confirmação enviado.');
                    } else {
                        flashMessage('success', 'Usuário atualizado!');
                    }
                }
                ob_end_clean();
                header('Location: ' . ADMIN_URL . '/user-edit?id=' . $id);
                exit;
            }
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/user-edit?id=' . $id);
        exit;
    }

    if ($_POST['action'] === 'resend_verification') {
        if (sendVerificationEmail($id)) {
            flashMessage('success', 'Email de confirmação reenviado!');
        } else {
            flashMessage('error', 'Falha ao enviar email. Verifique as configurações SMTP.');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/user-edit?id=' . $id);
        exit;
    }

    if ($_POST['action'] === 'verify_manually') {
        $db->prepare("UPDATE users SET email_verified_at = CURRENT_TIMESTAMP, email_verification_token = NULL WHERE id = ?")->execute([$id]);
        flashMessage('success', 'Email verificado manualmente com sucesso!');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/user-edit?id=' . $id);
        exit;
    }
}

$user = dbQueryOne("
    SELECT u.*, r.name AS role_name,
           l.name AS level_name, l.slug AS level_slug
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN levels l ON r.level_id = l.id
    WHERE u.id = ?
", [$id]);
$smtpConfigured = isSmtpConfigured();
$userRank = getSessionRank();
$targetLevelId = null;
if ($user['role_id']) {
    $t = $db->prepare("SELECT level_id FROM roles WHERE id = ?");
    $t->execute([$user['role_id']]);
    $targetLevelId = $t->fetchColumn();
}
$canChangeRole = !$isEditingMaster && ($currentUserId === 1 || ($targetLevelId && getLevelRank((int)$targetLevelId) < $userRank));
$assignableRoles = getAssignableRoles($db);
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Editar Usuário</h2>
        <a href="<?= ADMIN_URL ?>/users" class="btn btn-outline btn-sm">← Voltar</a>
    </div>
    <div class="card-body">
        <?php if ($isEditingMaster): ?>
        <div style="margin-bottom: 20px; padding: 12px 16px; background: oklch(75% 0.15 85 / 0.1); border: 1px solid oklch(75% 0.15 85 / 0.3); border-radius: 8px; font-size: 13px;">
            🛡️ <strong>Conta Master</strong> — Esta é a conta principal do sistema. Algumas alterações só podem ser feitas pelo próprio master.
        </div>
        <?php endif; ?>

        <?php if (empty($user['email'])): ?>
        <div style="margin-bottom: 20px; padding: 12px 16px; background: oklch(55% 0.20 25 / 0.1); border: 1px solid oklch(55% 0.20 25 / 0.3); border-radius: 8px; font-size: 13px;">
            ⚠️ Este usuário não possui email cadastrado. Adicione um email para permitir confirmação e recuperação de senha.
        </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="save">
            <?= csrfField() ?>

            <div class="form-row" style="margin-bottom: 0;">
                <div class="form-group">
                    <label for="username">Usuário *</label>
                    <input type="text" id="username" name="username" value="<?= e($user['username']) ?>" required <?= (!$isSelf && $currentUserId !== 1) ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($user['email']) ?>">
                </div>
            </div>

            <div class="form-group" style="margin-top: 12px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="change_password" value="1" onchange="document.getElementById('pw-fields').classList.toggle('hidden')">
                    <span style="font-size:13px;">Alterar senha</span>
                </label>
            </div>
            <div id="pw-fields" class="hidden form-row" style="margin-top: 8px;">
                <div class="form-group">
                    <label for="new_password">Nova senha</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Mínimo 6 caracteres" minlength="6">
                </div>
            </div>

            <?php if ($canChangeRole || ($isSelf && !$isEditingMaster)): ?>
            <div class="form-group" style="margin-top: 12px;">
                <label for="role_id">Cargo</label>
                <select id="role_id" name="role_id" <?= $isEditingMaster ? 'disabled' : '' ?>>
                    <?php if ($isEditingMaster || (!$canChangeRole && $isSelf)): ?>
                        <option value="<?= $user['role_id'] ?>" selected><?= e($user['role_name']) ?> (<?= e($user['level_name'] ?? $user['role_level'] ?? '') ?>)</option>
                    <?php else: ?>
                        <?php foreach ($assignableRoles as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= (int)$r['id'] === (int)$user['role_id'] ? 'selected' : '' ?>><?= e($r['name']) ?> (<?= e($r['level_slug'] ?? $r['level'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <?php else: ?>
            <input type="hidden" name="role_id" value="<?= $user['role_id'] ?>">
            <?php endif; ?>

            <?php if (!$isSelf && !$isEditingMaster): ?>
            <div class="form-group" style="margin-top: 12px;">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Ativo</option>
                    <option value="pending" <?= $user['status'] === 'pending' ? 'selected' : '' ?>>Pendente</option>
                </select>
                <p style="font-size: 12px; color: var(--fg-muted); margin-top: 4px;">Usuários pendentes não podem fazer login.</p>
            </div>
            <?php endif; ?>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="btn btn-gold btn-sm">💾 Salvar</button>
            </div>
        </form>
    </div>
</div>

<style>
.hidden { display: none; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
