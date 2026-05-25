<?php

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    } elseif ($_POST['action'] === 'test_mysql') {
        try {
            $tHost = $_POST['db_host'] ?? '127.0.0.1';
            $tPort = $_POST['db_port'] ?? '3306';
            $tName = $_POST['db_name'] ?? 'cms_db';
            $tUser = $_POST['db_user'] ?? 'root';
            $tPass = $_POST['db_pass'] ?? '';

            $dsnNoDb = "mysql:host=$tHost;port=$tPort;charset=utf8mb4";
            $pdo = new PDO($dsnNoDb, $tUser, $tPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = " . $pdo->quote($tName));
            $dbExists = $stmt->fetchColumn();

            $testResult = [
                'host' => $tHost, 'port' => $tPort, 'name' => $tName,
                'user' => $tUser, 'pass' => $tPass,
            ];

            if (!$dbExists) {
                $testResult['status'] = 'new';
                $testResult['user_count'] = 0;
                $testResult['username'] = '';
                $testResult['site_name'] = 'CMS de Jogos';
                $_SESSION['mysql_test'] = $testResult;
                $message = 'info:Conexão OK. Novo banco de dados será criado.';
            } else {
                $pdo = null;
                $dsn = "mysql:host=$tHost;port=$tPort;dbname=$tName;charset=utf8mb4";
                $pdo2 = new PDO($dsn, $tUser, $tPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $tables = $pdo2->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

                $testResult['status'] = 'existing';
                $testResult['user_count'] = 0;
                $testResult['username'] = '—';
                $testResult['site_name'] = 'CMS de Jogos';
                if (in_array('users', $tables)) {
                    $testResult['user_count'] = (int)$pdo2->query("SELECT COUNT(*) FROM users")->fetchColumn();
                    $uname = $pdo2->query("SELECT username FROM users ORDER BY id LIMIT 1")->fetchColumn();
                    if ($uname) $testResult['username'] = $uname;
                }
                if (in_array('site_settings', $tables)) {
                    $sn = $pdo2->query("SELECT value FROM site_settings WHERE `key` = 'site_name'")->fetchColumn();
                    if ($sn) $testResult['site_name'] = $sn;
                }
                $_SESSION['mysql_test'] = $testResult;

                $msg = 'Conexão OK. Banco ' . $tName . ' existe';
                if ($testResult['user_count'] > 0) {
                    $msg .= ' — já possui um CEO cadastrado.';
                } else {
                    $msg .= ', sem dados do CMS.';
                }
                $message = 'info:' . $msg;
            }
        } catch (Exception $ex) {
            $_SESSION['mysql_test'] = null;
            $message = 'error:' . $ex->getMessage();
        }
    } elseif ($_POST['action'] === 'mysql') {
        try {
            $host = $_POST['db_host'] ?? '127.0.0.1';
            $port = $_POST['db_port'] ?? '3306';
            $name = $_POST['db_name'] ?? 'cms_db';
            $dbUser = $_POST['db_user'] ?? 'root';
            $dbPass = $_POST['db_pass'] ?? '';
            $siteName = trim($_POST['site_name'] ?? 'CMS de Jogos');
            if ($siteName === '') $siteName = 'CMS de Jogos';

            $installFresh = isset($_POST['install_fresh']);
            $existingAdminUser = trim($_POST['existing_admin_user'] ?? '');
            $existingAdminPass = $_POST['existing_admin_pass'] ?? '';

            $dsnNoDb = "mysql:host=$host;port=$port;charset=utf8mb4";
            try {
                $pdo = new PDO($dsnNoDb, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo = null;
            } catch (Exception $_) {}
            $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
            $mysql = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            try {
                $existingUserCount = (int)$mysql->query("SELECT COUNT(*) FROM users")->fetchColumn();
            } catch (Exception $e) {
                $existingUserCount = 0;
            }

            if ($existingUserCount > 0 && $installFresh) {
                $stmt = $mysql->prepare("SELECT password_hash FROM users WHERE username = ?");
                $stmt->execute([$existingAdminUser]);
                $row = $stmt->fetch();
                if (!$row || !password_verify($existingAdminPass, $row['password_hash'])) {
                    throw new Exception('Credenciais do admin existente não conferem. Operação cancelada por segurança.');
                }
                $mysql = null;
                $pdo = new PDO($dsnNoDb, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo->exec("DROP DATABASE IF EXISTS `$name`");
                $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo = null;
                $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
                $mysql = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $adminUser = trim($_POST['admin_user'] ?? '');
                $adminPass = $_POST['admin_pass'] ?? '';
                if ($adminUser === '' || $adminPass === '') {
                    throw new Exception('Preencha o novo usuário e senha do administrador.');
                }
                dbInit($dsn, $dbUser, $dbPass, 'mysql');
                $stmt = $mysql->prepare("UPDATE users SET username = ?, password_hash = ? WHERE id = 1");
                $stmt->execute([$adminUser, password_hash($adminPass, PASSWORD_DEFAULT)]);
                $mysql->exec("REPLACE INTO site_settings (`key`, `value`) VALUES ('site_name', " . $mysql->quote($siteName) . ")");
            } elseif ($existingUserCount > 0) {
                $migratePdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                dbMigrate($migratePdo, 'mysql');
            } else {
                $adminUser = trim($_POST['admin_user'] ?? '');
                $adminPass = $_POST['admin_pass'] ?? '';
                if ($adminUser === '' || $adminPass === '') {
                    throw new Exception('Preencha o usuário e senha do administrador.');
                }
                dbInit($dsn, $dbUser, $dbPass, 'mysql');
                $stmt = $mysql->prepare("UPDATE users SET username = ?, password_hash = ? WHERE id = 1");
                $stmt->execute([$adminUser, password_hash($adminPass, PASSWORD_DEFAULT)]);
                $mysql->exec("REPLACE INTO site_settings (`key`, `value`) VALUES ('site_name', " . $mysql->quote($siteName) . ")");
            }
            $message = 'success';
            $_SESSION['mysql_test'] = null;
            writeLocalConfig('mysql', $host, $port, $name, $dbUser, $dbPass);
        } catch (Exception $ex) {
            $message = 'error:' . $ex->getMessage();
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

        <?php else:
            if (strpos($message, 'error:') === 0): ?>
                <div class="status error"><?= e(substr($message, 6)) ?></div>
            <?php elseif (strpos($message, 'info:') === 0): ?>
                <div class="status info"><?= e(substr($message, 5)) ?></div>
            <?php endif; ?>

        <?php if ($step === 1): ?>
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

        <?php elseif ($step === 2):
            $result = $_SESSION['mysql_test'] ?? null;
            $s = $result ? $result['status'] : null;
        ?>
            <div class="steps">
                <div class="step done">1</div>
                <div class="step active">2</div>
            </div>
            <p>Configure a conexão com o banco MySQL / MariaDB.</p>
            <form method="POST" action="?step=2">

                <h3 style="color: oklch(68% 0.16 220); font-size: 14px; margin-bottom: 12px;">Conexão MySQL</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="db_host">Host</label>
                        <input type="text" id="db_host" name="db_host" value="<?= e($result['host'] ?? '127.0.0.1') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="db_port">Porta</label>
                        <input type="text" id="db_port" name="db_port" value="<?= e($result['port'] ?? '3306') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="db_name">Database</label>
                    <input type="text" id="db_name" name="db_name" value="<?= e($result['name'] ?? 'cms_db') ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="db_user">Usuário</label>
                        <input type="text" id="db_user" name="db_user" value="<?= e($result['user'] ?? 'root') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="db_pass">Senha</label>
                        <input type="password" id="db_pass" name="db_pass" value="<?= e($result['pass'] ?? '') ?>">
                    </div>
                </div>

                <button type="submit" name="action" value="test_mysql" class="btn btn-outline" style="margin-bottom:20px;">Testar Conexão</button>

                <hr style="border: none; border-top: 1px solid oklch(25% 0.02 260); margin: 20px 0;">

                <?php if ($s === 'existing'): ?>
                    <h3 style="color: oklch(75% 0.15 85); font-size: 14px; margin-bottom: 4px;">Site</h3>
                    <p style="font-size:13px;color:oklch(60% 0.012 250);margin-bottom:16px;">
                        Nome atual: <strong><?= e($result['site_name'] ?? '—') ?></strong> — será preservado na migração.
                    </p>
                <?php else: ?>
                <h3 style="color: oklch(75% 0.15 85); font-size: 14px; margin-bottom: 12px;">Site</h3>
                <div class="form-group">
                    <label for="site_name">Nome do Site</label>
                    <input type="text" id="site_name" name="site_name" value="<?= e($result['site_name'] ?? 'CMS de Jogos') ?>">
                </div>
                <?php endif; ?>

                <?php if ($s === 'new'): ?>
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
                    <button type="submit" name="action" value="mysql" class="btn btn-gold">Instalar</button>

                <?php elseif ($s === 'existing'): ?>
                    <p style="color:oklch(50% 0.02 250);font-size:13px;text-align:center;margin:16px 0;">
                        Banco existente com <strong><?= $result['user_count'] ?></strong> usuário(s) — já possui um CEO cadastrado.
                    </p>

                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 12px;border:1px solid oklch(55% 0.20 25 / 0.3);border-radius:6px;">
                        <input type="checkbox" name="install_fresh" value="1" id="installFresh" onchange="
                            var f=document.getElementById('fresh-fields');
                            var b=document.getElementById('installBtn');
                            f.style.display=this.checked?'block':'none';
                            b.textContent=this.checked?'Instalar do Zero':'Migrar mantendo dados';
                        ">
                        <span style="font-size:13px;color:oklch(55% 0.20 25);">Instalar do zero (apagar todos os dados)</span>
                    </label>

                    <div id="fresh-fields" style="display:none;margin-top:16px;">
                        <p style="font-size:12px;color:oklch(60% 0.012 250);margin-bottom:12px;">Confirme o admin atual para autorizar a reinstalação:</p>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="existing_admin_user">Admin Atual</label>
                                <input type="text" id="existing_admin_user" name="existing_admin_user" placeholder="usuário do admin atual">
                            </div>
                            <div class="form-group">
                                <label for="existing_admin_pass">Senha Atual</label>
                                <input type="password" id="existing_admin_pass" name="existing_admin_pass">
                            </div>
                        </div>
                        <hr style="border:none;border-top:1px solid oklch(25% 0.02 260);margin:16px 0;">
                        <h3 style="color:oklch(75% 0.15 85);font-size:14px;margin-bottom:12px;">Novo Administrador</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="admin_user">Novo Usuário</label>
                                <input type="text" id="admin_user" name="admin_user" value="admin">
                            </div>
                            <div class="form-group">
                                <label for="admin_pass">Nova Senha</label>
                                <input type="password" id="admin_pass" name="admin_pass">
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="action" value="mysql" id="installBtn" class="btn btn-gold" style="margin-top:16px;">Migrar mantendo dados</button>

                <?php else: ?>
                    <p style="font-size:13px;color:oklch(50% 0.02 250);text-align:center;margin:24px 0;">
                        Clique em <strong>Testar Conexão</strong> para verificar o banco de dados.
                    </p>
                    <button type="submit" name="action" value="mysql" class="btn btn-gold" disabled style="opacity:0.5;">Instalar com MySQL</button>
                <?php endif; ?>

                <a href="?step=1" class="btn btn-outline">Voltar</a>
            </form>

        <?php else: ?>
            <p>Instalação do CMS. Escolha o tipo de banco para começar.</p>
            <a href="?step=1" class="btn btn-gold">Iniciar Instalação</a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
