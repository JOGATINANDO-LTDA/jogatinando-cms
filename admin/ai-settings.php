<?php
ob_start();
$pageTitle = 'Configurações de IA';
$requiredPerm = 'perm_settings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/ai/client.php';

$db = getDB();
$success = '';
$error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token inválido.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_config') {
            $configId = (int)($_POST['config_id'] ?? 0);
            $modelSlug = trim($_POST['model_slug'] ?? '');
            $apiKey = trim($_POST['api_key'] ?? '');
            $maxTokens = (int)($_POST['max_tokens'] ?? 4096);
            $temperature = (float)($_POST['temperature'] ?? 0.7);
            $systemPrompt = trim($_POST['system_prompt'] ?? '');

            if ($maxTokens < 256) $maxTokens = 256;
            if ($maxTokens > 128000) $maxTokens = 128000;
            if ($temperature < 0) $temperature = 0;
            if ($temperature > 2) $temperature = 2;

            if ($configId > 0) {
                $stmt = $db->prepare("UPDATE ai_configs SET api_key = ?, model_slug = ?, max_tokens = ?, temperature = ?, system_prompt = ? WHERE id = ?");
                $stmt->execute([$apiKey, $modelSlug, $maxTokens, $temperature, $systemPrompt, $configId]);
            } else {
                $providerId = (int)($_POST['provider_id'] ?? 0);
                if ($providerId > 0) {
                    $stmt = $db->prepare("INSERT INTO ai_configs (provider_id, api_key, model_slug, max_tokens, temperature, system_prompt, is_default) VALUES (?, ?, ?, ?, ?, ?, 0)");
                    $stmt->execute([$providerId, $apiKey, $modelSlug, $maxTokens, $temperature, $systemPrompt]);
                    $configId = (int)$db->lastInsertId();
                }
            }

            // Set as default if requested
            if (!empty($_POST['is_default']) && $configId > 0) {
                $db->exec("UPDATE ai_configs SET is_default = 0");
                $db->prepare("UPDATE ai_configs SET is_default = 1 WHERE id = ?")->execute([$configId]);
            }

            $success = 'Configuração salva!';
        }

        if ($action === 'delete_config') {
            $configId = (int)($_POST['config_id'] ?? 0);
            if ($configId > 0) {
                $db->prepare("DELETE FROM ai_configs WHERE id = ?")->execute([$configId]);
                $success = 'Configuração removida.';
            }
        }

        if ($action === 'toggle_provider') {
            $providerId = (int)($_POST['provider_id'] ?? 0);
            $active = (int)($_POST['active'] ?? 0);
            if ($providerId > 0) {
                $db->prepare("UPDATE ai_providers SET active = ? WHERE id = ?")->execute([$active, $providerId]);
                $success = $active ? 'Provider ativado.' : 'Provider desativado.';
            }
        }

        if ($action === 'test_connection') {
            $configId = (int)($_POST['config_id'] ?? 0);
            try {
                $client = AIClient::getInstance();
                $client->switchConfig($configId);
                $models = $client->listModels();
                if (count($models) > 0) {
                    $success = 'Conexão OK! ' . count($models) . ' modelos disponíveis.';
                } else {
                    $error = 'Nenhum modelo encontrado. Verifique a URL e a API key.';
                }
            } catch (Exception $e) {
                $error = 'Erro: ' . $e->getMessage();
            }
        }
    }
    header('Location: ' . ADMIN_URL . '/ai-settings' . ($success ? '?ok=1' : ''));
    exit;
}

