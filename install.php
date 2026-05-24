<?php

require_once 'config.php';

if (defined('APP_ENV') && APP_ENV === 'production') {
    header('Location: /');
    exit;
}

$message = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

$isInstalled = false;
$db = getDB();
if ($db) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $isInstalled = $count > 0;
    } catch (Exception $e) {}
}

if ($isInstalled) {
    header('Location: /');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'sqlite') {
        try {
            dbInit(null, null, null, 'sqlite');
            writeLocalConfig('sqlite');
            $message = 'success';
        } catch (Exception $ex) {
            $message = 'error: ' . $ex->getMessage();
        }
    } elseif ($_POST['action'] === 'mysql') {
        try {
            $adminUser = trim($_POST['admin_user'] ?? '');
            $adminPass = $_POST['admin_pass'] ?? '';
            if ($adminUser === '' || $adminPass === '') {
                throw new Exception('Preencha o usuário e senha do administrador.');
            }
            $adminHash = password_hash($adminPass, PASSWORD_DEFAULT);

            $host = $_POST['db_host'] ?? '127.0.0.1';
            $port = $_POST['db_port'] ?? '3306';
            $name = $_POST['db_name'] ?? 'cms_db';
            $user = $_POST['db_user'] ?? 'root';
            $pass = $_POST['db_pass'] ?? '';
            $dsnNoDb = "mysql:host=$host;port=$port;charset=utf8mb4";
            try {
                $pdo = new PDO($dsnNoDb, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo = null;
            } catch (Exception $_) {
                // User may not have CREATE privilege — db may already exist
            }
            $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
            $mysql = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            dbInit($dsn, $user, $pass, 'mysql');
            $stmt = $mysql->prepare("UPDATE users SET username = ?, password_hash = ? WHERE id = 1");
            $stmt->execute([$adminUser, $adminHash]);
            $message = 'success';
            writeLocalConfig('mysql', $host, $port, $name, $user, $pass);
        } catch (Exception $ex) {
            $message = 'error: ' . $ex->getMessage();
        }
    }
}

function writeLocalConfig($type, $host = null, $port = null, $name = null, $user = null, $pass = null) {
    $content = '<?php' . "\n\n";
    $content .= "if (!defined('CMS_INSTALL_VERSION')) define('CMS_INSTALL_VERSION', '" . CMS_VERSION . "');\n";
    $content .= "if (!defined('DB_TYPE')) {\n";
    $content .= "    define('DB_TYPE', '$type');\n";
    if ($type === 'mysql') {
        $content .= "    define('DB_HOST', '$host');\n";
        $content .= "    define('DB_PORT', '$port');\n";
        $content .= "    define('DB_NAME', '$name');\n";
        $content .= "    define('DB_USER', '$user');\n";
        $content .= "    define('DB_PASS', '$pass');\n";
    }
    $content .= "}\n";
    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
    file_put_contents(DATA_PATH . '/config.local.php', $content);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS de Jogos — Instalação</title>
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
        <h1>CMS de Jogos</h1>

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

                <h3 style="color: oklch(68% 0.16 220); font-size: 14px; margin-bottom: 12px;">Conexão MySQL</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="db_host">Host</label>
                        <input type="text" id="db_host" name="db_host" value="127.0.0.1" required>
                    </div>
                    <div class="form-group">
                        <label for="db_port">Porta</label>
                        <input type="text" id="db_port" name="db_port" value="3306" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="db_name">Database</label>
                    <input type="text" id="db_name" name="db_name" value="cms_db" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="db_user">Usuário</label>
                        <input type="text" id="db_user" name="db_user" value="root" required>
                    </div>
                    <div class="form-group">
                        <label for="db_pass">Senha</label>
                        <input type="password" id="db_pass" name="db_pass" value="">
                    </div>
                </div>

                <hr style="border: none; border-top: 1px solid oklch(25% 0.02 260); margin: 20px 0;">

                <h3 style="color: oklch(75% 0.15 85); font-size: 14px; margin-bottom: 12px;">Administrador</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="admin_user">Usuário Admin</label>
                        <input type="text" id="admin_user" name="admin_user" value="admin" required>
                    </div>
                    <div class="form-group">
                        <label for="admin_pass">Senha Admin</label>
                        <input type="password" id="admin_pass" name="admin_pass" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-gold">Instalar com MySQL</button>
                <a href="?step=1" class="btn btn-outline">Voltar</a>
            </form>

        <?php else: ?>
            <p>Instalação do CMS. Escolha o tipo de banco para começar.</p>
            <a href="?step=1" class="btn btn-gold">Iniciar Instalação</a>
        <?php endif; ?>
    </div>
</body>
</html>
