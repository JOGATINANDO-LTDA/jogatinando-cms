<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$validToken = false;
$username = '';

if ($token) {
    $db = getDB();
    if ($db) {
        $stmt = $db->prepare("SELECT id, username, setup_token_expires, status FROM users WHERE setup_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['status'] === 'pending') {
            if ($user['setup_token_expires'] && strtotime($user['setup_token_expires']) < time()) {
                $message = 'Este link de ativação expirou. Solicite um novo convite ao administrador.';
            } else {
                $validToken = true;
                $username = $user['username'];
            }
        } else {
            $message = 'Token inválido ou já utilizado.';
        }
    } else {
        $message = 'Erro de conexão com o banco de dados.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken && isset($_POST['password'])) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 6) {
        $message = 'A senha deve ter no mínimo 6 caracteres.';
    } elseif ($password !== $confirm) {
        $message = 'As senhas não conferem.';
    } else {
        $db = getDB();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ?, setup_token = NULL, setup_token_expires = NULL, status = 'active' WHERE setup_token = ?");
        $stmt->execute([$hash, $token]);

        $_SESSION = [];
        session_destroy();

        $message = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ativar Conta — CMS de Jogos</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: oklch(10% 0.03 260); color: oklch(96% 0.003 250); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: oklch(16% 0.035 265); border: 1px solid oklch(55% 0.12 85); border-radius: 12px; padding: 40px; max-width: 440px; width: 100%; }
        .card h1 { font-family: 'Cinzel', Georgia, serif; font-size: 22px; color: oklch(75% 0.15 85); margin-bottom: 8px; text-align: center; letter-spacing: 0.04em; }
        .card p { color: oklch(60% 0.012 250); margin-bottom: 24px; text-align: center; line-height: 1.6; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; color: oklch(70% 0.02 250); margin-bottom: 4px; font-size: 13px; }
        .form-group input { width: 100%; padding: 10px 12px; border: 1px solid oklch(30% 0.03 260); border-radius: 6px; background: oklch(12% 0.02 260); color: oklch(96% 0.003 250); font-size: 14px; }
        .form-group input:focus { outline: none; border-color: oklch(75% 0.15 85); }
        .btn { display: block; width: 100%; padding: 14px; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; }
        .btn-gold { background: linear-gradient(135deg, oklch(75% 0.15 85), oklch(62% 0.13 85)); color: oklch(8% 0.02 260); }
        .btn-gold:hover { background: linear-gradient(135deg, oklch(85% 0.13 85), oklch(75% 0.15 85)); }
        .btn-outline { background: transparent; border: 1px solid oklch(55% 0.12 85); color: oklch(75% 0.15 85); margin-top: 12px; }
        .status { padding: 16px; border-radius: 8px; margin-bottom: 24px; text-align: center; }
        .status.success { background: oklch(65% 0.18 145 / 0.15); border: 1px solid oklch(65% 0.18 145); color: oklch(65% 0.18 145); }
        .status.error { background: oklch(55% 0.20 25 / 0.15); border: 1px solid oklch(55% 0.20 25); color: oklch(55% 0.20 25); }
    </style>
</head>
<body>
    <div class="card">
        <h1>CMS de Jogos</h1>

        <?php if ($message === 'success'): ?>
            <div class="status success">Conta ativada com sucesso!</div>
            <p>Sua senha foi definida. Você já pode acessar o painel administrativo.</p>
            <a href="/admin/login" class="btn btn-gold">Acessar Painel</a>

        <?php elseif ($validToken): ?>
            <p>Olá, <strong style="color:var(--fg)"><?= e($username) ?></strong>! Defina sua senha para ativar sua conta.</p>

            <?php if ($message): ?>
            <div class="status error"><?= e($message) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="form-group">
                    <label for="password">Nova Senha *</label>
                    <input type="password" id="password" name="password" required minlength="6" placeholder="Mínimo 6 caracteres" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirmar Senha *</label>
                    <input type="password" id="password_confirm" name="password_confirm" required minlength="6" placeholder="Repita a senha" autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-gold">Ativar Conta</button>
            </form>

        <?php else: ?>
            <?php if ($message): ?>
            <div class="status error"><?= e($message) ?></div>
            <?php else: ?>
            <p>Token de ativação não informado.</p>
            <?php endif; ?>
            <a href="/admin/login" class="btn btn-outline">Ir para Login</a>
        <?php endif; ?>
    </div>
</body>
</html>
