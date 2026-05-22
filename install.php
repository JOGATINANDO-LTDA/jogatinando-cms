<?php

require_once 'config.php';

if (defined('APP_ENV') && APP_ENV === 'production') {
    header('Location: /');
    exit;
}

$isInstalled = false;
if (file_exists(DB_PATH) || (defined('DB_TYPE') && DB_TYPE === 'mysql')) {
    $db = getDB();
    if ($db) {
        $tables = getDbTables($db);
        $coreTables = ['games', 'banners', 'users', 'blog_posts', 'testimonials', 'faq_items', 'team_members', 'site_settings'];
        if (count(array_intersect($tables, $coreTables)) > 0) {
            $isInstalled = true;
        }
    }
}

if ($isInstalled) {
    header('Location: /');
    exit;
}

$message = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'sqlite') {
        try {
            dbInit();
            $message = 'success';
        } catch (Exception $ex) {
            $message = 'error: ' . $ex->getMessage();
        }
    } elseif ($_POST['action'] === 'mysql') {
        try {
            $host = $_POST['db_host'] ?? '127.0.0.1';
            $port = $_POST['db_port'] ?? '3306';
            $name = $_POST['db_name'] ?? 'jogatinando';
            $user = $_POST['db_user'] ?? 'root';
            $pass = $_POST['db_pass'] ?? '';

            $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
            dbInit($dsn, $user, $pass, 'mysql');
            $message = 'success';

            writeMysqlConfig($host, $port, $name, $user, $pass);
        } catch (Exception $ex) {
            $message = 'error: ' . $ex->getMessage();
        }
    }
}

