# Jogatinando CMS — Agent Guide

## Project

PHP 8.2 + SQLite CMS for Jogatinando game studio website. Flat-file PHP, no framework, no Composer.

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

1. Visit `http://localhost:8080/install.php` → click "Instalar CMS"
2. Login at `/admin/login.php` — user: `admin`, password: `jogatinando2024`
3. Delete or `chmod 000 install.php` after install (exposes reset endpoint)

## Architecture

- **Entry points**: `index.php` (frontend), `game.php` (game player), `install.php` (setup wizard), `admin/*.php` (admin panel)
- **Config**: `config.php` — defines paths, URLs, upload limits, admin creds, auto-loads all helpers. `config.local.php` (gitignored) overrides secrets and DB type.
- **DB**: Supports SQLite (default) or MySQL/MariaDB via PDO. DB type set via `DB_TYPE` constant (`sqlite`|`mysql`). MySQL credentials: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`. Single `getDB()` singleton.
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

- Max 100MB. Images: jpg/jpeg/png/gif/webp. Games: zip only.
- Structure: `uploads/{banners,games,thumbnails,avatars,blog}/`
- Game ZIPs are auto-extracted on first play via `game.php` (looks for `index.html` in root or subfolder)

## Docker notes

- Volumes `cms-data`, `cms-uploads`, and `cms-mysql` persist data across rebuilds
- MySQL 8.0 service (`db`) with credentials: `jogatinando` / `jogatinando2024`, database `jogatinando`, port `3307` (host) → `3306` (container)
- For MySQL dev, `config.local.php` sets `DB_TYPE=mysql` with host `db` — auto-migrates on first request
- For dev hot-reload: bind mount is already active (code changes reflect instantly)
- `.dockerignore` excludes `data/*.db`, `uploads/*`, `config.local.php` (volumes handle persistence)

## Security gotchas

- Admin password is a hardcoded hash in `config.php` — change via `ADMIN_PASSWORD_HASH` constant
- `install.php` has a **reset** action that deletes the DB and reseeds — remove after setup
- `SITE_URL` comes from `$_ENV['SITE_URL']`, defaults to `http://localhost` — set for production
- Errors logged but not displayed (`display_errors=0`, `log_errors=1`)

## Style

- Admin UI: dark theme with gold accents, OKLCH color tokens in `assets/css/admin.css`
- Frontend: `assets/css/style.css` — Cinzel + Inter fonts, cosmic/medieval aesthetic
- All HTML is inline PHP templates (no separate view layer)
- Language: pt-BR
