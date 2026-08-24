<?php

function migration_001($db, $type) {
    // --- Schema Version ---
    if ($type === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS schema_version (
            version INT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS schema_version (
            version INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // --- Users ---
    if ($type === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // --- Banners ---
    if ($type === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS banners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            subtitle VARCHAR(255) DEFAULT '',
            description TEXT,
            image_url VARCHAR(255) DEFAULT '',
            cta_text VARCHAR(255) DEFAULT 'Saiba Mais',
            cta_url VARCHAR(255) DEFAULT '#',
            engine_tag VARCHAR(255) DEFAULT '',
            sort_order INT DEFAULT 0,
            active INT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
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
    }

    // --- Games ---
    if ($type === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS games (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) DEFAULT '',
            engine VARCHAR(255) NOT NULL,
            description TEXT,
            thumbnail_url VARCHAR(255) DEFAULT '',
            zip_filename VARCHAR(255) DEFAULT '',
            game_path VARCHAR(255) DEFAULT '',
            featured INT DEFAULT 0,
            orientation VARCHAR(50) DEFAULT 'auto',
            optimized_at DATETIME DEFAULT NULL,
            sort_order INT DEFAULT 0,
            active INT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS games (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT DEFAULT '',
            engine TEXT NOT NULL,
            description TEXT DEFAULT '',
            thumbnail_url TEXT DEFAULT '',
            zip_filename TEXT DEFAULT '',
            game_path TEXT DEFAULT '',
            featured INTEGER DEFAULT 0,
            orientation TEXT DEFAULT 'auto',
            optimized_at TEXT DEFAULT NULL,
            sort_order INTEGER DEFAULT 0,
            active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // --- Blog Posts ---
    if ($type === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS blog_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content LONGTEXT,
            thumbnail_url VARCHAR(255) DEFAULT '',
            external_url VARCHAR(255) DEFAULT '',
            published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            active INT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
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
    }

    // --- Testimonials ---
    if ($type === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS testimonials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            role VARCHAR(255) DEFAULT '',
            quote TEXT NOT NULL,
            avatar_url VARCHAR(255) DEFAULT '',
            active INT DEFAULT 1,
            sort_order INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS testimonials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            role TEXT DEFAULT '',
            quote TEXT NOT NULL,
            avatar_url TEXT DEFAULT '',
            active INTEGER DEFAULT 1,
            sort_order INTEGER DEFAULT 0
        )");
    }

    // --- FAQ Items ---
    if ($type === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS faq_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question TEXT NOT NULL,
            answer TEXT NOT NULL,
            sort_order INT DEFAULT 0,
            active INT DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS faq_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            question TEXT NOT NULL,
            answer TEXT NOT NULL,
            sort_order INTEGER DEFAULT 0,
            active INTEGER DEFAULT 1
        )");
    }

    // --- Team Members ---
    if ($type === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS team_members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            role VARCHAR(255) NOT NULL,
            bio TEXT,
            avatar_url VARCHAR(255) DEFAULT '',
            social_youtube VARCHAR(255) DEFAULT '',
            social_twitch VARCHAR(255) DEFAULT '',
            social_linkedin VARCHAR(255) DEFAULT '',
            sort_order INT DEFAULT 0,
            active INT DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
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
    }

    // --- Site Settings ---
    if ($type === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS site_settings (
            `key` VARCHAR(255) PRIMARY KEY,
            `value` LONGTEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS site_settings (
            key TEXT PRIMARY KEY,
            value TEXT DEFAULT ''
        )");
    }
}

function migration_002($db, $type) {
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) DEFAULT '' AFTER password_hash");
        } else {
            $db->exec("ALTER TABLE users ADD COLUMN avatar_url TEXT DEFAULT ''");
        }
    } catch (Exception $e) {
        // Column may already exist — ignore
    }
}

function migration_003($db, $type) {
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'moderator' AFTER avatar_url");
        } else {
            $db->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'moderator'");
        }
    } catch (Exception $e) {
        // Column may already exist
    }
}

function migration_005($db, $type) {
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) DEFAULT '' AFTER username");
        } else {
            $cols = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('email', $cols)) {
                $db->exec("ALTER TABLE users ADD COLUMN email TEXT DEFAULT ''");
            }
        }
    } catch (Exception $e) {}
}

function migration_032($db, $type) {
    try {
        if ($type === 'mysql') {
            $cols = $db->query("SHOW COLUMNS FROM sync_queue LIKE 'status'")->fetch();
            if (!$cols) {
                $db->exec("ALTER TABLE sync_queue ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER created_at");
                $db->exec("ALTER TABLE sync_queue ADD COLUMN attempts INT NOT NULL DEFAULT 0 AFTER status");
                $db->exec("ALTER TABLE sync_queue ADD COLUMN last_error TEXT DEFAULT '' AFTER attempts");
            }
        } else {
            $cols = $db->query("PRAGMA table_info(sync_queue)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('status', $cols)) {
                $db->exec("ALTER TABLE sync_queue ADD COLUMN status TEXT NOT NULL DEFAULT 'pending'");
            }
            if (!in_array('attempts', $cols)) {
                $db->exec("ALTER TABLE sync_queue ADD COLUMN attempts INTEGER NOT NULL DEFAULT 0");
            }
            if (!in_array('last_error', $cols)) {
                $db->exec("ALTER TABLE sync_queue ADD COLUMN last_error TEXT DEFAULT ''");
            }
            $db->exec("UPDATE sync_queue SET status='pending' WHERE status IS NULL OR status=''");
        }
    } catch (Exception $e) {}
}

function migration_033($db, $type) {
    try {
        if ($type === 'mysql') {
            // Ensure status column exists (migration_005 was corrupted and lost it)
            $cols = $db->query("SHOW COLUMNS FROM users LIKE 'status'")->fetch();
            if (!$cols) {
                $db->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
            }
            $db->exec("ALTER TABLE users ADD COLUMN setup_token VARCHAR(64) DEFAULT NULL");
            $db->exec("ALTER TABLE users ADD COLUMN setup_token_expires DATETIME DEFAULT NULL");
        } else {
            $cols = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('setup_token', $cols)) {
                if (!in_array('status', $cols)) {
                    $db->exec("ALTER TABLE users ADD COLUMN status TEXT NOT NULL DEFAULT 'active'");
                }
                $db->exec("ALTER TABLE users ADD COLUMN setup_token TEXT DEFAULT NULL");
                $db->exec("ALTER TABLE users ADD COLUMN setup_token_expires TEXT DEFAULT NULL");
            }
        }
    } catch (Exception $e) {}
}

function migration_004($db, $type) {
    // Create roles table
    if ($type === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            level VARCHAR(20) NOT NULL DEFAULT 'moderator',
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            level TEXT NOT NULL DEFAULT 'moderator',
            description TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // Seed default roles
    $count = $db->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    if ($count == 0) {
        $stmt = $db->prepare("INSERT INTO roles (name, level, description) VALUES (?, ?, ?)");
        $stmt->execute(['CEO Administrador', 'ceo', 'Administrador master do sistema — controle total sobre todas as operações']);
        $stmt->execute(['CEO Sócio', 'ceo', 'Sócio com poderes administrativos — pode criar cargos de nível chief e moderator']);
        $stmt->execute(['CEO Investidor', 'ceo', 'Investidor com poderes administrativos — pode criar cargos de nível chief e moderator']);
        $stmt->execute(['CTO', 'chief', 'Chief Technology Officer — gerencia conteúdo, jogos e cargos moderator']);
        $stmt->execute(['CMO', 'chief', 'Chief Marketing Officer — gerencia conteúdo, blog e cargos moderator']);
        $stmt->execute(['Moderator', 'moderator', 'Gerenciamento operacional da plataforma — conteúdo e suporte']);
    }

    // Add role_id to users
    $needsRoleId = true;
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE users ADD COLUMN role_id INT DEFAULT NULL AFTER role");
        } else {
            // SQLite: check if column exists first
            $cols = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (in_array('role_id', $cols)) {
                $needsRoleId = false;
            } else {
                $db->exec("ALTER TABLE users ADD COLUMN role_id INTEGER DEFAULT NULL");
            }
        }
    } catch (Exception $e) {
        $needsRoleId = false;
    }

    if ($needsRoleId) {
        try {
            $ceoId = $db->query("SELECT id FROM roles WHERE name = 'CEO Administrador'")->fetchColumn();
            $ctoId = $db->query("SELECT id FROM roles WHERE name = 'CTO'")->fetchColumn();
            $modId = $db->query("SELECT id FROM roles WHERE name = 'Moderator'")->fetchColumn();

            $db->prepare("UPDATE users SET role_id = ? WHERE role = 'ceo' AND (role_id IS NULL OR role_id = 0)")->execute([$ceoId]);
            $db->prepare("UPDATE users SET role_id = ? WHERE role = 'chief' AND (role_id IS NULL OR role_id = 0)")->execute([$ctoId]);
            $db->prepare("UPDATE users SET role_id = ? WHERE (role = 'moderator' OR role_id IS NULL OR role_id = 0) AND role != 'ceo' AND role != 'chief'")->execute([$modId]);
        } catch (Exception $e) {
            // Migration may run in stages — ignore
        }
    }
}

