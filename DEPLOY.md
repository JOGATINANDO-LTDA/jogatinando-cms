# Hostinger deployment guide

## Branch model

| Branch | Environment | Deploy |
|---|---|---|
| `main` | Homologação (Docker local) | — |
| `master` | **Produção** (Hostinger) | cPanel Git Version Control → `.cpanel.yml` |

Releases: merge `main` → `master`, push, then deploy via cPanel. Never commit directly to `master`.

## 1. How deploy works (cPanel Git VC)

`.cpanel.yml` runs on every deploy:

1. Backs up `data/`, `uploads/`, `config.local.php` (webroot) and `$HOME/config.local.php` (outside webroot)
2. Copies the repo to `public_html/`
3. Removes `.git/`, `install.php.disabled`, legacy `robots.txt` from the webroot
4. Restores persistent data (DB files, uploads, config)
5. Prints config presence check to the deploy log

> `cp` does not delete removed files. When deleting a file from the repo, add a
> `rm -f $DEPLOYPATH/<file>` line to `.cpanel.yml` (step 3).

## 2. Config persistence

The installer writes `config.local.php` to **two locations**:

| Location | Role | Survives deploy? |
|---|---|---|
| `$HOME/config.local.php` (outside webroot) | **Primary** | Yes — deploy never touches it |
| `public_html/data/config.local.php` | Fallback | Yes — backed up/restored by `.cpanel.yml` |

`config.php` load order: persistent → `data/` → legacy root (auto-migrated).

## 3. First run (fresh server)

1. Deploy via cPanel (branch `master`)
2. Visit `https://yoursite.com/install.php`
3. Choose **MySQL / MariaDB**, fill Hostinger DB credentials
4. Installer writes config (both locations) and seeds the DB
5. Log in at `/admin/login`

`install.php` **stays in the webroot** after install. It is guarded:
- Without `?reconfigure=1` → redirects to `/`
- With `?reconfigure=1` → **CEO only** (user id 1)

## 4. Reconfigure (change DB credentials, site name, etc.)

1. Log in as the **CEO** (user id 1)
2. Visit `https://yoursite.com/install?reconfigure=1` (or `/install.php?reconfigure=1`)
3. The form is pre-filled with current credentials; leave password blank to keep it
4. Submit — config is rewritten to both locations

### Recovery mode (database unreachable)

If the DB connection is broken and no CEO session exists, `/install?reconfigure=1`
opens in **recovery mode**: no login required, with a warning banner and an
`error_log` entry (IP recorded). Use it to fix broken credentials.

## 5. Health check

`https://yoursite.com/health.php` — public basic diagnostics (no paths/secrets):
config source, DB connection, schema version, maintenance mode, writability,
`.git` exposure.

- Detailed mode: `?key=<cron_key>` (same key as `cron.php`) → adds paths/versions
- JSON: `?format=json`

## 6. Version prompt

When `CMS_VERSION` changes, the site shows a "Versão Desatualizada" page asking:
- **Usar config atual e migrar dados** (recommended)
- **Nova instalação** (destroys data)

After a version-bump deploy, click the first option once.

## 7. Rollback

- cPanel → Git VC → deploy a previous commit, **or**
- `git revert <commit>` on `master` and redeploy

Config, uploads and database are not affected by code rollbacks.

## 8. PHP limits (Hostinger)

Hostinger respects `.htaccess` / `.user.ini` directives:
- **Business**: `post_max_size` up to 256MB
- **Cloud**: `post_max_size` up to 512MB
- **Shared free**: limited to 30MB (upgrade needed)

## 9. RAR support

RAR is **not supported**. Only ZIP uploads are accepted (shared-hosting compatibility).

## 10. Image optimization

`pngquant`/`jpegoptim` are not available on shared hosting. The optimizer falls
back to **PHP GD**. GD is optional for the CMS (health check warns if absent).

## 11. Security checklist

- [ ] Admin password changed (settings or `ADMIN_PASSWORD_HASH`)
- [ ] `.git` not exposed — verify `https://yoursite.com/.git/HEAD` returns 403/404
- [ ] `data/` and `uploads/` not publicly accessible (`.htaccess` deny rules)
- [ ] `display_errors=0` (already in `config.php`)
- [ ] `health.php` returns `ok`/`warn` only (no `fail`)
