<?php
ob_start();
$pageTitle = 'Publicidade';
$requiredPerm = 'perm_settings';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Token inválido.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/ads');
        exit;
    }

    $action = $_POST['action'] ?? 'save';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slotKey = trim($_POST['slot_key'] ?? '');
        if ($slotKey === '' && $name !== '') {
            $slotKey = generateSlug($name);
        }
        $provider = trim($_POST['provider'] ?? 'custom_html');
        $codeHtml = $_POST['code_html'] ?? '';
        $active = !empty($_POST['active']) ? 1 : 0;
        $pages = trim($_POST['pages'] ?? '');
        $devices = trim($_POST['devices'] ?? 'all');
        $sticky = !empty($_POST['sticky']) ? 1 : 0;
        $heightDesktop = trim($_POST['height_desktop'] ?? '');
        $heightMobile = trim($_POST['height_mobile'] ?? '');
        $fallbackText = trim($_POST['fallback_text'] ?? '');

        $allowedProviders = ['custom_html', 'adsense', 'google_ads', 'image', 'script'];
        $allowedDevices = ['all', 'desktop', 'mobile'];

        if ($slotKey === '' || !preg_match('/^[a-z0-9_\-]+$/i', $slotKey)) {
            flashMessage('error', 'Slot key inválida.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/ads');
            exit;
        }
        if ($name === '') {
            flashMessage('error', 'Nome obrigatório.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/ads');
            exit;
        }
        if (!in_array($provider, $allowedProviders, true)) {
            flashMessage('error', 'Provider inválido.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/ads');
            exit;
        }
        if (!in_array($devices, $allowedDevices, true)) {
            flashMessage('error', 'Dispositivo inválido.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/ads');
            exit;
        }
        if ($provider === 'custom_html' && trim($codeHtml) === '') {
            flashMessage('error', 'Código HTML obrigatório para slot customizado.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/ads');
            exit;
        }
        if ($pages !== '' && !preg_match('/^[a-z0-9_,\- ]+$/i', $pages)) {
            flashMessage('error', 'Escopo de páginas inválido.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/ads');
            exit;
        }
        if ($heightDesktop !== '' && !preg_match('/^\d+(px|%|vh|vw)?$/', $heightDesktop)) {
            flashMessage('error', 'Altura desktop inválida.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/ads');
            exit;
        }
        if ($heightMobile !== '' && !preg_match('/^\d+(px|%|vh|vw)?$/', $heightMobile)) {
            flashMessage('error', 'Altura mobile inválida.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/ads');
            exit;
        }

        if ($id > 0) {
            $stmt = $db->prepare("UPDATE ad_slots SET slot_key = ?, name = ?, provider = ?, code_html = ?, active = ?, pages = ?, devices = ?, sticky = ?, height_desktop = ?, height_mobile = ?, fallback_text = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$slotKey, $name, $provider, $codeHtml, $active, $pages, $devices, $sticky, $heightDesktop, $heightMobile, $fallbackText, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO ad_slots (slot_key, name, provider, code_html, active, pages, devices, sticky, height_desktop, height_mobile, fallback_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$slotKey, $name, $provider, $codeHtml, $active, $pages, $devices, $sticky, $heightDesktop, $heightMobile, $fallbackText]);
        }
        flashMessage('success', 'Slot salvo.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/ads');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM ad_slots WHERE id = ?');
        $stmt->execute([$id]);
        flashMessage('success', 'Slot removido.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/ads');
        exit;
    }
}

$items = dbQuery('SELECT * FROM ad_slots ORDER BY id ASC');
$editId = (int)($_GET['edit'] ?? 0);
$editItem = $editId ? dbQueryOne('SELECT * FROM ad_slots WHERE id = ?', [$editId]) : null;
?>

