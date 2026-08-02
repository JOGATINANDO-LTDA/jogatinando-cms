# Goal
- Implement modal description with markdown and WYSIWYG editor for long texts; complete admin CSS consistency; Play Store integration schema and hub; full QA validation.

## Instructions
- Docker PHP lint via `app` service, no local `php` host.
- Hostinger production not accessible.
- QA must validate each material change.
- Maintain flow functionality.
- S3 optional; local default.
- Upload safe: allow `jpg|jpeg|png|gif|webp`; block `php|html|css|js|svg`.
- No inline `style="";` all admin pages use CSS classes.
- Forms use `form-row`, `form-group`, `label for`, `field-hint`, `form-section-title`, `toggle-group`.
- Uploads use `file-upload` standard.
- Distribution becomes integration hub starting with Play Store.
- UI standard: `.hidden` no `!important`; use `style.display` for overlay.
- `.btn-text` overrides `.btn` base with `!important` where needed.
- Prioritize reusable classes over inline styles.

## Discoveries
- TinyMCE CDN loaded in `includes/header.php` for all admin pages.
- Distribution tables: `distribution_platforms`, `game_distribution_stats`, `campaigns`, `campaign_metrics`.
- Future integration tables: `distribution_integrations`, `distribution_game_links`, `distribution_sync_logs`.
- Migration list in `includes/db.php` lines 141-178; `dbInit()` at line 181.
- Latest migration 035 exists, 036 now added.
- `distribution.php` processes POST actions at top; HTML queries run after.
- `getDistributionPlatforms()` in `includes/functions.php` returns platform rows.
- Google Play API covers publishing, reviews, purchases/subscriptions, voided purchases, vitals.
- `$youtubeUrl` and `$twitchUrl` warnings remain in `index.php` (frontend only).

## Accomplished
### Completed
- Fixed syntax error in `admin/games.php` line 493 (orphaned JS/PHP block).
- All 28 admin PHP files pass lint.
- Integrated TinyMCE WYSIWYG in `admin/games.php` (description) and `admin/blog.php` (content).
- Smoke tests pass 16/16 after every change.
- Created CSS utility classes in `admin.css`: `.form-card`, `.alert-static`, `.form-inline`, `.label-checkbox`, `.perm-grid`, `.perm-tag`, `.btn-toggle`, `.report-mono`, `.card-no-pad`, `.badge-muted`, `.text-cyan`/`.text-danger-muted`/`.text-gold-muted`/`.text-success-muted`/`.text-fg`, `.repair-actions`, `.card-danger`, `.btn-danger-fill`.
- Replaced inline styles in `users.php`, `roles.php`, `levels.php`, `user-edit.php`, `repair.php`.
- Removed all `<style>.hidden{display:none}</style>` blocks from admin pages.
- Created migration 036 (`add_distribution_integration_hub`): tables `distribution_integrations`, `distribution_game_links`, `distribution_sync_logs` with Play Store seed.
- Added migration 036 to `getMigrationList()` in `includes/db.php`.
- Added integration hub UI to `admin/distribution.php`: integrations CRUD, game links CRUD, sync logs viewer.

### In Progress
- Conduct full QA validation of material changes.

### Blocked
- (none)

## Relevant files / directories
- `admin/games.php`: button markup, modal markup, TinyMCE init.
- `admin/blog.php`: TinyMCE init for content field.
- `admin/users.php`: CSS classes applied, hidden style removed.
- `admin/roles.php`: CSS classes applied, hidden style removed.
- `admin/levels.php`: CSS classes applied, hidden style removed.
- `admin/user-edit.php`: CSS classes applied, hidden style removed.
- `admin/repair.php`: CSS classes applied, diagnostic UI standardized.
- `admin/distribution.php`: Play Store hub with integrations, game links, sync logs.
- `assets/css/admin.css`: all new utility classes.
- `includes/header.php`: TinyMCE CDN script inclusion.
- `includes/migrations.php`: migration definitions (1-36).
- `includes/db.php`: `getMigrationList()` at line 141, `dbInit()` at line 181.
- `scripts/smoke_test.php`: smoke test runner.
- `docker/docker-compose.yml`: Docker compose file.
