<?php
ob_start();
$pageTitle = 'Configurações';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { flashMessage('error', 'Token inválido.'); ob_end_clean(); header('Location: settings.php'); exit; }

    $settings = [
        'site_name' => trim($_POST['site_name']),
        'site_tagline' => trim($_POST['site_tagline']),
        'hero_title' => trim($_POST['hero_title']),
        'hero_subtitle' => trim($_POST['hero_subtitle']),
        'contact_email' => trim($_POST['contact_email']),
        'contact_whatsapp' => trim($_POST['contact_whatsapp']),
        'youtube_url' => trim($_POST['youtube_url']),
        'twitch_url' => trim($_POST['twitch_url']),
        'blog_url' => trim($_POST['blog_url']),
        'footer_description' => trim($_POST['footer_description']),
    ];

    foreach ($settings as $key => $value) {
        setSetting($key, $value);
    }
    flashMessage('success', 'Configurações salvas!');
    ob_end_clean();
    header('Location: settings.php');
    exit;
}

$settings = [];
$keys = ['site_name', 'site_tagline', 'hero_title', 'hero_subtitle', 'contact_email', 'contact_whatsapp', 'youtube_url', 'twitch_url', 'blog_url', 'footer_description'];
foreach ($keys as $key) {
    $settings[$key] = getSetting($key, '');
}
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Configurações do Site</h2>
        <div style="margin-top: 8px;">
            <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: <?= DB_TYPE === 'mysql' ? 'oklch(68% 0.16 220 / 0.15)' : 'oklch(75% 0.15 85 / 0.15)' ?>; color: <?= DB_TYPE === 'mysql' ? 'oklch(68% 0.16 220)' : 'oklch(75% 0.15 85)' ?>;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: <?= DB_TYPE === 'mysql' ? 'oklch(68% 0.16 220)' : 'oklch(75% 0.15 85)' ?>;"></span>
                Banco: <?= DB_TYPE === 'mysql' ? 'MySQL' : 'SQLite' ?>
            </span>
        </div>
    </div>
    <div class="card-body">
    <form method="POST">
        <input type="hidden" name="action" value="save">
        <?= csrfField() ?>

        <h3 class="form-section-title">Informações Gerais</h3>
        <div class="form-row">
            <div class="form-group"><label for="site_name">Nome do Site</label><input type="text" id="site_name" name="site_name" value="<?= e($settings['site_name']) ?>"></div>
            <div class="form-group"><label for="site_tagline">Tagline</label><input type="text" id="site_tagline" name="site_tagline" value="<?= e($settings['site_tagline']) ?>"></div>
        </div>

        <h3 class="form-section-title">Hero / Banner Principal</h3>
        <div class="form-group"><label for="hero_title">Título do Hero (HTML permitido)</label><textarea id="hero_title" name="hero_title" rows="2"><?= e($settings['hero_title']) ?></textarea></div>
        <div class="form-group"><label for="hero_subtitle">Subtítulo do Hero</label><textarea id="hero_subtitle" name="hero_subtitle" rows="3"><?= e($settings['hero_subtitle']) ?></textarea></div>

        <h3 class="form-section-title">Contato</h3>
        <div class="form-row">
            <div class="form-group"><label for="contact_email">Email de Contato</label><input type="email" id="contact_email" name="contact_email" value="<?= e($settings['contact_email']) ?>"></div>
            <div class="form-group"><label for="contact_whatsapp">WhatsApp (número com DDD)</label><input type="text" id="contact_whatsapp" name="contact_whatsapp" value="<?= e($settings['contact_whatsapp']) ?>" placeholder="5511999999999"></div>
        </div>

        <h3 class="form-section-title">Redes Sociais</h3>
        <div class="form-row">
            <div class="form-group"><label for="youtube_url">YouTube URL</label><input type="url" id="youtube_url" name="youtube_url" value="<?= e($settings['youtube_url']) ?>"></div>
            <div class="form-group"><label for="twitch_url">Twitch URL</label><input type="url" id="twitch_url" name="twitch_url" value="<?= e($settings['twitch_url']) ?>"></div>
        </div>
        <div class="form-group"><label for="blog_url">Blog URL</label><input type="url" id="blog_url" name="blog_url" value="<?= e($settings['blog_url']) ?>"></div>

        <h3 class="form-section-title">Footer</h3>
        <div class="form-group"><label for="footer_description">Descrição do Footer</label><textarea id="footer_description" name="footer_description" rows="3"><?= e($settings['footer_description']) ?></textarea></div>

        <div class="form-actions">
            <button type="submit" class="btn btn-gold">Salvar Configurações</button>
        </div>
    </form>
    </div>
</div>

