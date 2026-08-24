<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$db = getDB();

// Load donation settings
$config = json_decode(getSetting('donation_config', ''), true) ?: [
    'enabled' => false,
    'pix_key' => '',
    'pix_description' => 'Apoio ao Jogatinando',
    'paypal_url' => '',
    'custom_html' => '',
];

$tiers = json_decode(getSetting('donation_tiers', ''), true) ?: [
    ['amount' => 5, 'label' => 'Café'],
    ['amount' => 15, 'label' => 'Jogo Indie'],
    ['amount' => 50, 'label' => 'Desenvolvimento'],
    ['amount' => 100, 'label' => 'Patrocinador'],
];

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token inválido.';
    } else {
        $config['enabled'] = isset($_POST['donation_enabled']) ? 1 : 0;
        $config['pix_key'] = trim($_POST['pix_key'] ?? '');
        $config['pix_description'] = trim($_POST['pix_description'] ?? '');
        $config['paypal_url'] = trim($_POST['paypal_url'] ?? '');
        $config['custom_html'] = trim($_POST['custom_html'] ?? '');

        setSetting('donation_config', json_encode($config));

        // Process tiers
        $amounts = $_POST['tier_amount'] ?? [];
        $labels = $_POST['tier_label'] ?? [];
        $newTiers = [];
        foreach ($amounts as $i => $amt) {
            $amt = trim($amt);
            $lbl = trim($labels[$i] ?? '');
            if ($amt !== '' && $lbl !== '') {
                $amt = str_replace(['R$', '$', ','], ['', '', '.'], $amt);
                $amt = (float)$amt;
                if ($amt > 0) {
                    $newTiers[] = ['amount' => $amt, 'label' => $lbl];
                }
            }
        }
        setSetting('donation_tiers', json_encode($newTiers));
        $tiers = $newTiers;

        $success = 'Configurações de doação salvas!';
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/donations?ok=1');
        exit;
    }
}

$pageTitle = 'Doações';
$requiredPerm = 'perm_settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-page-header">
    <div>
        <h2>Configurações de Doações</h2>
        <p class="subtitle">PIX / PayPal / conteúdo personalizado</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="admin-card">
    <h3>Configuração Geral</h3>
    <form method="POST">
        <?= csrfField() ?>
        <div class="form-grid">
            <div class="form-group" style="grid-column: 1 / -1;">
                <div class="toggle-group">
                    <input type="checkbox" id="donation_enabled" name="donation_enabled" value="1" <?= $config['enabled'] ? 'checked' : '' ?>>
                    <label for="donation_enabled">Doações ativadas</label>
                </div>
            </div>

            <div class="form-group">
                <label for="pix_key">Chave PIX</label>
                <input type="text" id="pix_key" name="pix_key" value="<?= e($config['pix_key']) ?>" placeholder="ex: seu@email.com ou chave aleatória">
            </div>

            <div class="form-group">
                <label for="pix_description">Descrição PIX</label>
                <input type="text" id="pix_description" name="pix_description" value="<?= e($config['pix_description']) ?>">
                <div class="field-hint">Texto que aparecerá na descrição do PIX</div>
            </div>

            <div class="form-group">
                <label for="paypal_url">URL do PayPal</label>
                <input type="url" id="paypal_url" name="paypal_url" value="<?= e($config['paypal_url']) ?>" placeholder="https://paypal.me/seu-usuario">
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label for="custom_html">HTML Personalizado</label>
                <div class="field-hint">HTML que aparecerá acima dos botões de doação</div>
                <textarea id="custom_html" name="custom_html" rows="5"><?= e($config['custom_html']) ?></textarea>
            </div>
        </div>
</div>

<div class="admin-card">
    <h3>Casas de Doação (Tiers)</h3>
    <div class="table-responsive">
        <table class="admin-table" id="tiers-table">
            <thead>
                <tr><th>Valor (R$)</th><th>Label</th><th>Ações</th></tr>
            </thead>
            <tbody>
                <?php foreach ($tiers as $i => $tier): ?>
                <tr>
                    <td>
                        <input type="number" name="tier_amount[]" step="1" min="1" value="<?= e($tier['amount']) ?>" style="width:100px;">
                        <input type="hidden" name="tier_id[]" value="<?= $i ?>">
                    </td>
                    <td><input type="text" name="tier_label[]" value="<?= e($tier['label']) ?>" style="width:200px;"></td>
                    <td><button type="button" class="btn btn-danger btn-sm tier-remove" onclick="removeTier(this)">Remover</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-outline btn-sm" onclick="addTier()">+ Adicionar Tier</button>
</div>

<div class="form-actions">
    <button class="btn btn-gold" type="submit">Salvar</button>
</div>
</form>

<script>
function addTier() {
    var tbody = document.querySelector('#tiers-table tbody');
    var row = tbody.querySelector('tr').cloneNode(true);
    row.querySelectorAll('input').forEach(i => i.value = '');
    tbody.appendChild(row);
}
function removeTier(btn) {
    var row = btn.closest('tr');
    if (row && document.querySelectorAll('#tiers-table tbody tr').length > 1) {
        row.remove();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
