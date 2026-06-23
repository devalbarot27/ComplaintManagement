<?php
require __DIR__ . '/pdo_obconn.php';
$tables = $obconn->query("
    SELECT table_name FROM information_schema.tables
    WHERE table_schema = 'public' AND table_name LIKE 'complaint%'
    ORDER BY table_name
")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    echo $table . PHP_EOL;
    $cols = $obconn->query("
        SELECT column_name FROM information_schema.columns
        WHERE table_name = '$table' ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_COLUMN);
    echo '  ' . implode(', ', $cols) . PHP_EOL;
}
