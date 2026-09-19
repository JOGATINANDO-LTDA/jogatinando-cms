<?php
/**
 * Health check — diagnóstico de instalação/configuração.
 *
 * - Modo básico (público): status OK/WARN/FAIL sem paths ou segredos.
 * - Modo detalhado: adicione ?key=<cron_key> (mesma chave do cron.php) para
 *   ver paths, versões e contagens.
 * - ?format=json para saída de máquina.
 */
define('SKIP_INSTALL_CHECK', true);
require_once __DIR__ . '/config.php';

header('X-Robots-Tag: noindex');

$format = ($_GET['format'] ?? '') === 'json';
$key = (string)($_GET['key'] ?? '');
$detailed = false;
$cronKey = '';
if (defined('DB_TYPE')) {
    try {
        $cronKey = (string)getSetting('cron_key', '');
    } catch (Throwable $e) {
        $cronKey = '';
    }
}
if ($cronKey !== '' && $key !== '' && hash_equals($cronKey, $key)) {
    $detailed = true;
}

$checks = [];
$overall = 'ok';

function hcAdd(&$checks, &$overall, $label, $status, $message, $detail = '') {
    $checks[] = ['label' => $label, 'status' => $status, 'message' => $message, 'detail' => $detail];
    if ($status === 'fail') {
        $overall = 'fail';
    } elseif ($status === 'warn' && $overall === 'ok') {
        $overall = 'warn';
    }
}

// 1. PHP
$phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
$extRequired = ['pdo', 'zip', 'mbstring'];
$extOptional = ['gd'];
$extMissing = [];
foreach ($extRequired as $ext) {
    if (!extension_loaded($ext)) $extMissing[] = $ext;
}
if (!extension_loaded('pdo_mysql') && !extension_loaded('pdo_sqlite')) {
    $extMissing[] = 'pdo_mysql|pdo_sqlite';
}
$extOptionalMissing = [];
foreach ($extOptional as $ext) {
    if (!extension_loaded($ext)) $extOptionalMissing[] = $ext;
}
if ($phpOk && empty($extMissing)) {
    $status = empty($extOptionalMissing) ? 'ok' : 'warn';
    $msg = empty($extOptionalMissing) ? 'Versão e extensões compatíveis' : 'Extensões opcionais ausentes: ' . implode(', ', $extOptionalMissing);
    hcAdd($checks, $overall, 'PHP', $status, $msg, $detailed ? PHP_VERSION : '');
} else {
    hcAdd($checks, $overall, 'PHP', 'fail', $phpOk ? 'Extensões obrigatórias ausentes' : 'Versão mínima 8.1 não atendida', $detailed ? PHP_VERSION . ' · ausentes: ' . implode(', ', $extMissing) : '');
}

// 2. Config
$configSource = 'none';
if (file_exists(LOCAL_CONFIG_PERSISTENT)) {
    $configSource = 'persistente';
} elseif (file_exists(LOCAL_CONFIG)) {
    $configSource = 'data';
} elseif (file_exists(ROOT_PATH . '/config.local.php')) {
    $configSource = 'raiz-legado';
}
if ($configSource === 'none') {
    hcAdd($checks, $overall, 'Configuração', 'fail', 'config.local.php não encontrado — acesse /install.php', '');
} elseif ($configSource === 'raiz-legado') {
    hcAdd($checks, $overall, 'Configuração', 'warn', 'Config em local legado (será migrada na próxima carga)', $detailed ? ROOT_PATH . '/config.local.php' : '');
} else {
    $writable = @is_writable($configSource === 'persistente' ? LOCAL_CONFIG_PERSISTENT : LOCAL_CONFIG);
    hcAdd($checks, $overall, 'Configuração', $writable ? 'ok' : 'warn', $writable ? 'Presente (' . $configSource . ')' : 'Presente (' . $configSource . ') — somente leitura', $detailed ? ($configSource === 'persistente' ? LOCAL_CONFIG_PERSISTENT : LOCAL_CONFIG) : '');
}

