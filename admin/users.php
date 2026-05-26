<?php
ob_start();
$pageTitle = 'Usuários';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$currentUserId = $_SESSION['admin_user_id'] ?? 0;
$userLevel = $_SESSION['admin_role_level'] ?? 'moderator';
$isMasterCeo = ($currentUserId === 1 && $userLevel === 'ceo');

requireRole('chief');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: users'); exit; }

    if ($_POST['action'] === 'create') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleId = (int)($_POST['role_id'] ?? 0);
        $sendInvite = isset($_POST['send_invite']);

        if ($username === '' || $roleId === 0) {
            flashMessage('error', 'Usuário e cargo são obrigatórios.');
        } elseif (!$sendInvite && $password === '') {
            flashMessage('error', 'Defina uma senha ou marque "Enviar convite por email".');
        } elseif (!$sendInvite && strlen($password) < 6) {
            flashMessage('error', 'A senha deve ter no mínimo 6 caracteres.');
        } else {
            $existing = dbQueryOne("SELECT id FROM users WHERE username = ?", [$username]);
            if ($existing) {
                flashMessage('error', 'Este nome de usuário já existe.');
            } else {
                $roleCheck = $db->prepare("SELECT level FROM roles WHERE id = ?");
                $roleCheck->execute([$roleId]);
                $roleLevel = $roleCheck->fetchColumn();

                if (!$roleLevel) {
                    flashMessage('error', 'Cargo inválido.');
                } elseif (!$isMasterCeo && $roleLevel === 'ceo') {
                    flashMessage('error', 'Apenas o CEO Administrador pode criar usuários com cargo CEO.');
                } elseif (!$isMasterCeo && getRoleLevelRank($roleLevel) >= getRoleLevelRank($userLevel)) {
                    flashMessage('error', 'Você não pode criar usuários com cargo de nível igual ou superior ao seu.');
                } else {
                    if ($sendInvite) {
                        $hash = null;
                        $status = 'pending';
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
                        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role_id, status, setup_token, setup_token_expires) VALUES (?, ?, NULL, ?, ?, ?, ?)");
                        $stmt->execute([$username, $email, $roleId, $status, $token, $expires]);

                        $setupLink = SITE_URL . '/admin/setup-password?token=' . $token;
                        flashMessage('success', "Usuário '$username' criado! Link de ativação: $setupLink");
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $status = 'active';
                        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role_id, status) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$username, $email, $hash, $roleId, $status]);
                        flashMessage('success', "Usuário '$username' criado com sucesso!");
                    }
                }
            }
        }
        ob_end_clean();
        header('Location: users');
        exit;
    }

    if ($_POST['action'] === 'update_role') {
        $id = (int)$_POST['id'];
        $roleId = (int)($_POST['role_id'] ?? 0);

        if ($id === 1) {
            flashMessage('error', 'O cargo do CEO Administrador não pode ser alterado.');
        } elseif ($id === $currentUserId) {
            flashMessage('error', 'Você não pode alterar seu próprio cargo.');
        } else {
            $roleCheck = $db->prepare("SELECT level FROM roles WHERE id = ?");
            $roleCheck->execute([$roleId]);
            $roleLevel = $roleCheck->fetchColumn();

            if (!$roleLevel) {
                flashMessage('error', 'Cargo inválido.');
            } elseif (!$isMasterCeo && $roleLevel === 'ceo') {
                flashMessage('error', 'Apenas o CEO Administrador pode atribuir cargos CEO.');
            } else {
                $stmt = $db->prepare("UPDATE users SET role_id = ? WHERE id = ?");
                $stmt->execute([$roleId, $id]);
                flashMessage('success', 'Cargo do usuário atualizado!');
            }
        }
        ob_end_clean();
        header('Location: users');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        if ($id <= 1) { flashMessage('error', 'O CEO Administrador (ID 1) não pode ser excluído.'); ob_end_clean(); header('Location: users'); exit; }
        if ($id === $currentUserId) { flashMessage('error', 'Você não pode excluir seu próprio usuário.'); ob_end_clean(); header('Location: users'); exit; }
        $user = dbQueryOne("SELECT username FROM users WHERE id = ?", [$id]);
        if (!$user) { flashMessage('error', 'Usuário não encontrado.'); ob_end_clean(); header('Location: users'); exit; }
        dbDelete('users', $id);
        flashMessage('success', "Usuário '{$user['username']}' excluído.");
        ob_end_clean();
        header('Location: users');
        exit;
    }
}

