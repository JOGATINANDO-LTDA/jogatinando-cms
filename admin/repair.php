<?php
ob_start();
$pageTitle = 'Diagnóstico e Reparo';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$dbType = getDbType();
$userId = (int)($_SESSION['admin_user_id'] ?? 0);

if ($userId !== 1) {
    ob_end_clean();
    header('Location: ' . ADMIN_URL . '/dashboard');
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'index';
$message = null;

// ── Expected schema (final state after all 26 migrations) ──
function getExpectedSchema() {
    return [
        'schema_version' => [
            'columns' => [
                'version' => 'INT',
                'name' => 'VARCHAR(255) NOT NULL',
                'applied_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
        ],
        'users' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'username' => 'VARCHAR(255) NOT NULL',
                'email' => 'VARCHAR(255) DEFAULT \'\'',
                'password_hash' => 'VARCHAR(255) NULL',
                'avatar_url' => 'VARCHAR(255) DEFAULT \'\'',
                'status' => 'VARCHAR(20) NOT NULL DEFAULT \'active\'',
                'setup_token' => 'VARCHAR(64) DEFAULT NULL',
                'setup_token_expires' => 'DATETIME DEFAULT NULL',
                'email_verified_at' => 'DATETIME NULL',
                'email_verification_token' => 'VARCHAR(64) NULL',
                'role_id' => 'INT DEFAULT NULL',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
            'dropped' => ['role'],
        ],
        'banners' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'title' => 'VARCHAR(255) NOT NULL',
                'subtitle' => 'VARCHAR(255) DEFAULT \'\'',
                'description' => 'TEXT',
                'image_url' => 'VARCHAR(255) DEFAULT \'\'',
                'cta_text' => 'VARCHAR(255) DEFAULT \'Saiba Mais\'',
                'cta_url' => 'VARCHAR(255) DEFAULT \'#\'',
                'sort_order' => 'INT DEFAULT 0',
                'active' => 'INT DEFAULT 1',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
            'dropped' => ['engine_tag'],
        ],
        'games' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'title' => 'VARCHAR(255) NOT NULL',
                'slug' => 'VARCHAR(255) DEFAULT \'\'',
                'engine' => 'VARCHAR(255) NOT NULL',
                'description' => 'TEXT',
                'thumbnail_url' => 'VARCHAR(255) DEFAULT \'\'',
                'game_path' => 'VARCHAR(255) DEFAULT \'\'',
                'external_url' => 'VARCHAR(500) DEFAULT \'\'',
                'repo_url' => 'VARCHAR(500) DEFAULT \'\'',
                'is_open_source' => 'INT NOT NULL DEFAULT 0',
                'featured' => 'INT DEFAULT 0',
                'orientation' => 'VARCHAR(50) DEFAULT \'auto\'',
                'game_type' => 'VARCHAR(50) NOT NULL DEFAULT \'autoral\'',
                'is_web_playable' => 'INT NOT NULL DEFAULT 1',
                'optimized_at' => 'DATETIME DEFAULT NULL',
                'sort_order' => 'INT DEFAULT 0',
                'active' => 'INT DEFAULT 1',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
            'dropped' => ['zip_filename'],
        ],
        'blog_posts' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'title' => 'VARCHAR(255) NOT NULL',
                'slug' => 'VARCHAR(255) NOT NULL',
                'content' => 'LONGTEXT',
                'thumbnail_url' => 'VARCHAR(255) DEFAULT \'\'',
                'external_url' => 'VARCHAR(255) DEFAULT \'\'',
                'published_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
                'active' => 'INT DEFAULT 1',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
        ],
        'testimonials' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'name' => 'VARCHAR(255) NOT NULL',
                'role' => 'VARCHAR(255) DEFAULT \'\'',
                'quote' => 'TEXT NOT NULL',
                'avatar_url' => 'VARCHAR(255) DEFAULT \'\'',
                'active' => 'INT DEFAULT 1',
                'sort_order' => 'INT DEFAULT 0',
            ],
        ],
        'faq_items' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'question' => 'TEXT NOT NULL',
                'answer' => 'TEXT NOT NULL',
                'sort_order' => 'INT DEFAULT 0',
                'active' => 'INT DEFAULT 1',
            ],
        ],
        'team_members' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'name' => 'VARCHAR(255) NOT NULL',
                'role' => 'VARCHAR(255) NOT NULL',
                'bio' => 'TEXT',
                'avatar_url' => 'VARCHAR(255) DEFAULT \'\'',
                'social_youtube' => 'VARCHAR(255) DEFAULT \'\'',
                'social_twitch' => 'VARCHAR(255) DEFAULT \'\'',
                'social_linkedin' => 'VARCHAR(255) DEFAULT \'\'',
                'sort_order' => 'INT DEFAULT 0',
                'active' => 'INT DEFAULT 1',
                'user_id' => 'INT DEFAULT NULL',
            ],
        ],
        'site_settings' => [
            'columns' => [
                'key' => 'VARCHAR(255)',
                'value' => 'LONGTEXT',
            ],
        ],
        'social_links' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'scope' => "VARCHAR(20) NOT NULL DEFAULT 'site'",
                'platform_key' => 'VARCHAR(50) NOT NULL',
                'label' => "VARCHAR(100) NOT NULL DEFAULT ''",
                'url' => "VARCHAR(500) NOT NULL DEFAULT ''",
                'icon' => "VARCHAR(80) NOT NULL DEFAULT ''",
                'active' => 'TINYINT(1) NOT NULL DEFAULT 1',
                'sort_order' => 'INT NOT NULL DEFAULT 0',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
        ],
        'ad_slots' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'slot_key' => 'VARCHAR(50) NOT NULL',
                'name' => 'VARCHAR(100) NOT NULL',
                'provider' => "VARCHAR(30) NOT NULL DEFAULT 'custom_html'",
                'code_html' => 'LONGTEXT',
                'active' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'pages' => "VARCHAR(255) NOT NULL DEFAULT ''",
                'devices' => "VARCHAR(100) NOT NULL DEFAULT 'all'",
                'sticky' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'height_desktop' => "VARCHAR(20) NOT NULL DEFAULT ''",
                'height_mobile' => "VARCHAR(20) NOT NULL DEFAULT ''",
                'fallback_text' => "VARCHAR(255) NOT NULL DEFAULT ''",
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
        ],
        'distribution_platforms' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'name' => 'VARCHAR(100) NOT NULL',
                'slug' => 'VARCHAR(100) NOT NULL',
                'icon' => "VARCHAR(50) NOT NULL DEFAULT ''",
                'active' => 'TINYINT(1) NOT NULL DEFAULT 1',
                'sort_order' => 'INT NOT NULL DEFAULT 0',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
        ],
        'game_distribution_stats' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'game_id' => 'INT NOT NULL',
                'platform_id' => 'INT NOT NULL',
                'metric_key' => 'VARCHAR(50) NOT NULL',
                'metric_value' => 'DECIMAL(18,2) NOT NULL DEFAULT 0',
                'period_start' => 'DATE DEFAULT NULL',
                'period_end' => 'DATE DEFAULT NULL',
                'source' => "VARCHAR(50) NOT NULL DEFAULT 'manual'",
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
        ],
        'campaigns' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'name' => 'VARCHAR(150) NOT NULL',
                'game_id' => 'INT DEFAULT NULL',
                'platform_id' => 'INT DEFAULT NULL',
                'status' => "VARCHAR(20) NOT NULL DEFAULT 'draft'",
                'budget' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'start_at' => 'DATETIME DEFAULT NULL',
                'end_at' => 'DATETIME DEFAULT NULL',
                'notes' => 'TEXT',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
        ],
        'campaign_metrics' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'campaign_id' => 'INT NOT NULL',
                'metric_key' => 'VARCHAR(50) NOT NULL',
                'metric_value' => 'DECIMAL(18,2) NOT NULL DEFAULT 0',
                'period_start' => 'DATE DEFAULT NULL',
                'period_end' => 'DATE DEFAULT NULL',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
        ],
        'roles' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'name' => 'VARCHAR(100) NOT NULL',
                'level_id' => 'INT DEFAULT NULL',
                'description' => 'TEXT',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
            'dropped' => ['level'],
        ],
        'engines' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'name' => 'VARCHAR(255) NOT NULL',
                'slug' => 'VARCHAR(255) NOT NULL',
                'icon' => 'VARCHAR(50) NOT NULL DEFAULT \'🎮\'',
                'color' => 'VARCHAR(50) NOT NULL DEFAULT \'oklch(68% 0.16 220)\'',
                'active' => 'INT DEFAULT 0',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
        ],
        'store_platforms' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'name' => 'VARCHAR(255) NOT NULL',
                'slug' => 'VARCHAR(255) NOT NULL',
                'icon' => 'VARCHAR(50) NOT NULL DEFAULT \'🛒\'',
                'use_logo' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'logo_path' => 'VARCHAR(500) NOT NULL DEFAULT \'\'',
                'active' => 'INT DEFAULT 1',
                'sort_order' => 'INT DEFAULT 0',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
        ],
        'game_links' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'game_id' => 'INT NOT NULL',
                'platform_id' => 'INT NOT NULL',
                'url' => 'VARCHAR(500) NOT NULL',
                'sort_order' => 'INT DEFAULT 0',
            ],
        ],
        'game_templates' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'title' => 'VARCHAR(255) NOT NULL',
                'slug' => 'VARCHAR(255) DEFAULT \'\'',
                'engine' => 'VARCHAR(255) NOT NULL',
                'description' => 'TEXT',
                'language' => 'VARCHAR(100) DEFAULT \'\'',
                'language_version' => 'VARCHAR(50) DEFAULT \'\'',
                'game_path' => 'VARCHAR(255) DEFAULT \'\'',
                'thumbnail_url' => 'VARCHAR(255) DEFAULT \'\'',
                'gallery' => 'TEXT',
                'features' => 'TEXT',
                'requirements' => 'TEXT',
                'has_free_file' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'featured' => 'INT DEFAULT 0',
                'active' => 'INT DEFAULT 1',
                'sort_order' => 'INT DEFAULT 0',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
            'dropped' => ['store_url'],
        ],
        'retro_games' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'title' => 'VARCHAR(255) NOT NULL',
                'slug' => 'VARCHAR(255) DEFAULT \'\'',
                'console' => 'VARCHAR(100) NOT NULL',
                'type' => 'VARCHAR(20) NOT NULL DEFAULT \'original\'',
                'modification_description' => 'VARCHAR(60) DEFAULT \'\'',
                'rom_path' => 'VARCHAR(500) DEFAULT \'\'',
                'description' => 'TEXT',
                'thumbnail_url' => 'VARCHAR(255) DEFAULT \'\'',
                'emulator_core' => 'VARCHAR(100) DEFAULT \'\'',
                'active' => 'INT DEFAULT 1',
                'featured' => 'INT DEFAULT 0',
                'sort_order' => 'INT DEFAULT 0',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
            'dropped' => ['patch_url'],
        ],
        'retro_consoles' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'name' => 'VARCHAR(255) NOT NULL',
                'slug' => 'VARCHAR(255) NOT NULL',
                'icon' => 'VARCHAR(50) NOT NULL DEFAULT \'🎮\'',
                'thumbnail_url' => 'VARCHAR(255) DEFAULT \'\'',
                'emulator_core' => 'VARCHAR(100) NOT NULL DEFAULT \'\'',
                'active' => 'INT DEFAULT 1',
                'sort_order' => 'INT DEFAULT 0',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
        ],
        'levels' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'name' => 'VARCHAR(100) NOT NULL',
                'slug' => 'VARCHAR(50) NOT NULL',
                'is_protected' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'perm_banners' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'perm_games' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'perm_blog' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'perm_testimonials' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'perm_faq' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'perm_team' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'perm_users' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'perm_roles' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'perm_engines' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'perm_platforms' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'perm_consoles' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'perm_retro_games' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'perm_templates' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'perm_optimizer' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'perm_settings' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            ],
        ],
        'template_links' => [
            'columns' => [
                'id' => 'INT AUTO_INCREMENT',
                'template_id' => 'INT NOT NULL',
                'platform_id' => 'INT NOT NULL',
                'url' => 'VARCHAR(500) NOT NULL',
                'sort_order' => 'INT DEFAULT 0',
            ],
        ],
    ];
}

