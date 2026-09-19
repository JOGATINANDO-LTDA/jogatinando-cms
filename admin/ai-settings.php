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

        // AI chat (quick test)
        if ($action === 'ai_chat') {
            header('Content-Type: application/json');
            $prompt = trim($_POST['prompt'] ?? '');
            if ($prompt === '') {
                echo json_encode(['error' => 'Prompt não pode estar vazio.']);
                exit;
            }
            try {
                $client = AIClient::getInstance();
                if (!$client->isAvailable()) {
                    echo json_encode(['error' => 'Nenhum provider de IA configurado.']);
                    exit;
                }
                $result = $client->chat([
                    ['role' => 'user', 'content' => $prompt],
                ], ['feature' => 'admin_chat_test']);
                echo json_encode([
                    'content' => $result['content'] ?? '',
                    'provider' => $client->getProviderName(),
                    'model' => $client->getModel(),
                    'usage' => $result['usage'] ?? [],
                ]);
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
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
                </div>
            </form>
            <form method="POST" style="margin-top:8px;" onsubmit="return confirm('Remover esta configuração?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete_config">
                <input type="hidden" name="config_id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Remover</button>
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
    $totalUsage = $db->query("
        SELECT
               COUNT(*) AS total_calls,
               SUM(prompt_tokens) AS total_prompt,
               SUM(completion_tokens) AS total_completion,
               SUM(cost_cents) AS total_cost_cents,
               ROUND(AVG(latency_ms), 0) AS avg_latency
        FROM ai_usage
    ")->fetch();
    $totalCost = (float)($totalUsage['total_cost_cents'] ?? 0) / 100.0;
    ?>
    <?php if ($totalUsage && (int)$totalUsage['total_calls'] > 0): ?>
    <div class="usage-summary-cards">
        <div class="usage-summary-card">
            <div class="usage-summary-value"><?= number_format((int)$totalUsage['total_calls']) ?></div>
            <div class="usage-summary-label">Chamadas</div>
        </div>
        <div class="usage-summary-card">
            <div class="usage-summary-value"><?= number_format((int)$totalUsage['total_prompt']) ?></div>
            <div class="usage-summary-label">Tokens Prompt</div>
        </div>
        <div class="usage-summary-card">
            <div class="usage-summary-value"><?= number_format((int)$totalUsage['total_completion']) ?></div>
            <div class="usage-summary-label">Tokens Completion</div>
        </div>
        <div class="usage-summary-card">
            <div class="usage-summary-value">$<?= number_format($totalCost, 4) ?></div>
            <div class="usage-summary-label">Custo Total</div>
        </div>
    </div>
    <?php endif; ?>

    <?php
    $usage = $db->query("
        SELECT feature,
               COUNT(*) AS total_calls,
               SUM(prompt_tokens) AS total_prompt,
               SUM(completion_tokens) AS total_completion,
               ROUND(SUM(cost_cents) / 100.0, 4) AS total_cost,
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
                <th>Custo</th>
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
                <td>$<?= number_format((float)$u['total_cost'], 4) ?></td>
                <td><?= number_format((int)$u['avg_latency']) ?>ms</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- Quick AI Test -->
<div class="admin-card">
    <h3>Teste Rápido de IA</h3>
    <p class="text-muted" style="font-size:13px;">Envie um prompt de teste para verificar se a configuração padrão está funcionando.</p>
    <div id="aiChatResult" style="display:none;" class="chat-result-box">
        <div class="chat-result-meta"></div>
        <div class="chat-result-content"></div>
    </div>
    <form id="aiChatForm" class="config-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="ai_chat">
        <div class="form-group">
            <textarea name="prompt" id="aiChatPrompt" class="form-input form-textarea" rows="3" placeholder="Digite sua mensagem para testar a IA..." required></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-sm">Enviar</button>
            <button type="button" id="aiChatClear" class="btn btn-outline btn-sm">Limpar</button>
        </div>
    </form>
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

// Quick AI chat
const aiChatForm = document.getElementById('aiChatForm');
const aiChatResult = document.getElementById('aiChatResult');
if (aiChatForm) {
    aiChatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const prompt = formData.get('prompt').trim();
        if (!prompt) return;

        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Enviando...';

        fetch('<?= ADMIN_URL ?>/ai-settings', {
            method: 'POST',
            body: formData,
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                aiChatResult.querySelector('.chat-result-content').innerHTML =
                    '<span style="color:oklch(80% 0.1 30);">Erro: ' + data.error + '</span>';
                aiChatResult.querySelector('.chat-result-meta').textContent = '';
            } else {
                aiChatResult.querySelector('.chat-result-meta').innerHTML =
                    'Provider: <strong>' + (data.provider || 'N/A') + '</strong> | Modelo: <strong>' + (data.model || 'N/A') + '</strong>';
                if (data.usage && data.usage.total_tokens) {
                    aiChatResult.querySelector('.chat-result-meta') +=
                        ' | Tokens: <strong>' + data.usage.total_tokens + '</strong>';
                }
                const content = data.content.replace(/\n/g, '<br>');
                aiChatResult.querySelector('.chat-result-content').innerHTML = content;
            }
            aiChatResult.style.display = 'block';
        })
        .catch(err => {
            aiChatResult.querySelector('.chat-result-content').innerHTML =
                '<span style="color:oklch(80% 0.1 30);">Erro de rede: ' + err.message + '</span>';
            aiChatResult.querySelector('.chat-result-meta').textContent = '';
            aiChatResult.style.display = 'block';
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Enviar';
        });
    });
}
document.getElementById('aiChatClear').addEventListener('click', function() {
    document.getElementById('aiChatPrompt').value = '';
    aiChatResult.style.display = 'none';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
