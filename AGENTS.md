# Jogatinando CMS — Agent Guide

## Regras do Agente (fortes, não negociáveis)

1. **Idioma**: Responda APENAS em português do Brasil. Nunca use inglês ou outros idiomas.
2. **Sistema operacional**: Sempre use Windows 11 com PowerShell padrão (Windows PowerShell 5.1, não PowerShell 7/PowerShell Core). Nunca use comandos bash, sh, zsh ou sintaxe de terminal Linux/macOS. Use cmdlets PowerShell (`Get-ChildItem`, `Set-Content`, `Test-Path`, etc.), não comandos Unix (`ls`, `cat`, `chmod`, `grep`, etc.).
3. **Commit proibido sem autorização explícita**: Nunca faça commit, push, ou qualquer operação de escrita no repositório remoto a menos que o usuário diga EXATAMENTE a palavra "commit" no mesmo contexto.
4. **Um commit por vez**: Cada permissão de "commit" vale APENAS para o commit atual. Depois de executado, você DEVE esperar um novo "commit" explícito para o próximo. Mesmo que o commit anterior tenha passado, não assuma permissão para o seguinte.

## Project

PHP 8.2 + SQLite/MySQL CMS for Jogatinando game studio website. Flat-file PHP, no framework, no Composer.

## Commands

```bash
# Start (Docker)
docker compose -p jogatinando-cms -f docker/docker-compose.yml up -d --build --force-recreate

# Refresh (down + up evitar conflito de containers órfãos)
docker compose -p jogatinando-cms -f docker/docker-compose.yml down && docker compose -p jogatinando-cms -f docker/docker-compose.yml up -d --build

# Stop
docker compose -p jogatinando-cms -f docker/docker-compose.yml down

# Reset (destroy + rebuild from scratch)
docker compose -p jogatinando-cms -f docker/docker-compose.yml down -v && docker compose -p jogatinando-cms -f docker/docker-compose.yml up -d --build

# Logs
docker compose -f docker/docker-compose.yml logs -f
```

Site runs at **http://localhost:8080**. No npm, no build step, no test suite.

## First-run

1. **Docker (MySQL)**: `docker compose ... up --build` → visit `http://localhost:8080/install.php` → click "MySQL / MariaDB" → fill host `db`, user `cms_user`, pass `cms_pass2024` → done
2. **SQLite**: Visit `http://localhost:8080/install.php` → click "SQLite (Simples)"
3. Login at `/admin/login.php`:
   - **MySQL (Docker)**: user `sorameshi`, password `lotus10`
   - **SQLite**: user `admin`, password `admin1234`
4. Delete or `chmod 000 install.php` after install (exposes reset endpoint)

> `config.local.php` é a única forma do sistema saber se está instalado. SQLite e MySQL ambos criam `config.local.php` após o install. Se o arquivo for perdido, install.php aparece — mesmo que o `.db` ainda exista. O usuário clica SQLite (ou MySQL com mesmas credenciais) e os dados são preservados.

> **MySQL auto-create DB**: O install.php conecta primeiro ao MySQL sem database (`CREATE DATABASE IF NOT EXISTS \`$name\``) antes de rodar dbInit. Funciona se o usuário tiver privilégio CREATE. No Docker, `cms_user` não tem — usar database `cms_db` (já existe).

> **Admin credentials**: Em `config.php`, os env vars `ADMIN_USERNAME` e `ADMIN_PASSWORD` do Docker Compose só afetam **MySQL**. Quando `DB_TYPE=sqlite` (via `config.local.php` do install), as credenciais padrão `admin`/`admin1234` são usadas independentemente dos env vars. Isso garante que SQLite sempre use `admin`/`admin1234` mesmo dentro do container Docker.

> **Migrações automáticas**: `dbMigrate()` roda em toda conexão PDO. Se o schema do banco estiver desatualizado, as migrações são aplicadas automaticamente sem perder dados.

## Architecture

