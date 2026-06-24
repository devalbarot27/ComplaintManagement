<?php

require_once __DIR__ . '/../pdo_obconn.php';

$sql = file_get_contents(__DIR__ . '/migrate_complaint_categories.sql');
$obconn->exec($sql);

echo "Complaint categories table created successfully.\n";
