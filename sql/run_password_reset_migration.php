<?php
require_once __DIR__ . '/../pdo_obconn.php';

$sql = file_get_contents(__DIR__ . '/password_reset_schema.sql');
$obconn->exec($sql);
echo "Password reset schema created on complaint_management.\n";