function migration_006($db, $type) {
    if ($type === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS engines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            slug VARCHAR(255) NOT NULL UNIQUE,
            icon VARCHAR(50) NOT NULL DEFAULT '🎮',
            color VARCHAR(50) NOT NULL DEFAULT 'oklch(68% 0.16 220)',
            active INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS engines (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            slug TEXT NOT NULL UNIQUE,
            icon TEXT NOT NULL DEFAULT '🎮',
            color TEXT NOT NULL DEFAULT 'oklch(68% 0.16 220)',
            active INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // Seed engines if table is empty
    $count = $db->query("SELECT COUNT(*) FROM engines")->fetchColumn();
    if ($count == 0) {
        $stmt = $db->prepare("INSERT INTO engines (name, slug, icon, color, active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['GDevelop', 'gdevelop', '🎮', 'oklch(55% 0.15 145)', 1]);
        $stmt->execute(['Godot', 'godot', '🤖', 'oklch(65% 0.18 145)', 0]);
        $stmt->execute(['RPG Maker', 'rpgmaker', '⚔️', 'oklch(72% 0.14 85)', 0]);
        $stmt->execute(['Unity', 'unity', '🔷', 'oklch(55% 0.02 250)', 0]);
        $stmt->execute(['Unreal Engine', 'unrealengine', '🔶', 'oklch(35% 0.02 250)', 0]);
        $stmt->execute(['Construct', 'construct', '🏗️', 'oklch(70% 0.16 30)', 0]);
        $stmt->execute(['Defold', 'defold', '📦', 'oklch(60% 0.18 120)', 0]);
        $stmt->execute(['Game Maker', 'gamemaker', '🎯', 'oklch(65% 0.18 200)', 0]);
        $stmt->execute(["Ren'py", 'renpy', '💬', 'oklch(60% 0.18 340)', 0]);
        $stmt->execute(['Pixel Game Maker MV', 'pixelgamemakermv', '👾', 'oklch(70% 0.16 160)', 0]);
        $stmt->execute(['RPG Paper Maker', 'rpgpapermaker', '📜', 'oklch(68% 0.14 70)', 0]);
    }
}

function migration_007($db, $type) {
    $count = $db->query("SELECT COUNT(*) FROM engines")->fetchColumn();
    if ($count > 0) return;
    $stmt = $db->prepare("INSERT INTO engines (name, slug, icon, color, active) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['GDevelop', 'gdevelop', '🎮', 'oklch(55% 0.15 145)', 1]);
    $stmt->execute(['Godot', 'godot', '🤖', 'oklch(65% 0.18 145)', 0]);
    $stmt->execute(['RPG Maker', 'rpgmaker', '⚔️', 'oklch(72% 0.14 85)', 0]);
    $stmt->execute(['Unity', 'unity', '🔷', 'oklch(55% 0.02 250)', 0]);
    $stmt->execute(['Unreal Engine', 'unrealengine', '🔶', 'oklch(35% 0.02 250)', 0]);
    $stmt->execute(['Construct', 'construct', '🏗️', 'oklch(70% 0.16 30)', 0]);
    $stmt->execute(['Defold', 'defold', '📦', 'oklch(60% 0.18 120)', 0]);
    $stmt->execute(['Game Maker', 'gamemaker', '🎯', 'oklch(65% 0.18 200)', 0]);
    $stmt->execute(["Ren'py", 'renpy', '💬', 'oklch(60% 0.18 340)', 0]);
    $stmt->execute(['Pixel Game Maker MV', 'pixelgamemakermv', '👾', 'oklch(70% 0.16 160)', 0]);
    $stmt->execute(['RPG Paper Maker', 'rpgpapermaker', '📜', 'oklch(68% 0.14 70)', 0]);
}

function migration_008($db, $type) {
    $db->exec("DELETE FROM engines WHERE name = 'Outra'");
}

function migration_009($db, $type) {
    // Add game_type and is_web_playable to games
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE games ADD COLUMN game_type VARCHAR(50) NOT NULL DEFAULT 'autoral' AFTER orientation");
        } else {
            $cols = $db->query("PRAGMA table_info(games)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('game_type', $cols)) {
                $db->exec("ALTER TABLE games ADD COLUMN game_type TEXT NOT NULL DEFAULT 'autoral'");
            }
        }
    } catch (Exception $e) {}
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE games ADD COLUMN is_web_playable INT NOT NULL DEFAULT 1 AFTER game_type");
        } else {
            $cols = $db->query("PRAGMA table_info(games)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('is_web_playable', $cols)) {
                $db->exec("ALTER TABLE games ADD COLUMN is_web_playable INTEGER NOT NULL DEFAULT 1");
            }
        }
    } catch (Exception $e) {}

    // Create store_platforms table
    if ($type === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS store_platforms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            icon VARCHAR(50) NOT NULL DEFAULT '\xF0\x9F\x9B\x92',
            active INT DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS store_platforms (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            icon TEXT NOT NULL DEFAULT '\xF0\x9F\x9B\x92',
            active INTEGER DEFAULT 1,
            sort_order INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // Create game_links table
    if ($type === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS game_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            game_id INT NOT NULL,
            platform_id INT NOT NULL,
            url VARCHAR(500) NOT NULL,
            sort_order INT DEFAULT 0,
            FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
            FOREIGN KEY (platform_id) REFERENCES store_platforms(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS game_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            game_id INTEGER NOT NULL,
            platform_id INTEGER NOT NULL,
            url TEXT NOT NULL,
            sort_order INTEGER DEFAULT 0,
            FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
            FOREIGN KEY (platform_id) REFERENCES store_platforms(id) ON DELETE CASCADE
        )");
    }

    // Seed store platforms
    $count = $db->query("SELECT COUNT(*) FROM store_platforms")->fetchColumn();
    if ($count == 0) {
        $platforms = [
            ['Steam', 'steam', '🔥', 1],
            ['Epic Games', 'epic', '✨', 1],
            ['GOG', 'gog', '📦', 1],
            ['itch.io', 'itchio', '🔴', 1],
            ['gd.games', 'gdgames', '🎮', 1],
            ['Nintendo eShop', 'nintendo', '🎹', 0],
            ['PlayStation Store', 'playstation', '🎮', 0],
            ['Xbox Store', 'xbox', '🎮', 0],
            ['Google Play', 'googleplay', '📱', 0],
            ['App Store', 'appstore', '📱', 0],
            ['Amazon', 'amazon', '📦', 0],
        ];
        $stmt = $db->prepare("INSERT INTO store_platforms (name, slug, icon, active) VALUES (?, ?, ?, ?)");
        foreach ($platforms as $p) {
            $stmt->execute($p);
        }
    }
}

function migration_010($db, $type) {
    if ($type === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS game_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) DEFAULT '',
            engine VARCHAR(255) NOT NULL,
            description TEXT,
            language VARCHAR(100) DEFAULT '',
            language_version VARCHAR(50) DEFAULT '',
            store_url VARCHAR(500) DEFAULT '',
            game_path VARCHAR(255) DEFAULT '',
            thumbnail_url VARCHAR(255) DEFAULT '',
            features TEXT,
            requirements TEXT,
            featured INT DEFAULT 0,
            active INT DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS game_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT DEFAULT '',
            engine TEXT NOT NULL,
            description TEXT DEFAULT '',
            language TEXT DEFAULT '',
            language_version TEXT DEFAULT '',
            store_url TEXT DEFAULT '',
            game_path TEXT DEFAULT '',
            thumbnail_url TEXT DEFAULT '',
            features TEXT DEFAULT '',
            requirements TEXT DEFAULT '',
            featured INTEGER DEFAULT 0,
            active INTEGER DEFAULT 1,
            sort_order INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
    }
}

function migration_011($db, $type) {
    if ($type === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS retro_games (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) DEFAULT '',
            console VARCHAR(100) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'original',
            rom_path VARCHAR(500) DEFAULT '',
            patch_url VARCHAR(500) DEFAULT '',
            description TEXT,
            thumbnail_url VARCHAR(255) DEFAULT '',
            emulator_core VARCHAR(100) DEFAULT '',
            active INT DEFAULT 1,
            featured INT DEFAULT 0,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS retro_games (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT DEFAULT '',
            console TEXT NOT NULL,
            type TEXT NOT NULL DEFAULT 'original',
            rom_path TEXT DEFAULT '',
            patch_url TEXT DEFAULT '',
            description TEXT DEFAULT '',
            thumbnail_url TEXT DEFAULT '',
            emulator_core TEXT DEFAULT '',
            active INTEGER DEFAULT 1,
            featured INTEGER DEFAULT 0,
            sort_order INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
    }
}

function migration_012($db, $type) {
    if ($type === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS retro_consoles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            icon VARCHAR(50) NOT NULL DEFAULT '🎮',
            emulator_core VARCHAR(100) NOT NULL DEFAULT '',
            active INT DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS retro_consoles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            icon TEXT NOT NULL DEFAULT '🎮',
            emulator_core TEXT NOT NULL DEFAULT '',
            active INTEGER DEFAULT 1,
            sort_order INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
    }

    $count = $db->query("SELECT COUNT(*) FROM retro_consoles")->fetchColumn();
    if ($count == 0) {
        $stmt = $db->prepare("INSERT INTO retro_consoles (name, slug, icon, emulator_core, active, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['SNES', 'snes', '🎮', 'snes9x', 1, 1]);
    }
}

function migration_013($db, $type) {
    // Add external_url, repo_url, is_open_source to games
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE games ADD COLUMN external_url VARCHAR(500) DEFAULT '' AFTER game_path");
        } else {
            $cols = $db->query("PRAGMA table_info(games)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('external_url', $cols)) {
                $db->exec("ALTER TABLE games ADD COLUMN external_url TEXT DEFAULT ''");
            }
        }
    } catch (Exception $e) {}

    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE games ADD COLUMN repo_url VARCHAR(500) DEFAULT '' AFTER external_url");
        } else {
            $cols = $db->query("PRAGMA table_info(games)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('repo_url', $cols)) {
                $db->exec("ALTER TABLE games ADD COLUMN repo_url TEXT DEFAULT ''");
            }
        }
    } catch (Exception $e) {}

    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE games ADD COLUMN is_open_source INT NOT NULL DEFAULT 0 AFTER repo_url");
        } else {
            $cols = $db->query("PRAGMA table_info(games)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('is_open_source', $cols)) {
                $db->exec("ALTER TABLE games ADD COLUMN is_open_source INTEGER NOT NULL DEFAULT 0");
            }
        }
    } catch (Exception $e) {}
}

function migration_014($db, $type) {
    // Seed retro consoles with official EmulatorJS cores
    $count = $db->query("SELECT COUNT(*) FROM retro_consoles")->fetchColumn();
    if ($count <= 1) {
        $consoles = [
            ['SNES', 'snes', '🎮', 'snes9x', 1, 1],
            ['NES', 'nes', '🕹️', 'fceumm', 1, 2],
            ['Game Boy', 'gb', '📱', 'gambatte', 1, 3],
            ['Game Boy Advance', 'gba', '📱', 'mgba', 1, 4],
            ['Nintendo 64', 'n64', '🎮', 'mupen64plus_next', 1, 5],
            ['Nintendo DS', 'nds', '📱', 'melonds', 1, 6],
            ['Sega Mega Drive', 'megadrive', '🎮', 'genesis_plus_gx', 1, 7],
            ['Sega Game Gear', 'gamegear', '📱', 'genesis_plus_gx', 1, 8],
            ['PlayStation', 'psx', '🎮', 'pcsx_rearmed', 1, 9],
            ['Arcade', 'arcade', '🕹️', 'fbneo', 1, 10],
        ];
        $prefix = $type === 'mysql' ? 'INSERT IGNORE INTO' : 'INSERT OR IGNORE INTO';
        $stmt = $db->prepare("$prefix retro_consoles (name, slug, icon, emulator_core, active, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($consoles as $c) {
            $stmt->execute($c);
        }
    }
}