<div class="card">
    <div class="card-header"><h2 class="card-title">Gerenciar Slots de Publicidade</h2></div>
    <div class="card-body">
        <form method="POST" class="form-grid form-grid-limited">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= e((string)($editItem['id'] ?? 0)) ?>">
            <h3 class="form-section-title">Informações Básicas</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="slot_key">Slot Key</label>
                    <input type="text" id="slot_key" name="slot_key" value="<?= e($editItem['slot_key'] ?? '') ?>" placeholder="home_top">
                    <div class="field-hint">Identificador único usado no tema. É gerado automaticamente a partir do nome, mas pode ser ajustado manualmente.</div>
                </div>
                <div class="form-group">
                    <label for="name">Nome</label>
                    <input type="text" id="name" name="name" value="<?= e($editItem['name'] ?? '') ?>">
                </div>
            </div>

            <h3 class="form-section-title">Regras de Exibição</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="provider">Provider</label>
                    <select id="provider" name="provider">
                        <?php foreach (['custom_html' => 'custom_html', 'adsense' => 'adsense', 'google_ads' => 'google_ads', 'image' => 'image', 'script' => 'script'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($editItem['provider'] ?? 'custom_html') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-hint">Escolha o tipo do slot. Publicidade externa usa valores padronizados.</div>
                </div>
                <div class="form-group">
                    <label for="pages">Escopo de páginas</label>
                    <input type="text" id="pages" name="pages" value="<?= e($editItem['pages'] ?? '') ?>" placeholder="home,game,catalogo,all">
                    <div class="field-hint">Use vírgulas para múltiplas páginas ou `all` para todas.</div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="devices">Dispositivos</label>
                    <input type="text" id="devices" name="devices" value="<?= e($editItem['devices'] ?? 'all') ?>" placeholder="all,desktop,mobile">
                    <div class="field-hint">Define onde o slot aparece.</div>
                </div>
                <div class="form-group">
                    <label for="height_desktop">Altura desktop</label>
                    <input type="text" id="height_desktop" name="height_desktop" value="<?= e($editItem['height_desktop'] ?? '') ?>" placeholder="90px">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="height_mobile">Altura mobile</label>
                    <input type="text" id="height_mobile" name="height_mobile" value="<?= e($editItem['height_mobile'] ?? '') ?>" placeholder="60px">
                </div>
                <div class="form-group">
                    <div class="toggle-group">
                        <input type="checkbox" id="sticky" name="sticky" value="1" <?= !empty($editItem['sticky']) ? 'checked' : '' ?>>
                        <label for="sticky">Sticky</label>
                    </div>
                    <div class="field-hint">Mantém o slot fixo durante a rolagem.</div>
                </div>
            </div>

            <h3 class="form-section-title">Conteúdo</h3>
            <div class="form-group">
                <label for="code_html">Código HTML</label>
                <textarea id="code_html" name="code_html" rows="7"><?= e($editItem['code_html'] ?? '') ?></textarea>
                <div class="field-hint">Obrigatório para `custom_html`. Não injete scripts não confiáveis.</div>
            </div>
            <div class="form-group">
                <label for="fallback_text">Texto fallback</label>
                <input type="text" id="fallback_text" name="fallback_text" value="<?= e($editItem['fallback_text'] ?? '') ?>">
                <div class="field-hint">Mensagem exibida quando o slot não tem conteúdo disponível.</div>
            </div>

            <h3 class="form-section-title">Configurações</h3>
            <div class="form-row">
                <div class="form-group">
                    <div class="toggle-group">
                        <input type="checkbox" id="active" name="active" value="1" <?= !empty($editItem['active']) ? 'checked' : '' ?>>
                        <label for="active">Ativo</label>
                    </div>
                    <div class="field-hint">Slots inativos continuam salvos, mas não são renderizados.</div>
                </div>
            </div>
            <div class="form-actions"><button class="btn btn-gold" type="submit">Salvar</button></div>
        </form>
        <script>
        (function() {
            var nameInput = document.getElementById('name');
            var slotInput = document.getElementById('slot_key');
            if (!nameInput || !slotInput) return;

            var manual = slotInput.value.trim() !== '' && slotInput.value.trim() !== slugify(nameInput.value);

            function slugify(value) {
                return (value || '')
                    .toLowerCase()
                    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, '_')
                    .replace(/^_+|_+$/g, '');
            }

            slotInput.addEventListener('input', function() {
                manual = this.value.trim() !== '';
            });

            nameInput.addEventListener('input', function() {
                if (!manual) {
                    slotInput.value = slugify(this.value);
                }
            });

            if (!manual && slotInput.value.trim() === '') {
                slotInput.value = slugify(nameInput.value);
            }
        })();
        </script>
    </div>
</div>

<div class="card card-spaced">
    <div class="card-header"><h2 class="card-title">Slots</h2></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Slot</th><th>Páginas</th><th>Dispositivos</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['slot_key']) ?></td>
                    <td><?= e($item['pages']) ?></td>
                    <td><?= e($item['devices']) ?></td>
                    <td><?= !empty($item['active']) ? '<span class="badge badge-active">Ativo</span>' : '<span class="badge badge-inactive">Inativo</span>' ?></td>
                    <td class="actions">
                        <a class="btn btn-outline btn-sm" href="?edit=<?= (int)$item['id'] ?>">Editar</a>
                        <form method="POST" onsubmit="return confirm('Remover slot?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
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
