<?php
require 'c:/xampp/htdocs/ComplaintManagementLatest/pdo_obconn.php';

echo "Users:\n";
foreach ($obconn->query("SELECT id, username, role FROM user_master WHERE deleted_at IS NULL ORDER BY id") as $r) {
    echo json_encode($r) . PHP_EOL;
}

echo "\nRoles:\n";
foreach ($obconn->query("SELECT id, role_name FROM roles WHERE deleted_at IS NULL ORDER BY id") as $r) {
    echo json_encode($r) . PHP_EOL;
}

echo "\nRole permissions:\n";
$stmt = $obconn->query("
    SELECT rp.role_id, ro.role_name, m.module_slug, p.permission_slug
    FROM role_permissions rp
    JOIN roles ro ON ro.id = rp.role_id
    JOIN permissions p ON p.id = rp.permission_id AND p.deleted_at IS NULL
    JOIN modules m ON m.id = p.module_id AND m.deleted_at IS NULL
    WHERE rp.deleted_at IS NULL
    ORDER BY rp.role_id, m.module_slug, p.permission_slug
");
foreach ($stmt as $r) {
    echo json_encode($r) . PHP_EOL;
}
