# Jogatinando CMS — Agent Guide

## Project

PHP 8.2 + SQLite CMS for Jogatinando game studio website. Flat-file PHP, no framework, no Composer.

## Commands

```bash
# Start (Docker)
docker compose -f docker/docker-compose.yml up -d --build

# Stop
docker compose -f docker/docker-compose.yml down

# Logs
docker compose -f docker/docker-compose.yml logs -f
```

Site runs at **http://localhost:8080**. No npm, no build step, no test suite.

## First-run

1. Visit `http://localhost:8080/install.php` → click "Instalar CMS"
2. Login at `/admin/login.php` — user: `admin`, password: `jogatinando2024`
3. Delete or `chmod 000 install.php` after install (exposes reset endpoint)

## Architecture

- **Entry points**: `index.php` (frontend), `game.php` (game player), `install.php` (setup), `admin/*.php` (admin panel)
- **Config**: `config.php` — defines paths, URLs, upload limits, admin creds, auto-loads all helpers
- **DB**: SQLite at `data/jogatinando.db`. WAL mode, foreign keys ON. Single `getDB()` singleton.
- **Tables**: `users`, `banners`, `games`, `blog_posts`, `testimonials`, `faq_items`, `team_members`, `site_settings`
- **Auth**: Session-based login + CSRF tokens. All `admin/` pages call `requireLogin()`
- **URLs**: `.htaccess` rewrites `/jogar/<id>` → `game.php?id=$1`, everything else → `index.php`

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

- Volumes `cms-data` and `cms-uploads` persist DB and uploads across rebuilds
- For dev hot-reload: uncomment `- ..:/var/www/html` bind mount in `docker-compose.yml` (no rebuild needed)
- `.dockerignore` excludes `data/*.db` and `uploads/*` (volumes handle persistence)

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
