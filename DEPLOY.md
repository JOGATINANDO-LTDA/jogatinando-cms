# Hostinger deployment guide

## 1. Prepare files
```bash
# Remove Docker-only files from deploy
rm -rf docker/ .dockerignore
# Remove dev-only files
rm -rf data/*.db  # DB will be created on first run
rm -rf uploads/games/* uploads/thumbnails/* uploads/banners/* uploads/blog/* uploads/avatars/*
# Keep .htaccess (already has Hostinger-compatible rules)
```

## 2. Upload to Hostinger
- **FTP**: FileZilla → `ftp.yourdomain.com` → upload to `htdocs/`
- **Git**: Hostinger supports Git deploy → push to `htdocs/`

## 3. First run
1. Visit `https://yoursite.com/install.php`
2. Click "Instalar CMS"
3. Delete `install.php` after setup

## 4. Configure SITE_URL
Edit `config.php` or add before `require_once 'config.php'`:
```php
define('SITE_URL', 'https://yoursite.com');
```

## 5. PHP limits (Hostinger)
Hostinger respects `.htaccess` `php_value` directives:
- **Business plan**: `post_max_size` up to 256MB
- **Cloud plan**: `post_max_size` up to 512MB
- **Shared free**: `post_max_size` limited to 30MB (upgrade needed)

## 6. RAR support
Hostinger shared hosting **does not have** `unrar` or `rar` extension.
Users must upload **ZIP only**. The CMS detects this and shows a warning.

## 7. Image optimization
`pngquant` and `jpegoptim` are not available on shared hosting.
The optimizer falls back to **PHP GD** (always available).

## 8. Security checklist
- [ ] Delete `install.php`
- [ ] Change admin password (via settings or `ADMIN_PASSWORD_HASH` constant)
- [ ] Set `display_errors=0` in `.htaccess` (already set in `config.php`)
- [ ] Ensure `data/` and `uploads/` are NOT publicly accessible (they are outside web root if configured correctly)