function migration_015($db, $type) {
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE retro_consoles ADD COLUMN thumbnail_url VARCHAR(255) DEFAULT '' AFTER icon");
        } else {
            $cols = $db->query("PRAGMA table_info(retro_consoles)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('thumbnail_url', $cols)) {
                $db->exec("ALTER TABLE retro_consoles ADD COLUMN thumbnail_url TEXT DEFAULT ''");
            }
        }
    } catch (Exception $e) {}
}

function migration_016($db, $type) {
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE retro_games ADD COLUMN modification_description VARCHAR(60) DEFAULT '' AFTER type");
        } else {
            $cols = $db->query("PRAGMA table_info(retro_games)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('modification_description', $cols)) {
                $db->exec("ALTER TABLE retro_games ADD COLUMN modification_description TEXT DEFAULT ''");
            }
        }
    } catch (Exception $e) {}
}

function migration_017($db, $type) {
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE users MODIFY password_hash VARCHAR(255) NULL");
        }
    } catch (Exception $e) {}
}

function migration_018($db, $type) {
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER setup_token_expires");
            $db->exec("ALTER TABLE users ADD COLUMN email_verification_token VARCHAR(64) NULL AFTER email_verified_at");
        } else {
            $cols = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('email_verified_at', $cols)) {
                $db->exec("ALTER TABLE users ADD COLUMN email_verified_at TEXT DEFAULT NULL");
                $db->exec("ALTER TABLE users ADD COLUMN email_verification_token TEXT DEFAULT NULL");
            }
        }
    } catch (Exception $e) {}
    try {
        $db->prepare("UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL")->execute();
    } catch (Exception $e) {}
}

function migration_019($db, $type) {
    try {
        if ($type === 'mysql') {
            $db->exec("CREATE TABLE IF NOT EXISTS levels (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(50) NOT NULL UNIQUE,
                is_protected TINYINT(1) NOT NULL DEFAULT 0,
                perm_banners TINYINT(1) NOT NULL DEFAULT 0,
                perm_games TINYINT(1) NOT NULL DEFAULT 0,
                perm_blog TINYINT(1) NOT NULL DEFAULT 0,
                perm_testimonials TINYINT(1) NOT NULL DEFAULT 0,
                perm_faq TINYINT(1) NOT NULL DEFAULT 0,
                perm_team TINYINT(1) NOT NULL DEFAULT 0,
                perm_users TINYINT(1) NOT NULL DEFAULT 0,
                perm_roles TINYINT(1) NOT NULL DEFAULT 0,
                perm_engines TINYINT(1) NOT NULL DEFAULT 0,
                perm_platforms TINYINT(1) NOT NULL DEFAULT 0,
                perm_consoles TINYINT(1) NOT NULL DEFAULT 0,
                perm_retro_games TINYINT(1) NOT NULL DEFAULT 0,
                perm_templates TINYINT(1) NOT NULL DEFAULT 0,
                perm_optimizer TINYINT(1) NOT NULL DEFAULT 0,
                perm_settings TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $db->exec("CREATE TABLE IF NOT EXISTS levels (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                is_protected INTEGER NOT NULL DEFAULT 0,
                perm_banners INTEGER NOT NULL DEFAULT 0,
                perm_games INTEGER NOT NULL DEFAULT 0,
                perm_blog INTEGER NOT NULL DEFAULT 0,
                perm_testimonials INTEGER NOT NULL DEFAULT 0,
                perm_faq INTEGER NOT NULL DEFAULT 0,
                perm_team INTEGER NOT NULL DEFAULT 0,
                perm_users INTEGER NOT NULL DEFAULT 0,
                perm_roles INTEGER NOT NULL DEFAULT 0,
                perm_engines INTEGER NOT NULL DEFAULT 0,
                perm_platforms INTEGER NOT NULL DEFAULT 0,
                perm_consoles INTEGER NOT NULL DEFAULT 0,
                perm_retro_games INTEGER NOT NULL DEFAULT 0,
                perm_templates INTEGER NOT NULL DEFAULT 0,
                perm_optimizer INTEGER NOT NULL DEFAULT 0,
                perm_settings INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (Exception $e) {}

    $count = $db->query("SELECT COUNT(*) FROM levels")->fetchColumn();
    if ($count == 0) {
        $stmt = $db->prepare("INSERT INTO levels (name, slug, is_protected, perm_banners, perm_games, perm_blog, perm_testimonials, perm_faq, perm_team, perm_users, perm_roles, perm_engines, perm_platforms, perm_consoles, perm_retro_games, perm_templates, perm_optimizer, perm_settings) VALUES (?, ?, 1, 1,1,1,1,1,1,1,1,1,1,1,1,1,1,1)");
        $stmt->execute(['CEO', 'ceo']);
        $stmt = $db->prepare("INSERT INTO levels (name, slug, is_protected, perm_banners, perm_games, perm_blog, perm_testimonials, perm_faq, perm_team, perm_users, perm_roles, perm_engines, perm_platforms, perm_consoles, perm_retro_games, perm_templates, perm_optimizer, perm_settings) VALUES (?, ?, 0, 1,1,1,1,1,1,1,1,1,1,1,1,1,1,1)");
        $stmt->execute(['Chief', 'chief']);
        $stmt = $db->prepare("INSERT INTO levels (name, slug, is_protected, perm_banners, perm_games, perm_blog, perm_testimonials, perm_faq, perm_team, perm_users, perm_roles, perm_engines, perm_platforms, perm_consoles, perm_retro_games, perm_templates, perm_optimizer, perm_settings) VALUES (?, ?, 0, 1,1,1,1,1,1,0,0,0,0,0,0,0,0,0)");
        $stmt->execute(['Moderator', 'moderator']);
    }

    $needsLevelId = true;
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE roles ADD COLUMN level_id INT DEFAULT NULL AFTER level");
        } else {
            $cols = $db->query("PRAGMA table_info(roles)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (in_array('level_id', $cols)) {
                $needsLevelId = false;
            } else {
                $db->exec("ALTER TABLE roles ADD COLUMN level_id INTEGER DEFAULT NULL");
            }
        }
    } catch (Exception $e) {
        $needsLevelId = false;
    }

    if ($needsLevelId) {
        try {
            $db->exec("UPDATE roles SET level_id = (SELECT id FROM levels WHERE slug = 'ceo') WHERE level = 'ceo' AND (level_id IS NULL OR level_id = 0)");
            $db->exec("UPDATE roles SET level_id = (SELECT id FROM levels WHERE slug = 'chief') WHERE level = 'chief' AND (level_id IS NULL OR level_id = 0)");
            $db->exec("UPDATE roles SET level_id = (SELECT id FROM levels WHERE slug = 'moderator') WHERE level = 'moderator' AND (level_id IS NULL OR level_id = 0)");
            $fallbackId = $db->query("SELECT id FROM levels WHERE slug = 'moderator'")->fetchColumn();
            if ($fallbackId) {
                $db->prepare("UPDATE roles SET level_id = ? WHERE level_id IS NULL")->execute([$fallbackId]);
            }
        } catch (Exception $e) {}
    }
}

function migration_020($db, $type) {
    try {
        if ($type === 'mysql') {
            $tblCheck = $db->query("SHOW COLUMNS FROM game_templates LIKE 'has_free_file'")->fetch();
            if (!$tblCheck) {
                $db->exec("ALTER TABLE game_templates ADD COLUMN has_free_file TINYINT(1) NOT NULL DEFAULT 0 AFTER requirements");
            }
            $tblCheck = $db->query("SHOW COLUMNS FROM game_templates LIKE 'gallery'")->fetch();
            if (!$tblCheck) {
                $db->exec("ALTER TABLE game_templates ADD COLUMN gallery TEXT AFTER thumbnail_url");
                $db->exec("UPDATE game_templates SET gallery = '[]' WHERE gallery IS NULL");
            }
        } else {
            $cols = $db->query("PRAGMA table_info(game_templates)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('has_free_file', $cols)) {
                $db->exec("ALTER TABLE game_templates ADD COLUMN has_free_file INTEGER NOT NULL DEFAULT 0");
            }
            if (!in_array('gallery', $cols)) {
                $db->exec("ALTER TABLE game_templates ADD COLUMN gallery TEXT DEFAULT '[]'");
            }
        }
    } catch (Exception $e) {}
}

function migration_021($db, $type) {
    try {
        if ($type === 'mysql') {
            $cols = $db->query("SHOW COLUMNS FROM store_platforms LIKE 'use_logo'")->fetch();
            if (!$cols) {
                $db->exec("ALTER TABLE store_platforms ADD COLUMN use_logo TINYINT(1) NOT NULL DEFAULT 0 AFTER icon");
                $db->exec("ALTER TABLE store_platforms ADD COLUMN logo_path VARCHAR(500) NOT NULL DEFAULT '' AFTER use_logo");
            }
        } else {
            $cols = $db->query("PRAGMA table_info(store_platforms)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('use_logo', $cols)) {
                $db->exec("ALTER TABLE store_platforms ADD COLUMN use_logo INTEGER NOT NULL DEFAULT 0");
            }
            if (!in_array('logo_path', $cols)) {
                $db->exec("ALTER TABLE store_platforms ADD COLUMN logo_path TEXT NOT NULL DEFAULT ''");
            }
        }
    } catch (Exception $e) {}
}

function migration_022($db, $type) {
    // Create template_links table
    try {
        if ($type === 'mysql') {
            $db->exec("CREATE TABLE IF NOT EXISTS template_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                template_id INT NOT NULL,
                platform_id INT NOT NULL,
                url VARCHAR(500) NOT NULL,
                sort_order INT DEFAULT 0,
                FOREIGN KEY (template_id) REFERENCES game_templates(id) ON DELETE CASCADE,
                FOREIGN KEY (platform_id) REFERENCES store_platforms(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $db->exec("CREATE TABLE IF NOT EXISTS template_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                template_id INTEGER NOT NULL,
                platform_id INTEGER NOT NULL,
                url TEXT NOT NULL,
                sort_order INTEGER DEFAULT 0,
                FOREIGN KEY (template_id) REFERENCES game_templates(id) ON DELETE CASCADE,
                FOREIGN KEY (platform_id) REFERENCES store_platforms(id) ON DELETE CASCADE
            )");
        }
    } catch (Exception $e) {}

    // Migrate existing store_url to template_links
    try {
        $rows = $db->query("SELECT id, store_url FROM game_templates WHERE store_url IS NOT NULL AND store_url != ''")->fetchAll();
        if ($rows) {
            $genericPlatformId = $db->query("SELECT id FROM store_platforms WHERE slug = 'store'")->fetchColumn();
            if (!$genericPlatformId) {
                if ($type === 'mysql') {
                    $db->exec("INSERT INTO store_platforms (name, slug, icon, sort_order, active) VALUES ('Loja', 'store', '🛒', 999, 1)");
                } else {
                    $db->exec("INSERT INTO store_platforms (name, slug, icon, sort_order, active) VALUES ('Loja', 'store', '🛒', 999, 1)");
                }
                $genericPlatformId = $db->lastInsertId();
            }
            $stmt = $db->prepare("INSERT INTO template_links (template_id, platform_id, url, sort_order) VALUES (?, ?, ?, 0)");
            foreach ($rows as $row) {
                $stmt->execute([$row['id'], $genericPlatformId, $row['store_url']]);
            }
        }
    } catch (Exception $e) {}
}

function migration_023($db, $type) {
    // Drop banners.engine_tag — banner is carousel, not engine-specific
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE banners DROP COLUMN IF EXISTS engine_tag");
        } else {
            $db->exec("ALTER TABLE banners DROP COLUMN engine_tag");
        }
    } catch (Exception $e) {}
}

function migration_024($db, $type) {
    // Drop games.zip_filename — never used
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE games DROP COLUMN IF EXISTS zip_filename");
        } else {
            $db->exec("ALTER TABLE games DROP COLUMN zip_filename");
        }
    } catch (Exception $e) {}
}

