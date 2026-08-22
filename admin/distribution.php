<?php
ob_start();
$pageTitle = 'Distribuição';
$requiredPerm = 'perm_games';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Token inválido.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/distribution');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_integration') {
        $id = (int)($_POST['id'] ?? 0);
        $platformId = (int)($_POST['platform_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $integrationType = trim($_POST['integration_type'] ?? 'manual');
        $configJson = trim($_POST['config_json'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if ($name === '') {
            flashMessage('error', 'Nome da integração obrigatório.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/distribution');
            exit;
        }
        if ($platformId <= 0) {
            flashMessage('error', 'Selecione uma plataforma.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/distribution');
            exit;
        }

        if ($id > 0) {
            $db->prepare("UPDATE distribution_integrations SET platform_id = ?, name = ?, integration_type = ?, config_json = ?, active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$platformId, $name, $integrationType, $configJson, $active, $id]);
        } else {
            $db->prepare("INSERT INTO distribution_integrations (platform_id, name, integration_type, config_json, active) VALUES (?, ?, ?, ?, ?)")
                ->execute([$platformId, $name, $integrationType, $configJson, $active]);
        }
        flashMessage('success', 'Integração salva.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/distribution');
        exit;
    }

    if ($action === 'delete_integration') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM distribution_integrations WHERE id = ?")->execute([$id]);
        flashMessage('success', 'Integração removida.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/distribution');
        exit;
    }

    if ($action === 'save_game_link') {
        $id = (int)($_POST['id'] ?? 0);
        $gameId = (int)($_POST['game_id'] ?? 0);
        $integrationId = (int)($_POST['integration_id'] ?? 0) ?: null;
        $platformId = (int)($_POST['platform_id'] ?? 0);
        $storeUrl = trim($_POST['store_url'] ?? '');
        $storePackageId = trim($_POST['store_package_id'] ?? '');
        $storeStatus = trim($_POST['store_status'] ?? 'pending');
        $versionName = trim($_POST['version_name'] ?? '');

        if ($gameId <= 0 || $platformId <= 0) {
            flashMessage('error', 'Jogo e plataforma são obrigatórios.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/distribution');
            exit;
        }

        $allowedStatuses = ['pending', 'published', 'rejected', 'removed', 'draft'];
        if (!in_array($storeStatus, $allowedStatuses, true)) $storeStatus = 'pending';

        if ($id > 0) {
            $db->prepare("UPDATE distribution_game_links SET game_id = ?, integration_id = ?, platform_id = ?, store_url = ?, store_package_id = ?, store_status = ?, version_name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$gameId, $integrationId, $platformId, $storeUrl, $storePackageId, $storeStatus, $versionName, $id]);
        } else {
            $db->prepare("INSERT INTO distribution_game_links (game_id, integration_id, platform_id, store_url, store_package_id, store_status, version_name) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$gameId, $integrationId, $platformId, $storeUrl, $storePackageId, $storeStatus, $versionName]);
        }
        flashMessage('success', 'Link da loja salvo.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/distribution');
        exit;
    }

    if ($action === 'delete_game_link') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM distribution_game_links WHERE id = ?")->execute([$id]);
        flashMessage('success', 'Link removido.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/distribution');
        exit;
    }

    if ($action === 'save_campaign') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $gameId = (int)($_POST['game_id'] ?? 0) ?: null;
        $platformId = (int)($_POST['platform_id'] ?? 0) ?: null;
        $status = trim($_POST['status'] ?? 'draft');
        $budget = (float)($_POST['budget'] ?? 0);
        $startAt = trim($_POST['start_at'] ?? '');
        $endAt = trim($_POST['end_at'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $allowedCampaignStatuses = ['draft', 'active', 'paused', 'finished'];
        if ($name === '') {
            flashMessage('error', 'Nome da campanha obrigatório.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/distribution');
            exit;
        }
        if (!in_array($status, $allowedCampaignStatuses, true)) {
            flashMessage('error', 'Status da campanha inválido.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/distribution');
            exit;
        }
        if ($budget < 0) {
            flashMessage('error', 'Orçamento inválido.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/distribution');
            exit;
        }
        if ($startAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startAt)) {
            flashMessage('error', 'Data de início inválida.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/distribution');
            exit;
        }
        if ($endAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endAt)) {
            flashMessage('error', 'Data final inválida.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/distribution');
            exit;
        }

        if ($id > 0) {
            $stmt = $db->prepare("UPDATE campaigns SET name = ?, game_id = ?, platform_id = ?, status = ?, budget = ?, start_at = ?, end_at = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$name, $gameId, $platformId, $status, $budget, $startAt ?: null, $endAt ?: null, $notes, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO campaigns (name, game_id, platform_id, status, budget, start_at, end_at, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $gameId, $platformId, $status, $budget, $startAt ?: null, $endAt ?: null, $notes]);
        }
        flashMessage('success', 'Campanha salva.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/distribution');
        exit;
    }

    if ($action === 'delete_campaign') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM campaigns WHERE id = ?');
        $stmt->execute([$id]);
        flashMessage('success', 'Campanha removida.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/distribution');
        exit;
    }

    if ($action === 'save_metric') {
        $id = (int)($_POST['id'] ?? 0);
        $campaignId = (int)($_POST['campaign_id'] ?? 0);
        $gameId = (int)($_POST['game_id'] ?? 0);
        $platformId = (int)($_POST['platform_id'] ?? 0);
        $metricKey = trim($_POST['metric_key'] ?? 'views');
        $metricValue = (float)($_POST['metric_value'] ?? 0);
        $periodStart = trim($_POST['period_start'] ?? '');
        $periodEnd = trim($_POST['period_end'] ?? '');
        $source = trim($_POST['source'] ?? 'manual');

        $allowedMetricKeys = ['views', 'clicks', 'installs', 'signups', 'revenue', 'ctr', 'cpi'];
        $allowedSources = ['manual', 'ads', 'store', 'analytics', 'import'];

        if ($campaignId <= 0 && ($gameId <= 0 || $platformId <= 0)) {
            flashMessage('error', 'Selecione uma campanha ou jogo/plataforma válidos.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/distribution');
            exit;
        }
        if (!in_array($metricKey, $allowedMetricKeys, true)) {
            flashMessage('error', 'Métrica inválida.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/distribution');
            exit;
        }
        if (!in_array($source, $allowedSources, true)) {
            flashMessage('error', 'Fonte inválida.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/distribution');
            exit;
        }
        if ($metricValue < 0) {
            flashMessage('error', 'Valor da métrica inválido.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/distribution');
            exit;
        }
        if ($periodStart !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStart)) {
            flashMessage('error', 'Período inicial inválido.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/distribution');
            exit;
        }
        if ($periodEnd !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodEnd)) {
            flashMessage('error', 'Período final inválido.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/distribution');
            exit;
        }

        if ($campaignId > 0) {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE campaign_metrics SET campaign_id = ?, metric_key = ?, metric_value = ?, period_start = ?, period_end = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$campaignId, $metricKey, $metricValue, $periodStart ?: null, $periodEnd ?: null, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO campaign_metrics (campaign_id, metric_key, metric_value, period_start, period_end) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$campaignId, $metricKey, $metricValue, $periodStart ?: null, $periodEnd ?: null]);
            }
        } elseif ($gameId > 0 && $platformId > 0) {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE game_distribution_stats SET game_id = ?, platform_id = ?, metric_key = ?, metric_value = ?, period_start = ?, period_end = ?, source = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$gameId, $platformId, $metricKey, $metricValue, $periodStart ?: null, $periodEnd ?: null, $source, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO game_distribution_stats (game_id, platform_id, metric_key, metric_value, period_start, period_end, source) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$gameId, $platformId, $metricKey, $metricValue, $periodStart ?: null, $periodEnd ?: null, $source]);
            }
        }

        flashMessage('success', 'Métrica salva.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/distribution');
        exit;
    }

    if ($action === 'delete_metric') {
        $id = (int)($_POST['id'] ?? 0);
        $target = $_POST['target'] ?? 'campaign_metric';
        if ($target === 'game_stat') {
            $stmt = $db->prepare('DELETE FROM game_distribution_stats WHERE id = ?');
            $stmt->execute([$id]);
        } else {
            $stmt = $db->prepare('DELETE FROM campaign_metrics WHERE id = ?');
            $stmt->execute([$id]);
        }
        flashMessage('success', 'Métrica removida.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/distribution');
        exit;
    }

    if ($action === 'delete_selected_integrations') {
        $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db->prepare("DELETE FROM distribution_integrations WHERE id IN ($placeholders)")->execute($ids);
            flashMessage('success', count($ids) . ' integração(ões) removida(s).');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/distribution');
        exit;
    }

    if ($action === 'delete_selected_game_links') {
        $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db->prepare("DELETE FROM distribution_game_links WHERE id IN ($placeholders)")->execute($ids);
            flashMessage('success', count($ids) . ' link(s) removido(s).');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/distribution');
        exit;
    }

    if ($action === 'delete_selected_campaigns') {
        $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db->prepare("DELETE FROM campaigns WHERE id IN ($placeholders)")->execute($ids);
            flashMessage('success', count($ids) . ' campanha(s) removida(s).');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/distribution');
        exit;
    }

    if ($action === 'delete_selected_metrics') {
        $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db->prepare("DELETE FROM game_distribution_stats WHERE id IN ($placeholders)")->execute($ids);
            flashMessage('success', count($ids) . ' métrica(s) removida(s).');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/distribution');
        exit;
    }

    if ($action === 'delete_selected_campaign_metrics') {
        $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db->prepare("DELETE FROM campaign_metrics WHERE id IN ($placeholders)")->execute($ids);
            flashMessage('success', count($ids) . ' métrica(s) de campanha removida(s).');
        }
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/distribution');
        exit;
    }
}

