<?php
require_once '../config.php';

if (isLoggedIn()) {
    header('Location: ' . ADMIN_URL . '/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (login($username, $password)) {
        header('Location: ' . ADMIN_URL . '/index.php');
        exit;
    } else {
        $error = 'Usuário ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Jogatinando CMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-deep: oklch(8% 0.02 260);
            --bg-card: oklch(14% 0.03 265);
            --fg: oklch(97% 0.002 250);
            --fg-secondary: oklch(82% 0.006 250);
            --muted: oklch(60% 0.012 250);
            --gold: oklch(75% 0.15 85);
            --gold-light: oklch(85% 0.13 85);
            --gold-dark: oklch(62% 0.13 85);
            --gold-glow: oklch(75% 0.15 85 / 0.4);
            --gold-subtle: oklch(75% 0.15 85 / 0.08);
            --border: oklch(22% 0.025 260);
            --border-gold: oklch(55% 0.12 85);
            --danger: oklch(55% 0.20 25);
            --radius-md: 8px;
            --radius-lg: 14px;
            --font-display: 'Cinzel', Georgia, serif;
            --font-body: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-body);
            background: var(--bg-deep);
            color: var(--fg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }

        /* Cosmic background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 20%, oklch(15% 0.06 280 / 0.6), transparent),
                radial-gradient(ellipse 60% 50% at 80% 30%, oklch(12% 0.05 220 / 0.5), transparent),
                radial-gradient(ellipse 50% 40% at 50% 80%, oklch(14% 0.06 300 / 0.4), transparent),
                var(--bg-deep);
            z-index: -1;
        }

        /* Stars */
        .stars {
            position: fixed;
            inset: 0;
            z-index: -1;
            overflow: hidden;
        }

        .star {
            position: absolute;
            width: 2px;
            height: 2px;
            background: var(--gold-light);
            border-radius: 50%;
            animation: twinkle var(--duration, 3s) ease-in-out infinite;
            animation-delay: var(--delay, 0s);
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.2; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.5); }
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            animation: fadeUp 0.5s ease;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 40px 32px;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
        }

        .login-crest {
            display: flex;
            justify-content: center;
            margin-bottom: 24px;
        }

        .login-crest svg {
            width: 72px;
            height: 72px;
            filter: drop-shadow(0 0 20px var(--gold-glow));
            animation: crestGlow 4s ease-in-out infinite;
        }

        @keyframes crestGlow {
            0%, 100% { filter: drop-shadow(0 0 15px var(--gold-glow)); }
            50% { filter: drop-shadow(0 0 30px oklch(75% 0.15 85 / 0.6)); }
        }

        .login-card h1 {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 800;
            color: var(--gold);
            text-align: center;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .login-card .subtitle {
            color: var(--muted);
            text-align: center;
            margin-bottom: 32px;
            font-size: 14px;
        }

        .form-group { margin-bottom: 20px; }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--fg-secondary);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            background: oklch(10% 0.025 260);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            color: var(--fg);
            font-size: 15px;
            outline: none;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-group input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px var(--gold-glow);
        }

        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            border: 2px solid var(--gold);
            border-radius: var(--radius-md);
            font-family: var(--font-display);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: all 0.25s ease;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--bg-deep);
            box-shadow: 0 4px 15px var(--gold-glow);
        }

        .btn:hover {
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            transform: translateY(-1px);
            box-shadow: 0 6px 24px oklch(75% 0.15 85 / 0.5);
        }

        .error-msg {
            background: oklch(55% 0.20 25 / 0.12);
            border: 1px solid oklch(55% 0.20 25 / 0.3);
            color: var(--danger);
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 24px;
            color: var(--muted);
            font-size: 13px;
        }

        .back-link a {
            color: var(--gold);
            transition: color 0.2s;
        }

        .back-link a:hover { color: var(--gold-light); }

        @media (max-width: 480px) {
            .login-card { padding: 32px 24px; }
            .login-crest svg { width: 56px; height: 56px; }
            .login-card h1 { font-size: 18px; }
        }
    </style>
</head>
<body>
    <div class="stars" id="stars"></div>

    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-crest">
                <svg viewBox="0 0 200 200" fill="none">
                    <path d="M100 10L180 40V100C180 150 140 185 100 195C60 185 20 150 20 100V40L100 10Z" fill="oklch(75% 0.15 85 / 0.08)" stroke="oklch(75% 0.15 85)" stroke-width="2.5"/>
                    <path d="M100 22L168 48V100C168 142 136 172 100 182C64 172 32 142 32 100V48L100 22Z" fill="oklch(75% 0.15 85 / 0.05)" stroke="oklch(75% 0.15 85 / 0.4)" stroke-width="1.5"/>
                    <path d="M100 34L156 56V100C156 134 132 158 100 168C68 158 44 134 44 100V56L100 34Z" fill="oklch(75% 0.15 85 / 0.03)"/>
                    <text x="100" y="98" text-anchor="middle" dominant-baseline="central" font-family="Cinzel, serif" font-size="36" font-weight="900" fill="oklch(75% 0.15 85)" style="text-shadow: 0 0 20px oklch(75% 0.15 85 / 0.5);">JTN</text>
                    <rect x="72" y="128" width="56" height="28" rx="14" fill="oklch(65% 0.18 220 / 0.2)" stroke="oklch(65% 0.18 220)" stroke-width="1.5"/>
                    <circle cx="88" cy="142" r="4" fill="oklch(65% 0.18 220)"/>
                    <circle cx="112" cy="142" r="4" fill="oklch(55% 0.20 25)"/>
                    <circle cx="100" cy="136" r="3" fill="oklch(65% 0.18 145)"/>
                    <circle cx="100" cy="148" r="3" fill="oklch(80% 0.16 90)"/>
                </svg>
            </div>
            <h1>Jogatinando</h1>
            <p class="subtitle">Painel Administrativo</p>

            <?php if ($error): ?>
                <div class="error-msg"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="on">
                <div class="form-group">
                    <label for="username">Usuário</label>
                    <input type="text" id="username" name="username" required autofocus autocomplete="username" placeholder="seu-usuario">
                </div>
                <div class="form-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                </div>
                <button type="submit" class="btn">Entrar</button>
            </form>
        </div>

        <p class="back-link"><a href="../">← Voltar ao site</a></p>
    </div>

    <script>
        // Generate stars
        (function() {
            var container = document.getElementById('stars');
            if (!container) return;
            for (var i = 0; i < 60; i++) {
                var star = document.createElement('div');
                star.className = 'star';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.setProperty('--duration', (2 + Math.random() * 4) + 's');
                star.style.setProperty('--delay', (Math.random() * 3) + 's');
                star.style.opacity = 0.2 + Math.random() * 0.3;
                container.appendChild(star);
            }
        })();
    </script>
</body>
</html>