function getCurrentColumns($db, $dbType, $table) {
    try {
        if ($dbType === 'mysql') {
            $stmt = $db->prepare("SHOW COLUMNS FROM `$table`");
            $stmt->execute();
            $cols = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cols[$row['Field']] = $row;
            }
            return $cols;
        }
        $stmt = $db->prepare("PRAGMA table_info(`$table`)");
        $stmt->execute();
        $cols = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols[$row['name']] = $row;
        }
        return $cols;
    } catch (Exception $e) {
        return null;
    }
}

function buildDiagnostic($db, $dbType) {
    $expected = getExpectedSchema();
    $currentTables = getDbTables($db, $dbType);
    $diagnostic = [];

    foreach ($expected as $table => $def) {
        $entry = [
            'table' => $table,
            'exists' => in_array($table, $currentTables),
            'missing_cols' => [],
            'extra_cols' => [],
            'dropped_cols_present' => [],
            'col_count_mismatch' => false,
        ];

        if ($entry['exists']) {
            $currentCols = getCurrentColumns($db, $dbType, $table);
            if ($currentCols === null) {
                $entry['error'] = 'Não foi possível ler colunas';
                $diagnostic[$table] = $entry;
                continue;
            }

            $expectedColNames = array_keys($def['columns']);

            // Check missing expected columns
            foreach ($expectedColNames as $colName) {
                if (!isset($currentCols[$colName])) {
                    $entry['missing_cols'][] = $colName;
                }
            }

            // Check for dropped columns that still exist
            $dropped = $def['dropped'] ?? [];
            foreach ($dropped as $colName) {
                if (isset($currentCols[$colName])) {
                    $entry['dropped_cols_present'][] = $colName;
                }
            }

            // Check for unexpected extra columns (not in expected, not in dropped)
            foreach ($currentCols as $colName => $colInfo) {
                if (!in_array($colName, $expectedColNames) && !in_array($colName, $dropped)) {
                    $entry['extra_cols'][] = $colName;
                }
            }

            $entry['col_count_mismatch'] = count($currentCols) !== count($expectedColNames);
        }

        $diagnostic[$table] = $entry;
    }

    return $diagnostic;
}

