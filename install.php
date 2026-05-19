<?php
/**
 * Jogatinando CMS — Install / Setup Wizard
 * Run once to initialize the database and seed default data.
 */
require_once 'config.php';

// If DB already exists, show info page
if (file_exists(DB_PATH)) {
    $db = getDB();
    if ($db) {
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $gameCount = $db->query("SELECT COUNT(*) FROM games")->fetchColumn();
        $bannerCount = $db->query("SELECT COUNT(*) FROM banners")->fetchColumn();
        $postCount = $db->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();
    }
}

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'init') {
        try {
            dbInit();
            seedDefaultData();
            $message = 'success';
        } catch (Exception $ex) {
            $message = 'error: ' . $ex->getMessage();
        }
    } elseif ($_POST['action'] === 'reset') {
        if (file_exists(DB_PATH)) {
            unlink(DB_PATH);
        }
        dbInit();
        seedDefaultData();
        $message = 'reset';
    }
}

function seedDefaultData() {
    $db = getDB();

    // Check if already seeded
    $count = $db->query("SELECT COUNT(*) FROM banners")->fetchColumn();
    if ($count > 0) return;

    // Seed banners
    $banners = [
        ['title' => 'Nossos Projetos', 'subtitle' => 'Jogos que criamos com paixão', 'description' => 'Cada projeto é uma aventura única. Conheça os jogos que desenvolvemos para nossos clientes.', 'image_url' => '', 'cta_text' => 'Ver Portfólio', 'cta_url' => '#portfolio', 'engine_tag' => '', 'sort_order' => 1, 'active' => 1],
        ['title' => 'Multi-Engine', 'subtitle' => 'Qualquer engine, qualquer plataforma', 'description' => 'GDevelop, Godot, RPG Maker, Unity, Unreal e mais. Escolha a engine, nós fazemos o resto.', 'image_url' => '', 'cta_text' => 'Ver Engines', 'cta_url' => '#engines', 'engine_tag' => '', 'sort_order' => 2, 'active' => 1],
        ['title' => 'Aprenda Conosco', 'subtitle' => 'YouTube, Twitch e Blog', 'description' => 'Ensinamos a criar jogos no nosso canal do YouTube, fazemos lives na Twitch e publicamos artigos no blog.', 'image_url' => '', 'cta_text' => 'Conteúdo Gratuito', 'cta_url' => '#media', 'engine_tag' => '', 'sort_order' => 3, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO banners (title, subtitle, description, image_url, cta_text, cta_url, engine_tag, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($banners as $b) {
        $stmt->execute([$b['title'], $b['subtitle'], $b['description'], $b['image_url'], $b['cta_text'], $b['cta_url'], $b['engine_tag'], $b['sort_order'], $b['active']]);
    }

    // Seed games
    $games = [
        ['title' => 'Acacia: O Prólogo do Desespero', 'slug' => 'acacia-o-prologo-do-desespero', 'engine' => 'Ren\'py', 'description' => 'Uma visual novel sombria sobre escolhas impossíveis e consequências inevitáveis.', 'thumbnail_url' => '', 'zip_filename' => '', 'game_path' => '', 'featured' => 1, 'orientation' => 'portrait', 'sort_order' => 1, 'active' => 1],
        ['title' => 'Aventura Medieval', 'slug' => 'aventura-medieval', 'engine' => 'RPG Maker', 'description' => 'RPG clássico com exploração, batalhas por turno e uma história épica.', 'thumbnail_url' => '', 'zip_filename' => '', 'game_path' => '', 'featured' => 1, 'orientation' => 'landscape', 'sort_order' => 2, 'active' => 1],
        ['title' => 'Space Defender', 'slug' => 'space-defender', 'engine' => 'GDevelop', 'description' => 'Shooter espacial com ondas de inimigos, power-ups e boss fights.', 'thumbnail_url' => '', 'zip_filename' => '', 'game_path' => '', 'featured' => 0, 'orientation' => 'landscape', 'sort_order' => 3, 'active' => 1],
        ['title' => 'Platform Quest', 'slug' => 'platform-quest', 'engine' => 'Godot', 'description' => 'Plataforma 2D com física precisa, level design desafiador e pixel art.', 'thumbnail_url' => '', 'zip_filename' => '', 'game_path' => '', 'featured' => 0, 'orientation' => 'landscape', 'sort_order' => 4, 'active' => 1],
        ['title' => 'Neon Drift', 'slug' => 'neon-drift', 'engine' => 'Unity', 'description' => 'Corrida arcade futurista com trilha sonora synthwave e pistas neon.', 'thumbnail_url' => '', 'zip_filename' => '', 'game_path' => '', 'featured' => 0, 'orientation' => 'landscape', 'sort_order' => 5, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO games (title, slug, engine, description, thumbnail_url, zip_filename, game_path, featured, orientation, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($games as $g) {
        $stmt->execute([$g['title'], $g['slug'], $g['engine'], $g['description'], $g['thumbnail_url'], $g['zip_filename'], $g['game_path'], $g['featured'], $g['orientation'], $g['sort_order'], $g['active']]);
    }

    // Seed testimonials
    $testimonials = [
        ['name' => 'Carlos Silva', 'role' => 'CEO, GameStudio BR', 'quote' => 'A equipe da Jogatinando entregou nosso projeto antes do prazo e com qualidade excepcional. Recomendo!', 'avatar_url' => '', 'sort_order' => 1, 'active' => 1],
        ['name' => 'Ana Oliveira', 'role' => 'Product Manager, PlayTech', 'quote' => 'Profissionais incríveis. Entenderam nossa visão e transformaram em um jogo que nossos jogadores amam.', 'avatar_url' => '', 'sort_order' => 2, 'active' => 1],
        ['name' => 'Rafael Santos', 'role' => 'Indie Developer', 'quote' => 'Comecei como aluno no YouTube e hoje sou cliente. A qualidade do trabalho é impressionante.', 'avatar_url' => '', 'sort_order' => 3, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO testimonials (name, role, quote, avatar_url, sort_order, active) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($testimonials as $t) {
        $stmt->execute([$t['name'], $t['role'], $t['quote'], $t['avatar_url'], $t['sort_order'], $t['active']]);
    }

    // Seed FAQ
    $faqs = [
        ['question' => 'Quais engines vocês trabalham?', 'answer' => 'Trabalhamos com GDevelop, Godot, RPG Maker, Unity, Unreal Engine, Construct, Defold, Game Maker, Ren\'py, Pixel Game Maker MV, RPG Paper Maker e outras. Se a engine existe, nós trabalhamos com ela.', 'sort_order' => 1, 'active' => 1],
        ['question' => 'Quanto custa desenvolver um jogo?', 'answer' => 'O valor depende da complexidade, plataforma, engine e escopo do projeto. Entre em contato pelo formulário de orçamento e enviaremos uma proposta personalizada em até 48 horas.', 'sort_order' => 2, 'active' => 1],
        ['question' => 'Vocês fazem jogos para mobile?', 'answer' => 'Sim! Desenvolvemos para Android, iOS, Web, PC (Windows/Mac/Linux) e consoles. Cada projeto é otimizado para a plataforma alvo.', 'sort_order' => 3, 'active' => 1],
        ['question' => 'Posso acompanhar o desenvolvimento?', 'answer' => 'Claro! Fornecemos acesso ao repositório, relatórios semanais de progresso e reuniões de alinhamento. Transparência é nosso compromisso.', 'sort_order' => 4, 'active' => 1],
        ['question' => 'Vocês também publicam jogos próprios?', 'answer' => 'Sim! Além de desenvolver para clientes, criamos nossos próprios jogos que estão disponíveis no mercado. Confira nosso portfólio para ver os projetos.', 'sort_order' => 5, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO faq_items (question, answer, sort_order, active) VALUES (?, ?, ?, ?)");
    foreach ($faqs as $f) {
        $stmt->execute([$f['question'], $f['answer'], $f['sort_order'], $f['active']]);
    }

    // Seed team
    $team = [
        ['name' => 'Victor', 'role' => 'Fundador & Lead Developer', 'bio' => 'Criador da Jogatinando, apaixonado por desenvolvimento de jogos e educação. Ensina criação de games no YouTube e faz lives na Twitch.', 'avatar_url' => '', 'social_youtube' => 'https://youtube.com/@jogatinandodevs', 'social_twitch' => 'https://www.twitch.tv/jogatinandolive', 'social_linkedin' => '', 'sort_order' => 1, 'active' => 1],
    ];

    $stmt = $db->prepare("INSERT INTO team_members (name, role, bio, avatar_url, social_youtube, social_twitch, social_linkedin, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($team as $t) {
        $stmt->execute([$t['name'], $t['role'], $t['bio'], $t['avatar_url'], $t['social_youtube'], $t['social_twitch'], $t['social_linkedin'], $t['sort_order'], $t['active']]);
    }
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
        .install-card { background: oklch(16% 0.035 265); border: 1px solid oklch(55% 0.12 85); border-radius: 12px; padding: 40px; max-width: 520px; width: 100%; }
        .install-card h1 { font-family: 'Cinzel', Georgia, serif; font-size: 24px; color: oklch(75% 0.15 85); margin-bottom: 8px; text-align: center; letter-spacing: 0.04em; }
        .install-card p { color: oklch(60% 0.012 250); margin-bottom: 24px; text-align: center; line-height: 1.6; }
        .install-card .status { padding: 16px; border-radius: 8px; margin-bottom: 24px; text-align: center; }
        .status.success { background: oklch(65% 0.18 145 / 0.15); border: 1px solid oklch(65% 0.18 145); color: oklch(65% 0.18 145); }
        .status.error { background: oklch(55% 0.20 25 / 0.15); border: 1px solid oklch(55% 0.20 25); color: oklch(55% 0.20 25); }
        .status.info { background: oklch(68% 0.16 220 / 0.15); border: 1px solid oklch(68% 0.16 220); color: oklch(68% 0.16 220); }
        .btn { display: block; width: 100%; padding: 14px; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; margin-bottom: 12px; }
        .btn-gold { background: linear-gradient(135deg, oklch(75% 0.15 85), oklch(62% 0.13 85)); color: oklch(8% 0.02 260); }
        .btn-gold:hover { background: linear-gradient(135deg, oklch(85% 0.13 85), oklch(75% 0.15 85)); }
        .btn-outline { background: transparent; border: 1px solid oklch(55% 0.12 85); color: oklch(75% 0.15 85); }
        .btn-outline:hover { background: oklch(75% 0.15 85 / 0.1); }
        .info-table { width: 100%; margin-bottom: 24px; }
        .info-table td { padding: 8px 0; border-bottom: 1px solid oklch(25% 0.03 260); }
        .info-table td:first-child { color: oklch(60% 0.012 250); width: 40%; }
        .info-table td:last-child { color: oklch(96% 0.003 250); font-weight: 500; }
        .warning { background: oklch(80% 0.16 90 / 0.1); border: 1px solid oklch(80% 0.16 90); color: oklch(80% 0.16 90); padding: 12px; border-radius: 8px; margin-bottom: 24px; font-size: 13px; text-align: center; }
    </style>
</head>
<body>
    <div class="install-card">
        <h1>⚙️ Jogatinando CMS</h1>

        <?php if ($message === 'success'): ?>
            <div class="status success">✅ Banco de dados inicializado com sucesso!</div>
            <p>Dados padrão inseridos: banners, jogos, depoimentos, FAQ e equipe.</p>
            <a href="admin/login.php" class="btn btn-gold">Acessar Painel Admin</a>
            <a href="admin/index.php" class="btn btn-outline">Ver Site</a>

        <?php elseif ($message === 'reset'): ?>
            <div class="status success">🔄 Banco de dados resetado e re-inicializado!</div>
            <p>Todos os dados foram substituídos pelos valores padrão.</p>
            <a href="admin/login.php" class="btn btn-gold">Acessar Painel Admin</a>

        <?php elseif (strpos($message, 'error') === 0): ?>
            <div class="status error">❌ <?= e(substr($message, 7)) ?></div>
            <form method="POST">
                <input type="hidden" name="action" value="init">
                <button type="submit" class="btn btn-gold">Tentar Novamente</button>
            </form>

        <?php elseif (file_exists(DB_PATH) && isset($tables)): ?>
            <div class="status info">ℹ️ CMS já está configurado</div>
            <table class="info-table">
                <tr><td>Banco de dados</td><td>✅ Criado</td></tr>
                <tr><td>Tables</td><td><?= count($tables) ?> tabelas</td></tr>
                <tr><td>Jogos</td><td><?= $gameCount ?></td></tr>
                <tr><td>Banners</td><td><?= $bannerCount ?></td></tr>
                <tr><td>Blog posts</td><td><?= $postCount ?></td></tr>
            </table>
            <div class="warning">⚠️ Reset irá apagar todos os dados e re-inicializar com valores padrão.</div>
            <a href="admin/login.php" class="btn btn-gold">Acessar Painel Admin</a>
            <a href="admin/index.php" class="btn btn-outline">Ver Site</a>
            <form method="POST" onsubmit="return confirm('Tem certeza? Todos os dados serão perdidos.')">
                <input type="hidden" name="action" value="reset">
                <button type="submit" class="btn btn-outline">Resetar e Re-inicializar</button>
            </form>

        <?php else: ?>
            <p>Instalação do CMS Jogatinando. Clique abaixo para criar o banco de dados e inserir dados padrão.</p>
            <form method="POST">
                <input type="hidden" name="action" value="init">
                <button type="submit" class="btn btn-gold">Instalar CMS</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
