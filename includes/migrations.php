<?php

function migration_001($db, $type) {
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

    // Seed default admin user
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
        ['title' => 'Nossos Projetos', 'subtitle' => 'Jogos que criamos com paixão', 'description' => 'Cada projeto é uma aventura única. Conheça os jogos que desenvolvemos para nossos clientes.', 'image_url' => '', 'cta_text' => 'Ver Portfólio', 'cta_url' => '#portfolio', 'engine_tag' => '', 'sort_order' => 1, 'active' => 1],
        ['title' => 'Multi-Engine', 'subtitle' => 'Qualquer engine, qualquer plataforma', 'description' => 'GDevelop, Godot, RPG Maker, Unity, Unreal e mais. Escolha a engine, nós fazemos o resto.', 'image_url' => '', 'cta_text' => 'Ver Engines', 'cta_url' => '#engines', 'engine_tag' => '', 'sort_order' => 2, 'active' => 1],
        ['title' => 'Aprenda Conosco', 'subtitle' => 'YouTube, Twitch e Blog', 'description' => 'Ensinamos a criar jogos no nosso canal do YouTube, fazemos lives na Twitch e publicamos artigos no blog.', 'image_url' => '', 'cta_text' => 'Conteúdo Gratuito', 'cta_url' => '#media', 'engine_tag' => '', 'sort_order' => 3, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO banners (title, subtitle, description, image_url, cta_text, cta_url, engine_tag, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($banners as $b) {
        $stmt->execute([$b['title'], $b['subtitle'], $b['description'], $b['image_url'], $b['cta_text'], $b['cta_url'], $b['engine_tag'], $b['sort_order'], $b['active']]);
    }

    $games = [
        ['title' => 'Acacia: O Prólogo do Desespero', 'slug' => 'acacia-o-prologo-do-desespero', 'engine' => "Ren'py", 'description' => 'Uma visual novel sombria sobre escolhas impossíveis e consequências inevitáveis.', 'thumbnail_url' => '', 'zip_filename' => '', 'game_path' => '', 'featured' => 1, 'orientation' => 'portrait', 'sort_order' => 1, 'active' => 1],
        ['title' => 'Aventura Medieval', 'slug' => 'aventura-medieval', 'engine' => 'RPG Maker', 'description' => 'RPG clássico com exploração, batalhas por turno e uma história épica.', 'thumbnail_url' => '', 'zip_filename' => '', 'game_path' => '', 'featured' => 1, 'orientation' => 'landscape', 'sort_order' => 2, 'active' => 1],
        ['title' => 'Space Defender', 'slug' => 'space-defender', 'engine' => 'GDevelop', 'description' => 'Shooter espacial com ondas de inimigos, power-ups e boss fights.', 'thumbnail_url' => '', 'zip_filename' => '', 'game_path' => '', 'featured' => 0, 'orientation' => 'landscape', 'sort_order' => 3, 'active' => 1],
        ['title' => 'Platform Quest', 'slug' => 'platform-quest', 'engine' => 'Godot', 'description' => 'Plataforma 2D com física precisa, level design desafiador e pixel art.', 'thumbnail_url' => '', 'zip_filename' => '', 'game_path' => '', 'featured' => 0, 'orientation' => 'landscape', 'sort_order' => 4, 'active' => 1],
        ['title' => 'Neon Drift', 'slug' => 'neon-drift', 'engine' => 'Unity', 'description' => 'Corrida arcade futurista com trilha sonora synthwave e pistas neon.', 'thumbnail_url' => '', 'zip_filename' => '', 'game_path' => '', 'featured' => 0, 'orientation' => 'landscape', 'sort_order' => 5, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO games (title, slug, engine, description, thumbnail_url, zip_filename, game_path, featured, orientation, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($games as $g) {
        $stmt->execute([$g['title'], $g['slug'], $g['engine'], $g['description'], $g['thumbnail_url'], $g['zip_filename'], $g['game_path'], $g['featured'], $g['orientation'], $g['sort_order'], $g['active']]);
    }

    $testimonials = [
        ['name' => 'Carlos Silva', 'role' => 'CEO, GameStudio BR', 'quote' => 'A equipe da Jogatinando entregou nosso projeto antes do prazo e com qualidade excepcional. Recomendo!', 'avatar_url' => '', 'sort_order' => 1, 'active' => 1],
        ['name' => 'Ana Oliveira', 'role' => 'Product Manager, PlayTech', 'quote' => 'Profissionais incríveis. Entenderam nossa visão e transformaram em um jogo que nossos jogadores amam.', 'avatar_url' => '', 'sort_order' => 2, 'active' => 1],
        ['name' => 'Rafael Santos', 'role' => 'Indie Developer', 'quote' => 'Comecei como aluno no YouTube e hoje sou cliente. A qualidade do trabalho é impressionante.', 'avatar_url' => '', 'sort_order' => 3, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO testimonials (name, role, quote, avatar_url, sort_order, active) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($testimonials as $t) {
        $stmt->execute([$t['name'], $t['role'], $t['quote'], $t['avatar_url'], $t['sort_order'], $t['active']]);
    }

    $faqs = [
        ['question' => 'Quais engines vocês trabalham?', 'answer' => 'Trabalhamos com GDevelop, Godot, RPG Maker, Unity, Unreal Engine, Construct, Defold, Game Maker, Ren\'py, Pixel Game Maker MV, RPG Paper Maker e outras. Se a engine existe, nós trabalhamos com ela.', 'sort_order' => 1, 'active' => 1],
        ['question' => 'Quanto custa desenvolver um jogo?', 'answer' => 'O valor depende da complexidade, plataforma, engine e escopo do projeto. Entre em contato pelo formulário de orçamento e enviaremos uma proposta personalizada em até 48 horas.', 'sort_order' => 2, 'active' => 1],
        ['question' => 'Vocês fazem jogos para mobile?', 'answer' => 'Sim! Desenvolvemos para Android, iOS, Web, PC (Windows/Mac/Linux) e consoles. Cada projeto é otimizado para a plataforma alvo.', 'sort_order' => 3, 'active' => 1],
        ['question' => 'Posso acompanhar o desenvolvimento?', 'answer' => 'Claro! Fornecemos acesso ao repositório, relatórios semanais de progresso e relatórios de alinhamento. Transparência é nosso compromisso.', 'sort_order' => 4, 'active' => 1],
        ['question' => 'Vocês também publicam jogos próprios?', 'answer' => 'Sim! Além de desenvolver para clientes, criamos nossos próprios jogos que estão disponíveis no mercado. Confira nosso portfólio para ver os projetos.', 'sort_order' => 5, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO faq_items (question, answer, sort_order, active) VALUES (?, ?, ?, ?)");
    foreach ($faqs as $f) {
        $stmt->execute([$f['question'], $f['answer'], $f['sort_order'], $f['active']]);
    }

    $team = [
        ['name' => 'Sulivan', 'role' => 'Fundador & Lead Developer', 'bio' => 'Criador da Jogatinando, apaixonado por desenvolvimento de jogos e educação. Ensina criação de games no YouTube e faz lives na Twitch.', 'avatar_url' => '', 'social_youtube' => 'https://youtube.com/@jogatinandodevs', 'social_twitch' => 'https://www.twitch.tv/jogatinandolive', 'social_linkedin' => '', 'sort_order' => 1, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO team_members (name, role, bio, avatar_url, social_youtube, social_twitch, social_linkedin, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($team as $t) {
        $stmt->execute([$t['name'], $t['role'], $t['bio'], $t['avatar_url'], $t['social_youtube'], $t['social_twitch'], $t['social_linkedin'], $t['sort_order'], $t['active']]);
    }
}
