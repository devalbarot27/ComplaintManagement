<?php
session_start();
include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/user_helpers.php';

require_system_admin($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid user record.';
    header('Location: users.php');
    exit;
}

try {
    if (!user_get_by_id($obconn, $id)) {
        $_SESSION['error_message'] = 'User not found or already deleted.';
        header('Location: users.php');
        exit;
    }

    require_once __DIR__ . '/includes/login_helpers.php';
    login_ensure_session_version_column($obconn);

    $stmt = $obconn->prepare('
        UPDATE user_master
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP,
            session_version = COALESCE(session_version, 1) + 1
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $_SESSION['success_message'] = 'User deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete user.';
}

header('Location: users.php');
exit;