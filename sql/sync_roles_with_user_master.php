<?php
require_once __DIR__ . '/../pdo_obconn.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once __DIR__ . '/../includes/role_helpers.php';

$createdBy = 'system';

foreach (user_legacy_role_seed_map() as $roleId => $roleName) {
    $roleId = (int) $roleId;
    $existing = role_get_by_id($obconn, $roleId);

    if ($existing === null) {
        $stmt = $obconn->prepare('
            INSERT INTO roles (id, role_name, description, status, created_by, created_at)
            VALUES (:id, :role_name, :description, :status, :created_by, CURRENT_TIMESTAMP)
        ');
        $stmt->bindValue(':id', $roleId, PDO::PARAM_INT);
        $stmt->bindValue(':role_name', $roleName);
        $stmt->bindValue(':description', $roleName . ' role');
        $stmt->bindValue(':status', 'active');
        $stmt->bindValue(':created_by', $createdBy);
        $stmt->execute();
        echo "Inserted role #{$roleId}: {$roleName}\n";
    } else {
        $stmt = $obconn->prepare('
            UPDATE roles
            SET role_name = :role_name,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND deleted_at IS NULL
        ');
        $stmt->bindValue(':role_name', $roleName);
        $stmt->bindValue(':id', $roleId, PDO::PARAM_INT);
        $stmt->execute();
        echo "Updated role #{$roleId}: {$roleName}\n";
    }
}

$obconn->exec("SELECT setval(pg_get_serial_sequence('roles', 'id'), (SELECT COALESCE(MAX(id), 1) FROM roles))");
echo "Roles synced with user_master.role values.\n";
