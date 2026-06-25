<?php
require_once __DIR__ . '/../pdo_obconn.php';

$sql = file_get_contents(__DIR__ . '/migrate_complaint_closure_fields.sql');
$obconn->exec($sql);
echo "Complaint closure fields migration completed successfully.\n";
