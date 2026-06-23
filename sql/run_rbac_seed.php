<?php
require_once __DIR__ . '/../pdo_obconn.php';

$sql = file_get_contents(__DIR__ . '/rbac_seed_data.sql');
$obconn->exec($sql);

echo "RBAC seed data inserted on complaint_management.\n";
