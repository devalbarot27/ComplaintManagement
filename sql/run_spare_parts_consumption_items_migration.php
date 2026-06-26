<?php
require_once dirname(__DIR__) . '/pdo_obconn.php';

$sql = file_get_contents(__DIR__ . '/migrate_spare_parts_consumption_items.sql');
$obconn->exec($sql);

echo "spare_parts_consumption_items migration completed.\n";
