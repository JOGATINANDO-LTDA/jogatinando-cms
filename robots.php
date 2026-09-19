<?php
define('SKIP_INSTALL_CHECK', true);
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

$base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
?>
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /install.php
Disallow: /health.php
Disallow: /cron.php
Disallow: /subscribe.php
Disallow: /unsubscribe.php

Sitemap: <?= $base ?>/sitemap.xml
