<?php
require '/var/www/html/config.php';
$db = getDB();
echo "schema_version: " . $db->query('SELECT MAX(version) FROM schema_version')->fetchColumn() . "\n";
echo "newsletter_subscribers exists: ";
$tbl = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='newsletter_subscribers'")->fetchColumn();
echo ($tbl ? 'yes' : 'no') . "\n";
echo "newsletter_campaigns exists: ";
$tbl2 = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='newsletter_campaigns'")->fetchColumn();
echo ($tbl2 ? 'yes' : 'no') . "\n";