<!-- Migração SQLite → MySQL -->
<?php if (DB_TYPE === 'sqlite'): ?>
<div class="card" style="margin-top: 24px; border-color: oklch(68% 0.16 220 / 0.3);">
    <div class="card-header">
        <h2 class="card-title">Migrar para MySQL</h2>
    </div>
    <div class="card-body">
        <?php
        $migrateMessage = '';
        $migrateSuccess = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'migrate') {
            if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
                $migrateMessage = '<div class="status error">Token inválido.</div>';
            } else {
                try {
                    $host = $_POST['db_host'] ?? '127.0.0.1';
                    $port = $_POST['db_port'] ?? '3306';
                    $name = $_POST['db_name'] ?? 'jogatinando';
                    $user = $_POST['db_user'] ?? 'root';
                    $pass = $_POST['db_pass'] ?? '';

                    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
                    $mysql = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    ]);

                    // Validar credenciais do admin antes de começar
                    $adminUser = trim($_POST['admin_user'] ?? '');
                    $adminPass = $_POST['admin_pass'] ?? '';
                    if ($adminUser === '' || $adminPass === '') {
                        throw new Exception('Defina o usuário e senha do administrador para continuar.');
                    }
                    $adminHash = password_hash($adminPass, PASSWORD_DEFAULT);

                    require_once ROOT_PATH . '/includes/migrations.php';

                    // DDL (auto-commit) — cria tabelas e limpa dados anteriores
                    migration_001($mysql, 'mysql');
                    $mysql->exec("SET FOREIGN_KEY_CHECKS = 0");
                    foreach (['users', 'banners', 'games', 'blog_posts', 'testimonials', 'faq_items', 'team_members', 'site_settings', 'schema_version'] as $t) {
                        $mysql->exec("TRUNCATE TABLE `$t`");
                    }
                    $mysql->exec("SET FOREIGN_KEY_CHECKS = 1");

                    // Transação — cópia dos dados + admin + schema_version
                    $mysql->beginTransaction();
                    try {
                        $mysql->exec("INSERT INTO schema_version (version, name) VALUES (1, 'create_all_tables')");

                        $stmtUpd = $mysql->prepare("UPDATE users SET username = ?, password_hash = ? WHERE id = 1");

                        $sqlite = getDB();
                        foreach (['users', 'banners', 'games', 'blog_posts', 'testimonials', 'faq_items', 'team_members', 'site_settings'] as $table) {
                            $rows = $sqlite->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                            if (empty($rows)) continue;
                            $columns = array_keys($rows[0]);
                            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                            $cols = implode(', ', array_map(fn($c) => "`$c`", $columns));
                            $stmt = $mysql->prepare("INSERT INTO `$table` ($cols) VALUES ($placeholders)");
                            foreach ($rows as $row) {
                                $stmt->execute(array_values($row));
                            }
                        }

                        // Atualizar admin após copiar dados dos usuários
                        $stmtUpd->execute([$adminUser, $adminHash]);

                        $mysql->commit();
                    } catch (Exception $e) {
                        $mysql->rollBack();
                        throw $e;
                    }

                    // Write config.local.php
                    $localConfig = '<?php' . "\n\n";
                    $localConfig .= "if (!defined('DB_TYPE')) {\n";
                    $localConfig .= "    define('DB_TYPE', 'mysql');\n";
                    $localConfig .= "    define('DB_HOST', '$host');\n";
                    $localConfig .= "    define('DB_PORT', '$port');\n";
                    $localConfig .= "    define('DB_NAME', '$name');\n";
                    $localConfig .= "    define('DB_USER', '$user');\n";
                    $localConfig .= "    define('DB_PASS', '$pass');\n";
                    $localConfig .= "}\n\n";

                    if (file_exists(ROOT_PATH . '/config.local.php')) {
                        $existing = file_get_contents(ROOT_PATH . '/config.local.php');
                        if (preg_match_all('/define\(\'(SMTP_\w+)\',\s*\'(.*?)\'\);/', $existing, $m)) {
                            $localConfig .= "if (!defined('SMTP_PASS')) {\n";
                            foreach ($m[1] as $i => $const) {
                                $localConfig .= "    define('$const', '" . addslashes($m[2][$i]) . "');\n";
                            }
                            $localConfig .= "}\n";
                        }
                    }
                    file_put_contents(ROOT_PATH . '/config.local.php', $localConfig);

                    $migrateMessage = '<div class="status success">Migração concluída! O sistema agora está usando MySQL.</div>';
                    $migrateSuccess = true;
                } catch (Exception $ex) {
                    $migrateMessage = '<div class="status error">' . e($ex->getMessage()) . '</div>';
                }
            }
        }
        echo $migrateMessage;
        ?>
        <?php if (!$migrateSuccess): ?>
        <p style="margin-bottom: 16px; color: oklch(68% 0.16 220);">
            Seus dados atuais (SQLite) serão copiados para um banco MySQL. O arquivo SQLite original será preservado.
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="migrate">
            <?= csrfField() ?>
            <div class="form-row">
                <div class="form-group"><label for="db_host">Host</label><input type="text" id="db_host" name="db_host" value="<?= e(DB_HOST) ?>"></div>
                <div class="form-group"><label for="db_port">Porta</label><input type="text" id="db_port" name="db_port" value="<?= e(DB_PORT) ?>"></div>
            </div>
            <div class="form-group"><label for="db_name">Database</label><input type="text" id="db_name" name="db_name" value="<?= e(DB_NAME) ?>"></div>
            <div class="form-row">
                <div class="form-group"><label for="db_user">Usuário MySQL</label><input type="text" id="db_user" name="db_user" value="<?= e(DB_USER) ?>"></div>
                <div class="form-group"><label for="db_pass">Senha MySQL</label><input type="password" id="db_pass" name="db_pass" value="<?= e(DB_PASS) ?>"></div>
            </div>

            <h3 class="form-section-title" style="margin-top: 24px;">Administrador</h3>
            <p style="color: oklch(60% 0.012 250); font-size: 13px; margin-bottom: 16px;">Defina o novo login e senha do painel admin para o MySQL.</p>
            <div class="form-row">
                <div class="form-group"><label for="admin_user">* Usuário Admin</label><input type="text" id="admin_user" name="admin_user" placeholder="admin" required></div>
                <div class="form-group"><label for="admin_pass">* Senha Admin</label><input type="password" id="admin_pass" name="admin_pass" placeholder="Nova senha" required></div>
            </div>

            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, oklch(68% 0.16 220), oklch(55% 0.14 220)); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer;">Migrar para MySQL</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; // DB_TYPE === 'sqlite' ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