// ── Schema_version check ──
function getSchemaVersionDiagnostic($db) {
    $expectedList = getMigrationList();
    $installed = [];
    $stmt = $db->query("SELECT version, name, applied_at FROM schema_version ORDER BY version");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $installed[(int)$row['version']] = $row;
    }

    $missing = [];
    $extra = [];
    $mismatch = [];

    foreach ($expectedList as $ver => $name) {
        if (!isset($installed[$ver])) {
            $missing[] = ['version' => $ver, 'name' => $name];
        } elseif ($installed[$ver]['name'] !== $name) {
            $mismatch[] = ['version' => $ver, 'expected' => $name, 'found' => $installed[$ver]['name']];
        }
    }

    foreach ($installed as $ver => $row) {
        if (!isset($expectedList[$ver])) {
            $extra[] = $row;
        }
    }

    return [
        'installed' => $installed,
        'missing' => $missing,
        'extra' => $extra,
        'mismatch' => $mismatch,
        'all_ok' => empty($missing) && empty($extra) && empty($mismatch),
    ];
}

// ── Handle POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Token de segurança inválido.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/repair');
        exit;
    }

    if ($action === 'repair') {
        $expected = getExpectedSchema();
        $currentTables = getDbTables($db, $dbType);
        $adds = 0;
        $drops = 0;
        $errors = [];

        foreach ($expected as $table => $def) {
            if (!in_array($table, $currentTables)) {
                $errors[] = "Tabela '$table' não existe (crie manualmente ou use Reset)";
                continue;
            }

            $currentCols = getCurrentColumns($db, $dbType, $table);
            if ($currentCols === null) {
                $errors[] = "Não foi possível ler colunas de '$table'";
                continue;
            }

            $colNames = array_keys($currentCols);
            $errors[] = "$table: colunas atuais = [" . implode(', ', $colNames) . "]";

            // Add missing columns
            foreach ($def['columns'] as $colName => $colType) {
                if (!isset($currentCols[$colName])) {
                    try {
                        $db->exec("ALTER TABLE `$table` ADD COLUMN `$colName` $colType");
                        $adds++;
                        $errors[] = "  + Adicionada $table.$colName";
                    } catch (Exception $e) {
                        $errors[] = "  ! ERRO ao adicionar $table.$colName: " . $e->getMessage();
                    }
                }
            }

            // Drop columns that were explicitly dropped in migrations
            $dropped = $def['dropped'] ?? [];
            foreach ($dropped as $colName) {
                if (isset($currentCols[$colName])) {
                    try {
                        $db->exec("ALTER TABLE `$table` DROP COLUMN `$colName`");
                        $drops++;
                        $errors[] = "  - Removida $table.$colName";
                    } catch (Exception $e) {
                        $errors[] = "  ! ERRO ao remover $table.$colName: " . $e->getMessage();
                    }
                } else {
                    $errors[] = "  . $table.$colName já não existe (nada a fazer)";
                }
            }
        }

        // Fix schema_version — add missing entries
        $svDiag = getSchemaVersionDiagnostic($db);
        $fixedSv = 0;
        foreach ($svDiag['missing'] as $m) {
            try {
                $stmt = $db->prepare("INSERT INTO schema_version (version, name) VALUES (?, ?)");
                $stmt->execute([$m['version'], $m['name']]);
                $fixedSv++;
                $errors[] = "  + schema_version: adicionada v{$m['version']} ({$m['name']})";
            } catch (Exception $e) {
                $errors[] = "  ! ERRO schema_version v{$m['version']}: " . $e->getMessage();
            }
        }
        foreach ($svDiag['mismatch'] as $m) {
            try {
                $stmt = $db->prepare("UPDATE schema_version SET name = ? WHERE version = ?");
                $stmt->execute([$m['expected'], $m['version']]);
                $fixedSv++;
                $errors[] = "  ~ schema_version v{$m['version']}: '{$m['found']}' → '{$m['expected']}'";
            } catch (Exception $e) {
                $errors[] = "  ! ERRO schema_version v{$m['version']}: " . $e->getMessage();
            }
        }

        $parts = [];
        if ($adds > 0) $parts[] = "$adds coluna(s) adicionada(s)";
        if ($drops > 0) $parts[] = "$drops coluna(s) obsoleta(s) removida(s)";
        if ($fixedSv > 0) $parts[] = "$fixedSv entrada(s) do schema_version corrigida(s)";
        if (empty($parts)) $parts[] = 'Nenhuma correção necessária';

        $repairResult = 'Reparo concluído: ' . implode(', ', $parts) . ".\n\n" . implode("\n", $errors);
        // Don't redirect — show result inline on this page
    }

    if ($action === 'reset') {
        $password = $_POST['master_password'] ?? '';
        if ($password === '') {
            flashMessage('error', 'Senha master é obrigatória.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/repair?action=reset');
            exit;
        }

        // Verify master password
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = 1");
        $stmt->execute();
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($password, $hash)) {
            flashMessage('error', 'Senha master incorreta.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/repair?action=reset');
            exit;
        }

        // Confirm via checkbox
        if (empty($_POST['confirm_reset'])) {
            flashMessage('error', 'Confirmação de reset é obrigatória.');
            ob_end_clean();
            header('Location: ' . ADMIN_URL . '/repair?action=reset');
            exit;
        }

        // Drop all tables
        $tables = getDbTables($db, $dbType);
        if ($dbType === 'mysql') {
            $db->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($tables as $t) {
                $db->exec("DROP TABLE IF EXISTS `$t`");
            }
            $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        } else {
            foreach ($tables as $t) {
                $db->exec("DROP TABLE IF EXISTS `$t`");
            }
        }

        // Re-init
        dbInit(null, null, null, $dbType);

        flashMessage('success', 'Reset completo — banco de dados recriado do zero.');
        ob_end_clean();
        header('Location: ' . ADMIN_URL . '/dashboard');
        exit;
    }
}

