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
$aiSettings = $base . '/admin/ai-settings.php';
$games = $base . '/admin/games.php';

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

// AI Settings page
$status = request($aiSettings, 'GET', null, $headers, $body, $cookieFile);
if ($status !== 200) fail('ai-settings deveria responder 200, veio ' . $status);
pageContains($body, 'Configurações de IA', 'ai-settings page');
pageContains($body, 'Uso de IA', 'ai-settings usage section');
pageContains($body, 'Teste Rápido de IA', 'ai-settings chat widget');
ok('ai-settings responde após login');

// Test AI chat endpoint
$aiChatCsrf = extractCsrf($body);
$status = request($aiSettings, 'POST', [
    'csrf_token' => $aiChatCsrf,
    'action' => 'ai_chat',
    'prompt' => 'Olá, tudo bem?',
], $headers, $body, $cookieFile);
// Chat endpoint returns JSON with 200, or redirects if config changed
if ($status !== 200) fail('ai-chat POST deveria responder 200, veio ' . $status);
ok('ai-settings chat endpoint responde');

// AI game description generation
$status = request($games . '?action=edit&id=1', 'GET', null, $headers, $body, $cookieFile);
if ($status !== 200) fail('games edit page deveria responder 200, veio ' . $status);
$gamesCsrf = extractCsrf($body);
$status = request($games . '?action=edit&id=1', 'POST', [
    'csrf_token' => $gamesCsrf,
    'action' => 'ai_generate_description',
    'game_title' => 'Jogo Teste',
    'engine' => 'GDevelop',
    'genre' => '',
], $headers, $body, $cookieFile);
// Debug: print status and body
$bodyLen = strlen($body);
if ($status === 302) {
    // Redirect — likely CSRF failure. Check where it redirects.
    if (preg_match('/Location: ([^\r\n]+)/i', $headers, $loc)) {
        fail('ai-generate redirected (CSRF?): ' . trim($loc[1]) . " | body: " . substr($body, 0, 200));
    }
}
// AI may be unavailable (no API key) — endpoint should still return JSON
$decoded = json_decode($body, true);
if ($decoded === null) fail('ai-generate-description deveria retornar JSON. Status: ' . $status . " Resposta: " . substr($body, 0, 300));
ok('ai-generate-description responde');

// AI blog content generation
$blog = $base . '/admin/blog.php';
$status = request($blog . '?action=new', 'GET', null, $headers, $body, $cookieFile);
if ($status !== 200) fail('blog new page deveria responder 200');
$blogCsrf = extractCsrf($body);
$status = request($blog, 'POST', [
    'csrf_token' => $blogCsrf,
    'action' => 'ai_generate_blog',
    'title' => 'Notícia de Teste',
    'excerpt' => '',
], $headers, $body, $cookieFile);
if ($status === 302) {
    if (preg_match('/Location: ([^\r\n]+)/i', $headers, $loc)) {
        fail('ai-generate-blog redirected (CSRF?): ' . trim($loc[1]));
    }
}
$decoded = json_decode($body, true);
if ($decoded === null) fail('ai-generate-blog deveria retornar JSON. Status: ' . $status . " Resposta: " . substr($body, 0, 300));
ok('ai-generate-blog responde');

// Newsletter admin page
$newsletter = $base . '/admin/newsletter.php';
$status = request($newsletter, 'GET', null, $headers, $body, $cookieFile);
if ($status !== 200) fail('newsletter admin page deveria responder 200, veio ' . $status);
pageContains($body, 'Newsletter', 'newsletter admin page');
pageContains($body, 'Inscritos', 'newsletter subscribers section');
ok('newsletter admin responde');

// Create newsletter subscriber
$nlCsrf = extractCsrf($body);
$nlEmail = 'newsletter-' . substr(md5(microtime(true)), 0, 8) . '@test.com';
$status = request($newsletter, 'POST', [
    'csrf_token' => $nlCsrf,
    'action' => 'edit',
    'id' => 0,
    'email' => $nlEmail,
    'name' => 'Test Subscriber',
    'tags' => 'smoke-test',
], $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) fail('newsletter não redirecionou após salvar');
$status = request($newsletter, 'GET', null, $headers, $body, $cookieFile);
pageContains($body, $nlEmail, 'newsletter CRUD');
ok('newsletter criou inscrito');

// Frontend newsletter signup
$status = request($base . '/subscribe.php', 'POST', [
    'email' => 'subscribe-' . substr(md5(microtime(true)), 0, 8) . '@test.com',
    'name' => 'Frontend User',
], $headers, $body, $cookieFile);
if ($status !== 200) fail('subscribe.php deveria responder 200');
$decoded = json_decode($body, true);
if ($decoded === null) fail('subscribe.php deveria retornar JSON');
if (!isset($decoded['success'])) fail('subscribe.php deveria retornar sucesso');
ok('frontend newsletter signup responde');