function migration_025($db, $type) {
    // Drop retro_games.patch_url — never used
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE retro_games DROP COLUMN IF EXISTS patch_url");
        } else {
            $db->exec("ALTER TABLE retro_games DROP COLUMN patch_url");
        }
    } catch (Exception $e) {}
}

function migration_026($db, $type) {
    // Drop game_templates.store_url — migrated to template_links
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE game_templates DROP COLUMN IF EXISTS store_url");
        } else {
            $db->exec("ALTER TABLE game_templates DROP COLUMN store_url");
        }
    } catch (Exception $e) {}

    // Drop users.role — legacy, all logic uses role_id
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE users DROP COLUMN IF EXISTS `role`");
        } elseif (version_compare($db->getAttribute(PDO::ATTR_SERVER_VERSION), '3.35.0', '>=')) {
            $db->exec("ALTER TABLE users DROP COLUMN `role`");
        }
    } catch (Exception $e) {}

    // Drop roles.level — never used, display-only was from level_id
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE roles DROP COLUMN IF EXISTS `level`");
        } elseif (version_compare($db->getAttribute(PDO::ATTR_SERVER_VERSION), '3.35.0', '>=')) {
            $db->exec("ALTER TABLE roles DROP COLUMN `level`");
        }
    } catch (Exception $e) {}
}

function migration_027($db, $type) {
    // Add user_id to team_members — links team member to a user account
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE team_members ADD COLUMN user_id INT DEFAULT NULL");
        } else {
            $db->exec("ALTER TABLE team_members ADD COLUMN user_id INTEGER DEFAULT NULL");
        }
    } catch (Exception $e) {}

    // Seed master admin team member (user_id = 1)
    $masterUserId = $db->query("SELECT id FROM users WHERE id = 1")->fetchColumn();
    if ($masterUserId) {
        $exists = $db->query("SELECT id FROM team_members WHERE user_id = 1")->fetchColumn();
        if (!$exists) {
            $stmt = $db->prepare("INSERT INTO team_members (name, role, bio, avatar_url, social_youtube, social_twitch, social_linkedin, sort_order, active, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(['Administrador', 'CEO Administrador', 'Responsável pelo gerenciamento do portfólio de jogos.', '', '', '', '', 0, 1, 1]);
        }
    }
}

function migration_028($db, $type) {
    // Add aspect_ratio to games — container proportion for theater player
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE games ADD COLUMN aspect_ratio VARCHAR(10) DEFAULT '16:9'");
        } else {
            $db->exec("ALTER TABLE games ADD COLUMN aspect_ratio TEXT DEFAULT '16:9'");
        }
    } catch (Exception $e) {}
}

function migration_029($db, $type) {
    // Add iframe_width and iframe_height to games — manual sizing for externo iframes
    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE games ADD COLUMN iframe_width VARCHAR(10) DEFAULT '100%'");
            $db->exec("ALTER TABLE games ADD COLUMN iframe_height VARCHAR(10) DEFAULT '100%'");
        } else {
            $db->exec("ALTER TABLE games ADD COLUMN iframe_width TEXT DEFAULT '100%'");
            $db->exec("ALTER TABLE games ADD COLUMN iframe_height TEXT DEFAULT '100%'");
        }
    } catch (Exception $e) {}
}

