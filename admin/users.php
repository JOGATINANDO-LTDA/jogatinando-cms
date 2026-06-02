<?php
ob_start();
$pageTitle = 'Usuários';
$requiredPerm = 'perm_users';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$currentUserId = $_SESSION['admin_user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/users'); exit; }

    if ($_POST['action'] === 'create') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleId = (int)($_POST['role_id'] ?? 0);

        if ($username === '' || $roleId === 0) {
            flashMessage('error', 'Usuário e cargo são obrigatórios.');
        } elseif (strlen($password) < 6) {
            flashMessage('error', 'A senha deve ter no mínimo 6 caracteres.');
        } else {
            $existing = dbQueryOne("SELECT id FROM users WHERE username = ?", [$username]);
            if ($existing) {
                flashMessage('error', 'Este nome de usuário já existe.');
            } else {
                $roleCheck = $db->prepare("SELECT r.level_id FROM roles r WHERE r.id = ?");
                $roleCheck->execute([$roleId]);
                $roleRow = $roleCheck->fetch();

                if (!$roleRow) {
                    flashMessage('error', 'Cargo inválido.');
                } elseif ($currentUserId !== 1 && getLevelRank((int)$roleRow['level_id']) >= getSessionRank()) {
                    flashMessage('error', 'Você não pode criar usuários com cargo de nível igual ou superior ao seu.');
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, status) VALUES (?, ?, ?, ?, 'active')")
                        ->execute([$username, $email, $hash, $roleId]);

                    flashMessage('success', "Usuário '$username' criado com sucesso!");
                    ob_end_clean();
                    header('Location: ' . ADMIN_URL . '/users');
                    exit;
                }
            }
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/users');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        if ($id <= 1) { flashMessage('error', 'O CEO Administrador (ID 1) não pode ser excluído.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/users'); exit; }
        if ($id === $currentUserId) { flashMessage('error', 'Você não pode excluir seu próprio usuário.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/users'); exit; }
        $user = dbQueryOne("SELECT username FROM users WHERE id = ?", [$id]);
        if (!$user) { flashMessage('error', 'Usuário não encontrado.'); ob_end_clean(); header('Location: ' . ADMIN_URL . '/users'); exit; }
        dbDelete('users', $id);
        flashMessage('success', "Usuário '{$user['username']}' excluído.");
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/users');
        exit;
    }
}

$users = $db->query("
    SELECT u.id, u.username, u.email, u.status, u.role_id, u.created_at, u.email_verified_at,
           r.name AS role_name,
           l.name AS level_name, l.slug AS level_slug
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN levels l ON r.level_id = l.id
    ORDER BY u.id ASC
")->fetchAll();

$assignableRoles = getAssignableRoles($db);
$pendingCount = $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending' OR email_verified_at IS NULL")->fetchColumn();
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Usuários do Painel</h2>
        <button class="btn btn-gold btn-sm" onclick="document.getElementById('newUserForm').classList.toggle('hidden')">+ Novo Usuário</button>
    </div>
    <div class="card-body">
        <?php if ($pendingCount > 0): ?>
        <div style="margin-bottom: 16px; padding: 12px 16px; background: oklch(68% 0.16 220 / 0.1); border: 1px solid oklch(68% 0.16 220 / 0.3); border-radius: 8px; color: oklch(68% 0.16 220); font-size: 14px;">
            ⏳ <strong><?= $pendingCount ?></strong> usuário(s) aguardando confirmação de email.
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
                    <option value="<?= $r['id'] ?>"><?= e($r['name']) ?> (<?= e($r['level_slug'] ?? $r['level'] ?? '') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-top: 12px;">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" placeholder="Mínimo 6 caracteres (deixe vazio para enviar convite)" minlength="6">
                <p style="font-size:12px;color:var(--fg-muted);margin-top:4px;">Se deixar vazio, o usuário receberá um convite para definir a própria senha.</p>
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
                            <span class="badge badge-featured"><?= e($u['level_name'] ?? $u['role_name'] ?? '—') ?></span>
                        </td>
                        <td>
                            <?php if ($u['status'] === 'pending'): ?>
                                <span class="badge badge-inactive" style="background:oklch(55% 0.20 25 / 0.15);color:oklch(55% 0.20 25);">Pendente</span>
                            <?php elseif (!$u['email_verified_at']): ?>
                                <span class="badge badge-active" style="background:oklch(75% 0.15 85 / 0.2);color:oklch(75% 0.15 85);">Ativo</span>
                                <span class="badge badge-inactive" style="margin-left:4px;font-size:10px;">Email não verif.</span>
                            <?php else: ?>
                                <span class="badge badge-active">Ativo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $u['created_at'] ? date('d/m/Y H:i', strtotime($u['created_at'])) : '—' ?></td>
                        <td class="actions">
                            <a href="<?= ADMIN_URL ?>/user-edit?id=<?= $u['id'] ?>" class="btn btn-outline btn-sm" title="Editar">✏️ Editar</a>
                            <?php if ($u['id'] !== 1 && (int)$u['id'] !== $currentUserId): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Excluir usuário <?= e($u['username']) ?>?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Excluir">🗑️</button>
                            </form>
                            <?php endif; ?>
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
