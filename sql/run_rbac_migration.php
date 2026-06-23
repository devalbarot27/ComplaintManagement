<?php
require_once __DIR__ . '/../pdo_obconn.php';

$sql = file_get_contents(__DIR__ . '/rbac_schema.sql');
$obconn->exec($sql);
echo "RBAC schema created successfully.\n";
