<?php

require_once __DIR__ . '/../config.php';

function fail($message) {
    fwrite(STDERR, "FALHA: {$message}\n");
    exit(1);
}

function ok($message) {
    fwrite(STDOUT, "OK: {$message}\n");
}

function request($url, $method = 'GET', $fields = null, &$headersOut = null, &$bodyOut = null, $cookieFile = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERAGENT, 'JogatinandoSmokeTest/1.0');
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields ?? []));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    }
    $response = curl_exec($ch);
    if ($response === false) {
        fail('curl: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headersOut = substr($response, 0, $headerSize);
    $bodyOut = substr($response, $headerSize);
    curl_close($ch);
    return $status;
}

function extractCsrf($html) {
    if (!preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m)) {
        fail('CSRF não encontrado na página');
    }
    return $m[1];
}

function pageContains($html, $needle, $label) {
    if (strpos($html, $needle) === false) {
        fail($label . ' sem conteúdo esperado');
    }
}

function buildMultipartFile($path, $mime = 'image/png', $name = null) {
    return new CURLFile($path, $mime, $name ?: basename($path));
}

function postMultipart($url, array $fields, $cookieFile, &$headersOut, &$bodyOut) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERAGENT, 'JogatinandoSmokeTest/1.0');
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    $response = curl_exec($ch);
    if ($response === false) {
        fail('curl: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headersOut = substr($response, 0, $headerSize);
    $bodyOut = substr($response, $headerSize);
    curl_close($ch);
    return $status;
}

$base = getenv('BASE_URL') ?: 'http://localhost';
$login = $base . '/admin/login.php';
$home = $base . '/';
$admin = $base . '/admin/index.php';
$social = $base . '/admin/social-links.php';
$ads = $base . '/admin/ads.php';
$distribution = $base . '/admin/distribution.php';

$cookieFile = tempnam(sys_get_temp_dir(), 'jogatinando_smoke_');
if ($cookieFile === false) {
    fail('não foi possível criar cookie jar');
}

$headers = '';
$body = '';
$status = request($home, 'GET', null, $headers, $body, $cookieFile);
if ($status !== 200) fail('home deveria responder 200, veio ' . $status);
if (strpos($body, 'CMS') === false && strpos($body, 'Jogatinando') === false) fail('home sem marca esperada');
ok('home responde e contém marca');

$status = request($login, 'GET', null, $headers, $body, $cookieFile);
if ($status !== 200) fail('login deveria responder 200, veio ' . $status);
if (!preg_match('/name="csrf_token" value="([^"]+)"/', $body, $m)) fail('CSRF não encontrado no login');
$csrf = $m[1];
ok('login entrega CSRF');

$post = [
    'csrf_token' => $csrf,
    'username' => getenv('ADMIN_USERNAME') ?: 'sorameshi',
    'password' => getenv('ADMIN_PASSWORD') ?: 'lotus10',
];
$status = request($login, 'POST', $post, $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) {
    fail('login não redirecionou após autenticação');
}
if (strpos($headers, 'Location:') === false) fail('login sem redirect');
ok('login autenticou e redirecionou');

$status = request($admin, 'GET', null, $headers, $body, $cookieFile);
if ($status !== 200) fail('admin dashboard deveria responder 200 após login, veio ' . $status);
if (strpos($body, 'Dashboard') === false) fail('admin dashboard sem conteúdo esperado');
ok('admin dashboard responde após login');

foreach ([$social => 'Redes Sociais', $ads => 'Publicidade', $distribution => 'Distribuição'] as $url => $needle) {
    $status = request($url, 'GET', null, $headers, $body, $cookieFile);
    if ($status !== 200) fail($url . ' deveria responder 200 após login, veio ' . $status);
    if (strpos($body, $needle) === false) fail($url . ' sem conteúdo esperado');
    ok($needle . ' responde após login');
}

// CRUD social links
$status = request($social, 'GET', null, $headers, $body, $cookieFile);
$socialCsrf = extractCsrf($body);
$socialName = 'Smoke Test ' . substr(md5((string)microtime(true)), 0, 8);
$socialUrl = 'https://example.com/' . strtolower(substr(md5($socialName), 0, 8));
$post = [
    'csrf_token' => $socialCsrf,
    'action' => 'save',
    'id' => 0,
    'scope' => 'site',
    'platform_key' => 'website',
    'label' => $socialName,
    'url' => $socialUrl,
    'active' => 1,
    'sort_order' => 99,
    'image_file' => buildMultipartFile(__DIR__ . '/fixtures/social-link.png'),
];
$status = postMultipart($social, $post, $cookieFile, $headers, $body);
if ($status >= 500) fail('social-links gerou erro ao salvar');
$status = request($social, 'GET', null, $headers, $body, $cookieFile);
pageContains($body, 'website', 'social-links CRUD');
pageContains($body, $socialUrl, 'social-links CRUD');
ok('social-links criou item');
if (!preg_match('/<tr>\s*<td><input[^>]*class="row-select"[^>]*><\/td>\s*<td>site<\/td>\s*<td>website<\/td>\s*<td>' . preg_quote($socialUrl, '/') . '<\/td>[\s\S]*?<input type="hidden" name="id" value="(\d+)"/m', $body, $m)) {
    fail('não foi possível localizar o ID do social link criado');
}
$socialId = (int)$m[1];
$status = request($social, 'POST', [
    'csrf_token' => $socialCsrf,
    'action' => 'delete',
    'id' => $socialId,
], $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) fail('social-links não redirecionou após excluir');
ok('social-links removeu item');

$status = request($social, 'GET', null, $headers, $body, $cookieFile);
$socialCsrf = extractCsrf($body);

// Clean up any leftover twitch links in footer from previous runs
preg_match_all('/<tr>\s*<td><input[^>]*class="row-select"[^>]*value="(\d+)"[^>]*><\/td>\s*<td>footer<\/td>\s*<td>twitch<\/td>/m', $body, $leftovers);
foreach ($leftovers[1] as $leftoverId) {
    request($social, 'POST', ['csrf_token' => $socialCsrf, 'action' => 'delete', 'id' => (int)$leftoverId], $headers, $body, $cookieFile);
}
if (!empty($leftovers[1])) {
    $status = request($social, 'GET', null, $headers, $body, $cookieFile);
    $socialCsrf = extractCsrf($body);
}

$socialName = 'Smoke Test Custom ' . substr(md5((string)microtime(true) . 'custom'), 0, 8);
$socialUrl = 'https://example.com/' . strtolower(substr(md5($socialName), 0, 8));
$status = postMultipart($social, [
    'csrf_token' => $socialCsrf,
    'action' => 'save',
    'id' => 0,
    'scope' => 'footer',
    'platform_key' => 'twitch',
    'label' => $socialName,
    'url' => $socialUrl,
    'custom_image' => 1,
    'active' => 1,
    'sort_order' => 98,
    'image_file' => buildMultipartFile(__DIR__ . '/fixtures/social-link.png'),
], $cookieFile, $headers, $body);
if ($status >= 500) fail('social-links custom image gerou erro ao salvar');
$status = request($social, 'GET', null, $headers, $body, $cookieFile);
pageContains($body, $socialUrl, 'social-links custom image');
ok('social-links salva imagem customizada em plataforma comum');
if (preg_match('/<tr>\s*<td><input[^>]*class="row-select"[^>]*value="(\d+)"[^>]*><\/td>\s*<td>footer<\/td>\s*<td>twitch<\/td>\s*<td>' . preg_quote($socialUrl, '/') . '/m', $body, $m2)) {
    $customId = (int)$m2[1];
    request($social, 'POST', ['csrf_token' => $socialCsrf, 'action' => 'delete', 'id' => $customId], $headers, $body, $cookieFile);
}

$tmpBad = tempnam(sys_get_temp_dir(), 'jogatinando_bad_');
if ($tmpBad === false) {
    fail('não foi possível criar arquivo temporário para teste de upload inválido');
}
file_put_contents($tmpBad, "<?php echo 'x'; ?>");
$directResult = uploadFile([
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmpBad),
    'name' => 'bad.php',
    'tmp_name' => $tmpBad,
], 'social-links', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
@unlink($tmpBad);
if ($directResult['success']) {
    fail('upload proibido deveria ser rejeitado pelo helper');
}
if (strpos($directResult['message'], 'Extensão') === false && strpos($directResult['message'], 'Tipo') === false) {
    fail('rejeição do upload proibido veio com mensagem inesperada');
}
ok('upload proibido rejeitado pelo helper');

// CRUD ads
$status = request($ads, 'GET', null, $headers, $body, $cookieFile);
$adsCsrf = extractCsrf($body);
$slotKey = 'smoke_' . substr(md5((string)microtime(true) . 'ad'), 0, 10);
$slotName = 'Slot ' . $slotKey;
$post = [
    'csrf_token' => $adsCsrf,
    'action' => 'save',
    'id' => 0,
    'slot_key' => $slotKey,
    'name' => $slotName,
    'provider' => 'custom_html',
    'code_html' => '<div>ad</div>',
    'active' => 1,
    'pages' => 'all',
    'devices' => 'all',
    'sticky' => 0,
    'height_desktop' => '90px',
    'height_mobile' => '60px',
    'fallback_text' => 'Ad smoke',
];
$status = request($ads, 'POST', $post, $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) fail('ads não redirecionou após salvar');
$status = request($ads, 'GET', null, $headers, $body, $cookieFile);
pageContains($body, $slotKey, 'ads CRUD');
ok('ads criou slot');
if (!preg_match('/<tr>\s*<td>' . preg_quote($slotKey, '/') . '<\/td>[\s\S]*?<input type="hidden" name="id" value="(\d+)"/m', $body, $m)) {
    fail('não foi possível localizar o ID do ad slot criado');
}
$slotId = (int)$m[1];
$status = request($ads, 'POST', [
    'csrf_token' => $adsCsrf,
    'action' => 'delete',
    'id' => $slotId,
], $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) fail('ads não redirecionou após excluir');
ok('ads removeu slot');

// CRUD distribution campaign
$status = request($distribution, 'GET', null, $headers, $body, $cookieFile);
$distCsrf = extractCsrf($body);
$campaignName = 'Campanha Smoke ' . substr(md5((string)microtime(true) . 'dist'), 0, 8);
$post = [
    'csrf_token' => $distCsrf,
    'action' => 'save_campaign',
    'id' => 0,
    'name' => $campaignName,
    'game_id' => 0,
    'platform_id' => 0,
    'status' => 'draft',
    'budget' => '0',
    'start_at' => '',
    'end_at' => '',
    'notes' => 'smoke',
];
$status = request($distribution, 'POST', $post, $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) fail('distribution não redirecionou após salvar campanha');
$status = request($distribution, 'GET', null, $headers, $body, $cookieFile);
pageContains($body, $campaignName, 'distribution campaign CRUD');
ok('distribution criou campanha');
if (!preg_match('/<tr>\s*<td><input[^>]*class="row-select"[^>]*><\/td>\s*<td>' . preg_quote($campaignName, '/') . '<\/td>[\s\S]*?<input type="hidden" name="id" value="(\d+)"/m', $body, $m)) {
    fail('não foi possível localizar o ID da campanha criada');
}
$campaignId = (int)$m[1];
$status = request($distribution, 'POST', [
    'csrf_token' => $distCsrf,
    'action' => 'delete_campaign',
    'id' => $campaignId,
], $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) fail('distribution não redirecionou após excluir campanha');
ok('distribution removeu campanha');

// CRUD distribution integration
$intName = 'Integração Smoke ' . substr(md5((string)microtime(true) . 'int'), 0, 8);
$post = [
    'csrf_token' => $distCsrf,
    'action' => 'save_integration',
    'id' => 0,
    'platform_id' => 1,
    'name' => $intName,
    'integration_type' => 'manual',
    'config_json' => '{"api_key": "test123"}',
    'active' => 1,
];
$status = request($distribution, 'POST', $post, $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) fail('distribution não redirecionou após salvar integração');
$status = request($distribution, 'GET', null, $headers, $body, $cookieFile);
pageContains($body, $intName, 'integration CRUD');
ok('distribution criou integração');
if (!preg_match('/<tr>\s*<td><input[^>]*class="row-select"[^>]*><\/td>\s*<td>' . preg_quote($intName, '/') . '<\/td>[\s\S]*?<input type="hidden" name="id" value="(\d+)"/m', $body, $m)) {
    fail('não foi possível localizar o ID da integração criada');
}
$intId = (int)$m[1];
$status = request($distribution, 'POST', [
    'csrf_token' => $distCsrf,
    'action' => 'delete_integration',
    'id' => $intId,
], $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) fail('distribution não redirecionou após excluir integração');
ok('distribution removeu integração');

// CRUD distribution game link
$glName = 'GameLink Smoke ' . substr(md5((string)microtime(true) . 'gl'), 0, 8);
$post = [
    'csrf_token' => $distCsrf,
    'action' => 'save_game_link',
    'id' => 0,
    'game_id' => 1,
    'platform_id' => 1,
    'integration_id' => 0,
    'store_url' => 'https://store.steampowered.com/app/123456',
    'store_package_id' => 'com.test.' . $glName,
    'store_status' => 'published',
    'version_name' => '1.0.0',
];
$status = request($distribution, 'POST', $post, $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) fail('distribution não redirecionou após salvar game link');
$status = request($distribution, 'GET', null, $headers, $body, $cookieFile);
ok('distribution criou link de jogo');
if (!preg_match('/delete_game_link.*?name="id" value="(\d+)"/ms', $body, $m)) {
    fail('não foi possível localizar o ID do game link criado');
}
$glLinkId = (int)$m[1];
$status = request($distribution, 'POST', [
    'csrf_token' => $distCsrf,
    'action' => 'delete_game_link',
    'id' => $glLinkId,
], $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) fail('distribution não redirecionou após excluir game link');
ok('distribution removeu link de jogo');

// CRUD distribution metric (game_distribution_stats)
$metricKey = 'views';
$post = [
    'csrf_token' => $distCsrf,
    'action' => 'save_metric',
    'id' => 0,
    'campaign_id' => 0,
    'game_id' => 1,
    'platform_id' => 1,
    'metric_key' => $metricKey,
    'metric_value' => '42',
    'period_start' => date('Y-m-d'),
    'period_end' => date('Y-m-d'),
    'source' => 'manual',
];
$status = request($distribution, 'POST', $post, $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) fail('distribution não redirecionou após salvar métrica de jogo');
$status = request($distribution, 'GET', null, $headers, $body, $cookieFile);
pageContains($body, '42', 'game distribution stats metric CRUD');
ok('distribution criou métrica de jogo');
if (!preg_match('/<tr>\s*<td><input[^>]*class="row-select"[^>]*><\/td>\s*<td>[^<]*<\/td>\s*<td>[^<]*<\/td>\s*<td[^>]*>[^<]*Visualizações[^<]*<\/td>\s*<td>42<\/td>[\s\S]*?<input type="hidden" name="id" value="(\d+)"/m', $body, $m)) {
    fail('não foi possível localizar o ID da métrica de jogo criada');
}
$metricId = (int)$m[1];
$status = request($distribution, 'POST', [
    'csrf_token' => $distCsrf,
    'action' => 'delete_metric',
    'target' => 'game_stat',
    'id' => $metricId,
], $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) fail('distribution não redirecionou após excluir métrica de jogo');
ok('distribution removeu métrica de jogo');

@unlink($cookieFile);
ok('smoke test concluído');
