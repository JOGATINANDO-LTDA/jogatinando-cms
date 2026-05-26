<?php
ob_start();
$pageTitle = 'Usuários';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: users'); exit; }

    if ($_POST['action'] === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($username === '' || $password === '') {
            flashMessage('error', 'Usuário e senha são obrigatórios.');
        } elseif (strlen($password) < 6) {
            flashMessage('error', 'A senha deve ter no mínimo 6 caracteres.');
        } else {
            $existing = dbQueryOne("SELECT id FROM users WHERE username = ?", [$username]);
            if ($existing) {
                flashMessage('error', 'Este nome de usuário já existe.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                dbExec("INSERT INTO users (username, password_hash) VALUES (?, ?)", [$username, $hash]);
                flashMessage('success', "Usuário '$username' criado com sucesso!");
            }
        }
        ob_end_clean();
        header('Location: users');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        if ($id <= 1) { flashMessage('error', 'O usuário master (ID 1) não pode ser excluído.'); ob_end_clean(); header('Location: users'); exit; }
        if ($id === 1) { flashMessage('error', 'Você não pode excluir seu próprio usuário.'); ob_end_clean(); header('Location: users'); exit; }
        $user = dbQueryOne("SELECT username FROM users WHERE id = ?", [$id]);
        if (!$user) { flashMessage('error', 'Usuário não encontrado.'); ob_end_clean(); header('Location: users'); exit; }
        dbDelete('users', $id);
        flashMessage('success', "Usuário '{$user['username']}' excluído.");
        ob_end_clean();
        header('Location: users');
        exit;
    }
}

$users = dbQuery("SELECT id, username, created_at FROM users ORDER BY id ASC");
$currentUser = $_SESSION['admin_username'] ?? '';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Usuários do Painel</h2>
        <button class="btn btn-gold btn-sm" onclick="document.getElementById('newUserForm').classList.toggle('hidden')">+ Novo Usuário</button>
    </div>
    <div class="card-body">
        <form id="newUserForm" method="POST" class="hidden" style="margin-bottom: 24px; padding: 16px; background: oklch(16% 0.035 265); border: 1px solid var(--border); border-radius: 8px;">
            <input type="hidden" name="action" value="create">
            <?= csrfField() ?>
            <div class="form-row" style="margin-bottom: 0;">
                <div class="form-group">
                    <label for="username">Novo Usuário *</label>
                    <input type="text" id="username" name="username" required placeholder="nome-de-usuario">
                </div>
                <div class="form-group">
                    <label for="password">Senha *</label>
                    <input type="password" id="password" name="password" required placeholder="Mínimo 6 caracteres" minlength="6">
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
                    <tr><th>ID</th><th>Usuário</th><th>Criado em</th><th>Ações</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><strong style="color:var(--fg)"><?= e($u['username']) ?></strong>
                            <?php if ($u['id'] === 1): ?><span class="badge badge-active" style="margin-left: 8px;">Master</span><?php endif; ?>
                            <?php if ($u['username'] === $currentUser): ?><span class="badge badge-active" style="margin-left: 4px; background: oklch(68% 0.16 220 / 0.15); color: oklch(68% 0.16 220);">Atual</span><?php endif; ?>
                        </td>
                        <td><?= $u['created_at'] ? date('d/m/Y H:i', strtotime($u['created_at'])) : '—' ?></td>
                        <td class="actions">
                            <form method="POST" style="display:inline" onsubmit="return confirm('Excluir usuário <?= e($u['username']) ?>?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Excluir" <?= $u['id'] === 1 ? 'disabled' : '' ?>>🗑️</button>
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