// 3. Banco de dados
$db = null;
$dbError = '';
try {
    $db = getDB();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
$dbType = defined('DB_TYPE') ? DB_TYPE : 'não definido';
if ($db === null) {
    hcAdd($checks, $overall, 'Banco de dados', 'fail', 'Falha de conexão — reconfigure em /install?reconfigure=1', $detailed ? ('tipo: ' . $dbType . ($dbError !== '' ? ' · ' . $dbError : '')) : '');
} else {
    $schemaVersion = 0;
    $userCount = 0;
    $counts = [];
    try {
        $row = $db->query("SELECT MAX(version) AS v FROM schema_version")->fetch();
        $schemaVersion = (int)($row['v'] ?? 0);
        $userCount = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($detailed) {
            $counts = [
                'games' => (int)$db->query("SELECT COUNT(*) FROM games")->fetchColumn(),
                'posts' => (int)$db->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn(),
                'subscribers' => (int)$db->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn(),
            ];
        }
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
    if ($userCount === 0) {
        hcAdd($checks, $overall, 'Banco de dados', 'fail', 'Conectado, mas sem usuários — instalação incompleta', $detailed ? ('tipo: ' . $dbType . ' · schema: v' . $schemaVersion) : '');
    } else {
        $detail = $detailed ? ('tipo: ' . $dbType . ' · schema: v' . $schemaVersion . ' · usuários: ' . $userCount . ($counts ? ' · ' . json_encode($counts) : '')) : '';
        hcAdd($checks, $overall, 'Banco de dados', 'ok', 'Conectado · schema v' . $schemaVersion, $detail);
    }
}

// 4. install.php presente
if (file_exists(ROOT_PATH . '/install.php')) {
    hcAdd($checks, $overall, 'Instalador', 'ok', 'install.php presente (acesso restrito ao CEO)', '');
} else {
    hcAdd($checks, $overall, 'Instalador', 'warn', 'install.php ausente — reconfigure via redeploy CI/CD', '');
}

// 5. Maintenance mode
$dataPath = defined('DATA_PATH') ? DATA_PATH : ROOT_PATH . '/data';
$maintenance = file_exists($dataPath . '/.maintenance') || file_exists(dirname(ROOT_PATH) . '/.maintenance');
hcAdd($checks, $overall, 'Manutenção', $maintenance ? 'warn' : 'ok', $maintenance ? 'Modo manutenção ATIVO' : 'Desativado', '');

// 6. Diretórios graváveis
$dataWritable = is_dir($dataPath) && @is_writable($dataPath);
$uploadsPath = defined('UPLOAD_PATH') ? UPLOAD_PATH : ROOT_PATH . '/uploads';
$uploadsWritable = is_dir($uploadsPath) && @is_writable($uploadsPath);
if ($dataWritable && $uploadsWritable) {
    hcAdd($checks, $overall, 'Diretórios', 'ok', 'data/ e uploads/ graváveis', $detailed ? ($dataPath . ' · ' . $uploadsPath) : '');
} else {
    hcAdd($checks, $overall, 'Diretórios', 'fail', (!$dataWritable ? 'data/ sem permissão de escrita' : 'uploads/ sem permissão de escrita'), $detailed ? ($dataPath . ' · ' . $uploadsPath) : '');
}

// 7. .git exposto
$gitPresent = file_exists(ROOT_PATH . '/.git');
if ($gitPresent) {
    hcAdd($checks, $overall, 'Segurança', 'warn', '.git presente no webroot — verifique se o .htaccess bloqueia /.git', $detailed ? ROOT_PATH . '/.git' : '');
} else {
    hcAdd($checks, $overall, 'Segurança', 'ok', 'Sem exposição de .git', '');
}

// 8. Recuperação disponível
if ($db === null && $configSource !== 'none') {
    hcAdd($checks, $overall, 'Recuperação', 'ok', 'Modo recuperação disponível em /install?reconfigure=1', '');
} else {
    hcAdd($checks, $overall, 'Recuperação', 'ok', 'Reconfigure via CEO em /install?reconfigure=1', '');
}

$siteUrl = defined('SITE_URL') ? SITE_URL : '';
$cmsVersion = defined('CMS_VERSION') ? CMS_VERSION : '';

if ($format) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => $overall,
        'cms_version' => $cmsVersion,
        'site_url' => $siteUrl,
        'detailed' => $detailed,
        'server_time' => date('c'),
        'checks' => $checks,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$statusLabel = ['ok' => 'OK', 'warn' => 'ATENÇÃO', 'fail' => 'FALHA'];
$statusColor = ['ok' => '#4ade80', 'warn' => '#fbbf24', 'fail' => '#f87171'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico — <?= e(defined('CMS_VERSION') ? 'CMS' : 'CMS') ?></title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: oklch(10% 0.03 260); color: oklch(96% 0.003 250); min-height: 100vh; padding: 40px 16px; }
        .wrap { max-width: 720px; margin: 0 auto; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .sub { color: oklch(60% 0.012 250); font-size: 13px; margin-bottom: 24px; }
        .overall { display: inline-block; padding: 6px 16px; border-radius: 20px; font-weight: 700; font-size: 13px; margin-bottom: 24px; }
        .overall.ok { background: rgba(74, 222, 128, .15); color: #4ade80; border: 1px solid #4ade80; }
        .overall.warn { background: rgba(251, 191, 36, .15); color: #fbbf24; border: 1px solid #fbbf24; }
        .overall.fail { background: rgba(248, 113, 113, .15); color: #f87171; border: 1px solid #f87171; }
        table { width: 100%; border-collapse: collapse; background: oklch(16% 0.035 265); border-radius: 12px; overflow: hidden; }
        th, td { text-align: left; padding: 12px 16px; font-size: 13px; border-bottom: 1px solid oklch(25% 0.02 260); vertical-align: top; }
        th { color: oklch(60% 0.012 250); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; }
        tr:last-child td { border-bottom: none; }
        .st { font-weight: 700; white-space: nowrap; }
        .st.ok { color: #4ade80; }
        .st.warn { color: #fbbf24; }
        .st.fail { color: #f87171; }
        .detail { color: oklch(55% 0.012 250); font-size: 12px; margin-top: 4px; word-break: break-all; }
        .foot { margin-top: 20px; color: oklch(45% 0.012 250); font-size: 12px; text-align: center; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Diagnóstico do Sistema</h1>
        <p class="sub">
            CMS v<?= e($cmsVersion) ?>
            <?php if ($siteUrl): ?> · <?= e($siteUrl) ?><?php endif; ?>
            · <?= date('d/m/Y H:i') ?>
        </p>
        <div class="overall <?= e($overall) ?>"><?= e($statusLabel[$overall]) ?></div>

        <table>
            <thead><tr><th>Item</th><th>Status</th><th>Detalhe</th></tr></thead>
            <tbody>
                <?php foreach ($checks as $c): ?>
                <tr>
                    <td><strong><?= e($c['label']) ?></strong></td>
                    <td><span class="st <?= e($c['status']) ?>"><?= e($statusLabel[$c['status']]) ?></span></td>
                    <td>
                        <?= e($c['message']) ?>
                        <?php if ($detailed && $c['detail'] !== ''): ?>
                            <div class="detail"><?= e($c['detail']) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="foot">
            <?php if (!$detailed): ?>
                Diagnóstico básico · detalhado com <code>?key=SUA_CHAVE_CRON</code> · JSON com <code>?format=json</code>
            <?php else: ?>
                Modo detalhado ativo · JSON com <code>?key=...&amp;format=json</code>
            <?php endif; ?>
        </p>
    </div>
</body>
</html>
