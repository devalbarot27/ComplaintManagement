<?php
require_once __DIR__ . '/../pdo_obconn.php';
require_once __DIR__ . '/../includes/module_helpers.php';
require_once __DIR__ . '/../includes/permission_helpers.php';

$createdBy = 'system';

$setup = [
    [
        'module_name' => 'Dashboard',
        'module_slug' => 'dashboard',
        'description' => 'Dashboard overview',
        'permissions' => [
            ['permission_name' => 'View', 'permission_slug' => 'view', 'description' => 'View dashboard'],
        ],
    ],
    [
        'module_name' => 'Installed Base Capture',
        'module_slug' => 'installed-base-capture',
        'description' => 'Installed base capture management',
        'permissions' => [
            ['permission_name' => 'View', 'permission_slug' => 'view', 'description' => 'View installed base records'],
            ['permission_name' => 'Add', 'permission_slug' => 'add', 'description' => 'Add installed base records'],
            ['permission_name' => 'Edit', 'permission_slug' => 'edit', 'description' => 'Edit installed base records'],
            ['permission_name' => 'Delete', 'permission_slug' => 'delete', 'description' => 'Delete installed base records'],
        ],
    ],
    [
        'module_name' => 'Service Log Capture',
        'module_slug' => 'service-log-capture',
        'description' => 'Service log capture management',
        'permissions' => [
            ['permission_name' => 'View', 'permission_slug' => 'view', 'description' => 'View service log records'],
            ['permission_name' => 'Add', 'permission_slug' => 'add', 'description' => 'Add service log records'],
            ['permission_name' => 'Edit', 'permission_slug' => 'edit', 'description' => 'Edit service log records'],
            ['permission_name' => 'Delete', 'permission_slug' => 'delete', 'description' => 'Delete service log records'],
        ],
    ],
    [
        'module_name' => 'Spare Parts Consumption',
        'module_slug' => 'spare-parts-consumption',
        'description' => 'Spare parts consumption management',
        'permissions' => [
            ['permission_name' => 'View', 'permission_slug' => 'view', 'description' => 'View spare parts consumption records'],
            ['permission_name' => 'Add', 'permission_slug' => 'add', 'description' => 'Add spare parts consumption records'],
            ['permission_name' => 'Edit', 'permission_slug' => 'edit', 'description' => 'Edit spare parts consumption records'],
            ['permission_name' => 'Delete', 'permission_slug' => 'delete', 'description' => 'Delete spare parts consumption records'],
        ],
    ],
    [
        'module_name' => 'Complaint Entry',
        'module_slug' => 'complaint-entry',
        'description' => 'Complaint entry management',
        'permissions' => [
            ['permission_name' => 'View', 'permission_slug' => 'view', 'description' => 'View complaints'],
            ['permission_name' => 'Add', 'permission_slug' => 'add', 'description' => 'Add complaints'],
            ['permission_name' => 'Delete', 'permission_slug' => 'delete', 'description' => 'Delete complaints'],
            ['permission_name' => 'Assign Complaint', 'permission_slug' => 'assign-complaint', 'description' => 'Assign complaint'],
            ['permission_name' => 'Complaint Closure', 'permission_slug' => 'complaint-closure', 'description' => 'Close complaint'],
            ['permission_name' => 'Reassign Complaint', 'permission_slug' => 'reassign-complaint', 'description' => 'Reassign complaint'],
        ],
    ],
    [
        'module_name' => 'Assigned Complaint List',
        'module_slug' => 'assigned-complaint-list',
        'description' => 'Assigned complaint list management',
        'permissions' => [
            ['permission_name' => 'View', 'permission_slug' => 'view', 'description' => 'View assigned complaints'],
            ['permission_name' => 'Service Update', 'permission_slug' => 'service-update', 'description' => 'Update service for assigned complaint'],
        ],
    ],
];

function find_module_id_by_slug(PDO $conn, string $slug): ?int
{
    $stmt = $conn->prepare('
        SELECT id
        FROM modules
        WHERE LOWER(TRIM(module_slug)) = LOWER(TRIM(:module_slug))
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':module_slug', $slug);
    $stmt->execute();
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

foreach ($setup as $item) {
    $moduleId = find_module_id_by_slug($obconn, $item['module_slug']);

    if ($moduleId === null) {
        $moduleId = module_insert($obconn, [
            'module_name' => $item['module_name'],
            'module_slug' => $item['module_slug'],
            'description' => $item['description'],
            'status' => 'active',
            'create_default_permissions' => false,
        ], $createdBy);
        echo "Added module: {$item['module_name']}\n";
    } else {
        echo "Module exists: {$item['module_name']}\n";
    }

    $allowedSlugs = [];
    foreach ($item['permissions'] as $permission) {
        $allowedSlugs[] = $permission['permission_slug'];

        if (permission_slug_exists($obconn, $moduleId, $permission['permission_slug'])) {
            echo "  Permission exists: {$permission['permission_name']}\n";
            continue;
        }

        permission_insert($obconn, [
            'module_id' => $moduleId,
            'permission_name' => $permission['permission_name'],
            'permission_slug' => $permission['permission_slug'],
            'description' => $permission['description'],
            'status' => 'active',
        ], $createdBy);
        echo "  Added permission: {$permission['permission_name']}\n";
    }

    if (!empty($allowedSlugs)) {
        $placeholders = [];
        $params = [':module_id' => $moduleId];
        foreach ($allowedSlugs as $index => $slug) {
            $key = ':slug_' . $index;
            $placeholders[] = $key;
            $params[$key] = $slug;
        }

        $sql = '
            UPDATE permissions
            SET deleted_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE module_id = :module_id
              AND deleted_at IS NULL
              AND LOWER(TRIM(permission_slug)) NOT IN (' . implode(', ', $placeholders) . ')
        ';
        $stmt = $obconn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo "  Removed extra permissions: {$stmt->rowCount()}\n";
        }
    }
}

echo "Module and permission setup complete.\n";