function writeMysqlConfig($host, $port, $name, $user, $pass) {
    $localConfig = '<?php' . "\n\n";
    $localConfig .= "if (!defined('DB_TYPE')) {\n";
    $localConfig .= "    define('DB_TYPE', 'mysql');\n";
    $localConfig .= "    define('DB_HOST', '$host');\n";
    $localConfig .= "    define('DB_PORT', '$port');\n";
    $localConfig .= "    define('DB_NAME', '$name');\n";
    $localConfig .= "    define('DB_USER', '$user');\n";
    $localConfig .= "    define('DB_PASS', '$pass');\n";
    $localConfig .= "}\n\n";

    if (file_exists(__DIR__ . '/config.local.php')) {
        $existing = file_get_contents(__DIR__ . '/config.local.php');
        if (preg_match_all('/define\(\'(SMTP_\w+)\',\s*\'(.*?)\'\);/', $existing, $m)) {
            $localConfig .= "if (!defined('SMTP_PASS')) {\n";
            foreach ($m[1] as $i => $const) {
                $localConfig .= "    define('$const', '" . addslashes($m[2][$i]) . "');\n";
            }
            $localConfig .= "}\n";
        }
    }

    file_put_contents(__DIR__ . '/config.local.php', $localConfig);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jogatinando CMS — Instalação</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: oklch(10% 0.03 260); color: oklch(96% 0.003 250); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .install-card { background: oklch(16% 0.035 265); border: 1px solid oklch(55% 0.12 85); border-radius: 12px; padding: 40px; max-width: 560px; width: 100%; }
        .install-card h1 { font-family: 'Cinzel', Georgia, serif; font-size: 24px; color: oklch(75% 0.15 85); margin-bottom: 8px; text-align: center; letter-spacing: 0.04em; }
        .install-card p { color: oklch(60% 0.012 250); margin-bottom: 24px; text-align: center; line-height: 1.6; }
        .status { padding: 16px; border-radius: 8px; margin-bottom: 24px; text-align: center; }
        .status.success { background: oklch(65% 0.18 145 / 0.15); border: 1px solid oklch(65% 0.18 145); color: oklch(65% 0.18 145); }
        .status.error { background: oklch(55% 0.20 25 / 0.15); border: 1px solid oklch(55% 0.20 25); color: oklch(55% 0.20 25); }
        .status.info { background: oklch(68% 0.16 220 / 0.15); border: 1px solid oklch(68% 0.16 220); color: oklch(68% 0.16 220); }
        .btn { display: block; width: 100%; padding: 14px; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; margin-bottom: 12px; }
        .btn-gold { background: linear-gradient(135deg, oklch(75% 0.15 85), oklch(62% 0.13 85)); color: oklch(8% 0.02 260); }
        .btn-gold:hover { background: linear-gradient(135deg, oklch(85% 0.13 85), oklch(75% 0.15 85)); }
        .btn-outline { background: transparent; border: 1px solid oklch(55% 0.12 85); color: oklch(75% 0.15 85); }
        .btn-outline:hover { background: oklch(75% 0.15 85 / 0.1); }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; color: oklch(70% 0.02 250); margin-bottom: 4px; font-size: 13px; }
        .form-group input { width: 100%; padding: 10px 12px; border: 1px solid oklch(30% 0.03 260); border-radius: 6px; background: oklch(12% 0.02 260); color: oklch(96% 0.003 250); font-size: 14px; }
        .form-group input:focus { outline: none; border-color: oklch(75% 0.15 85); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .steps { display: flex; gap: 8px; justify-content: center; margin-bottom: 24px; }
        .step { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; background: oklch(20% 0.03 260); color: oklch(50% 0.02 250); }
        .step.active { background: oklch(75% 0.15 85); color: oklch(8% 0.02 260); }
        .step.done { background: oklch(65% 0.18 145); color: oklch(8% 0.02 260); }
    </style>
</head>
<body>
    <div class="install-card">
        <h1>Jogatinando CMS</h1>

        <?php if ($message === 'success'): ?>
            <div class="status success">Banco de dados inicializado com sucesso!</div>
            <p>Dados padrão inseridos: banners, jogos, depoimentos, FAQ e equipe.</p>
            <a href="admin/login.php" class="btn btn-gold">Acessar Painel Admin</a>
            <a href="/" class="btn btn-outline">Ver Site</a>

        <?php elseif (strpos($message, 'error') === 0): ?>
            <div class="status error"><?= e(substr($message, 7)) ?></div>
            <form method="POST">
                <input type="hidden" name="action" value="init">
                <button type="submit" class="btn btn-gold">Tentar Novamente</button>
            </form>

        <?php elseif ($step === 1): ?>
            <div class="steps">
                <div class="step active">1</div>
                <div class="step">2</div>
            </div>
            <p>Escolha o tipo de banco de dados para instalação.</p>
            <form method="POST" action="?step=1">
                <input type="hidden" name="action" value="sqlite">
                <button type="submit" class="btn btn-gold">SQLite (Simples)</button>
            </form>
            <p style="font-size: 13px; color: oklch(50% 0.02 250); margin-top: -12px; margin-bottom: 16px; text-align: center;">
                Recomendado — nenhuma configuração necessária
            </p>
            <a href="?step=2" class="btn btn-outline">MySQL / MariaDB</a>
            <p style="font-size: 13px; color: oklch(50% 0.02 250); margin-top: -12px; text-align: center;">
                Para produção com múltiplos acessos simultâneos
            </p>

        <?php elseif ($step === 2): ?>
            <div class="steps">
                <div class="step done">1</div>
                <div class="step active">2</div>
            </div>
            <p>Configure a conexão com o banco MySQL / MariaDB.</p>
            <form method="POST" action="?step=2">
                <input type="hidden" name="action" value="mysql">
                <div class="form-row">
                    <div class="form-group">
                        <label for="db_host">Host</label>
                        <input type="text" id="db_host" name="db_host" value="<?= e(DB_HOST) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="db_port">Porta</label>
                        <input type="text" id="db_port" name="db_port" value="<?= e(DB_PORT) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="db_name">Database</label>
                    <input type="text" id="db_name" name="db_name" value="<?= e(DB_NAME) ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="db_user">Usuário</label>
                        <input type="text" id="db_user" name="db_user" value="<?= e(DB_USER) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="db_pass">Senha</label>
                        <input type="password" id="db_pass" name="db_pass" value="<?= e(DB_PASS) ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-gold">Instalar com MySQL</button>
                <a href="?step=1" class="btn btn-outline">Voltar</a>
            </form>

        <?php else: ?>
            <p>Instalação do CMS Jogatinando. Escolha o tipo de banco para começar.</p>
            <a href="?step=1" class="btn btn-gold">Iniciar Instalação</a>
        <?php endif; ?>
    </div>
</body>
</html>
