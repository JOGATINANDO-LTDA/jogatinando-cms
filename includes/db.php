<?php
/**
 * Database helper — SQLite with PDO
 */

function getDB() {
    static $db = null;
    if ($db === null) {
        if (!file_exists(DB_PATH)) {
            return null; // DB not initialized yet
        }
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('PRAGMA foreign_keys=ON');
    }
    return $db;
}

function dbInit() {
    if (!is_dir(DATA_PATH)) {
        mkdir(DATA_PATH, 0755, true);
    }

    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS banners (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        subtitle TEXT DEFAULT '',
        description TEXT DEFAULT '',
        image_url TEXT DEFAULT '',
        cta_text TEXT DEFAULT 'Saiba Mais',
        cta_url TEXT DEFAULT '#',
        engine_tag TEXT DEFAULT '',
        sort_order INTEGER DEFAULT 0,
        active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS games (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        engine TEXT NOT NULL,
        description TEXT DEFAULT '',
        thumbnail_url TEXT DEFAULT '',
        zip_filename TEXT DEFAULT '',
        featured INTEGER DEFAULT 0,
        sort_order INTEGER DEFAULT 0,
        active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS blog_posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT UNIQUE NOT NULL,
        content TEXT DEFAULT '',
        thumbnail_url TEXT DEFAULT '',
        external_url TEXT DEFAULT '',
        published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS testimonials (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        role TEXT DEFAULT '',
        quote TEXT NOT NULL,
        avatar_url TEXT DEFAULT '',
        active INTEGER DEFAULT 1,
        sort_order INTEGER DEFAULT 0
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS faq_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        question TEXT NOT NULL,
        answer TEXT NOT NULL,
        sort_order INTEGER DEFAULT 0,
        active INTEGER DEFAULT 1
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS team_members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        role TEXT NOT NULL,
        bio TEXT DEFAULT '',
        avatar_url TEXT DEFAULT '',
        social_youtube TEXT DEFAULT '',
        social_twitch TEXT DEFAULT '',
        social_linkedin TEXT DEFAULT '',
        sort_order INTEGER DEFAULT 0,
        active INTEGER DEFAULT 1
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS site_settings (
        key TEXT PRIMARY KEY,
        value TEXT DEFAULT ''
    )");

    // Seed default admin user if none exists
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM users");
    $row = $stmt->fetch();
    if ($row['cnt'] == 0) {
        $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([ADMIN_USERNAME, ADMIN_PASSWORD_HASH]);
    }

    // Seed default settings
    $defaults = [
        ['site_name', SITE_NAME],
        ['site_tagline', SITE_TAGLINE],
        ['hero_title', 'Transformamos Ideias em <span class="gold">Jogos Incríveis</span>'],
        ['hero_subtitle', 'Somos um estúdio brasileiro especializado em desenvolvimento de jogos digitais. Da concepção ao lançamento, criamos experiências que encantam jogadores e geram resultados.'],
        ['contact_email', 'contato@jogatinando.com.br'],
        ['contact_whatsapp', ''],
        ['youtube_url', 'https://youtube.com/@jogatinandodevs'],
        ['twitch_url', 'https://www.twitch.tv/jogatinandolive'],
        ['blog_url', 'https://gamenews.xo.je/'],
        ['footer_description', 'Estúdio brasileiro de desenvolvimento de jogos digitais. Criamos games sob medida em diversas engines para clientes de todo o mundo.'],
    ];

    foreach ($defaults as $def) {
        $stmt = $db->prepare("INSERT OR IGNORE INTO site_settings (key, value) VALUES (?, ?)");
        $stmt->execute($def);
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
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $db->lastInsertId();
}

function dbDelete($table, $id) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM $table WHERE id = ?");
    return $stmt->execute([$id]);
}

function getSetting($key, $default = '') {
    $db = getDB();
    if (!$db) return $default;
    $stmt = $db->prepare("SELECT value FROM site_settings WHERE key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}

function setSetting($key, $value) {
    $db = getDB();
    $stmt = $db->prepare("INSERT OR REPLACE INTO site_settings (key, value) VALUES (?, ?)");
    return $stmt->execute([$key, $value]);
}
