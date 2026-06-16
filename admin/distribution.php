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
}

$games = dbQuery('SELECT id, title FROM games ORDER BY title ASC');
$platforms = getDistributionPlatforms(false);
$campaigns = dbQuery('SELECT c.*, g.title as game_title, p.name as platform_name FROM campaigns c LEFT JOIN games g ON g.id = c.game_id LEFT JOIN distribution_platforms p ON p.id = c.platform_id ORDER BY c.id DESC LIMIT 20');
$metrics = dbQuery('SELECT m.*, c.name as campaign_name FROM campaign_metrics m LEFT JOIN campaigns c ON c.id = m.campaign_id ORDER BY m.id DESC LIMIT 20');
$stats = dbQuery('SELECT s.*, g.title as game_title, p.name as platform_name FROM game_distribution_stats s LEFT JOIN games g ON g.id = s.game_id LEFT JOIN distribution_platforms p ON p.id = s.platform_id ORDER BY s.id DESC LIMIT 20');
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
    <div class="card-header"><h2 class="card-title">Campanhas</h2></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Nome</th><th>Jogo</th><th>Plataforma</th><th>Status</th><th>Orçamento</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($campaigns as $campaign): ?>
                <?php $statusClass = $campaign['status'] === 'active' ? 'badge-active' : (($campaign['status'] === 'paused' || $campaign['status'] === 'finished') ? 'badge-inactive' : 'badge-featured'); ?>
                <tr>
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
            </tbody>
        </table>
    </div>
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
    <div class="card-header"><h2 class="card-title">Métricas Recentes</h2></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Jogo</th><th>Plataforma</th><th>Métrica</th><th>Valor</th><th>Fonte</th></tr></thead>
            <tbody>
            <?php foreach ($stats as $row): ?>
                <tr>
                    <td><?= e($row['game_title'] ?? '—') ?></td>
                    <td><?= e($row['platform_name'] ?? '—') ?></td>
                    <td><?= e($metricOptions[$row['metric_key']] ?? $row['metric_key']) ?></td>
                    <td><?= e((string)$row['metric_value']) ?></td>
                    <td><?= e($sourceOptions[$row['source']] ?? $row['source']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Métricas de Campanha</h2></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Campanha</th><th>Métrica</th><th>Valor</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($metrics as $metric): ?>
                <tr>
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
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