$games = dbQuery('SELECT id, title FROM games ORDER BY title ASC');
$platforms = getDistributionPlatforms(false);

$integrationsPager = paginateQueryPrefix('page_integrations', 'SELECT COUNT(*) as c FROM distribution_integrations', 'SELECT i.*, p.name as platform_name, p.slug as platform_slug FROM distribution_integrations i LEFT JOIN platforms p ON p.id = i.platform_id ORDER BY i.id DESC');
$integrations = $integrationsPager['items'];

$gameLinksPager = paginateQueryPrefix('page_game_links', 'SELECT COUNT(*) as c FROM distribution_game_links', 'SELECT gl.*, g.title as game_title, i.name as integration_name, p.name as platform_name, p.slug as platform_slug FROM distribution_game_links gl LEFT JOIN games g ON g.id = gl.game_id LEFT JOIN distribution_integrations i ON i.id = gl.integration_id LEFT JOIN platforms p ON p.id = gl.platform_id ORDER BY gl.id DESC');
$gameLinks = $gameLinksPager['items'];

$syncLogs = dbQuery('SELECT sl.*, i.name as integration_name FROM distribution_sync_logs sl LEFT JOIN distribution_integrations i ON i.id = sl.integration_id ORDER BY sl.id DESC LIMIT 20');