// ── Integrity checks (orphaned foreign keys) ──
function getIntegrityChecks($db, $dbType) {
    $checks = [];

    // template_links → game_templates (template_id)
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM template_links tl LEFT JOIN game_templates gt ON tl.template_id = gt.id WHERE gt.id IS NULL");
        $orphaned = (int)$stmt->fetchColumn();
        $checks[] = ['label' => 'template_links → game_templates', 'orphaned' => $orphaned, 'ok' => $orphaned === 0];
    } catch (Exception $e) {
        $checks[] = ['label' => 'template_links → game_templates', 'orphaned' => 0, 'ok' => true, 'skip' => true];
    }

    // template_links → store_platforms (platform_id)
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM template_links tl LEFT JOIN store_platforms p ON tl.platform_id = p.id WHERE p.id IS NULL");
        $orphaned = (int)$stmt->fetchColumn();
        $checks[] = ['label' => 'template_links → store_platforms', 'orphaned' => $orphaned, 'ok' => $orphaned === 0];
    } catch (Exception $e) {
        $checks[] = ['label' => 'template_links → store_platforms', 'orphaned' => 0, 'ok' => true, 'skip' => true];
    }

    // game_links → store_platforms (platform_id)
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM game_links gl LEFT JOIN store_platforms p ON gl.platform_id = p.id WHERE p.id IS NULL");
        $orphaned = (int)$stmt->fetchColumn();
        $checks[] = ['label' => 'game_links → store_platforms', 'orphaned' => $orphaned, 'ok' => $orphaned === 0];
    } catch (Exception $e) {
        $checks[] = ['label' => 'game_links → store_platforms', 'orphaned' => 0, 'ok' => true, 'skip' => true];
    }

    // users → roles (role_id)
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.role_id IS NOT NULL AND r.id IS NULL");
        $orphaned = (int)$stmt->fetchColumn();
        $checks[] = ['label' => 'users → roles', 'orphaned' => $orphaned, 'ok' => $orphaned === 0];
    } catch (Exception $e) {
        $checks[] = ['label' => 'users → roles', 'orphaned' => 0, 'ok' => true, 'skip' => true];
    }

    // roles → levels (level_id)
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM roles r LEFT JOIN levels l ON r.level_id = l.id WHERE r.level_id IS NOT NULL AND l.id IS NULL");
        $orphaned = (int)$stmt->fetchColumn();
        $checks[] = ['label' => 'roles → levels', 'orphaned' => $orphaned, 'ok' => $orphaned === 0];
    } catch (Exception $e) {
        $checks[] = ['label' => 'roles → levels', 'orphaned' => 0, 'ok' => true, 'skip' => true];
    }

    // games → engines (engine slug match via slug)
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM games g LEFT JOIN engines e ON g.engine = e.name WHERE g.engine != '' AND e.id IS NULL");
        $orphaned = (int)$stmt->fetchColumn();
        $checks[] = ['label' => 'games → engines', 'orphaned' => $orphaned, 'ok' => $orphaned === 0];
    } catch (Exception $e) {
        $checks[] = ['label' => 'games → engines', 'orphaned' => 0, 'ok' => true, 'skip' => true];
    }

    // retro_games → retro_consoles (console slug match via name)
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM retro_games rg LEFT JOIN retro_consoles rc ON rg.console = rc.name WHERE rc.id IS NULL");
        $orphaned = (int)$stmt->fetchColumn();
        $checks[] = ['label' => 'retro_games → retro_consoles', 'orphaned' => $orphaned, 'ok' => $orphaned === 0];
    } catch (Exception $e) {
        $checks[] = ['label' => 'retro_games → retro_consoles', 'orphaned' => 0, 'ok' => true, 'skip' => true];
    }

    // team_members → users (user_id)
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM team_members tm LEFT JOIN users u ON tm.user_id = u.id WHERE tm.user_id IS NOT NULL AND u.id IS NULL");
        $orphaned = (int)$stmt->fetchColumn();
        $checks[] = ['label' => 'team_members → users', 'orphaned' => $orphaned, 'ok' => $orphaned === 0];
    } catch (Exception $e) {
        $checks[] = ['label' => 'team_members → users', 'orphaned' => 0, 'ok' => true, 'skip' => true];
    }

    return $checks;
}