function migration_034($db, $type) {
    try {
        if ($type === 'mysql') {
            $db->exec("CREATE TABLE IF NOT EXISTS social_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                scope VARCHAR(20) NOT NULL DEFAULT 'site',
                platform_key VARCHAR(50) NOT NULL,
                label VARCHAR(100) NOT NULL DEFAULT '',
                url VARCHAR(500) NOT NULL DEFAULT '',
                image_path VARCHAR(500) NOT NULL DEFAULT '',
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $db->exec("CREATE TABLE IF NOT EXISTS social_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                scope TEXT NOT NULL DEFAULT 'site',
                platform_key TEXT NOT NULL,
                label TEXT NOT NULL DEFAULT '',
                url TEXT NOT NULL DEFAULT '',
                image_path TEXT NOT NULL DEFAULT '',
                active INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (Exception $e) {}

    try {
        if ($type === 'mysql') {
            $cols = $db->query("SHOW COLUMNS FROM social_links LIKE 'image_path'")->fetch();
            if (!$cols) {
                $db->exec("ALTER TABLE social_links ADD COLUMN image_path VARCHAR(500) NOT NULL DEFAULT '' AFTER url");
            }
        } else {
            $cols = $db->query("PRAGMA table_info(social_links)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('image_path', $cols)) {
                $db->exec("ALTER TABLE social_links ADD COLUMN image_path TEXT NOT NULL DEFAULT ''");
            }
        }
    } catch (Exception $e) {}

    try {
        if ($type === 'mysql') {
            $db->exec("CREATE TABLE IF NOT EXISTS ad_slots (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slot_key VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(100) NOT NULL,
                provider VARCHAR(30) NOT NULL DEFAULT 'custom_html',
                code_html LONGTEXT,
                active TINYINT(1) NOT NULL DEFAULT 0,
                pages VARCHAR(255) NOT NULL DEFAULT '',
                devices VARCHAR(100) NOT NULL DEFAULT 'all',
                sticky TINYINT(1) NOT NULL DEFAULT 0,
                height_desktop VARCHAR(20) NOT NULL DEFAULT '',
                height_mobile VARCHAR(20) NOT NULL DEFAULT '',
                fallback_text VARCHAR(255) NOT NULL DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $db->exec("CREATE TABLE IF NOT EXISTS ad_slots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slot_key TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                provider TEXT NOT NULL DEFAULT 'custom_html',
                code_html TEXT DEFAULT '',
                active INTEGER NOT NULL DEFAULT 0,
                pages TEXT NOT NULL DEFAULT '',
                devices TEXT NOT NULL DEFAULT 'all',
                sticky INTEGER NOT NULL DEFAULT 0,
                height_desktop TEXT NOT NULL DEFAULT '',
                height_mobile TEXT NOT NULL DEFAULT '',
                fallback_text TEXT NOT NULL DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (Exception $e) {}

    try {
        if ($type === 'mysql') {
            $db->exec("CREATE TABLE IF NOT EXISTS distribution_platforms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                icon VARCHAR(50) NOT NULL DEFAULT '',
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS game_distribution_stats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                game_id INT NOT NULL,
                platform_id INT NOT NULL,
                metric_key VARCHAR(50) NOT NULL,
                metric_value DECIMAL(18,2) NOT NULL DEFAULT 0,
                period_start DATE DEFAULT NULL,
                period_end DATE DEFAULT NULL,
                source VARCHAR(50) NOT NULL DEFAULT 'manual',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_gds_game_platform (game_id, platform_id),
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
                FOREIGN KEY (platform_id) REFERENCES distribution_platforms(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS campaigns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                game_id INT DEFAULT NULL,
                platform_id INT DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                budget DECIMAL(12,2) NOT NULL DEFAULT 0,
                start_at DATETIME DEFAULT NULL,
                end_at DATETIME DEFAULT NULL,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE SET NULL,
                FOREIGN KEY (platform_id) REFERENCES distribution_platforms(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS campaign_metrics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                campaign_id INT NOT NULL,
                metric_key VARCHAR(50) NOT NULL,
                metric_value DECIMAL(18,2) NOT NULL DEFAULT 0,
                period_start DATE DEFAULT NULL,
                period_end DATE DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $db->exec("CREATE TABLE IF NOT EXISTS distribution_platforms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                icon TEXT NOT NULL DEFAULT '',
                active INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS game_distribution_stats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id INTEGER NOT NULL,
                platform_id INTEGER NOT NULL,
                metric_key TEXT NOT NULL,
                metric_value REAL NOT NULL DEFAULT 0,
                period_start TEXT DEFAULT NULL,
                period_end TEXT DEFAULT NULL,
                source TEXT NOT NULL DEFAULT 'manual',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
                FOREIGN KEY (platform_id) REFERENCES distribution_platforms(id) ON DELETE CASCADE
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS campaigns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                game_id INTEGER DEFAULT NULL,
                platform_id INTEGER DEFAULT NULL,
                status TEXT NOT NULL DEFAULT 'draft',
                budget REAL NOT NULL DEFAULT 0,
                start_at TEXT DEFAULT NULL,
                end_at TEXT DEFAULT NULL,
                notes TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE SET NULL,
                FOREIGN KEY (platform_id) REFERENCES distribution_platforms(id) ON DELETE SET NULL
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS campaign_metrics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campaign_id INTEGER NOT NULL,
                metric_key TEXT NOT NULL,
                metric_value REAL NOT NULL DEFAULT 0,
                period_start TEXT DEFAULT NULL,
                period_end TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
            )");
        }
    } catch (Exception $e) {}

    try {
        $count = (int)$db->query("SELECT COUNT(*) FROM social_links")->fetchColumn();
        if ($count === 0) {
            $stmt = $db->prepare("INSERT INTO social_links (scope, platform_key, label, url, image_path, active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ([
                ['site', 'youtube', 'YouTube', '', '', 1, 1],
                ['site', 'twitch', 'Twitch', '', '', 1, 2],
                ['site', 'x', 'X', '', '', 1, 3],
                ['site', 'tiktok', 'TikTok', '', '', 1, 4],
                ['site', 'facebook', 'Facebook', '', '', 1, 5],
                ['site', 'instagram', 'Instagram', '', '', 1, 6],
                ['site', 'linkedin', 'LinkedIn', '', '', 1, 7],
                ['site', 'kick', 'Kick', '', '', 1, 8],
                ['site', 'kwai', 'Kwai', '', '', 1, 9],
            ] as $row) {
                $stmt->execute($row);
            }
        }
    } catch (Exception $e) {}

    try {
        $count = (int)$db->query("SELECT COUNT(*) FROM ad_slots")->fetchColumn();
        if ($count === 0) {
            $stmt = $db->prepare("INSERT INTO ad_slots (slot_key, name, provider, code_html, active, pages, devices, sticky, height_desktop, height_mobile, fallback_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ([
                ['home_top', 'Topo da Home', 'custom_html', '', 0, 'home', 'all', 0, '90px', '70px', 'Espaço reservado para anúncio'],
                ['home_inline_1', 'Inline da Home', 'custom_html', '', 0, 'home', 'all', 0, '250px', '200px', 'Publicidade'],
                ['game_before_player', 'Antes do Player', 'custom_html', '', 0, 'game', 'desktop,mobile', 0, '90px', '70px', 'Publicidade'],
                ['game_after_player', 'Depois do Player', 'custom_html', '', 0, 'game', 'desktop,mobile', 0, '90px', '70px', 'Publicidade'],
                ['catalogo_sidebar', 'Sidebar do Catálogo', 'custom_html', '', 0, 'catalogo', 'desktop', 0, '250px', '', 'Publicidade'],
                ['mobile_sticky', 'Sticky Mobile', 'custom_html', '', 0, 'all', 'mobile', 1, '', '60px', 'Publicidade'],
            ] as $row) {
                $stmt->execute($row);
            }
        }
    } catch (Exception $e) {}

    try {
        $count = (int)$db->query("SELECT COUNT(*) FROM distribution_platforms")->fetchColumn();
        if ($count === 0) {
            $stmt = $db->prepare("INSERT INTO distribution_platforms (name, slug, icon, active, sort_order) VALUES (?, ?, ?, ?, ?)");
            foreach ([
                ['Site Próprio', 'site', '🌐', 1, 1],
                ['Steam', 'steam', '🎮', 1, 2],
                ['Epic Games', 'epic', '✨', 1, 3],
                ['Play Store', 'play_store', '📱', 1, 4],
                ['App Store', 'app_store', '📱', 1, 5],
                ['GOG', 'gog', '🧩', 0, 6],
            ] as $row) {
                $stmt->execute($row);
            }
        }
    } catch (Exception $e) {}
}

function migration_036($db, $type) {
    // Distribution integration hub tables
    try {
        if ($type === 'mysql') {
            $db->exec("CREATE TABLE IF NOT EXISTS distribution_integrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                platform_id INT NOT NULL,
                name VARCHAR(150) NOT NULL,
                integration_type VARCHAR(50) NOT NULL DEFAULT 'manual',
                config_json LONGTEXT,
                active TINYINT(1) NOT NULL DEFAULT 1,
                last_sync_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (platform_id) REFERENCES distribution_platforms(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS distribution_game_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                game_id INT NOT NULL,
                integration_id INT DEFAULT NULL,
                platform_id INT NOT NULL,
                store_url VARCHAR(500) NOT NULL DEFAULT '',
                store_package_id VARCHAR(255) NOT NULL DEFAULT '',
                store_status VARCHAR(30) NOT NULL DEFAULT 'pending',
                version_name VARCHAR(50) NOT NULL DEFAULT '',
                last_sync_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_dgl_game (game_id),
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
                FOREIGN KEY (integration_id) REFERENCES distribution_integrations(id) ON DELETE SET NULL,
                FOREIGN KEY (platform_id) REFERENCES distribution_platforms(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS distribution_sync_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                integration_id INT NOT NULL,
                game_link_id INT DEFAULT NULL,
                direction VARCHAR(20) NOT NULL DEFAULT 'pull',
                status VARCHAR(20) NOT NULL DEFAULT 'success',
                message TEXT,
                details_json LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (integration_id) REFERENCES distribution_integrations(id) ON DELETE CASCADE,
                FOREIGN KEY (game_link_id) REFERENCES distribution_game_links(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $db->exec("CREATE TABLE IF NOT EXISTS distribution_integrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                platform_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                integration_type TEXT NOT NULL DEFAULT 'manual',
                config_json TEXT DEFAULT '',
                active INTEGER NOT NULL DEFAULT 1,
                last_sync_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (platform_id) REFERENCES distribution_platforms(id) ON DELETE CASCADE
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS distribution_game_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id INTEGER NOT NULL,
                integration_id INTEGER DEFAULT NULL,
                platform_id INTEGER NOT NULL,
                store_url TEXT NOT NULL DEFAULT '',
                store_package_id TEXT NOT NULL DEFAULT '',
                store_status TEXT NOT NULL DEFAULT 'pending',
                version_name TEXT NOT NULL DEFAULT '',
                last_sync_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
                FOREIGN KEY (integration_id) REFERENCES distribution_integrations(id) ON DELETE SET NULL,
                FOREIGN KEY (platform_id) REFERENCES distribution_platforms(id) ON DELETE CASCADE
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS distribution_sync_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                integration_id INTEGER NOT NULL,
                game_link_id INTEGER DEFAULT NULL,
                direction TEXT NOT NULL DEFAULT 'pull',
                status TEXT NOT NULL DEFAULT 'success',
                message TEXT DEFAULT '',
                details_json TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (integration_id) REFERENCES distribution_integrations(id) ON DELETE CASCADE,
                FOREIGN KEY (game_link_id) REFERENCES distribution_game_links(id) ON DELETE SET NULL
            )");
        }
    } catch (Exception $e) {}

    // Seed Play Store integration entry
    try {
        $psId = $db->query("SELECT id FROM distribution_platforms WHERE slug = 'play_store'")->fetchColumn();
        if ($psId) {
            $count = (int)$db->query("SELECT COUNT(*) FROM distribution_integrations WHERE platform_id = $psId")->fetchColumn();
            if ($count === 0) {
                $db->prepare("INSERT INTO distribution_integrations (platform_id, name, integration_type, active) VALUES (?, ?, ?, ?)")
                    ->execute([$psId, 'Google Play Console', 'google_play', 1]);
            }
        }
    } catch (Exception $e) {}
}

function dbSeed($db, $type) {
    // Seed default roles
    $roleCount = $db->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    if ($roleCount == 0) {
        $stmt = $db->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
        $stmt->execute(['CEO Administrador', 'Administrador master do sistema — controle total sobre todas as operações']);
        $stmt->execute(['CEO Sócio', 'Sócio com poderes administrativos — pode criar cargos de nível chief e moderator']);
        $stmt->execute(['CEO Investidor', 'Investidor com poderes administrativos — pode criar cargos de nível chief e moderator']);
        $stmt->execute(['CTO', 'Chief Technology Officer — gerencia conteúdo, jogos e cargos moderator']);
        $stmt->execute(['CMO', 'Chief Marketing Officer — gerencia conteúdo, blog e cargos moderator']);
        $stmt->execute(['Moderator', 'Gerenciamento operacional da plataforma — conteúdo e suporte']);
    }

    // Seed default admin user
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM users");
    $row = $stmt->fetch();
    if ($row['cnt'] == 0) {
        $ceoRoleId = $db->query("SELECT id FROM roles WHERE name = 'CEO Administrador'")->fetchColumn();
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, role_id) VALUES (?, ?, ?)");
        $stmt->execute([ADMIN_USERNAME, ADMIN_PASSWORD_HASH, $ceoRoleId]);
    } else {
        // Ensure admin user has role_id set
        $ceoRoleId = $db->query("SELECT id FROM roles WHERE name = 'CEO Administrador'")->fetchColumn();
        if ($ceoRoleId) {
            $db->prepare("UPDATE users SET role_id = ? WHERE id = 1 AND (role_id IS NULL OR role_id = 0)")->execute([$ceoRoleId]);
        }
    }

    // Seed default settings
    $defaults = [
        ['site_name', SITE_NAME],
        ['site_tagline', SITE_TAGLINE],
        ['contact_email', 'contato@exemplo.com.br'],
        ['contact_whatsapp', ''],
        ['footer_description', 'Sistema de gerenciamento de portfólio de jogos digitais. Publique seus games em diversas engines para qualquer plataforma.'],
    ];

    $seedCount = $db->query("SELECT COUNT(*) as cnt FROM site_settings")->fetch();
    if ($seedCount['cnt'] == 0) {
        $prefix = $type === 'mysql' ? 'INSERT IGNORE INTO' : 'INSERT OR IGNORE INTO';
        $stmt = $db->prepare("$prefix site_settings (`key`, `value`) VALUES (?, ?)");
        foreach ($defaults as $def) {
            $stmt->execute($def);
        }
    }

    // Seed banners, games, testimonials, FAQ, team
    seedDefaultData($db);

    // Seed demo distribution campaigns and metrics
    seedDemoDistributionData($db);
}

function seedDemoDistributionData($db) {
    $existingCampaigns = $db->query("SELECT COUNT(*) FROM campaigns")->fetchColumn();
    if ((int)$existingCampaigns > 0) return;

    $games = $db->query("SELECT id FROM games ORDER BY id LIMIT 5")->fetchAll();
    if (empty($games)) return;

    $platformRows = $db->query("SELECT id FROM platforms ORDER BY id LIMIT 5")->fetchAll();
    if (empty($platformRows)) return;

    $demoCampaigns = [
        ['name' => 'Lançamento Steam', 'platform_idx' => 0, 'status' => 'active', 'budget' => 5000.00, 'start' => '-3 days', 'end' => '+10 days'],
        ['name' => 'Campanha Epic', 'platform_idx' => 1, 'status' => 'active', 'budget' => 3000.00, 'start' => '-1 day', 'end' => '+14 days'],
        ['name' => 'Push itch.io', 'platform_idx' => 3, 'status' => 'finished', 'budget' => 500.00, 'start' => '-30 days', 'end' => '-7 days'],
    ];

    $insertedCampaigns = [];
    foreach ($demoCampaigns as $i => $c) {
        $gameId = $games[$i % count($games)]['id'];
        $platformId = $platformRows[$c['platform_idx'] % count($platformRows)]['id'];
        $stmt = $db->prepare("INSERT INTO campaigns (name, game_id, platform_id, status, budget, start_at, end_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute([
            $c['name'], $gameId, $platformId, $c['status'], $c['budget'],
            date('Y-m-d', strtotime($c['start'])),
            date('Y-m-d', strtotime($c['end'])),
        ]);
        $insertedCampaigns[] = $db->lastInsertId();
    }

    $metricKeys = ['views', 'clicks', 'installs', 'revenue'];
    foreach ($insertedCampaigns as $cid) {
        foreach ($metricKeys as $mk) {
            $val = match($mk) {
                'views' => rand(100, 5000),
                'clicks' => rand(20, 300),
                'installs' => rand(5, 80),
                'revenue' => rand(500, 5000) / 100,
                default => 0,
            };
            $period = date('Y-m-d', strtotime('-' . rand(1, 7) . ' days'));
            $stmt = $db->prepare("INSERT INTO campaign_metrics (campaign_id, metric_key, metric_value, period_start, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$cid, $mk, $val, $period]);
        }
    }
}

// ── Migration 043: Newsletter subscribers table ──
function migration_043($db, $type) {
    $pkType = $type === 'mysql' ? 'INT' : 'INTEGER';
    $txt = $type === 'mysql' ? 'VARCHAR(255)' : 'TEXT';

    $db->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id $pkType PRIMARY KEY AUTOINCREMENT,
        email $txt NOT NULL UNIQUE,
        name $txt DEFAULT '',
        source $txt NOT NULL DEFAULT 'site',
        subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        confirmed_at DATETIME DEFAULT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        unsubscribe_token $txt NOT NULL,
        last_sent_at DATETIME DEFAULT NULL,
        tags $txt DEFAULT ''
    )");

    if ($type === 'mysql') {
        $db->exec("ALTER TABLE newsletter_subscribers ADD COLUMN preferences JSON DEFAULT NULL");
    } else {
        $db->exec("ALTER TABLE newsletter_subscribers ADD COLUMN preferences TEXT DEFAULT NULL");
    }
}

function seedDefaultData($db) {
    $count = $db->query("SELECT COUNT(*) FROM banners")->fetchColumn();
    if ($count > 0) return;

    $banners = [
        ['title' => 'Seu Portfólio de Jogos', 'subtitle' => 'Publique em qualquer engine', 'description' => 'Gerencie e publique seus jogos digitais em uma plataforma unificada. Compatível com GDevelop, Godot, Unity, RPG Maker e muito mais.', 'image_url' => '', 'cta_text' => 'Ver Portfólio', 'cta_url' => '#portfolio', 'sort_order' => 1, 'active' => 1],
        ['title' => 'Multi-Engine', 'subtitle' => 'Qualquer engine, qualquer plataforma', 'description' => 'GDevelop, Godot, RPG Maker, Unity, Construct, Game Maker e outras. Sua escolha, seu jogo.', 'image_url' => '', 'cta_text' => 'Ver Engines', 'cta_url' => '#engines', 'sort_order' => 2, 'active' => 1],
        ['title' => 'Demonstração', 'subtitle' => 'Explore os recursos do CMS', 'description' => 'Este é um ambiente de demonstração. Configure seus banners, adicione jogos e personalize seu site.', 'image_url' => '', 'cta_text' => 'Começar', 'cta_url' => '#start', 'sort_order' => 3, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO banners (title, subtitle, description, image_url, cta_text, cta_url, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($banners as $b) {
        $stmt->execute([$b['title'], $b['subtitle'], $b['description'], $b['image_url'], $b['cta_text'], $b['cta_url'], $b['sort_order'], $b['active']]);
    }

    $games = [
        ['title' => 'Meu Primeiro Jogo', 'slug' => 'meu-primeiro-jogo', 'engine' => 'GDevelop', 'description' => 'Um jogo de exemplo criado com GDevelop para demonstrar os recursos do CMS.', 'thumbnail_url' => '', 'game_path' => '', 'featured' => 1, 'orientation' => 'landscape', 'sort_order' => 1, 'active' => 1],
        ['title' => 'Aventura em Pixel', 'slug' => 'aventura-em-pixel', 'engine' => 'Godot', 'description' => 'Jogo de plataforma 2D com pixel art e física precisa.', 'thumbnail_url' => '', 'game_path' => '', 'featured' => 1, 'orientation' => 'landscape', 'sort_order' => 2, 'active' => 1],
        ['title' => 'RPG das Dungeons', 'slug' => 'rpg-das-dungeons', 'engine' => 'RPG Maker', 'description' => 'RPG clássico com exploração, batalhas e uma história envolvente.', 'thumbnail_url' => '', 'game_path' => '', 'featured' => 0, 'orientation' => 'landscape', 'sort_order' => 3, 'active' => 1],
        ['title' => 'Corrida Arcade', 'slug' => 'corrida-arcade', 'engine' => 'Unity', 'description' => 'Jogo de corrida arcade com gráficos 3D e trilha sonora eletrônica.', 'thumbnail_url' => '', 'game_path' => '', 'featured' => 0, 'orientation' => 'landscape', 'sort_order' => 4, 'active' => 1],
        ['title' => 'Visual Novel Demo', 'slug' => 'visual-novel-demo', 'engine' => "Ren'py", 'description' => 'Uma visual novel interativa demonstrando recursos de narrativa e escolhas.', 'thumbnail_url' => '', 'game_path' => '', 'featured' => 0, 'orientation' => 'portrait', 'sort_order' => 5, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO games (title, slug, engine, description, thumbnail_url, game_path, featured, orientation, aspect_ratio, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($games as $g) {
        $stmt->execute([$g['title'], $g['slug'], $g['engine'], $g['description'], $g['thumbnail_url'], $g['game_path'], $g['featured'], $g['orientation'], '16:9', $g['sort_order'], $g['active']]);
    }

    $testimonials = [
        ['name' => 'Maria Souza', 'role' => 'Game Designer', 'quote' => 'O CMS simplificou todo o processo de publicação dos meus jogos. Interface intuitiva e suporte a várias engines.', 'avatar_url' => '', 'sort_order' => 1, 'active' => 1],
        ['name' => 'João Lima', 'role' => 'Indie Developer', 'quote' => 'Finalmente um sistema que me permite gerenciar todo meu portfólio em um só lugar. Recomendo!', 'avatar_url' => '', 'sort_order' => 2, 'active' => 1],
        ['name' => 'Ana Costa', 'role' => 'Produtora de Conteúdo', 'quote' => 'Uso para hospedar meus projetos e compartilhar com seguidores. Funciona perfeitamente.', 'avatar_url' => '', 'sort_order' => 3, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO testimonials (name, role, quote, avatar_url, sort_order, active) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($testimonials as $t) {
        $stmt->execute([$t['name'], $t['role'], $t['quote'], $t['avatar_url'], $t['sort_order'], $t['active']]);
    }

    $faqs = [
        ['question' => 'Quais engines são compatíveis?', 'answer' => 'GDevelop, Godot, RPG Maker, Unity, Unreal Engine, Construct, Defold, Game Maker, Ren\'py e muitas outras.', 'sort_order' => 1, 'active' => 1],
        ['question' => 'Como publicar um jogo?', 'answer' => 'Faça upload do arquivo ZIP do seu jogo pelo painel admin. O sistema extrai e disponibiliza automaticamente para jogar.', 'sort_order' => 2, 'active' => 1],
        ['question' => 'Posso usar meu próprio domínio?', 'answer' => 'Sim! Configure o domínio no arquivo de configuração e o CMS funcionará normalmente com seu domínio personalizado.', 'sort_order' => 3, 'active' => 1],
        ['question' => 'Quais plataformas são suportadas?', 'answer' => 'Os jogos podem ser publicados para Web, e o CMS gerencia todo o portfólio em uma interface centralizada.', 'sort_order' => 4, 'active' => 1],
        ['question' => 'É possível ter múltiplos usuários?', 'answer' => 'Sim! O administrador master pode criar contas adicionais para outros usuários com acesso ao painel.', 'sort_order' => 5, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO faq_items (question, answer, sort_order, active) VALUES (?, ?, ?, ?)");
    foreach ($faqs as $f) {
        $stmt->execute([$f['question'], $f['answer'], $f['sort_order'], $f['active']]);
    }

    $team = [
        ['name' => 'Administrador', 'role' => 'CEO Administrador', 'bio' => 'Responsável pelo gerenciamento do portfólio de jogos.', 'avatar_url' => '', 'social_youtube' => '', 'social_twitch' => '', 'social_linkedin' => '', 'sort_order' => 0, 'active' => 1, 'user_id' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO team_members (name, role, bio, avatar_url, social_youtube, social_twitch, social_linkedin, sort_order, active, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($team as $t) {
        $stmt->execute([$t['name'], $t['role'], $t['bio'], $t['avatar_url'], $t['social_youtube'], $t['social_twitch'], $t['social_linkedin'], $t['sort_order'], $t['active'], $t['user_id']]);
    }
}

function migration_030($db, $type) {
    // Sync queue for automatic S3 sync
    try {
        if ($type === 'mysql') {
            $db->exec("CREATE TABLE IF NOT EXISTS sync_queue (
                id INT AUTO_INCREMENT PRIMARY KEY,
                local_path VARCHAR(500) NOT NULL,
                s3_name VARCHAR(500) NOT NULL,
                ref_table VARCHAR(100) DEFAULT '',
                ref_column VARCHAR(100) DEFAULT '',
                ref_id INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $db->exec("CREATE TABLE IF NOT EXISTS sync_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                local_path TEXT NOT NULL,
                s3_name TEXT NOT NULL,
                ref_table TEXT DEFAULT '',
                ref_column TEXT DEFAULT '',
                ref_id INTEGER DEFAULT NULL,
                created_at TEXT DEFAULT (datetime('now'))
            )");
        }
    } catch (Exception $e) {}
}

function migration_031($db, $type) {
    // Settings for S3 auto-sync and serve-media
    try {
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM site_settings WHERE key = ?");
        $stmtInsert = $db->prepare("INSERT INTO site_settings (`key`, `value`) VALUES (?, ?)");
        $set = function($key, $val) use ($stmtCheck, $stmtInsert) {
            $stmtCheck->execute([$key]);
            if (!$stmtCheck->fetchColumn()) {
                $stmtInsert->execute([$key, $val]);
            }
        };
        $set('s3_auto_sync', '0');
        $set('s3_serve_media', '0');
    } catch (Exception $e) {}

    // Normalize all media URLs to relative /uploads/... paths
    $columns = [
        ['games', 'thumbnail_url'],
        ['blog_posts', 'thumbnail_url'],
        ['banners', 'image_url'],
        ['team_members', 'avatar_url'],
        ['testimonials', 'avatar_url'],
        ['users', 'avatar_url'],
        ['game_templates', 'thumbnail_url'],
        ['retro_consoles', 'thumbnail_url'],
        ['retro_games', 'thumbnail_url'],
        ['store_platforms', 'logo_path'],
    ];

    foreach ($columns as $col) {
        try {
            $q = $db->prepare("SELECT id, {$col[1]} FROM {$col[0]} WHERE {$col[1]} LIKE ?");
            $q->execute(['http%']);
            $u = $db->prepare("UPDATE {$col[0]} SET {$col[1]} = ? WHERE id = ?");
            foreach ($q as $row) {
                $url = $row[$col[1]];
                $pos = strpos($url, '/uploads/');
                if ($pos !== false) {
                    $u->execute([substr($url, $pos), $row['id']]);
            }
        }
    } catch (Exception $e) {}
}

    // Normalize site_logo_url and site_favicon_url
    try {
        $s = $db->prepare("SELECT value FROM site_settings WHERE key = ?");
        $u = $db->prepare("UPDATE site_settings SET value = ? WHERE key = ?");
        foreach (['site_logo_url', 'site_favicon_url'] as $key) {
            $s->execute([$key]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if ($row && str_starts_with($row['value'] ?? '', 'http')) {
                $pos = strpos($row['value'], '/uploads/');
                if ($pos !== false) {
                    $u->execute([substr($row['value'], $pos), $key]);
                }
            }
        }
    } catch (Exception $e) {}
}

// ── Migration 037: Drop dead tables and permissions ──
function migration_037($db, $type) {

    // Drop template_links table (foreign key first)
    try {
        $db->exec("DROP TABLE IF EXISTS template_links");
    } catch (Exception $e) {}

    // Drop game_templates table
    try {
        $db->exec("DROP TABLE IF EXISTS game_templates");
    } catch (Exception $e) {}

    // Remove perm_templates column from levels (if exists)
    try {
        if ($type === 'mysql') {
            $cols = $db->query("SHOW COLUMNS FROM levels LIKE 'perm_templates'")->fetch();
            if ($cols) $db->exec("ALTER TABLE levels DROP COLUMN perm_templates");
        } else {
            $cols = $db->query("PRAGMA table_info(levels)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (in_array('perm_templates', $cols)) {
                $db->exec("ALTER TABLE levels DROP COLUMN perm_templates");
            }
        }
    } catch (Exception $e) {}

    // Remove perm_optimizer column from levels (if exists)
    try {
        if ($type === 'mysql') {
            $cols = $db->query("SHOW COLUMNS FROM levels LIKE 'perm_optimizer'")->fetch();
            if ($cols) $db->exec("ALTER TABLE levels DROP COLUMN perm_optimizer");
        } else {
            $cols = $db->query("PRAGMA table_info(levels)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (in_array('perm_optimizer', $cols)) {
                $db->exec("ALTER TABLE levels DROP COLUMN perm_optimizer");
            }
        }
    } catch (Exception $e) {}
}

// ── Migration 038: Create AI system tables ──
function migration_038($db, $type) {
    $pkType = $type === 'mysql' ? 'INT' : 'INTEGER';

    $db->exec("CREATE TABLE IF NOT EXISTS ai_providers (
        id $pkType PRIMARY KEY AUTOINCREMENT,
        slug TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        base_url TEXT NOT NULL,
        auth_type TEXT DEFAULT 'bearer',
        models_endpoint TEXT DEFAULT '/v1/models',
        chat_endpoint TEXT DEFAULT '/v1/chat/completions',
        supports_streaming INTEGER DEFAULT 1,
        active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS ai_configs (
        id $pkType PRIMARY KEY AUTOINCREMENT,
        provider_id $pkType NOT NULL,
        user_id $pkType,
        api_key TEXT,
        model_slug TEXT,
        max_tokens INTEGER DEFAULT 4096,
        temperature REAL DEFAULT 0.7,
        system_prompt TEXT,
        is_default INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (provider_id) REFERENCES ai_providers(id) ON DELETE CASCADE
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS ai_usage (
        id $pkType PRIMARY KEY AUTOINCREMENT,
        config_id $pkType NOT NULL,
        feature TEXT NOT NULL,
        prompt_tokens INTEGER DEFAULT 0,
        completion_tokens INTEGER DEFAULT 0,
        cost_cents INTEGER DEFAULT 0,
        latency_ms INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (config_id) REFERENCES ai_configs(id) ON DELETE CASCADE
    )");

    // Seed default providers
    $providers = [
        ['zen', 'OpenCode Zen', 'https://opencode.ai/zen/v1', 'bearer', '/v1/models', '/v1/chat/completions', 1],
        ['ollama', 'Ollama (Local)', 'http://localhost:11434/v1', 'bearer', '/v1/models', '/v1/chat/completions', 1],
        ['lmstudio', 'LM Studio (Local)', 'http://localhost:1234/v1', 'bearer', '/v1/models', '/v1/chat/completions', 1],
        ['openai-compat', 'OpenAI Compatible', 'https://api.openai.com/v1', 'bearer', '/v1/models', '/v1/chat/completions', 1],
    ];

    $stmt = $db->prepare("INSERT OR IGNORE INTO ai_providers (slug, name, base_url, auth_type, models_endpoint, chat_endpoint, supports_streaming) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($providers as $p) {
        $stmt->execute($p);
    }

    // Set Zen as default
    $db->exec("UPDATE ai_providers SET active = 1 WHERE slug = 'zen'");

    // Seed default Zen config (free tier, no API key needed)
    $zenId = $db->query("SELECT id FROM ai_providers WHERE slug = 'zen'")->fetchColumn();
    if ($zenId) {
        $exists = $db->query("SELECT COUNT(*) FROM ai_configs WHERE provider_id = {$zenId}")->fetchColumn();
        if (!$exists) {
            $db->exec("INSERT INTO ai_configs (provider_id, model_slug, max_tokens, temperature, system_prompt, is_default)
                       VALUES ({$zenId}, 'mimo-v2.5-free', 4096, 0.7, 'Você é um assistente útil.', 1)");
        }
    }
}

// ── Migration 040: Unify store_platforms and distribution_platforms ──
function migration_040($db, $type) {
    $pkType = $type === 'mysql' ? 'INT' : 'INTEGER';
    $txt = $type === 'mysql' ? 'VARCHAR(255)' : 'TEXT';

    // Disable FK enforcement for SQLite (we need to remap FKs)
    if ($type === 'sqlite') {
        $db->exec("PRAGMA foreign_keys = OFF");
    }

    // Create unified platforms table
    $db->exec("CREATE TABLE IF NOT EXISTS platforms (
        id $pkType PRIMARY KEY AUTOINCREMENT,
        name $txt NOT NULL,
        slug $txt NOT NULL UNIQUE,
        icon $txt NOT NULL DEFAULT '',
        visibility $txt NOT NULL DEFAULT 'public',
        active INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        use_logo INTEGER NOT NULL DEFAULT 0,
        logo_path TEXT NOT NULL DEFAULT '',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Check if old tables exist and have data
    $oldTables = ['store_platforms' => 0, 'distribution_platforms' => 0];
    try {
        $oldTables['store_platforms'] = (int)$db->query("SELECT COUNT(*) FROM store_platforms")->fetchColumn();
    } catch (Exception $e) {}
    try {
        $oldTables['distribution_platforms'] = (int)$db->query("SELECT COUNT(*) FROM distribution_platforms")->fetchColumn();
    } catch (Exception $e) {}

    $totalOld = array_sum($oldTables);

    // Check if platforms already has data (idempotency)
    $platformCount = 0;
    try {
        $platformCount = (int)$db->query("SELECT COUNT(*) FROM platforms")->fetchColumn();
    } catch (Exception $e) {}

    if ($platformCount > 0) {
        // Platforms table already populated — skip migration, just ensure old tables are dropped
        if ($oldTables['store_platforms'] > 0 || $oldTables['distribution_platforms'] > 0) {
            try { $db->exec("DROP TABLE IF EXISTS store_platforms"); } catch (Exception $e) {}
            try { $db->exec("DROP TABLE IF EXISTS distribution_platforms"); } catch (Exception $e) {}
        }
        return;
    }

    if ($totalOld === 0) {
        // Seed default unified platforms
        $defaultPlatforms = [
            ['Site Próprio', 'site', '🌐', 'internal', 1, 1],
            ['Steam', 'steam', '🔥', 'both', 1, 2],
            ['Epic Games', 'epic', '✨', 'both', 1, 3],
             ['GOG', 'gog', '🧩', 'both', 1, 4],
             ['itch.io', 'itchio', '🔴', 'public', 1, 5],
             ['gd.games', 'gdgames', '🎮', 'public', 1, 6],
             ['Google Play', 'googleplay', '📱', 'both', 1, 7],
             ['App Store', 'appstore', '📱', 'both', 1, 8],
             ['Nintendo eShop', 'nintendo', '🎹', 'public', 0, 9],
             ['PlayStation Store', 'playstation', '🎮', 'public', 0, 10],
             ['Xbox Store', 'xbox', '🎮', 'public', 0, 11],
             ['Amazon', 'amazon', '📦', 'public', 0, 12],
        ];
        $stmt = $db->prepare("INSERT INTO platforms (name, slug, icon, visibility, active, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($defaultPlatforms as $p) {
            $stmt->execute($p);
        }
    }

    // Build ID maps for FK remapping
    $idMap = ['store_platforms' => [], 'distribution_platforms' => []];
    $slugToNewId = [];

    // Canonical slug map: normalize distribution_platforms slugs to store_platforms slugs
    // e.g. "play_store" → "googleplay", "app_store" → "appstore"
    $slugAlias = [
        'play_store' => 'googleplay',
        'app_store' => 'appstore',
    ];

    // Collect all platforms from both tables, merged by slug
    $platformMap = []; // canonical_slug => [name, icon, visibility, active, sort_order, use_logo, logo_path, store_id, dist_id]

    // Query store_platforms
    if ($oldTables['store_platforms'] > 0) {
        $rows = $db->query("SELECT id, name, slug, icon, active, sort_order, use_logo, logo_path FROM store_platforms")->fetchAll();
        foreach ($rows as $r) {
            $slug = $r['slug'];
            $platformMap[$slug] = [
                'name' => $r['name'],
                'icon' => $r['icon'],
                'visibility' => 'public',
                'active' => (int)$r['active'],
                'sort_order' => (int)$r['sort_order'],
                'use_logo' => (int)($r['use_logo'] ?? 0),
                'logo_path' => $r['logo_path'] ?? '',
                'store_id' => $r['id'],
                'dist_id' => null,
            ];
        }
    }

    // Query distribution_platforms and merge
    if ($oldTables['distribution_platforms'] > 0) {
        $rows = $db->query("SELECT id, name, slug, icon, active, sort_order FROM distribution_platforms")->fetchAll();
        foreach ($rows as $r) {
            $slug = $slugAlias[$r['slug']] ?? $r['slug'];
            if (isset($platformMap[$slug])) {
                // Already exists from store_platforms → visibility = 'both'
                $platformMap[$slug]['visibility'] = 'both';
                $platformMap[$slug]['dist_id'] = $r['id'];
                // Prefer store icon/logo if set, otherwise use dist icon
                if (empty($platformMap[$slug]['icon']) && !empty($r['icon'])) {
                    $platformMap[$slug]['icon'] = $r['icon'];
                }
                if (empty($platformMap[$slug]['active'])) {
                    $platformMap[$slug]['active'] = (int)$r['active'];
                }
                if (empty($platformMap[$slug]['sort_order'])) {
                    $platformMap[$slug]['sort_order'] = (int)$r['sort_order'];
                }
            } else {
                // Only in distribution_platforms → visibility = 'internal'
                $platformMap[$slug] = [
                    'name' => $r['name'],
                    'icon' => $r['icon'],
                    'visibility' => 'internal',
                    'active' => (int)$r['active'],
                    'sort_order' => (int)$r['sort_order'],
                    'use_logo' => 0,
                    'logo_path' => '',
                    'store_id' => null,
                    'dist_id' => $r['id'],
                ];
            }
        }
    }

    // Insert unified platforms and build ID mapping
    $stmt = $db->prepare("INSERT INTO platforms (name, slug, icon, visibility, active, sort_order, use_logo, logo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($platformMap as $slug => $data) {
        try {
            $stmt->execute([
                $data['name'],
                $slug,
                $data['icon'],
                $data['visibility'],
                $data['active'],
                $data['sort_order'],
                $data['use_logo'],
                $data['logo_path'],
            ]);
            $newId = (int)$db->lastInsertId();
            $slugToNewId[$slug] = $newId;
            if ($data['store_id']) $idMap['store_platforms'][$data['store_id']] = $newId;
            if ($data['dist_id']) $idMap['distribution_platforms'][$data['dist_id']] = $newId;
        } catch (Exception $e) {
            // Duplicate slug — get existing
            $stmt = $db->prepare("SELECT id FROM platforms WHERE slug = ?");
            $stmt->execute([$slug]);
            $existingId = $stmt->fetchColumn();
            if ($existingId) {
                $slugToNewId[$slug] = (int)$existingId;
                if ($data['store_id']) $idMap['store_platforms'][$data['store_id']] = (int)$existingId;
                if ($data['dist_id']) $idMap['distribution_platforms'][$data['dist_id']] = (int)$existingId;
            }
        }
    }

    // Update FKs in referencing tables
    // game_links → platforms (from store_platforms)
    foreach ($idMap['store_platforms'] as $oldId => $newId) {
        $db->prepare("UPDATE game_links SET platform_id = ? WHERE platform_id = ?")->execute([$newId, $oldId]);
    }

    // distribution_game_links → platforms (from distribution_platforms)
    foreach ($idMap['distribution_platforms'] as $oldId => $newId) {
        $db->prepare("UPDATE distribution_game_links SET platform_id = ? WHERE platform_id = ?")->execute([$newId, $oldId]);
    }

    // distribution_integrations → platforms (from distribution_platforms)
    foreach ($idMap['distribution_platforms'] as $oldId => $newId) {
        $db->prepare("UPDATE distribution_integrations SET platform_id = ? WHERE platform_id = ?")->execute([$newId, $oldId]);
    }

    // campaigns → platforms (from distribution_platforms)
    foreach ($idMap['distribution_platforms'] as $oldId => $newId) {
        $db->prepare("UPDATE campaigns SET platform_id = ? WHERE platform_id = ?")->execute([$newId, $oldId]);
    }

    // game_distribution_stats → platforms (from distribution_platforms)
    foreach ($idMap['distribution_platforms'] as $oldId => $newId) {
        $db->prepare("UPDATE game_distribution_stats SET platform_id = ? WHERE platform_id = ?")->execute([$newId, $oldId]);
    }

    // Drop old tables (only if they had data)
    // NOTE: We keep the tables as empty shells to avoid breaking FK constraints
    // Migration 041 will fix the FKs by recreating tables that reference old platform tables
    if ($oldTables['store_platforms'] > 0 || $oldTables['distribution_platforms'] > 0) {
        try { $db->exec("DELETE FROM store_platforms"); } catch (Exception $e) {}
        try { $db->exec("DELETE FROM distribution_platforms"); } catch (Exception $e) {}
    }
}

// ── Migration 041: Fix FK constraints to reference unified platforms table ──
function migration_041($db, $type) {
    if ($type === 'sqlite') {
        $db->exec("PRAGMA foreign_keys = OFF");
    } else {
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    }

    // Tables that had FK to old platform tables, now need FK to platforms
    $tablesToFix = [
        'game_links' => [
            'create' => "CREATE TABLE game_links_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id INTEGER NOT NULL,
                platform_id INTEGER NOT NULL,
                url TEXT NOT NULL,
                sort_order INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE CASCADE
            )",
            'oldFkTable' => 'store_platforms',
        ],
        'distribution_game_links' => [
            'create' => "CREATE TABLE distribution_game_links_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id INTEGER,
                integration_id INTEGER,
                platform_id INTEGER NOT NULL,
                store_url TEXT,
                store_package_id TEXT,
                store_status VARCHAR(20) DEFAULT 'draft',
                version_name VARCHAR(100),
                last_sync_at DATETIME DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (integration_id) REFERENCES distribution_integrations(id) ON DELETE SET NULL,
                FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE CASCADE
            )",
            'oldFkTable' => 'distribution_platforms',
        ],
        'distribution_integrations' => [
            'create' => "CREATE TABLE distribution_integrations_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                platform_id INTEGER NOT NULL,
                name VARCHAR(150) NOT NULL,
                integration_type VARCHAR(50) NOT NULL DEFAULT 'manual',
                config_json TEXT,
                active INTEGER NOT NULL DEFAULT 1,
                last_sync_at DATETIME DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE CASCADE
            )",
            'oldFkTable' => 'distribution_platforms',
        ],
        'campaigns' => [
            'create' => "CREATE TABLE campaigns_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(150) NOT NULL,
                game_id INTEGER DEFAULT NULL,
                platform_id INTEGER DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                budget DECIMAL(12,2) NOT NULL DEFAULT 0,
                start_at DATETIME DEFAULT NULL,
                end_at DATETIME DEFAULT NULL,
                notes TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE SET NULL,
                FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE SET NULL
            )",
            'oldFkTable' => 'distribution_platforms',
        ],
        'game_distribution_stats' => [
            'create' => "CREATE TABLE game_distribution_stats_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id INTEGER NOT NULL,
                platform_id INTEGER NOT NULL,
                metric_key VARCHAR(50) NOT NULL,
                metric_value DECIMAL(18,2) NOT NULL DEFAULT 0,
                period_start DATE DEFAULT NULL,
                period_end DATE DEFAULT NULL,
                source VARCHAR(50) NOT NULL DEFAULT 'manual',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
                FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE CASCADE
            )",
            'oldFkTable' => 'distribution_platforms',
        ],
    ];

    foreach ($tablesToFix as $table => $spec) {
        try {
            // Check if table exists
            $exists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'")->fetchColumn();
            if (!$exists) continue;

            // Check if FK already points to correct table (idempotency)
            $fkInfo = $db->query("PRAGMA foreign_key_list('{$table}')")->fetchAll();
            $needsFix = false;
            foreach ($fkInfo as $fk) {
                if ($fk['table'] === $spec['oldFkTable']) {
                    $needsFix = true;
                    break;
                }
            }
            if (!$needsFix) continue;

            // Create new table
            $db->exec($spec['create']);

            // Copy data
            $db->exec("INSERT INTO {$table}_new SELECT * FROM {$table}");

            // Drop old table and rename
            $db->exec("DROP TABLE {$table}");
            $db->exec("ALTER TABLE {$table}_new RENAME TO {$table}");
        } catch (Exception $e) {
            // Non-critical, skip
        }
    }

    // Drop old empty platform tables
    try { $db->exec("DROP TABLE IF EXISTS store_platforms"); } catch (Exception $e) {}
    try { $db->exec("DROP TABLE IF EXISTS distribution_platforms"); } catch (Exception $e) {}

    // Re-enable FK for SQLite
    if ($type === 'sqlite') {
        $db->exec("PRAGMA foreign_keys = ON");
    } else {
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
}

function migration_042($db, $type) {
    // Seeding of demo campaigns/metrics moved to dbSeed to ensure games exist
}


