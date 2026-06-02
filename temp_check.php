<?php
require '/var/www/html/config.php';
\ = getDB();
\ = \->query("SELECT DISTINCT engine FROM games WHERE engine NOT IN (SELECT name FROM engines)")->fetchAll(PDO::FETCH_COLUMN);
echo "Mismatched engines in games:\n";
foreach (\ as \) echo "  - \\n";
echo "\nEngines:\n";
\ = \->query("SELECT name, active FROM engines")->fetchAll(PDO::FETCH_ASSOC);
foreach (\ as \) echo "  - " . \['name'] . " (active=" . \['active'] . ")\n";
echo "\nGames:\n";
\ = \->query("SELECT id, title, engine FROM games ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach (\ as \) echo "  #" . \['id'] . " " . \['title'] . " -> \"" . \['engine'] . "\"\n";
