<?php
require '/var/www/html/config.php';
$db = getDB();
$db->exec('DELETE FROM schema_version WHERE version IN (45, 46)');
echo "Removed failed migrations\n";
$r = $db->query('SELECT MAX(version) FROM schema_version')->fetchColumn();
echo "schema_version now: $r\n";
// Re-run migrations
dbMigrate($db);
$r = $db->query('SELECT MAX(version) FROM schema_version')->fetchColumn();
echo "schema_version after: $r\n";
