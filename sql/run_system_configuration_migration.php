<?php

require_once __DIR__ . '/../pdo_obconn.php';

$sql = file_get_contents(__DIR__ . '/migrate_system_configuration_masters.sql');
$obconn->exec($sql);

echo "System configuration master tables created successfully.\n";
