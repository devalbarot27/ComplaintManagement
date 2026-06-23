<?php
require_once __DIR__ . '/../pdo_obconn.php';
require_once __DIR__ . '/../includes/role_helpers.php';
require_once __DIR__ . '/../includes/module_helpers.php';

$createdBy = 'system';

$count = (int) $obconn->query('SELECT COUNT(*) FROM roles WHERE deleted_at IS NULL')->fetchColumn();
if ($count === 0) {
    role_insert($obconn, [
        'role_name' => 'Admin',
        'description' => 'Full system administrator',
        'status' => 'active',
    ], $createdBy);
    role_insert($obconn, [
        'role_name' => 'Dealer User',
        'description' => 'Standard dealer portal user',
        'status' => 'active',
    ], $createdBy);
    echo "Seeded roles.\n";
}

$moduleCount = (int) $obconn->query('SELECT COUNT(*) FROM modules WHERE deleted_at IS NULL')->fetchColumn();
if ($moduleCount === 0) {
    module_insert($obconn, [
        'module_name' => 'Dashboard',
        'module_slug' => 'dashboard',
        'description' => 'Dashboard overview',
        'status' => 'active',
        'create_default_permissions' => true,
    ], $createdBy);

    module_insert($obconn, [
        'module_name' => 'User Management',
        'module_slug' => 'user-management',
        'description' => 'Manage application users',
        'status' => 'active',
        'create_default_permissions' => true,
    ], $createdBy);

    module_insert($obconn, [
        'module_name' => 'Orders',
        'module_slug' => 'orders',
        'description' => 'Order booking and management',
        'status' => 'active',
        'create_default_permissions' => true,
    ], $createdBy);

    echo "Seeded modules with default permissions.\n";
}

echo "Seed complete.\n";
