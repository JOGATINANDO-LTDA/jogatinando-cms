<?php
/**
 * Cron endpoint para envio de campanhas agendadas.
 *
 * Uso: configurar no servidor (crontab / task scheduler):
 *   curl -s "https://SEU-SITE/cron.php?key=CHAVE"
 *
 * A CHAVE é gerada automaticamente na primeira execução e armazenada em
 * site_settings (cron_key). Ela é exibida uma vez caso esteja vazia.
 */
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

$cronKey = getSetting('cron_key', '');
$provided = $_GET['key'] ?? '';

if ($cronKey === '') {
    // Gera chave na primeira execução
    $cronKey = bin2hex(random_bytes(16));
    setSetting('cron_key', $cronKey);
    echo "Chave de cron gerada: $cronKey\n";
    echo "Configure sua tarefa agendada como:\n";
    echo "  curl -s \"" . SITE_URL . "/cron.php?key=$cronKey\"\n\n";
} elseif (!hash_equals($cronKey, $provided)) {
    http_response_code(403);
    echo "Acesso negado: chave invalida.\n";
    exit;
}

$db = getDB();
if (!$db) {
    echo "Erro: banco de dados indisponivel.\n";
    exit;
}

$sentIds = sendDueCampaigns();

if (empty($sentIds)) {
    echo "Nenhuma campanha agendada pendente.\n";
} else {
    echo count($sentIds) . " campanha(s) enviada(s): " . implode(', ', $sentIds) . "\n";
}
exit;
