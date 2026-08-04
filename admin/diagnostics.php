<?php
ob_start();
$pageTitle = 'Diagnóstico do Servidor';
require_once __DIR__ . '/../includes/header.php';

$userId = (int)($_SESSION['admin_user_id'] ?? 0);
if ($userId !== 1) {
    ob_end_clean();
    header('Location: ' . ADMIN_URL . '/dashboard');
    exit;
}

$db = getDB();
$dbType = getDbType();

function diagOk($msg) {
    echo '<div style="padding:6px 12px;margin:4px 0;border-radius:6px;background:oklch(55% 0.25 145 / 0.12);border-left:3px solid oklch(55% 0.25 145);color:oklch(75% 0.2 145)">', e($msg), '</div>';
}
function diagWarn($msg, $detail = '') {
    echo '<div style="padding:6px 12px;margin:4px 0;border-radius:6px;background:oklch(65% 0.2 85 / 0.12);border-left:3px solid oklch(65% 0.2 85);color:oklch(80% 0.18 85)">', e($msg), $detail ? '<br><code style="font-size:12px;color:oklch(60% 0.1 85)">' . e($detail) . '</code>' : '', '</div>';
}
function diagFail($msg, $detail = '') {
    echo '<div style="padding:6px 12px;margin:4px 0;border-radius:6px;background:oklch(55% 0.25 25 / 0.12);border-left:3px solid oklch(55% 0.25 25);color:oklch(75% 0.2 25)">', e($msg), $detail ? '<br><code style="font-size:12px;color:oklch(60% 0.15 25)">' . e($detail) . '</code>' : '', '</div>';
}
?>
<div style="padding:32px;max-width:800px;margin:0 auto">
<h1 style="font-family:'Cinzel',serif;font-size:22px;color:oklch(75% 0.15 85);margin-bottom:24px">Diagnóstico do Servidor</h1>

<section style="margin-bottom:32px">
<h2 style="font-size:16px;color:oklch(80% 0.1 85);margin-bottom:12px;border-bottom:1px solid oklch(25% 0.02 260);padding-bottom:8px">PHP</h2>
<?php
diagOk('Versão: ' . PHP_VERSION);

$exts = [
    'fileinfo' => 'Detecção de MIME em uploads',
    'curl' => 'Comunicação S3/R2, requisições externas',
    'zip' => 'Extração de jogos em .zip',
    'gd' => 'Geração de thumbnails',
    'mbstring' => 'Manipulação de strings UTF-8',
    'json' => 'API e dados estruturados',
    'session' => 'Login e flash messages',
    'pdo_mysql' => 'Conexão MySQL/MariaDB',
    'pdo_sqlite' => 'Conexão SQLite',
];
foreach ($exts as $ext => $impact) {
    if (extension_loaded($ext)) {
        diagOk("Extensão <code>{$ext}</code> habilitada");
    } else {
        diagWarn("Extensão <code>{$ext}</code> ausente", $impact);
    }
}

$uploadMax = ini_get('upload_max_filesize');
$postMax = ini_get('post_max_size');
$maxExec = ini_get('max_execution_time');
$memLimit = ini_get('memory_limit');
diagOk("upload_max_filesize: {$uploadMax}");
diagOk("post_max_size: {$postMax}");
diagOk("max_execution_time: {$maxExec}s");
diagOk("memory_limit: {$memLimit}");
if (function_exists('finfo_open')) {
    diagOk('finfo_open() disponível (nativo)');
} else {
    diagWarn('finfo_open() indisponível — usando fallback mime_content_type()', 'getFileMimeType()');
}
if (function_exists('mime_content_type')) {
    diagOk('mime_content_type() disponível (fallback)');
}
?>
</section>

<section style="margin-bottom:32px">
<h2 style="font-size:16px;color:oklch(80% 0.1 85);margin-bottom:12px;border-bottom:1px solid oklch(25% 0.02 260);padding-bottom:8px">Sessão</h2>
<?php
$sessionPath = session_save_path();
if ($sessionPath && $sessionPath !== '') {
    $writable = is_writable($sessionPath);
    diagOk("Session save path: {$sessionPath}" . ($writable ? ' (gravável)' : ''));
    if (!$writable) diagFail("Session path não gravável", 'Flash messages e login podem falhar');
} else {
    diagWarn('Session save path: default do PHP');
}
// diagOk('Session ID: ' . session_id());
?>
</section>

<section style="margin-bottom:32px">
<h2 style="font-size:16px;color:oklch(80% 0.1 85);margin-bottom:12px;border-bottom:1px solid oklch(25% 0.02 260);padding-bottom:8px">Arquivos</h2>
<?php
$checks = [
    'ROOT_PATH' => ROOT_PATH,
    'DATA_PATH' => DATA_PATH,
    'UPLOAD_PATH' => UPLOAD_PATH,
    'data/config.local.php' => DATA_PATH . '/config.local.php',
    'config.local.php (fora webroot)' => dirname(ROOT_PATH) . '/config.local.php',
    'data/sessions/' => DATA_PATH . '/sessions',
];
foreach ($checks as $label => $path) {
    if (file_exists($path)) {
        $w = is_writable($path) ? ' (gravável)' : ' (não gravável)';
        $d = is_dir($path) ? ' [diretório]' : '';
        diagOk("{$label}: {$path}{$d}{$w}");
    } else {
        if (str_contains($label, 'config.local.php')) {
            diagWarn("{$label}: {$path} — não encontrado", 'Se estiver em produção, criar via install.php');
        } else {
            diagWarn("{$label}: {$path} — não encontrado");
        }
    }
}
?>
</section>