- **Entry points**: `index.php` (frontend), `game.php` (game player), `install.php` (setup wizard), `admin/*.php` (admin panel)
- **Config**: `config.php` — defines paths, URLs, upload limits, admin creds, auto-loads all helpers. `config.local.php` (gitignored) overrides secrets and DB type. Install marker: system only considers itself installed if `config.local.php` exists.
- **DB**: Supports SQLite or MySQL/MariaDB via PDO. DB type set via `DB_TYPE` constant (`sqlite`|`mysql`) defined in `config.local.php`. Without it, `getDB()` returns `null` → redirect to install.php. MySQL credentials: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`. Single `getDB()` singleton.
- **Migration system**: `schema_version` table tracks applied migrations. `includes/migrations.php` contains numbered functions (`migration_001()` etc.). Auto-runs on `getDB()` via `dbMigrate()`. Existing SQLite DBs without `schema_version` are detected and backfilled.
- **Tables**: `users`, `banners`, `games`, `blog_posts`, `testimonials`, `faq_items`, `team_members`, `site_settings`, `schema_version`
- **Auth**: Session-based login + CSRF tokens. All `admin/` pages call `requireLogin()`
- **URLs**: `.htaccess` rewrites `/<engine-slug>/<game-slug>` → `game.php?engine=$1&slug=$2`, everything else → `index.php`

### DB helpers (includes/db.php)

| Function | Purpose |
|---|---|
| `getDB()` | PDO singleton — auto-detects DB type, runs migrations |
| `getDbType()` | Returns `'sqlite'` or `'mysql'` from `DB_TYPE` constant |
| `dbInit($dsn, $user, $pass, $type)` | Setup — creates all tables + seeds data |
| `dbMigrate($db)` | Runs pending migrations from `schema_version` |
| `dbQuery()` / `dbQueryOne()` / `dbExec()` | Query helpers using `getDB()` |
| `dbDelete()` | DELETE by id |
| `getSetting()` / `setSetting()` | site_settings CRUD |
| `dbRandom()` | Returns `RANDOM()` (SQLite) or `RAND()` (MySQL) |
| `dbInsertIgnore()` / `dbInsertReplace()` | Cross-DB INSERT wrappers |
| `getDbTables()` | Lists all table names (works on both DB types) |

## Key helpers (includes/)

| File | What |
|---|---|
| `db.php` | `getDB()`, `dbInit()`, `dbQuery()`, `dbQueryOne()`, `dbExec()`, `dbDelete()`, `getSetting()`, `setSetting()` |
| `auth.php` | `isLoggedIn()`, `requireLogin()`, `login()`, `logout()`, `getCSRFToken()`, `verifyCSRF()`, `csrfField()` |
| `functions.php` | `e()` (escape), `generateSlug()`, `uploadFile()`, `deleteFile()`, `truncateText()`, `timeAgo()`, `flashMessage()`, `renderFlash()`, `getEngineIcon()`, `getEngineColor()` |

## Uploads

- Max 100MB. Images: jpg/jpeg/png/gif/webg. Games: zip only.
- Structure: `uploads/{banners,games,thumbnails,avatars,blog}/`
- Game ZIPs are auto-extracted on first play via `game.php` (looks for `index.html` in root or subfolder)
- Sync to S3/R2 via `admin/bucket-sync.php`

## Docker notes

- Volumes `cms-data`, `cms-uploads`, and `cms-mysql` persist data across rebuilds
- MySQL 8.0 service (`db`) with credentials: `cms_db` / `cms_user` / `cms_pass2024`, database `cms_db`, port `3307` (host) → `3306` (container)
- `docker/mysql-init.sql` grants `cms_user` ALL PRIVILEGES ON *.* (incl. CREATE DATABASE) — runs only on first MySQL init (`down -v` to reset)
- **MySQL database não é pré-criado** — não tem `MYSQL_DATABASE` nem `MYSQL_USER` no docker-compose. O container sobe zerado. O `cms_user` é criado via init script, mas o database só será criado quando o usuário preencher o form MySQL no install.php.
- For MySQL dev, `config.local.php` sets `DB_TYPE=mysql` with host `db` — created by install form
- For dev hot-reload: bind mount is already active (code changes reflect instantly)
- `.dockerignore` excludes `data/*.db`, `uploads/*`, `config.local.php`, `docker/mysql-init.sql` (volumes handle persistence)

## Security gotchas

- Admin password is a hardcoded hash in `config.php` — change via `ADMIN_PASSWORD_HASH` constant
- `install.php` has a **reset** action that deletes the DB and reseeds — remove after setup
- `SITE_URL` comes from `$_ENV['SITE_URL']`, defaults to `http://localhost` — set for production
- Errors logged but not displayed (`display_errors=0`, `log_errors=1`)

## Production checklist

- **HSTS**: Already set in `.htaccess` (Strict-Transport-Security). Ensure HTTPS is configured at the reverse proxy.
- **Session secure flag**: `config.php` checks both `$_SERVER['HTTPS']` and `HTTP_X_FORWARDED_PROTO` (for reverse proxies).
- **data/**: Protected by `.htaccess` (deny all). SQLite DB is not downloadable.
- **uploads/**: Protected by `.htaccess` (blocks PHP execution).

## External Games & Cursor

Jogos externos (tipo `externo`) carregam em iframe cross-origin. Alguns jogos usam `cursor: none` no CSS e renderizam cursor customizado via canvas/JS (ex: Kaetram MMORPG). Em iframe cross-origin, o Service Worker/Partytown do jogo falha e o cursor customizado não renderiza — resultado: cursor invisível.

**Decisão:** Usar iframe direto (cross-origin). O navegador mostra o cursor padrão (seta). Usuários nunca ficam sem cursor.

**Proxy rejeitado:** Proxy prefixado (`/proxy/game/<slug>/`) testado via Apache `mod_proxy` + `ProxyPass`. Não funciona para jogos com backend próprio porque:
- Assets root-relative (ex: `/_astro/...`) não passam pelo proxy
- WebSocket constrói URL baseada em `window.location` → aponta para o CMS, não para o backend real
- Partytown/Service Worker continua quebrado em iframe (mesmo same-origin)

Exceção: jogos autorais HTML5 (uploadados) carregam same-origin → cursor funciona normalmente.

**Toggle:** `$useProxy` em `game.php:34` — `false` por padrão. Estrutura de fallback JS mantida (timeout 8s + iframe.onerror → URL direta) para uso futuro.

**mod_proxy** habilitado no Docker (`a2enmod proxy proxy_http proxy_wstunnel`) — inofensivo, reservado.

## Game Player (game.php)

- **Fullscreen button**: draggable via CSS `cursor: grab` + JS `mousedown`/`mousemove`/`mouseup` handlers. Clamped to container bounds. Drag threshold 4px to distinguish click vs drag.
- **CSP**: set via PHP `header()` em runtime. `$frameOrigin` inclui porta para jogos externos. `Header setifempty` no `.htaccess` dá precedência ao PHP.
- **COEP/COOP removidos**: bloqueavam carregamento de documentos cross-origin no iframe. O jogo externo não envia `Cross-Origin-Resource-Policy` — Chrome recusava o document.
- **`allowfullscreen` removido** do `<iframe>` — `allow="fullscreen"` já cobre.

## Iframe Sizing Strategy

Two strategies depending on game type:

- **Autoral / Cliente (same-origin)**: JS auto-fit no `load` do iframe — acessa `contentDocument`, força `overflow:hidden`, `width:100%`, `height:100%`, `margin:0`, `padding:0` no body/html do conteúdo. Container usa `aspect-ratio: 16/9` padrão do CSS.
- **Externo (cross-origin)**: não é possível acessar `contentDocument`. Usa campos manuais `iframe_width` / `iframe_height` no form (Link Externo). Aplicados como `width` e `height` inline no container `.theater-player-externo`.

**Migration 029** (`add_iframe_width_height_to_games`): adiciona colunas `iframe_width` e `iframe_height` (VARCHAR(10), default `'100%'`).

**`admin/games.php`** — Link Externo section:
- `iframe_width` input (placeholder `800px, 100%`)
- `iframe_height` input (placeholder `600px, 80vh`)
- Validados por regex `/^\d+(px|%|vh|vw)?$/`; fallback `'100%'`.

**`game.php`**:
- Lê `$iframeWidth` / `$iframeHeight` do DB
- Externo: aplica como `style="width:X;height:Y"` no `.theater-player-externo`
- `$orientation` derivado de `intval($iframeWidth)` vs `intval($iframeHeight)` para externo; `'auto'` para same-origin (sem lock forçado)
- `aspect_ratio` coluna mantida mas não usada

**CSS** (`style.css`):
- `.theater-player`: base `aspect-ratio: 16/9`, `overflow: hidden`
- `.theater-player-externo`: `aspect-ratio: unset`, `width/height: auto` (inline style do PHP controla), `max-width: 100%`
- `.theater-player-externo .theater-iframe`: `object-fit: contain`, `width: 100%`, `height: 100%`
- `[data-aspect]` selectors removidos (não usados)

## Admin Game Form Order

The game edit form (`admin/games.php`) sections are ordered:
1. **Informações Básicas** — title, engine
2. **Descrição** — textarea
3. **Tipo** — game_type select (autoral/cliente/externo)
4. **Link Externo** — external_url, iframe_width, iframe_height, is_open_source, repo_url (hidden unless externo)
5. **Mídia** — thumbnail upload, game_archive upload (hidden for externo)
6. **Configurações** — sort_order, is_web_playable, featured, active
7. **Links de Distribuição** — distribution links

## Style

- Admin UI: dark theme with gold accents, OKLCH color tokens in `assets/css/admin.css`
- Frontend: `assets/css/style.css` — Cinzel + Inter fonts, cosmic/medieval aesthetic
- All HTML is inline PHP templates (no separate view layer)
- Language: pt-BR