// ── Run diagnostic ──
if (isset($_GET['rescan'])) {
    flashMessage('success', 'Escaneamento concluído — todas as verificações foram atualizadas.');
    ob_end_clean();
    header('Location: ' . ADMIN_URL . '/repair');
    exit;
}
$diagnostic = buildDiagnostic($db, $dbType);
$svDiag = getSchemaVersionDiagnostic($db);
$integrityChecks = getIntegrityChecks($db, $dbType);
$rowCounts = [];
foreach (array_keys(getExpectedSchema()) as $table) {
    try {
        $rowCounts[$table] = (int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    } catch (Exception $e) {
        $rowCounts[$table] = null;
    }
}

$totalIssues = 0;
foreach ($diagnostic as $d) {
    if (!$d['exists']) $totalIssues++;
    elseif (!empty($d['missing_cols']) || !empty($d['dropped_cols_present'])) $totalIssues++;
}
if (!$svDiag['all_ok']) $totalIssues++;

if (!isset($repairResult)) { $repairResult = null; }
if (isset($_GET['repair_result'])) {
    $repairResult = base64_decode(urldecode($_GET['repair_result']));
}
?>

<?php if ($repairResult): ?>
<div class="card" style="margin-bottom:24px;border-color:var(--gold, #c9a84c)">
    <div class="card-header">
        <h3 class="card-title" style="color:var(--gold, #c9a84c)">📋 Relatório do Reparo</h3>
    </div>
    <div class="card-body" style="font-family:var(--mono, 'JetBrains Mono', monospace);font-size:13px;line-height:1.7;white-space:pre-wrap;color:var(--fg-muted, #aaa)">
        <?= e($repairResult) ?>
    </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <h2 class="card-title">🔍 Diagnóstico do Banco de Dados</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="<?= ADMIN_URL ?>/repair?rescan=1" class="btn btn-outline">
                🔄 Re-escanear
            </a>
            <form method="POST" style="display:inline" id="repairForm">
                <?= csrfField() ?>
                <button type="submit" name="action" value="repair" class="btn btn-gold" <?= $totalIssues === 0 ? 'disabled' : '' ?>>
                    ⚡ Executar Reparo
                </button>
            </form>
            <a href="<?= ADMIN_URL ?>/repair?action=reset" class="btn btn-outline" style="color:var(--danger, #e74c3c);border-color:var(--danger, #e74c3c)">
                ⚠ Reset Completo
            </a>
        </div>
    </div>
    <div class="card-body">
        <p style="color:var(--fg-muted);margin-bottom:16px">
            Compara o schema atual do banco com o esperado após todas as migrações (001–027),
            verifica integridade referencial e contagem de registros.
            Clique em "Re-escanear" para atualizar os resultados.
        </p>

        <?php if ($totalIssues > 0): ?>
            <div class="alert alert-warning" style="padding:12px 16px;border:1px solid var(--gold, #c9a84c);border-radius:8px;margin-bottom:16px;background:oklch(68% 0.16 85 / 0.08)">
                <strong style="color:var(--gold, #c9a84c)">⚠ <?= $totalIssues ?> problema(s)</strong>
                <span style="color:var(--fg-muted);margin-left:8px">
                    — revise abaixo e clique em "Executar Reparo" para corrigir.
                </span>
            </div>
        <?php else: ?>
            <div class="alert alert-success" style="padding:12px 16px;border:1px solid oklch(55% 0.15 145);border-radius:8px;margin-bottom:16px;background:oklch(55% 0.15 145 / 0.08)">
                <strong style="color:oklch(55% 0.15 145)">✅ Nenhum problema encontrado</strong>
                <span style="color:var(--fg-muted);margin-left:8px">
                    — todas as <?= count($diagnostic) ?> tabelas conferem com o schema esperado.
                </span>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">📋 Tabelas</h3>
        <span class="badge" style="background:var(--bg-card, #1a1a2e);color:var(--fg-muted, #888)"><?= count($diagnostic) ?> tabelas esperadas</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Tabela</th>
                        <th>Linhas</th>
                        <th>Colunas</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($diagnostic as $d): ?>
                    <tr>
                        <td><code style="font-weight:600;color:var(--cyan, #5bc0de)"><?= e($d['table']) ?></code></td>
                        <td style="font-family:var(--mono, 'JetBrains Mono', monospace);font-size:13px;color:var(--fg-muted)">
                            <?php if (!$d['exists']): ?>
                                —
                            <?php elseif ($rowCounts[$d['table']] !== null): ?>
                                <?= number_format($rowCounts[$d['table']], 0, ',', '.') ?>
                            <?php else: ?>
                                ?
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$d['exists']): ?>
                                <span style="color:var(--danger, #e74c3c)">❌ Tabela não existe</span>
                            <?php elseif (!empty($d['missing_cols'])): ?>
                                <span style="color:var(--gold, #c9a84c)">⚠ Faltam: </span>
                                <code style="color:var(--gold, #c9a84c)"><?= e(implode(', ', $d['missing_cols'])) ?></code>
                            <?php elseif (!empty($d['dropped_cols_present'])): ?>
                                <span style="color:var(--gold, #c9a84c)">⚠ Obsoletas: </span>
                                <code style="color:var(--gold, #c9a84c)"><?= e(implode(', ', $d['dropped_cols_present'])) ?></code>
                            <?php else: ?>
                                <span style="color:oklch(55% 0.15 145)">✅ Ok</span>
                            <?php endif; ?>
                            <?php if (!empty($d['extra_cols'])): ?>
                                <br><span style="color:var(--fg-muted, #666);font-size:12px">Extras: <?= e(implode(', ', $d['extra_cols'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$d['exists']): ?>
                                <span class="badge badge-inactive">Ausente</span>
                            <?php elseif (!empty($d['missing_cols']) || !empty($d['dropped_cols_present'])): ?>
                                <span class="badge badge-featured">Inconsistente</span>
                            <?php else: ?>
                                <span class="badge badge-active">Ok</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card" style="margin-top:24px">
    <div class="card-header">
        <h3 class="card-title">📜 Schema Version</h3>
        <span class="badge" style="background:var(--bg-card, #1a1a2e);color:var(--fg-muted, #888)"><?= count($svDiag['installed']) ?>/<?= count(getMigrationList()) ?> versões</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Versão</th>
                        <th>Nome</th>
                        <th>Aplicada em</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $expectedList = getMigrationList(); ?>
                    <?php foreach ($expectedList as $ver => $name): ?>
                        <?php $inst = $svDiag['installed'][$ver] ?? null; ?>
                        <tr>
                            <td><?= sprintf('%03d', $ver) ?></td>
                            <td><code><?= e($name) ?></code></td>
                            <td><?= $inst ? e($inst['applied_at']) : '—' ?></td>
                            <td>
                                <?php if (!$inst): ?>
                                    <span class="badge badge-inactive">Faltando</span>
                                <?php elseif ($inst['name'] !== $name): ?>
                                    <span class="badge badge-featured">Nome divergente</span>
                                <?php else: ?>
                                    <span class="badge badge-active">Ok</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!empty($svDiag['extra'])): ?>
                        <?php foreach ($svDiag['extra'] as $ext): ?>
                        <tr style="background:oklch(55% 0.15 145 / 0.04)">
                            <td><?= sprintf('%03d', $ext['version']) ?></td>
                            <td><code><?= e($ext['name']) ?></code></td>
                            <td><?= e($ext['applied_at']) ?></td>
                            <td><span class="badge badge-featured">Extra (desconhecida)</span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card" style="margin-top:24px">
    <div class="card-header">
        <h3 class="card-title">🔗 Integridade Referencial</h3>
        <span class="badge" style="background:var(--bg-card, #1a1a2e);color:var(--fg-muted, #888)"><?= count($integrityChecks) ?> verificações</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Relação</th>
                        <th>Registros Órfãos</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($integrityChecks as $check): ?>
                    <tr>
                        <td><code style="font-weight:600;color:var(--cyan, #5bc0de);font-size:13px"><?= e($check['label']) ?></code></td>
                        <td style="font-family:var(--mono, 'JetBrains Mono', monospace);font-size:13px">
                            <?php if (!empty($check['skip'])): ?>
                                <span style="color:var(--fg-muted)">—</span>
                            <?php else: ?>
                                <?= $check['orphaned'] ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($check['skip'])): ?>
                                <span class="badge badge-na">N/A</span>
                            <?php elseif ($check['ok']): ?>
                                <span class="badge badge-active">Ok</span>
                            <?php else: ?>
                                <span class="badge badge-featured">Órfãos: <?= $check['orphaned'] ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($action === 'reset'): ?>