<section style="margin-bottom:32px">
<h2 style="font-size:16px;color:oklch(80% 0.1 85);margin-bottom:12px;border-bottom:1px solid oklch(25% 0.02 260);padding-bottom:8px">Banco de Dados</h2>
<?php
if ($db && $dbType) {
    diagOk("Tipo: {$dbType}");
    try {
        $db->query('SELECT 1');
        diagOk('Conexão: OK');
    } catch (Exception $e) {
        diagFail('Conexão: FALHA', $e->getMessage());
    }
} else {
    diagFail('Banco não configurado', 'Execute install.php');
}
?>
</section>

<section style="margin-bottom:32px">
<h2 style="font-size:16px;color:oklch(80% 0.1 85);margin-bottom:12px;border-bottom:1px solid oklch(25% 0.02 260);padding-bottom:8px">S3 / R2</h2>
<?php
if (Storage::isS3Configured()) {
    diagOk('S3/R2 configurado');
    $cfg = S3::getResolvedConfig();
    diagOk("Endpoint: {$cfg['endpoint']}");
    diagOk("Bucket: {$cfg['bucket']}");
    diagOk("Public URL: {$cfg['public_url']}");
    if (function_exists('curl_version')) {
        $cv = curl_version();
        diagOk("cURL: {$cv['version']} (SSL: {$cv['ssl_version']})");
    }
} else {
    diagWarn('S3/R2 não configurado', 'Uploads usam armazenamento local');
}
?>
</section>

<section style="margin-bottom:32px">
<h2 style="font-size:16px;color:oklch(80% 0.1 85);margin-bottom:12px;border-bottom:1px solid oklch(25% 0.02 260);padding-bottom:8px">Config (debug)</h2>
<?php
$configLoaded = defined('DB_TYPE');
diagOk("DB_TYPE: " . (defined('DB_TYPE') ? DB_TYPE : 'NÃO DEFINIDO'));
diagOk("LOCAL_CONFIG: " . (defined('LOCAL_CONFIG') ? LOCAL_CONFIG : 'NÃO DEFINIDO'));
diagOk("LOCAL_CONFIG_PERSISTENT: " . (defined('LOCAL_CONFIG_PERSISTENT') ? LOCAL_CONFIG_PERSISTENT : 'NÃO DEFINIDO'));
diagOk("Active config existe: " . (file_exists(DATA_PATH . '/config.local.php') || file_exists(dirname(ROOT_PATH) . '/config.local.php') ? 'SIM' : 'NÃO'));
?>
</section>

<section style="margin-bottom:32px">
<h2 style="font-size:16px;color:oklch(80% 0.1 85);margin-bottom:12px;border-bottom:1px solid oklch(25% 0.02 260);padding-bottom:8px">Teste de Queries</h2>
<?php
if ($db && $dbType) {
    $queries = [
        'SELECT COUNT(*) as c FROM banners' => 'banners (count)',
        'SELECT * FROM banners ORDER BY sort_order ASC, id DESC LIMIT 5 OFFSET 0' => 'banners (list)',
        'SELECT COUNT(*) as c FROM games g LEFT JOIN engines e ON g.engine = e.name' => 'games+engines (count)',
        'SELECT g.*, COALESCE(e.active, 0) as engine_active FROM games g LEFT JOIN engines e ON g.engine = e.name ORDER BY g.sort_order ASC, g.id DESC LIMIT 5 OFFSET 0' => 'games+engines (list)',
        'SELECT COUNT(*) as c FROM blog_posts' => 'blog_posts (count)',
        'SELECT COUNT(*) as c FROM social_links' => 'social_links (count)',
        'SELECT COUNT(*) as c FROM testimonials' => 'testimonials (count)',
        'SELECT COUNT(*) as c FROM faq_items' => 'faq_items (count)',
        'SELECT COUNT(*) as c FROM team_members' => 'team_members (count)',
        'SELECT COUNT(*) as c FROM engines' => 'engines (count)',
        'SELECT COUNT(*) as c FROM store_platforms' => 'store_platforms (count)',
    ];
    foreach ($queries as $sql => $label) {
        try {
            $start = microtime(true);
            $result = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $ms = round((microtime(true) - $start) * 1000, 1);
            $count = is_array($result) ? count($result) : 1;
            diagOk("{$label}: {$count} rows ({$ms}ms)");
        } catch (Exception $e) {
            diagFail("{$label}: FALHA", $e->getMessage());
        }
    }
    // Test LIMIT/OFFSET interpolated as int (MariaDB compat — paginateQuery behavior)
    try {
        $perPage = 5;
        $offset = 0;
        $stmt = $db->query('SELECT * FROM banners LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset);
        $stmt->fetchAll();
        diagOk('LIMIT/OFFSET (int): OK');
    } catch (Exception $e) {
        diagFail('LIMIT/OFFSET (int): FALHA', $e->getMessage());
    }
} else {
    diagFail('Banco não disponível para testes de query');
}
?>
</section>

</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
