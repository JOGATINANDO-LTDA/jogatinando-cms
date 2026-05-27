# Jogatinando CMS — Plano de Reestruturação

## Visão Geral
Refatorar CMS para suportar catálogo de jogos, templates, emulação retro e armazenamento em Cloudflare R2 + CDN.

## Fases

### Fase 0 — Storage Layer
- `includes/storage.php`: classe estática wrapping filesystem
- Métodos: `upload`, `delete`, `url`, `exists`, `extractZip`
- Novo código usa `Storage::`, legado continua com funções atuais

### Fase 1 — Home: Roda de Categorias
- Seção `#games` vira carrossel horizontal com 4 cards: Autorais, Clientes, Templates, Emulação
- Cada card linka pra página da categoria
- Atualizar `main.js` (selector `.game-card` → `.category-card`) + `style.css`

### Fase 2 — Migrations
| # | Nome | Ação |
|---|---|---|
| 8 | `clean_engine_outra` | DELETE engines WHERE name='Outra' (já existe) |
| 9 | `add_game_fields` | game_type + is_web_playable em games; store_platforms + game_links |
| 10 | `create_templates` | game_templates table |
| 11 | `create_retro` | retro_games table |

### Fase 3 — Admin
- `admin/games.php`: + game_type, is_web_playable, game_links (plataformas)
- `admin/platforms.php` (novo): CRUD de plataformas (Steam, Epic, etc)
- `admin/templates.php` (novo): CRUD templates
- `admin/retro.php` (novo): CRUD retro + ROM upload
- Sidebar: Templates e Retro em "Conteúdo", Plataformas em "Sistema"

### Fase 4 — Frontend
- `catalogo.php`: grid com filtros (?tipo=autoral|cliente, engine)
- `game.php`: branch is_web_playable — teatro vs detalhes + store links
- `templates.php`: grid templates
- `template.php`: player + store link
- `retro.php`: Originais / Modificados → grid por console
- `retro-play.php`: EmulatorJS embed transparente

### Fase 5 — Navegação
- Navbar: Início | Catálogo | Templates | Retro | Blog (ancora) | Contato (ancora)
- Admin sidebar reorganizada com novos itens

## Arquivos (25)

### Criar
- `includes/storage.php`
- `catalogo.php`, `templates.php`, `template.php`
- `retro.php`, `retro-play.php`
- `admin/templates.php`, `admin/retro.php`, `admin/platforms.php`

### Modificar
- `includes/db.php` (+ migration list 8-11)
- `includes/migrations.php` (+ functions 9-11)
- `admin/games.php`
- `includes/header.php` (sidebar)
- `router.php` (rotas)
- `index.php` (navbar + category wheel + footer links)
- `game.php` (non-playable branch)
- `config.php` (STORAGE_DRIVER)
- `assets/js/main.js` (category wheel selectors)
- `assets/css/style.css` (grids, category wheel, retro)
- `assets/css/admin.css` (new forms)

## Risco
- **Médio**: game.php vira 2 páginas — testar ambos os paths
- **Médio**: carrossel JS precisa atualizar selector .game-card → .category-card
- **Baixo**: router collision mitigada (específico antes do genérico)
- **Baixo**: migration 008 não registrada — adicionar com segurança
