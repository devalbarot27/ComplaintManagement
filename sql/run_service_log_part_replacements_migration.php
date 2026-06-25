<?php
require_once dirname(__DIR__) . '/pdo_obconn.php';

$sql = file_get_contents(__DIR__ . '/migrate_service_log_part_replacements.sql');
$obconn->exec($sql);

echo "service_log_part_replacements migration completed.\n";
