<?php
require_once __DIR__ . '/../config.php';
$db = getDB();
if ($db) {
    $v = $db->query("SELECT MAX(version) FROM schema_version")->fetchColumn();
    echo "OK: schema_version = $v\n";
    $cols = $db->query("PRAGMA table_info(games)")->fetchAll(PDO::FETCH_COLUMN, 1);
    echo "games columns: " . implode(', ', $cols) . "\n";
} else {
    echo "FAIL: no DB\n";
}
