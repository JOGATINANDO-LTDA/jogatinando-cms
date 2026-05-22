<?php

function getDbType() {
    if (!defined('DB_TYPE')) {
        define('DB_TYPE', 'sqlite');
    }
    return DB_TYPE;
}

function getDsn() {
    $type = getDbType();
    if ($type === 'mysql') {
        $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        $port = defined('DB_PORT') ? DB_PORT : '3306';
        $name = defined('DB_NAME') ? DB_NAME : 'jogatinando';
        return "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    }
    return 'sqlite:' . DB_PATH;
}

function getDbOptions() {
    $type = getDbType();
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    if ($type === 'mysql') {
        $opts[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4";
    }
    return $opts;
}

function getDbUser() {
    return getDbType() === 'mysql' ? (defined('DB_USER') ? DB_USER : 'root') : null;
}

function getDbPass() {
    return getDbType() === 'mysql' ? (defined('DB_PASS') ? DB_PASS : '') : null;
}

function getDB() {
    static $db = null;
    if ($db === null) {
        $type = getDbType();
        if ($type === 'sqlite' && !file_exists(DB_PATH)) {
            return null;
        }
        try {
            $db = new PDO(getDsn(), getDbUser(), getDbPass(), getDbOptions());
            if ($type === 'sqlite') {
                $db->exec('PRAGMA journal_mode=WAL');
                $db->exec('PRAGMA foreign_keys=ON');
            }
            dbMigrate($db);
        } catch (PDOException $ex) {
            error_log("DB Connection failed: " . $ex->getMessage());
            return null;
        }
    }
    return $db;
}

function getDbTables($db) {
    if (getDbType() === 'mysql') {
        $stmt = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
}

function dbRandom() {
    return getDbType() === 'mysql' ? 'RAND()' : 'RANDOM()';
}

function dbInsertIgnore($db, $table, $columns, $values) {
    $placeholder = implode(', ', array_fill(0, count($columns), '?'));
    $cols = implode(', ', $columns);
    $prefix = getDbType() === 'mysql' ? 'INSERT IGNORE INTO' : 'INSERT OR IGNORE INTO';
    $stmt = $db->prepare("$prefix $table ($cols) VALUES ($placeholder)");
    return $stmt->execute($values);
}

function dbInsertReplace($db, $table, $columns, $values) {
    $placeholder = implode(', ', array_fill(0, count($columns), '?'));
    $cols = implode(', ', $columns);
    $prefix = getDbType() === 'mysql' ? 'REPLACE INTO' : 'INSERT OR REPLACE INTO';
    $stmt = $db->prepare("$prefix $table ($cols) VALUES ($placeholder)");
    return $stmt->execute($values);
}

function dbMigrate($db) {
    $type = getDbType();
    $pkType = $type === 'mysql' ? 'INT' : 'INTEGER';

    $db->exec("CREATE TABLE IF NOT EXISTS schema_version (
        version $pkType PRIMARY KEY,
        name TEXT NOT NULL,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $currentVersion = 0;
    $row = $db->query("SELECT MAX(version) as v FROM schema_version")->fetch();
    if ($row && $row['v'] !== null) {
        $currentVersion = (int)$row['v'];
    }

    // Detect existing DB without schema_version entries
    if ($currentVersion === 0) {
        $tables = getDbTables($db);
        $coreTables = ['games', 'banners', 'users', 'blog_posts', 'testimonials', 'faq_items', 'team_members', 'site_settings'];
        $hasExistingData = count(array_intersect($tables, $coreTables)) > 0;

        if ($hasExistingData) {
            $stmt = $db->prepare("INSERT INTO schema_version (version, name) VALUES (?, ?)");
            foreach (getMigrationList() as $ver => $name) {
                $stmt->execute([$ver, $name]);
            }
            return;
        }
    }

    $migrations = getMigrationList();
    require_once __DIR__ . '/migrations.php';

    foreach ($migrations as $version => $name) {
        if ($version > $currentVersion) {
            $func = 'migration_' . str_pad($version, 3, '0', STR_PAD_LEFT);
            if (function_exists($func)) {
                $func($db, $type);
                $stmt = $db->prepare("INSERT INTO schema_version (version, name) VALUES (?, ?)");
                $stmt->execute([$version, $name]);
            }
        }
    }
}

function getMigrationList() {
    return [
        1 => 'create_all_tables',
    ];
}

function dbInit($dsn = null, $user = null, $pass = null, $type = null) {
    $type = $type ?? getDbType();
    $dsn = $dsn ?? getDsn();
    $user = $user ?? getDbUser();
    $pass = $pass ?? getDbPass();

    if ($type === 'sqlite' && !is_dir(DATA_PATH)) {
        mkdir(DATA_PATH, 0755, true);
    }

    $db = new PDO($dsn, $user, $pass, getDbOptions());
    if ($type === 'sqlite') {
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('PRAGMA foreign_keys=ON');
    }

    $pkType = $type === 'mysql' ? 'INT' : 'INTEGER';
    $db->exec("CREATE TABLE IF NOT EXISTS schema_version (
        version $pkType PRIMARY KEY,
        name TEXT NOT NULL,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $migrations = getMigrationList();
    require_once __DIR__ . '/migrations.php';

    foreach ($migrations as $version => $name) {
        $func = 'migration_' . str_pad($version, 3, '0', STR_PAD_LEFT);
        if (function_exists($func)) {
            $func($db, $type);
        }
        $stmt = $db->prepare("INSERT INTO schema_version (version, name) VALUES (?, ?)");
        $stmt->execute([$version, $name]);
    }

    return $db;
}

function dbQuery($sql, $params = []) {
    $db = getDB();
    if (!$db) return [];
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function dbQueryOne($sql, $params = []) {
    $db = getDB();
    if (!$db) return null;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function dbExec($sql, $params = []) {
    $db = getDB();
    if (!$db) return false;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $db->lastInsertId();
}

function dbDelete($table, $id) {
    $db = getDB();
    if (!$db) return false;
    $stmt = $db->prepare("DELETE FROM $table WHERE id = ?");
    return $stmt->execute([$id]);
}

function getSetting($key, $default = '') {
    $db = getDB();
    if (!$db) return $default;
    $stmt = $db->prepare("SELECT `value` FROM site_settings WHERE `key` = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}

function setSetting($key, $value) {
    $db = getDB();
    if (!$db) return false;
    $prefix = getDbType() === 'mysql' ? 'REPLACE INTO' : 'INSERT OR REPLACE INTO';
    $stmt = $db->prepare("$prefix site_settings (`key`, `value`) VALUES (?, ?)");
    return $stmt->execute([$key, $value]);
}
