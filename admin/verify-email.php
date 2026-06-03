<?php
require_once __DIR__ . '/../config.php';

$token = $_GET['token'] ?? '';
if ($token === '') {
    http_response_code(400);
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Link inválido</title><link rel="icon" href="../assets/svg/logo.svg" type="image/svg+xml">';
    echo '<style>body{font-family:sans-serif;background:#111;color:#eee;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}';
    echo '.card{background:#1a1a2e;border:1px solid #c9a84c;border-radius:12px;padding:40px;max-width:400px;text-align:center}';
    echo 'h1{color:#c9a84c;font-family:Georgia,serif}</style></head><body>';
    echo '<div class="card"><h1>Link inválido</h1><p>Token de verificação não informado.</p></div></body></html>';
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT id, username, email FROM users WHERE email_verification_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Token inválido</title><link rel="icon" href="../assets/svg/logo.svg" type="image/svg+xml">';
    echo '<style>body{font-family:sans-serif;background:#111;color:#eee;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}';
    echo '.card{background:#1a1a2e;border:1px solid #c9a84c;border-radius:12px;padding:40px;max-width:400px;text-align:center}';
    echo 'h1{color:#c9a84c;font-family:Georgia,serif;margin-bottom:12px}</style></head><body>';
    echo '<div class="card"><h1>Token inválido</h1><p>Este link de verificação é inválido ou já expirou.</p></div></body></html>';
    exit;
}

$stmt = $db->prepare("UPDATE users SET email_verified_at = CURRENT_TIMESTAMP, email_verification_token = NULL, status = 'active' WHERE id = ? AND email_verification_token = ?");
$stmt->execute([$user['id'], $token]);

if ($stmt->rowCount() > 0) {
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Email confirmado!</title><link rel="icon" href="../assets/svg/logo.svg" type="image/svg+xml">';
    echo '<style>body{font-family:sans-serif;background:#111;color:#eee;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}';
    echo '.card{background:#1a1a2e;border:1px solid #c9a84c;border-radius:12px;padding:40px;max-width:400px;text-align:center}';
    echo 'h1{color:#c9a84c;font-family:Georgia,serif;margin-bottom:12px}';
    echo 'p{color:#999;line-height:1.6}</style></head><body>';
    echo '<div class="card"><h1>Email confirmado!</h1>';
    echo '<p>O email <strong>' . e($user['email']) . '</strong> foi verificado com sucesso para o usuário <strong>' . e($user['username']) . '</strong>.</p>';
    echo '<p><a href="' . ADMIN_URL . '/login" style="color:#c9a84c;">Fazer login →</a></p>';
    echo '</div></body></html>';
} else {
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Erro</title><link rel="icon" href="../assets/svg/logo.svg" type="image/svg+xml">';
    echo '<style>body{font-family:sans-serif;background:#111;color:#eee;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}';
    echo '.card{background:#1a1a2e;border:1px solid #c9a84c;border-radius:12px;padding:40px;max-width:400px;text-align:center}';
    echo 'h1{color:#c9a84c;font-family:Georgia,serif}</style></head><body>';
    echo '<div class="card"><h1>Ops!</h1><p>Não foi possível verificar o email. O link pode ter expirado.</p></div></body></html>';
}
