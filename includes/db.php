<?php

function getDbType() {
    return defined('DB_TYPE') ? DB_TYPE : null;
}

function getDsn() {
    $type = getDbType();
    if ($type === 'mysql') {
        $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        $port = defined('DB_PORT') ? DB_PORT : '3306';
        $name = defined('DB_NAME') ? DB_NAME : 'cms_db';
        return "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    }
    return 'sqlite:' . DB_PATH;
}

function getDbOptions($type = null) {
    $type = $type ?? getDbType();
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
        if ($type === null) return null;
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

function getDbTables($db, $type = null) {
    $type = $type ?? getDbType();
    if ($type === 'mysql') {
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

function dbMigrate($db, $type = null) {
    $type = $type ?? getDbType();
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
        $tables = getDbTables($db, $type);
        $coreTables = ['games', 'banners', 'users', 'blog_posts', 'testimonials', 'faq_items', 'team_members', 'site_settings', 'social_links', 'ad_slots', 'platforms', 'game_distribution_stats', 'campaigns', 'campaign_metrics'];
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
                try {
                    $func($db, $type);
                } catch (Exception $e) {
                    error_log("Migration {$version} ({$name}) failed: " . $e->getMessage());
                    continue;
                }
                $stmt = $db->prepare("INSERT INTO schema_version (version, name) VALUES (?, ?)");
                $stmt->execute([$version, $name]);
            }
        }
    }
}

function getMigrationList() {
    return [
        1 => 'create_all_tables',
        2 => 'add_user_avatar',
        3 => 'add_user_role',
        4 => 'create_roles_table',
        5 => 'add_user_setup_fields',
        6 => 'create_engines_table',
        7 => 'seed_engines_data',
        8 => 'clean_engine_outra',
        9 => 'add_game_fields_platforms',
        10 => 'create_game_templates',
        11 => 'create_retro_games',
        12 => 'create_retro_consoles',
        13 => 'add_external_game_fields',
        14 => 'seed_retro_consoles',
        15 => 'add_console_thumbnail',
        16 => 'add_modification_description',
        17 => 'make_password_hash_nullable',
        18 => 'add_email_verification',
        19 => 'create_levels_table',
        20 => 'add_template_free_file_gallery',
        21 => 'add_platform_logo',
         22 => 'add_template_links',
         23 => 'drop_banners_engine_tag',
         24 => 'drop_games_zip_filename',
         25 => 'drop_retro_games_patch_url',
         26 => 'drop_templates_store_url_users_role_roles_level',
          27 => 'add_team_member_user_id',
           28 => 'add_aspect_ratio_to_games',
            29 => 'add_iframe_width_height_to_games',
             30 => 'create_sync_queue',
             31 => 'normalize_media_urls_s3_settings',
             32 => 'add_sync_queue_retry',
         33 => 'add_user_setup_token_fields',
         34 => 'add_social_links_ads_distribution',
         35 => 'add_social_links_media_fields',
         36 => 'add_distribution_integration_hub',
         37 => 'drop_dead_tables_and_permissions',
          38 => 'create_ai_system_tables',
          40 => 'unify_store_and_distribution_platforms',
           41 => 'fix_fk_constraints_to_platforms',
           42 => 'seed_demo_distribution_data',
           43 => 'create_newsletter_subscribers',
       ];
}

function dbInit($dsn = null, $user = null, $pass = null, $type = null) {
    $type = $type ?? getDbType();
    if ($dsn === null) {
        if ($type === 'sqlite') {
            $dsn = 'sqlite:' . DB_PATH;
            $user = null;
            $pass = null;
        } else {
            $dsn = getDsn();
            $user = $user ?? getDbUser();
            $pass = $pass ?? getDbPass();
        }
    }

    if ($type === 'sqlite' && !is_dir(DATA_PATH)) {
        mkdir(DATA_PATH, 0755, true);
    }

    $db = new PDO($dsn, $user, $pass, getDbOptions($type));
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
        try {
            $stmt = $db->prepare("INSERT INTO schema_version (version, name) VALUES (?, ?)");
            $stmt->execute([$version, $name]);
        } catch (Exception $e) {
            // Already applied — skip
        }
    }

    // Seed default data (called only by install, never by auto-migration)
    if (function_exists('dbSeed')) {
        dbSeed($db, $type);
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
    $allowed = ['banners','games','blog_posts','testimonials','faq_items','team_members','users','engines','platforms','game_links','retro_games','retro_consoles','levels','roles','site_settings','social_links','ad_slots','campaigns','campaign_metrics'];
    if (!in_array($table, $allowed)) return false;
    $stmt = $db->prepare("DELETE FROM `$table` WHERE id = ?");
    return $stmt->execute([$id]);
}

function getSetting($key, $default = '') {
    if (array_key_exists($key, $GLOBALS['_setting_cache'] ?? [])) {
        return $GLOBALS['_setting_cache'][$key];
    }
    $db = getDB();
    if (!$db) return $default;
    $stmt = $db->prepare("SELECT `value` FROM site_settings WHERE `key` = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    $GLOBALS['_setting_cache'][$key] = $row ? $row['value'] : $default;
    return $GLOBALS['_setting_cache'][$key];
}

function setSetting($key, $value) {
    $db = getDB();
    if (!$db) return false;
    $prefix = getDbType() === 'mysql' ? 'REPLACE INTO' : 'INSERT OR REPLACE INTO';
    $stmt = $db->prepare("$prefix site_settings (`key`, `value`) VALUES (?, ?)");
    $ok = $stmt->execute([$key, $value]);
    unset($GLOBALS['_setting_cache'][$key]);
    return $ok;
}
