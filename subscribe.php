<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$name = trim($_POST['name'] ?? '');

if ($email === '') {
    echo json_encode(['error' => 'E-mail é obrigatório.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'E-mail inválido.']);
    exit;
}

$db = getDB();
if (!$db) {
    echo json_encode(['error' => 'Sistema não configurado.']);
    exit;
}

// Check if already subscribed
$existing = dbQueryOne('SELECT id, is_active FROM newsletter_subscribers WHERE email = ?', [$email]);
if ($existing) {
    if ($existing['is_active']) {
        echo json_encode(['error' => 'Você já está inscrito nesta newsletter.', 'subscribed' => true]);
    } else {
        // Reactivate
        $db->prepare("UPDATE newsletter_subscribers SET is_active = 1, name = ? WHERE id = ?")->execute([$name, $existing['id']]);
        echo json_encode(['success' => 'Inscrição reativada com sucesso!']);
    }
    exit;
}

// New subscription
$token = bin2hex(random_bytes(32));
$db->prepare("INSERT INTO newsletter_subscribers (email, name, source, unsubscribe_token) VALUES (?, ?, 'site', ?)")->execute([$email, $name, $token]);

echo json_encode(['success' => 'Obrigado! Confira seu e-mail para confirmar a inscrição.']);