$users = $db->query("
    SELECT u.id, u.username, u.email, u.status, u.role_id, u.created_at,
           r.name AS role_name, r.level AS role_level
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    ORDER BY u.id ASC
")->fetchAll();

$assignableRoles = getAssignableRoles($db);
$pendingCount = $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Usuários do Painel</h2>
        <button class="btn btn-gold btn-sm" onclick="document.getElementById('newUserForm').classList.toggle('hidden')">+ Novo Usuário</button>
    </div>
    <div class="card-body">
        <?php if ($pendingCount > 0): ?>
        <div style="margin-bottom: 16px; padding: 12px 16px; background: oklch(68% 0.16 220 / 0.1); border: 1px solid oklch(68% 0.16 220 / 0.3); border-radius: 8px; color: oklch(68% 0.16 220); font-size: 14px;">
            ⏳ <strong><?= $pendingCount ?></strong> usuário(s) pendente(s) de ativação por email.
        </div>
        <?php endif; ?>

        <form id="newUserForm" method="POST" class="hidden" style="margin-bottom: 24px; padding: 16px; background: oklch(16% 0.035 265); border: 1px solid var(--border); border-radius: 8px;">
            <input type="hidden" name="action" value="create">
            <?= csrfField() ?>
            <div class="form-row" style="margin-bottom: 0;">
                <div class="form-group">
                    <label for="username">Usuário *</label>
                    <input type="text" id="username" name="username" required placeholder="nome-de-usuario">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="usuario@exemplo.com">
                </div>
            </div>
            <div class="form-group" style="margin-top: 12px;">
                <label for="role_id">Cargo *</label>
                <select id="role_id" name="role_id" required>
                    <option value="">Selecione um cargo...</option>
                    <?php foreach ($assignableRoles as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= e($r['name']) ?> (<?= $r['level'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row" style="margin-top: 12px;">
                <div class="form-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" placeholder="Mínimo 6 caracteres" minlength="6">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:10px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="send_invite" value="1" checked>
                        <span style="font-size:13px;">Enviar convite por email (usuário define senha)</span>
                    </label>
                </div>
            </div>
            <div class="form-actions" style="margin-top: 12px;">
                <button type="submit" class="btn btn-gold btn-sm">Criar</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="this.closest('form').classList.add('hidden')">Cancelar</button>
            </div>
        </form>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>ID</th><th>Usuário</th><th>Email</th><th>Cargo</th><th>Status</th><th>Criado em</th><th>Ações</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><strong style="color:var(--fg)"><?= e($u['username']) ?></strong>
                            <?php if ($u['id'] === 1): ?><span class="badge badge-featured" style="margin-left: 8px;">Master</span><?php endif; ?>
                            <?php if ((int)$u['id'] === $currentUserId): ?><span class="badge badge-active" style="margin-left: 4px; background: oklch(68% 0.16 220 / 0.15); color: oklch(68% 0.16 220);">Atual</span><?php endif; ?>
                        </td>
                        <td style="color:var(--fg-muted);font-size:13px;"><?= e($u['email'] ?? '—') ?></td>
                        <td>
                            <?php if ($u['role_level'] === 'ceo'): ?>
                                <span class="badge badge-featured"><?= e($u['role_name'] ?? 'CEO') ?></span>
                            <?php elseif ($u['role_level'] === 'chief'): ?>
                                <span class="badge badge-active"><?= e($u['role_name'] ?? 'Chief') ?></span>
                            <?php else: ?>
                                <span class="badge badge-inactive"><?= e($u['role_name'] ?? 'Moderator') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['status'] === 'pending'): ?>
                                <span class="badge badge-inactive" style="background:oklch(55% 0.20 25 / 0.15);color:oklch(55% 0.20 25);">Pendente</span>
                            <?php else: ?>
                                <span class="badge badge-active">Ativo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $u['created_at'] ? date('d/m/Y H:i', strtotime($u['created_at'])) : '—' ?></td>
                        <td class="actions">
                            <?php if ($u['id'] !== 1): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Alterar cargo de <?= e($u['username']) ?>?')">
                                <input type="hidden" name="action" value="update_role">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <?= csrfField() ?>
                                <select name="role_id" onchange="this.form.submit()" style="padding:4px 8px;border:1px solid var(--border);border-radius:4px;background:oklch(12% 0.02 260);color:var(--fg);font-size:12px;cursor:pointer;">
                                    <?php foreach ($assignableRoles as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= (int)$r['id'] === (int)$u['role_id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Excluir usuário <?= e($u['username']) ?>?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Excluir" <?= $u['id'] === 1 || (int)$u['id'] === $currentUserId ? 'disabled' : '' ?>>🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.hidden { display: none; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
