<?php

require_once __DIR__ . '/../pdo_obconn.php';

$sql = file_get_contents(__DIR__ . '/migrate_complaint_category_fields.sql');
$obconn->exec($sql);

echo "Complaint category fields added to complaints table successfully.\n";