// Newsletter campaigns admin
$campaigns = $base . '/admin/newsletter-campaigns';
$status = request($campaigns, 'GET', null, $headers, $body, $cookieFile);
if ($status !== 200) fail('newsletter-campaigns admin page deveria responder 200');
pageContains($body, 'Campanhas de Newsletter', 'campaigns page title');
ok('newsletter-campaigns admin responde');

// Create campaign
$status = request($campaigns . '?action=new', 'GET', null, $headers, $body, $cookieFile);
$camCsrf = extractCsrf($body);
$status = request($campaigns, 'POST', [
    'csrf_token' => $camCsrf,
    'action' => 'save',
    'id' => 0,
    'title' => 'Test Campaign',
    'subject' => 'Test Subject',
    'content' => '<p>Hello {name}!</p>',
    'sender_name' => 'Test CMS',
    'sender_email' => 'test@localhost',
    'scheduled_at' => '',
], $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) fail('campaign save should redirect');
$status = request($campaigns, 'GET', null, $headers, $body, $cookieFile);
pageContains($body, 'Test Campaign', 'campaign created');
ok('newsletter-campaigns cria campanha');

// Send test email
$status = request($campaigns, 'POST', [
    'csrf_token' => $camCsrf,
    'action' => 'save',
    'id' => 0,
    'title' => 'Test Campaign 2',
    'subject' => 'Test Subject 2',
    'content' => '<p>Test</p>',
    'sender_name' => 'Test CMS',
    'sender_email' => 'test@localhost',
    'scheduled_at' => '',
], $headers, $body, $cookieFile);
$status = request($campaigns, 'GET', null, $headers, $body, $cookieFile);
// Find campaign ID from the edit links on the page
preg_match_all('/action=edit&edit=(\d+)/', $body, $matches);
$campId = (int)($matches[1][0] ?? 0);
if ($campId === 0) fail('No campaign edit link found. Body: ' . substr($body, 0, 500));

// Test send
$status = request($campaigns, 'POST', [
    'csrf_token' => $camCsrf,
    'action' => 'send_test',
    'id' => $campId,
    'test_email' => 'admin@test.local',
], $headers, $body, $cookieFile);
$page = $body;
// Check redirect or success message
$page2 = '';
$status2 = request($campaigns, 'GET', null, $headers, $body, $cookieFile);
pageContains($body, 'Test Campaign', 'campaign still listed after test send');
ok('newsletter-campaigns test send responde');

// Donations admin page
$donations = $base . '/admin/donations.php';
$status = request($donations, 'GET', null, $headers, $body, $cookieFile);
if ($status !== 200) fail('donations admin page deveria responder 200');
pageContains($body, 'Configurações de Doações', 'donations page title');
ok('donations admin responde');

// Save donation settings
$donCsrf = extractCsrf($body);
$status = request($donations, 'POST', [
    'csrf_token' => $donCsrf,
    'donation_enabled' => 1,
    'pix_key' => 'test-pix-key-123',
    'pix_description' => 'Test donation',
    'paypal_url' => 'https://paypal.me/test',
    'custom_html' => '<p>Teste apoio:</p>',
], $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) fail('donations save should redirect');
$status = request($donations, 'GET', null, $headers, $body, $cookieFile);
pageContains($body, 'test-pix-key-123', 'donations settings saved');
ok('donations salva configurações');

// Frontend donation banner visible
$status = request($base, 'GET', null, $headers, $body, $cookieFile);
pageContains($body, 'showPixModal', 'frontend donation banner JS');
pageContains($body, 'Doação via PIX', 'frontend donation modal');
ok('frontend donation banner visível');

// ── Blog público + premium gating ──
$uniq = substr(md5(microtime(true)), 0, 8);
$normalSlug = 'post-publico-' . $uniq;
$premiumSlug = 'post-premium-' . $uniq;

$status = request($blog . '?action=new', 'GET', null, $headers, $body, $cookieFile);
if ($status !== 200) fail('blog new page deveria responder 200 para criar posts de teste');
$blogCsrf = extractCsrf($body);

// Post normal
$status = request($blog, 'POST', [
    'csrf_token' => $blogCsrf,
    'action' => 'save',
    'id' => 0,
    'title' => 'Post Publico ' . $uniq,
    'slug' => $normalSlug,
    'content' => 'Conteudo completo publico ' . $uniq . ' com todos os detalhes do artigo.',
    'external_url' => '',
    'active' => 1,
], $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) fail('criar post publico deveria redirecionar');

