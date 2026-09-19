<?php
/**
 * Newsletter helpers — campaign body composition + send logic.
 */

if (!function_exists('buildCampaignBody')) {
    /**
     * Substitui placeholders {name} e {unsubscribe_url} e garante rodapé de
     * descadastro (LGPD-friendly) quando o template não o inclui.
     */
    function buildCampaignBody($content, $name, $unsubUrl) {
        $body = str_replace(
            ['{name}', '{unsubscribe_url}'],
            [$name ?: '', $unsubUrl],
            $content
        );
        if (strpos($body, '/unsubscribe') === false) {
            $body .= '<hr style="margin-top:32px;border:none;border-top:1px solid #ddd;">'
                . '<p style="font-size:12px;color:#888;">Você recebe este e-mail porque se inscreveu na nossa newsletter. '
                . '<a href="' . e($unsubUrl) . '" style="color:#888;">Descadastrar</a></p>';
        }
        return $body;
    }
}

if (!function_exists('sendCampaignToSubscriber')) {
    /**
     * Envia a campanha para um inscrito ativo. Retorna bool (sucesso do mail()).
     */
    function sendCampaignToSubscriber($campaign, $sub) {
        $unsubUrl = SITE_URL . '/unsubscribe?token=' . urlencode($sub['unsubscribe_token']);
        $body = buildCampaignBody($campaign['content'], $sub['name'], $unsubUrl);
        $headers = "From: " . ($campaign['sender_name'] ?: 'CMS') . " <" . ($campaign['sender_email'] ?: 'admin@localhost') . ">\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        return mail($sub['email'], $campaign['subject'], $body, $headers);
    }
}

if (!function_exists('sendCampaign')) {
    /**
     * Dispara a campanha para todos os inscritos ativos.
     * Retorna ['sent' => int, 'total' => int].
     */
    function sendCampaign($campaignId) {
        $campaignId = (int)$campaignId;
        $db = getDB();
        if (!$db) return ['sent' => 0, 'total' => 0];

        $campaign = $db->query("SELECT * FROM newsletter_campaigns WHERE id = $campaignId")->fetch();
        if (!$campaign) return ['sent' => 0, 'total' => 0];

        $subs = $db->query("SELECT id, email, name, unsubscribe_token FROM newsletter_subscribers WHERE is_active = 1 ORDER BY id ASC");
        $sent = 0;
        $total = 0;
        while ($sub = $subs->fetch()) {
            $total++;
            $ok = sendCampaignToSubscriber($campaign, $sub);
            if ($ok) $sent++;
            $db->prepare("INSERT INTO newsletter_campaign_recipients (campaign_id, subscriber_id, status, sent_at) VALUES (?, ?, ?, ?)")
                ->execute([$campaignId, $sub['id'], $ok ? 'sent' : 'failed', date('Y-m-d H:i:s')]);
        }

        $db->prepare("UPDATE newsletter_campaigns SET status = 'sent', sent_count = ?, total_recipients = ?, sent_at = ? WHERE id = ?")
            ->execute([$sent, $total, date('Y-m-d H:i:s'), $campaignId]);

        return ['sent' => $sent, 'total' => $total];
    }
}

if (!function_exists('sendDueCampaigns')) {
    /**
     * Envia campanhas agendadas cujo scheduled_at já chegou. Usado pelo cron.php.
     * Retorna array de IDs enviados.
     */
    function sendDueCampaigns() {
        $db = getDB();
        if (!$db) return [];
        $now = date('Y-m-d H:i:s');
        $due = $db->query("SELECT id FROM newsletter_campaigns WHERE status = 'scheduled' AND scheduled_at <= '$now' ORDER BY scheduled_at ASC")->fetchAll();
        $sentIds = [];
        foreach ($due as $c) {
            sendCampaign((int)$c['id']);
            $sentIds[] = (int)$c['id'];
        }
        return $sentIds;
    }
}