// Fetch data
$providers = $db->query("SELECT * FROM ai_providers ORDER BY slug")->fetchAll();
$configs = $db->query("
    SELECT c.*, p.slug AS provider_slug, p.name AS provider_name, p.base_url
    FROM ai_configs c
    JOIN ai_providers p ON c.provider_id = p.id
    ORDER BY c.is_default DESC, p.name, c.id
")->fetchAll();

$ok = isset($_GET['ok']);
?>

<div class="admin-page-header">
    <div>
        <h2>Configurações de IA</h2>
        <p class="subtitle">Gerencie providers, modelos e chaves de API</p>
    </div>
</div>

<?php if ($ok || $success): ?>
    <div class="alert alert-success"><?= e($success ?: 'Operação realizada com sucesso!') ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<!-- Providers Section -->
<div class="admin-card">
    <h3>Providers Disponíveis</h3>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Provider</th>
                    <th>Base URL</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($providers as $p): ?>
                <tr>
                    <td><strong><?= e($p['name']) ?></strong> <code><?= e($p['slug']) ?></code></td>
                    <td><code class="url-display"><?= e($p['base_url']) ?></code></td>
                    <td>
                        <span class="badge badge-<?= $p['active'] ? 'success' : 'muted' ?>">
                            <?= $p['active'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="toggle_provider">
                            <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="active" value="<?= $p['active'] ? 0 : 1 ?>">
                            <button type="submit" class="btn btn-sm btn-outline">
                                <?= $p['active'] ? 'Desativar' : 'Ativar' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Configs Section -->
<div class="admin-card">
    <div class="card-header-row">
        <h3>Configurações de API</h3>
    </div>

    <?php if (empty($configs)): ?>
        <p class="text-muted">Nenhuma configuração criada. Clique em "Nova Configuração" para começar.</p>
    <?php else: ?>
    <div class="config-grid">
        <?php foreach ($configs as $c): ?>
        <div class="config-card <?= $c['is_default'] ? 'config-default' : '' ?>">
            <div class="config-header">
                <span class="config-provider"><?= e($c['provider_name']) ?></span>
                <?php if ($c['is_default']): ?>
                    <span class="badge badge-gold">Padrão</span>
                <?php endif; ?>
            </div>
            <form method="POST" class="config-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_config">
                <input type="hidden" name="config_id" value="<?= $c['id'] ?>">

                <div class="form-group">
                    <label>Modelo</label>
                    <input type="text" name="model_slug" value="<?= e($c['model_slug']) ?>" class="form-input" placeholder="mimo-v2.5-free">
                </div>

                <?php if ($c['provider_slug'] !== 'zen'): ?>
                <div class="form-group">
                    <label>API Key</label>
                    <input type="password" name="api_key" value="<?= e($c['api_key']) ?>" class="form-input" placeholder="sk-...">
                </div>
                <?php else: ?>
                    <input type="hidden" name="api_key" value="">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group form-group-half">
                        <label>Max Tokens</label>
                        <input type="number" name="max_tokens" value="<?= (int)$c['max_tokens'] ?>" class="form-input" min="256" max="128000">
                    </div>
                    <div class="form-group form-group-half">
                        <label>Temperature</label>
                        <input type="number" name="temperature" value="<?= (float)$c['temperature'] ?>" class="form-input" min="0" max="2" step="0.1">
                    </div>
                </div>

                <div class="form-group">
                    <label>System Prompt</label>
                    <textarea name="system_prompt" class="form-input form-textarea" rows="2"><?= e($c['system_prompt']) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_default" value="1" <?= $c['is_default'] ? 'checked' : '' ?>>
                        Definir como padrão
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                    <button type="button" class="btn btn-outline btn-sm test-btn" data-config-id="<?= $c['id'] ?>">Testar Conexão</button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Remover esta configuração?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_config">
                        <input type="hidden" name="config_id" value="<?= $c['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Remover</button>
                    </form>
                </div>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- New Config Form -->
    <div class="new-config-section">
        <h4>Nova Configuração</h4>
        <form method="POST" class="config-form config-form-new">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_config">
            <input type="hidden" name="config_id" value="0">

            <div class="form-group">
                <label>Provider</label>
                <select name="provider_id" class="form-input" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($providers as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Modelo</label>
                <input type="text" name="model_slug" class="form-input" placeholder="gpt-4o-mini, llama3.2:3b, etc.">
            </div>

            <div class="form-group">
                <label>API Key</label>
                <input type="password" name="api_key" class="form-input" placeholder="Opcional para providers locais">
            </div>

            <div class="form-row">
                <div class="form-group form-group-half">
                    <label>Max Tokens</label>
                    <input type="number" name="max_tokens" value="4096" class="form-input" min="256" max="128000">
                </div>
                <div class="form-group form-group-half">
                    <label>Temperature</label>
                    <input type="number" name="temperature" value="0.7" class="form-input" min="0" max="2" step="0.1">
                </div>
            </div>

            <div class="form-group">
                <label>System Prompt</label>
                <textarea name="system_prompt" class="form-input form-textarea" rows="2" placeholder="Instruções do sistema..."></textarea>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_default" value="1">
                    Definir como padrão
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Criar Configuração</button>
            </div>
        </form>
    </div>
</div>

<!-- Usage Stats -->
<div class="admin-card">
    <h3>Uso de IA</h3>
    <?php
    $usage = $db->query("
        SELECT feature,
               COUNT(*) AS total_calls,
               SUM(prompt_tokens) AS total_prompt,
               SUM(completion_tokens) AS total_completion,
               ROUND(AVG(latency_ms), 0) AS avg_latency
        FROM ai_usage
        GROUP BY feature
        ORDER BY total_calls DESC
    ")->fetchAll();
    ?>
    <?php if (empty($usage)): ?>
        <p class="text-muted">Nenhum uso registrado ainda.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>Chamadas</th>
                    <th>Tokens (Prompt)</th>
                    <th>Tokens (Completion)</th>
                    <th>Latência Média</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usage as $u): ?>
                <tr>
                    <td><?= e($u['feature']) ?></td>
                    <td><?= (int)$u['total_calls'] ?></td>
                    <td><?= number_format((int)$u['total_prompt']) ?></td>
                    <td><?= number_format((int)$u['total_completion']) ?></td>
                    <td><?= number_format((int)$u['avg_latency']) ?>ms</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.test-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const configId = this.dataset.configId;
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <?= csrfField() ?>
            <input type="hidden" name="action" value="test_connection">
            <input type="hidden" name="config_id" value="${configId}">
        `;
        document.body.appendChild(form);
        form.submit();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