// Post premium
$fillerWords = trim(str_repeat('palavra-preenchimento ', 120));
$status = request($blog, 'POST', [
    'csrf_token' => $blogCsrf,
    'action' => 'save',
    'id' => 0,
    'title' => 'Post Premium ' . $uniq,
    'slug' => $premiumSlug,
    'content' => 'Teaser visivel do artigo premium. ' . $fillerWords . ' SEGREDO-PREMIUM-' . $uniq . ' conteudo que nao deve aparecer para visitantes.',
    'external_url' => '',
    'active' => 1,
    'is_premium' => 1,
], $headers, $body, $cookieFile);
if ($status < 300 || $status >= 400) fail('criar post premium deveria redirecionar');

// Listagem /blog
$status = request($base . '/blog', 'GET', null, $headers, $body, $cookieFile);
if ($status !== 200) fail('/blog deveria responder 200, veio ' . $status);
pageContains($body, $normalSlug, 'listagem blog contem post normal');
pageContains($body, $premiumSlug, 'listagem blog contem post premium');
ok('blog listagem responde');

// Post normal completo
$status = request($base . '/blog/' . $normalSlug, 'GET', null, $headers, $body, $cookieFile);
if ($status !== 200) fail('/blog/{slug} deveria responder 200, veio ' . $status);
pageContains($body, 'Conteudo completo publico ' . $uniq, 'post normal mostra conteudo integral');
ok('blog post publico exibe conteudo');

// Post premium com gate
$status = request($base . '/blog/' . $premiumSlug, 'GET', null, $headers, $body, $cookieFile);
if ($status !== 200) fail('/blog/{slug} premium deveria responder 200, veio ' . $status);
pageContains($body, 'Conteúdo Premium', 'post premium mostra gate');
if (strpos($body, 'SEGREDO-PREMIUM-' . $uniq) !== false) {
    fail('conteudo premium vazou para visitante!');
}
ok('blog premium bloqueia conteudo');

// Slug inexistente → 404
$status = request($base . '/blog/slug-inexistente-' . $uniq, 'GET', null, $headers, $body, $cookieFile);
if ($status !== 404) fail('slug inexistente deveria responder 404, veio ' . $status);
ok('blog 404 para slug invalido');

// ── Descadastro de newsletter (unsubscribe) ──
$unsubEmail = 'unsub-' . substr(md5(microtime(true)), 0, 8) . '@test.com';
$status = request($base . '/subscribe.php', 'POST', [
    'email' => $unsubEmail,
    'name' => 'Unsub Test',
], $headers, $body, $cookieFile);
if ($status !== 200) fail('subscribe para teste de descadastro deveria responder 200');

$sub = dbQueryOne("SELECT id, unsubscribe_token FROM newsletter_subscribers WHERE email = ?", [$unsubEmail]);
if (!$sub) fail('inscrito de teste nao encontrado no banco');
if (empty($sub['unsubscribe_token'])) fail('token de descadastro vazio');

$status = request($base . '/unsubscribe?token=' . urlencode($sub['unsubscribe_token']), 'GET', null, $headers, $body, $cookieFile);
if ($status !== 200) fail('/unsubscribe deveria responder 200, veio ' . $status);
pageContains($body, 'Descadastro confirmado', 'pagina de descadastro');
$after = dbQueryOne("SELECT is_active FROM newsletter_subscribers WHERE id = ?", [$sub['id']]);
if ((int)$after['is_active'] !== 0) fail('inscrito ainda ativo apos descadastro');
ok('descadastro desativa inscrito');

// Token inválido não desativa ninguém
$status = request($base . '/unsubscribe?token=token-invalido-xyz', 'GET', null, $headers, $body, $cookieFile);
pageContains($body, 'Link inválido', 'token invalido rejeitado');
ok('token invalido mostra erro');

// ── SEO ──
// robots.txt
$status = request($base . '/robots.txt', 'GET', null, $headers, $body, $cookieFile);
if ($status !== 200) fail('robots.txt deveria responder 200, veio ' . $status);
pageContains($body, 'Sitemap:', 'robots.txt contem sitemap');
ok('robots.txt responde');

// sitemap.xml
$status = request($base . '/sitemap.xml', 'GET', null, $headers, $body, $cookieFile);
if ($status !== 200) fail('sitemap.xml deveria responder 200, veio ' . $status);
pageContains($body, '<urlset', 'sitemap xml valido');
pageContains($body, '<loc>', 'sitemap tem URLs');
ok('sitemap.xml responde com XML valido');

// Open Graph no homepage
$status = request($base . '/', 'GET', null, $headers, $body, $cookieFile);
pageContains($body, 'og:title', 'homepage tem og:title');
pageContains($body, 'og:description', 'homepage tem og:description');
pageContains($body, 'og:image', 'homepage tem og:image');
ok('homepage tem meta tags Open Graph');

// Open Graph em post do blog
$status = request($base . '/blog', 'GET', null, $headers, $body, $cookieFile);
pageContains($body, 'og:title', 'blog listing tem og:title');
ok('blog listing tem Open Graph');

@unlink($cookieFile);
ok('smoke test concluído');