$campaignsPager = paginateQueryPrefix('page_campaigns', 'SELECT COUNT(*) as c FROM campaigns', 'SELECT c.*, g.title as game_title, p.name as platform_name FROM campaigns c LEFT JOIN games g ON g.id = c.game_id LEFT JOIN platforms p ON p.id = c.platform_id ORDER BY c.id DESC');
$campaigns = $campaignsPager['items'];

$metricsPager = paginateQueryPrefix('page_metrics', 'SELECT COUNT(*) as c FROM campaign_metrics', 'SELECT m.*, c.name as campaign_name FROM campaign_metrics m LEFT JOIN campaigns c ON c.id = m.campaign_id ORDER BY m.id DESC');
$metrics = $metricsPager['items'];

$statsPager = paginateQueryPrefix('page_stats', 'SELECT COUNT(*) as c FROM game_distribution_stats', 'SELECT s.*, g.title as game_title, p.name as platform_name FROM game_distribution_stats s LEFT JOIN games g ON g.id = s.game_id LEFT JOIN platforms p ON p.id = s.platform_id ORDER BY s.id DESC');
$stats = $statsPager['items'];
$editIntegrationId = (int)($_GET['edit_integration'] ?? 0);
$editIntegration = $editIntegrationId ? dbQueryOne('SELECT * FROM distribution_integrations WHERE id = ?', [$editIntegrationId]) : null;
$editGameLinkId = (int)($_GET['edit_game_link'] ?? 0);
$editGameLink = $editGameLinkId ? dbQueryOne('SELECT * FROM distribution_game_links WHERE id = ?', [$editGameLinkId]) : null;
$editCampaignId = (int)($_GET['edit_campaign'] ?? 0);
$editCampaign = $editCampaignId ? dbQueryOne('SELECT * FROM campaigns WHERE id = ?', [$editCampaignId]) : null;
$editMetricId = (int)($_GET['edit_metric'] ?? 0);
$editMetric = $editMetricId ? dbQueryOne('SELECT * FROM campaign_metrics WHERE id = ?', [$editMetricId]) : null;
$campaignOptions = $campaigns;
$campaignStatuses = [
    'draft' => 'Rascunho',
    'active' => 'Ativa',
    'paused' => 'Pausada',
    'finished' => 'Finalizada',
];
$metricOptions = [
    'views' => 'Visualizações',
    'clicks' => 'Cliques',
    'installs' => 'Instalações',
    'signups' => 'Cadastros',
    'revenue' => 'Receita',
    'ctr' => 'CTR',
    'cpi' => 'CPI',
];
$sourceOptions = [
    'manual' => 'Manual',
    'ads' => 'Anúncios',
    'store' => 'Loja',
    'analytics' => 'Analytics',
    'import' => 'Importado',
];
$integrationTypeOptions = [
    'manual' => 'Manual',
    'google_play' => 'Google Play Console',
    'steam' => 'Steam',
    'apple_connect' => 'App Store Connect',
    'itch_io' => 'itch.io',
    'other' => 'Outro',
];
$storeStatusOptions = [
    'draft' => 'Rascunho',
    'pending' => 'Pendente',
    'published' => 'Publicado',
    'rejected' => 'Rejeitado',
    'removed' => 'Removido',
];
$summaryCards = [
    ['label' => 'Jogos cadastrados', 'value' => count($games), 'icon' => 'gamepad'],
    ['label' => 'Plataformas', 'value' => count($platforms), 'icon' => 'store'],
    ['label' => 'Campanhas recentes', 'value' => count($campaigns), 'icon' => 'chart-line'],
];
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Distribuição por Jogo</h2>
    </div>
    <div class="card-body">
        <div class="settings-section-subtitle">
            Hub de distribuição para acompanhar campanhas, plataformas e métricas por jogo. A estrutura está pronta para receber integrações de lojas.
        </div>
        <div class="stats-grid distribution-stats">
            <?php foreach ($summaryCards as $card): ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <?php if ($card['icon'] === 'gamepad'): ?>
                        <path d="M6 12h.01M18 12h.01M7.5 16l1.5-2 1.5 2 1.5-2 1.5 2"/><path d="M4 12V9a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3"/><path d="M6 16h12v2a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2v-2Z"/>
                        <?php elseif ($card['icon'] === 'store'): ?>
                        <path d="M3 9l1.5-5h15L21 9"/><path d="M4 9h16v11H4z"/><path d="M9 20v-6h6v6"/>
                        <?php else: ?>
                        <path d="M3 3v18h18"/><path d="M18 17l-6-5 4-4-4 1-3-5"/>
                        <?php endif; ?>
                    </svg>
                </div>
                <div class="stat-info">
                    <div class="stat-number"><?= (int)$card['value'] ?></div>
                    <div class="stat-label"><?= e($card['label']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Integrações com Lojas (<?= (int)$integrationsPager['total'] ?>)</h2>
        <div class="card-actions">
            <a class="btn btn-outline btn-sm" href="?edit_integration=0">+ Nova Integração</a>
        </div>
    </div>
    <?php if ($editIntegration !== null || (isset($_GET['edit_integration']) && $_GET['edit_integration'] === '0')): ?>
    <div class="card-body">
        <form method="POST" class="form-grid">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_integration">
            <input type="hidden" name="id" value="<?= e((string)($editIntegration['id'] ?? 0)) ?>">

            <h3 class="form-section-title">Dados da Integração</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="int_name">Nome</label>
                    <input type="text" id="int_name" name="name" value="<?= e($editIntegration['name'] ?? '') ?>" placeholder="Ex: Google Play Console" required>
                </div>
                <div class="form-group">
                    <label for="int_platform_id">Plataforma</label>
                    <select id="int_platform_id" name="platform_id" required>
                        <option value="0">— selecione —</option>
                        <?php foreach ($platforms as $platform): ?>
                            <option value="<?= (int)$platform['id'] ?>" <?= !empty($editIntegration['platform_id']) && (int)$editIntegration['platform_id'] === (int)$platform['id'] ? 'selected' : '' ?>><?= e($platform['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="int_type">Tipo</label>
                    <select id="int_type" name="integration_type">
                        <?php foreach ($integrationTypeOptions as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= ($editIntegration['integration_type'] ?? 'manual') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="label-checkbox">
                        <input type="checkbox" name="active" value="1" <?= ($editIntegration['active'] ?? 1) ? 'checked' : '' ?>>
                        Ativa
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="int_config">Configuração (JSON)</label>
                <textarea id="int_config" name="config_json" rows="5" placeholder='{"service_account_key": "...", "package_name": "..."}'><?= e($editIntegration['config_json'] ?? '') ?></textarea>
                <div class="field-hint">Dados de autenticação ou configuração específica da integração. Formato JSON.</div>
            </div>

            <div class="form-actions">
                <button class="btn btn-gold" type="submit">Salvar</button>
                <a class="btn btn-outline" href="?">Cancelar</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <form method="POST" id="bulkForm_integrations" class="bulk-form" data-bulk-group="integrations">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete_selected_integrations">
    </form>
    <div class="bulk-bar" id="bulkBar_integrations" data-bulk-group="integrations">
        <span class="bulk-count">0 selecionados</span>
        <button type="button" class="btn btn-danger btn-sm bulk-delete-btn" data-bulk-group="integrations" disabled>Excluir selecionados</button>
    </div>

    <div class="table-wrapper">
        <table>
            <thead><tr><th><input type="checkbox" id="select-all-integrations" class="select-all" data-bulk-group="integrations"></th><th>Nome</th><th>Plataforma</th><th>Tipo</th><th>Ativa</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($integrations as $int): ?>
                <tr>
                    <td><input type="checkbox" class="row-select" data-bulk-group="integrations" value="<?= (int)$int['id'] ?>"></td>
                    <td><?= e($int['name']) ?></td>
                    <td><?= e($int['platform_name'] ?? '—') ?></td>
                    <td><?= e($integrationTypeOptions[$int['integration_type']] ?? $int['integration_type']) ?></td>
                    <td><span class="badge <?= $int['active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $int['active'] ? 'Sim' : 'Não' ?></span></td>
                    <td class="actions">
                        <a class="btn btn-outline btn-sm" href="?edit_integration=<?= (int)$int['id'] ?>">Editar</a>
                        <form method="POST" onsubmit="return confirm('Remover integração?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_integration">
                            <input type="hidden" name="id" value="<?= (int)$int['id'] ?>">
                            <button class="btn btn-outline btn-sm" type="submit">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($integrations)): ?>
                <tr><td colspan="6" class="text-muted">Nenhuma integração configurada.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= renderPaginationPrefix('page_integrations', $integrationsPager['page'], $integrationsPager['pages']) ?>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Links de Jogos nas Lojas (<?= (int)$gameLinksPager['total'] ?>)</h2>
        <div class="card-actions">
            <a class="btn btn-outline btn-sm" href="?edit_game_link=0">+ Novo Link</a>
        </div>
    </div>
    <?php if ($editGameLink !== null || (isset($_GET['edit_game_link']) && $_GET['edit_game_link'] === '0')): ?>
    <div class="card-body">
        <form method="POST" class="form-grid">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_game_link">
            <input type="hidden" name="id" value="<?= e((string)($editGameLink['id'] ?? 0)) ?>">

            <h3 class="form-section-title">Dados do Link</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="gl_game_id">Jogo</label>
                    <select id="gl_game_id" name="game_id" required>
                        <option value="0">— selecione —</option>
                        <?php foreach ($games as $game): ?>
                            <option value="<?= (int)$game['id'] ?>" <?= !empty($editGameLink['game_id']) && (int)$editGameLink['game_id'] === (int)$game['id'] ? 'selected' : '' ?>><?= e($game['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="gl_platform_id">Plataforma</label>
                    <select id="gl_platform_id" name="platform_id" required>
                        <option value="0">— selecione —</option>
                        <?php foreach ($platforms as $platform): ?>
                            <option value="<?= (int)$platform['id'] ?>" <?= !empty($editGameLink['platform_id']) && (int)$editGameLink['platform_id'] === (int)$platform['id'] ? 'selected' : '' ?>><?= e($platform['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="gl_integration_id">Integração</label>
                    <select id="gl_integration_id" name="integration_id">
                        <option value="0">— nenhuma —</option>
                        <?php foreach ($integrations as $int): ?>
                            <option value="<?= (int)$int['id'] ?>" <?= !empty($editGameLink['integration_id']) && (int)$editGameLink['integration_id'] === (int)$int['id'] ? 'selected' : '' ?>><?= e($int['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-hint">Vincule a uma integração para uso futuro com APIs.</div>
                </div>
                <div class="form-group">
                    <label for="gl_status">Status</label>
                    <select id="gl_status" name="store_status">
                        <?php foreach ($storeStatusOptions as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= ($editGameLink['store_status'] ?? 'pending') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="gl_url">URL da Loja</label>
                <input type="url" id="gl_url" name="store_url" value="<?= e($editGameLink['store_url'] ?? '') ?>" placeholder="https://play.google.com/store/apps/details?id=...">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="gl_package">Package ID</label>
                    <input type="text" id="gl_package" name="store_package_id" value="<?= e($editGameLink['store_package_id'] ?? '') ?>" placeholder="com.jogatinando.meujogo">
                </div>
                <div class="form-group">
                    <label for="gl_version">Versão</label>
                    <input type="text" id="gl_version" name="version_name" value="<?= e($editGameLink['version_name'] ?? '') ?>" placeholder="1.0.0">
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-gold" type="submit">Salvar</button>
                <a class="btn btn-outline" href="?">Cancelar</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <form method="POST" id="bulkForm_game_links" class="bulk-form" data-bulk-group="game_links">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete_selected_game_links">
    </form>
    <div class="bulk-bar" id="bulkBar_game_links" data-bulk-group="game_links">
        <span class="bulk-count">0 selecionados</span>
        <button type="button" class="btn btn-danger btn-sm bulk-delete-btn" data-bulk-group="game_links" disabled>Excluir selecionados</button>
    </div>

    <div class="table-wrapper">
        <table>
            <thead><tr><th><input type="checkbox" id="select-all-game-links" class="select-all" data-bulk-group="game_links"></th><th>Jogo</th><th>Plataforma</th><th>Status</th><th>Versão</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($gameLinks as $gl): ?>
                <?php $statusClass = $gl['store_status'] === 'published' ? 'badge-active' : (($gl['store_status'] === 'rejected' || $gl['store_status'] === 'removed') ? 'badge-inactive' : 'badge-featured'); ?>
                <tr>
                    <td><input type="checkbox" class="row-select" data-bulk-group="game_links" value="<?= (int)$gl['id'] ?>"></td>
                    <td><?= e($gl['game_title'] ?? '—') ?></td>
                    <td><?= e($gl['platform_name'] ?? '—') ?></td>
                    <td><span class="badge <?= $statusClass ?>"><?= e($storeStatusOptions[$gl['store_status']] ?? $gl['store_status']) ?></span></td>
                    <td><?= e($gl['version_name'] ?: '—') ?></td>
                    <td class="actions">
                        <a class="btn btn-outline btn-sm" href="?edit_game_link=<?= (int)$gl['id'] ?>">Editar</a>
                        <form method="POST" onsubmit="return confirm('Remover link?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_game_link">
                            <input type="hidden" name="id" value="<?= (int)$gl['id'] ?>">
                            <button class="btn btn-outline btn-sm" type="submit">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($gameLinks)): ?>
                <tr><td colspan="6" class="text-muted">Nenhum link cadastrado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= renderPaginationPrefix('page_game_links', $gameLinksPager['page'], $gameLinksPager['pages']) ?>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Logs de Sincronização</h2></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Integração</th><th>Direção</th><th>Status</th><th>Mensagem</th><th>Data</th></tr></thead>
            <tbody>
            <?php foreach ($syncLogs as $log): ?>
                <?php $logStatusClass = $log['status'] === 'success' ? 'badge-active' : 'badge-inactive'; ?>
                <tr>
                    <td><?= e($log['integration_name'] ?? '—') ?></td>
                    <td><?= e($log['direction']) ?></td>
                    <td><span class="badge <?= $logStatusClass ?>"><?= e($log['status']) ?></span></td>
                    <td><?= e(mb_substr($log['message'] ?? '', 0, 60)) ?></td>
                    <td><?= e($log['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($syncLogs)): ?>
                <tr><td colspan="5" class="text-muted">Nenhum log registrado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Campanha</h2></div>
    <div class="card-body">
        <form method="POST" class="form-grid">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_campaign">
            <input type="hidden" name="id" value="<?= e((string)($editCampaign['id'] ?? 0)) ?>">

            <h3 class="form-section-title">Informações Básicas</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="campaign_name">Nome</label>
                    <input type="text" id="campaign_name" name="name" value="<?= e($editCampaign['name'] ?? '') ?>" placeholder="Ex: Lançamento mobile">
                    <div class="field-hint">Use um nome curto para identificar a campanha.</div>
                </div>
                <div class="form-group">
                    <label for="campaign_status">Status</label>
                    <select id="campaign_status" name="status">
                        <?php foreach ($campaignStatuses as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= ($editCampaign['status'] ?? 'draft') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-hint">Status usado para filtrar campanhas ativas.</div>
                </div>
            </div>

            <h3 class="form-section-title">Vínculos</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="campaign_game_id">Jogo</label>
                    <select id="campaign_game_id" name="game_id">
                        <option value="0">—</option>
                        <?php foreach ($games as $game): ?>
                            <option value="<?= (int)$game['id'] ?>" <?= !empty($editCampaign['game_id']) && (int)$editCampaign['game_id'] === (int)$game['id'] ? 'selected' : '' ?>><?= e($game['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="campaign_platform_id">Plataforma</label>
                    <select id="campaign_platform_id" name="platform_id">
                        <option value="0">—</option>
                        <?php foreach ($platforms as $platform): ?>
                            <option value="<?= (int)$platform['id'] ?>" <?= !empty($editCampaign['platform_id']) && (int)$editCampaign['platform_id'] === (int)$platform['id'] ? 'selected' : '' ?>><?= e($platform['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <h3 class="form-section-title">Período e Orçamento</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="campaign_budget">Orçamento</label>
                    <input type="number" id="campaign_budget" step="0.01" name="budget" value="<?= e((string)($editCampaign['budget'] ?? '0')) ?>">
                    <div class="field-hint">Valor total planejado para a campanha.</div>
                </div>
                <div class="form-group">
                    <label for="campaign_start_at">Início</label>
                    <input type="date" id="campaign_start_at" name="start_at" value="<?= e(substr((string)($editCampaign['start_at'] ?? ''), 0, 10)) ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="campaign_end_at">Fim</label>
                <input type="date" id="campaign_end_at" name="end_at" value="<?= e(substr((string)($editCampaign['end_at'] ?? ''), 0, 10)) ?>">
            </div>

            <h3 class="form-section-title">Observações</h3>
            <div class="form-group">
                <label for="campaign_notes">Notas</label>
                <textarea id="campaign_notes" name="notes" rows="4" placeholder="Objetivo, público, criativos ou observações internas."><?= e($editCampaign['notes'] ?? '') ?></textarea>
            </div>

            <div class="form-actions"><button class="btn btn-gold" type="submit">Salvar campanha</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Campanhas (<?= (int)$campaignsPager['total'] ?>)</h2></div>
    <form method="POST" id="bulkForm_campaigns" class="bulk-form" data-bulk-group="campaigns">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete_selected_campaigns">
    </form>
    <div class="bulk-bar" id="bulkBar_campaigns" data-bulk-group="campaigns">
        <span class="bulk-count">0 selecionados</span>
        <button type="button" class="btn btn-danger btn-sm bulk-delete-btn" data-bulk-group="campaigns" disabled>Excluir selecionados</button>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th><input type="checkbox" id="select-all-campaigns" class="select-all" data-bulk-group="campaigns"></th><th>Nome</th><th>Jogo</th><th>Plataforma</th><th>Status</th><th>Orçamento</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($campaigns as $campaign): ?>
                <?php $statusClass = $campaign['status'] === 'active' ? 'badge-active' : (($campaign['status'] === 'paused' || $campaign['status'] === 'finished') ? 'badge-inactive' : 'badge-featured'); ?>
                <tr>
                    <td><input type="checkbox" class="row-select" data-bulk-group="campaigns" value="<?= (int)$campaign['id'] ?>"></td>
                    <td><?= e($campaign['name']) ?></td>
                    <td><?= e($campaign['game_title'] ?? '—') ?></td>
                    <td><?= e($campaign['platform_name'] ?? '—') ?></td>
                    <td><span class="badge <?= $statusClass ?>"><?= e($campaignStatuses[$campaign['status']] ?? $campaign['status']) ?></span></td>
                    <td><?= e((string)$campaign['budget']) ?></td>
                    <td class="actions">
                        <a class="btn btn-outline btn-sm" href="?edit_campaign=<?= (int)$campaign['id'] ?>">Editar</a>
                        <form method="POST" onsubmit="return confirm('Remover campanha?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_campaign">
                            <input type="hidden" name="id" value="<?= (int)$campaign['id'] ?>">
                            <button class="btn btn-outline btn-sm" type="submit">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($campaigns)): ?>
                <tr><td colspan="7" class="text-muted">Nenhuma campanha cadastrada.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= renderPaginationPrefix('page_campaigns', $campaignsPager['page'], $campaignsPager['pages']) ?>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Métrica de Campanha</h2></div>
    <div class="card-body">
        <form method="POST" class="form-grid">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_metric">
            <input type="hidden" name="id" value="<?= e((string)($editMetric['id'] ?? 0)) ?>">

            <h3 class="form-section-title">Origem</h3>
            <div class="form-group">
                <label for="metric_campaign_id">Campanha</label>
                <select id="metric_campaign_id" name="campaign_id" required>
                    <option value="0">— selecione —</option>
                    <?php foreach ($campaignOptions as $campaign): ?>
                        <option value="<?= (int)$campaign['id'] ?>" <?= !empty($editMetric['campaign_id']) && (int)$editMetric['campaign_id'] === (int)$campaign['id'] ? 'selected' : '' ?>><?= e($campaign['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="field-hint">Vincule métricas de campanha aqui. Métricas por jogo/plataforma podem ser importadas futuramente de lojas.</div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="metric_game_id">Jogo</label>
                    <select id="metric_game_id" name="game_id">
                        <option value="0">—</option>
                        <?php foreach ($games as $game): ?>
                            <option value="<?= (int)$game['id'] ?>" <?= !empty($editMetric['game_id']) && (int)$editMetric['game_id'] === (int)$game['id'] ? 'selected' : '' ?>><?= e($game['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="metric_platform_id">Plataforma</label>
                    <select id="metric_platform_id" name="platform_id">
                        <option value="0">—</option>
                        <?php foreach ($platforms as $platform): ?>
                            <option value="<?= (int)$platform['id'] ?>" <?= !empty($editMetric['platform_id']) && (int)$editMetric['platform_id'] === (int)$platform['id'] ? 'selected' : '' ?>><?= e($platform['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <h3 class="form-section-title">Métrica</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="metric_key">Tipo de métrica</label>
                    <select id="metric_key" name="metric_key">
                        <?php foreach ($metricOptions as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= ($editMetric['metric_key'] ?? 'views') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-hint">Escolha o tipo padronizado em vez de digitar manualmente.</div>
                </div>
                <div class="form-group">
                    <label for="metric_value">Valor</label>
                    <input type="number" id="metric_value" step="0.01" name="metric_value" value="<?= e((string)($editMetric['metric_value'] ?? '0')) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="metric_period_start">Período início</label>
                    <input type="date" id="metric_period_start" name="period_start" value="<?= e(substr((string)($editMetric['period_start'] ?? ''), 0, 10)) ?>">
                </div>
                <div class="form-group">
                    <label for="metric_period_end">Período fim</label>
                    <input type="date" id="metric_period_end" name="period_end" value="<?= e(substr((string)($editMetric['period_end'] ?? ''), 0, 10)) ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="metric_source">Fonte</label>
                <select id="metric_source" name="source">
                    <?php foreach ($sourceOptions as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($editMetric['source'] ?? 'manual') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="field-hint">A fonte ajuda a separar dados manuais, anúncios, lojas e imports.</div>
            </div>

            <div class="form-actions"><button class="btn btn-gold" type="submit">Salvar métrica</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Métricas Recentes (<?= (int)$statsPager['total'] ?>)</h2></div>
    <form method="POST" id="bulkForm_metrics" class="bulk-form" data-bulk-group="metrics">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete_selected_metrics">
    </form>
    <div class="bulk-bar" id="bulkBar_metrics" data-bulk-group="metrics">
        <span class="bulk-count">0 selecionados</span>
        <button type="button" class="btn btn-danger btn-sm bulk-delete-btn" data-bulk-group="metrics" disabled>Excluir selecionados</button>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th><input type="checkbox" id="select-all-metrics" class="select-all" data-bulk-group="metrics"></th><th>Jogo</th><th>Plataforma</th><th>Métrica</th><th>Valor</th><th>Fonte</th></tr></thead>
            <tbody>
            <?php foreach ($stats as $row): ?>
                <tr>
                    <td><input type="checkbox" class="row-select" data-bulk-group="metrics" value="<?= (int)$row['id'] ?>"></td>
                    <td><?= e($row['game_title'] ?? '—') ?></td>
                    <td><?= e($row['platform_name'] ?? '—') ?></td>
                    <td><?= e($metricOptions[$row['metric_key']] ?? $row['metric_key']) ?></td>
                    <td><?= e((string)$row['metric_value']) ?></td>
                    <td><?= e($sourceOptions[$row['source']] ?? $row['source']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($stats)): ?>
                <tr><td colspan="6" class="text-muted">Nenhuma métrica cadastrada.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= renderPaginationPrefix('page_stats', $statsPager['page'], $statsPager['pages']) ?>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Métricas de Campanha (<?= (int)$metricsPager['total'] ?>)</h2></div>
    <form method="POST" id="bulkForm_campaign_metrics" class="bulk-form" data-bulk-group="campaign_metrics">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete_selected_campaign_metrics">
    </form>
    <div class="bulk-bar" id="bulkBar_campaign_metrics" data-bulk-group="campaign_metrics">
        <span class="bulk-count">0 selecionados</span>
        <button type="button" class="btn btn-danger btn-sm bulk-delete-btn" data-bulk-group="campaign_metrics" disabled>Excluir selecionados</button>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th><input type="checkbox" id="select-all-campaign-metrics" class="select-all" data-bulk-group="campaign_metrics"></th><th>Campanha</th><th>Métrica</th><th>Valor</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($metrics as $metric): ?>
                <tr>
                    <td><input type="checkbox" class="row-select" data-bulk-group="campaign_metrics" value="<?= (int)$metric['id'] ?>"></td>
                    <td><?= e($metric['campaign_name'] ?? '—') ?></td>
                    <td><?= e($metricOptions[$metric['metric_key']] ?? $metric['metric_key']) ?></td>
                    <td><?= e((string)$metric['metric_value']) ?></td>
                    <td class="actions">
                        <a class="btn btn-outline btn-sm" href="?edit_metric=<?= (int)$metric['id'] ?>">Editar</a>
                        <form method="POST" onsubmit="return confirm('Remover métrica?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_metric">
                            <input type="hidden" name="target" value="campaign_metric">
                            <input type="hidden" name="id" value="<?= (int)$metric['id'] ?>">
                            <button class="btn btn-outline btn-sm" type="submit">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($metrics)): ?>
                <tr><td colspan="5" class="text-muted">Nenhuma métrica de campanha cadastrada.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= renderPaginationPrefix('page_campaign_metrics', $metricsPager['page'], $metricsPager['pages']) ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
