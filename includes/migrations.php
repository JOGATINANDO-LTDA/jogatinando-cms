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

    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER role_id");
        } else {
            $cols = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('status', $cols)) {
                $db->exec("ALTER TABLE users ADD COLUMN status TEXT NOT NULL DEFAULT 'active'");
            }
        }
    } catch (Exception $e) {}

    try {
        if ($type === 'mysql') {
            $db->exec("ALTER TABLE users ADD COLUMN setup_token VARCHAR(64) DEFAULT NULL AFTER status");
            $db->exec("ALTER TABLE users ADD COLUMN setup_token_expires DATETIME DEFAULT NULL AFTER setup_token");
        } else {
            $cols = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('setup_token', $cols)) {
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

function dbSeed($db, $type) {
    // Seed default roles
    $roleCount = $db->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    if ($roleCount == 0) {
        $stmt = $db->prepare("INSERT INTO roles (name, level, description) VALUES (?, ?, ?)");
        $stmt->execute(['CEO Administrador', 'ceo', 'Administrador master do sistema — controle total sobre todas as operações']);
        $stmt->execute(['CEO Sócio', 'ceo', 'Sócio com poderes administrativos — pode criar cargos de nível chief e moderator']);
        $stmt->execute(['CEO Investidor', 'ceo', 'Investidor com poderes administrativos — pode criar cargos de nível chief e moderator']);
        $stmt->execute(['CTO', 'chief', 'Chief Technology Officer — gerencia conteúdo, jogos e cargos moderator']);
        $stmt->execute(['CMO', 'chief', 'Chief Marketing Officer — gerencia conteúdo, blog e cargos moderator']);
        $stmt->execute(['Moderator', 'moderator', 'Gerenciamento operacional da plataforma — conteúdo e suporte']);
    }

    // Seed default admin user
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM users");
    $row = $stmt->fetch();
    if ($row['cnt'] == 0) {
        $ceoRoleId = $db->query("SELECT id FROM roles WHERE name = 'CEO Administrador'")->fetchColumn();
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, role, role_id) VALUES (?, ?, 'ceo', ?)");
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
        ['hero_title', 'Crie, Gerencie e Publique <span class="gold">Seus Jogos</span>'],
        ['hero_subtitle', 'Sistema completo para gerenciar seu portfólio de jogos digitais. Publique em qualquer engine e compartilhe com o mundo.'],
        ['contact_email', 'contato@exemplo.com.br'],
        ['contact_whatsapp', ''],
        ['youtube_url', ''],
        ['twitch_url', ''],
        ['blog_url', ''],
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
}

function seedDefaultData($db) {
    $count = $db->query("SELECT COUNT(*) FROM banners")->fetchColumn();
    if ($count > 0) return;

    $banners = [
        ['title' => 'Seu Portfólio de Jogos', 'subtitle' => 'Publique em qualquer engine', 'description' => 'Gerencie e publique seus jogos digitais em uma plataforma unificada. Compatível com GDevelop, Godot, Unity, RPG Maker e muito mais.', 'image_url' => '', 'cta_text' => 'Ver Portfólio', 'cta_url' => '#portfolio', 'engine_tag' => '', 'sort_order' => 1, 'active' => 1],
        ['title' => 'Multi-Engine', 'subtitle' => 'Qualquer engine, qualquer plataforma', 'description' => 'GDevelop, Godot, RPG Maker, Unity, Construct, Game Maker e outras. Sua escolha, seu jogo.', 'image_url' => '', 'cta_text' => 'Ver Engines', 'cta_url' => '#engines', 'engine_tag' => '', 'sort_order' => 2, 'active' => 1],
        ['title' => 'Demonstração', 'subtitle' => 'Explore os recursos do CMS', 'description' => 'Este é um ambiente de demonstração. Configure seus banners, adicione jogos e personalize seu site.', 'image_url' => '', 'cta_text' => 'Começar', 'cta_url' => '#start', 'engine_tag' => '', 'sort_order' => 3, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO banners (title, subtitle, description, image_url, cta_text, cta_url, engine_tag, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($banners as $b) {
        $stmt->execute([$b['title'], $b['subtitle'], $b['description'], $b['image_url'], $b['cta_text'], $b['cta_url'], $b['engine_tag'], $b['sort_order'], $b['active']]);
    }

    $games = [
        ['title' => 'Meu Primeiro Jogo', 'slug' => 'meu-primeiro-jogo', 'engine' => 'GDevelop', 'description' => 'Um jogo de exemplo criado com GDevelop para demonstrar os recursos do CMS.', 'thumbnail_url' => '', 'zip_filename' => '', 'game_path' => '', 'featured' => 1, 'orientation' => 'landscape', 'sort_order' => 1, 'active' => 1],
        ['title' => 'Aventura em Pixel', 'slug' => 'aventura-em-pixel', 'engine' => 'Godot', 'description' => 'Jogo de plataforma 2D com pixel art e física precisa.', 'thumbnail_url' => '', 'zip_filename' => '', 'game_path' => '', 'featured' => 1, 'orientation' => 'landscape', 'sort_order' => 2, 'active' => 1],
        ['title' => 'RPG das Dungeons', 'slug' => 'rpg-das-dungeons', 'engine' => 'RPG Maker', 'description' => 'RPG clássico com exploração, batalhas e uma história envolvente.', 'thumbnail_url' => '', 'zip_filename' => '', 'game_path' => '', 'featured' => 0, 'orientation' => 'landscape', 'sort_order' => 3, 'active' => 1],
        ['title' => 'Corrida Arcade', 'slug' => 'corrida-arcade', 'engine' => 'Unity', 'description' => 'Jogo de corrida arcade com gráficos 3D e trilha sonora eletrônica.', 'thumbnail_url' => '', 'zip_filename' => '', 'game_path' => '', 'featured' => 0, 'orientation' => 'landscape', 'sort_order' => 4, 'active' => 1],
        ['title' => 'Visual Novel Demo', 'slug' => 'visual-novel-demo', 'engine' => "Ren'py", 'description' => 'Uma visual novel interativa demonstrando recursos de narrativa e escolhas.', 'thumbnail_url' => '', 'zip_filename' => '', 'game_path' => '', 'featured' => 0, 'orientation' => 'portrait', 'sort_order' => 5, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO games (title, slug, engine, description, thumbnail_url, zip_filename, game_path, featured, orientation, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($games as $g) {
        $stmt->execute([$g['title'], $g['slug'], $g['engine'], $g['description'], $g['thumbnail_url'], $g['zip_filename'], $g['game_path'], $g['featured'], $g['orientation'], $g['sort_order'], $g['active']]);
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
        ['name' => 'Administrador', 'role' => 'Desenvolvedor', 'bio' => 'Responsável pelo gerenciamento do portfólio de jogos.', 'avatar_url' => '', 'social_youtube' => '', 'social_twitch' => '', 'social_linkedin' => '', 'sort_order' => 1, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO team_members (name, role, bio, avatar_url, social_youtube, social_twitch, social_linkedin, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($team as $t) {
        $stmt->execute([$t['name'], $t['role'], $t['bio'], $t['avatar_url'], $t['social_youtube'], $t['social_twitch'], $t['social_linkedin'], $t['sort_order'], $t['active']]);
    }
}
