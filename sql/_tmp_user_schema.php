<?php
require_once dirname(__DIR__) . '/pdo_obconn.php';
foreach (['dpconn' => $dpconn, 'obconn' => $obconn] as $label => $conn) {
    echo "=== $label ===" . PHP_EOL;
    $stmt = $conn->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename ILIKE '%user%' ORDER BY tablename");
    foreach ($stmt as $row) {
        echo $row['tablename'] . PHP_EOL;
    }
}