<div class="card" style="margin-top:24px;border-color:var(--danger, #e74c3c)">
    <div class="card-header" style="border-bottom-color:var(--danger, #e74c3c)">
        <h3 class="card-title" style="color:var(--danger, #e74c3c)">⚠ Modo Reset — Destrutivo</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-danger" style="padding:12px 16px;border:1px solid var(--danger, #e74c3c);border-radius:8px;margin-bottom:16px;background:oklch(55% 0.2 29 / 0.08)">
            <strong style="color:var(--danger, #e74c3c)">🚨 ATENÇÃO:</strong>
            <span style="color:var(--fg-muted)">
                Todas as tabelas serão removidas e recriadas do zero.
                <strong>Todos os dados serão perdidos.</strong>
                Use apenas como último recurso, quando o Modo Reparo não for suficiente.
            </span>
        </div>

        <form method="POST">
            <?= csrfField() ?>
            <div style="margin-bottom:16px">
                <label for="master_password" style="display:block;margin-bottom:6px;font-weight:600;color:var(--fg)">Senha do Master</label>
                <input type="password" name="master_password" id="master_password" required
                    style="width:100%;max-width:400px" class="form-input"
                    placeholder="Digite a senha do administrador master">
            </div>

            <div style="margin-bottom:20px">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--danger, #e74c3c)">
                    <input type="checkbox" name="confirm_reset" value="1" required>
                    <span>Sim, quero resetar o banco de dados e perder TODOS os dados</span>
                </label>
            </div>

            <div style="display:flex;gap:8px">
                <button type="submit" name="action" value="reset" class="btn" style="background:var(--danger, #e74c3c);color:#fff;border:none;padding:10px 24px">
                    🗑 Executar Reset
                </button>
                <a href="<?= ADMIN_URL ?>/repair" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
