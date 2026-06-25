<?php

const SYSTEM_ADMIN_ROLE = 6;
const DEALER_USER_ROLE = 1;

function admin_refresh_session_role(PDO $conn): void
{
    $username = trim((string) ($_SESSION['usr_name'] ?? ''));
    if ($username === '') {
        $_SESSION['role'] = 0;
        return;
    }

    $stmt = $conn->prepare('
        SELECT role
        FROM user_master
        WHERE TRIM(username) = :username
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':username', $username);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['role'] = $row ? (int) $row['role'] : 0;
}

function current_user_role(): int
{
    return (int) ($_SESSION['role'] ?? 0);
}

function is_system_admin(): bool
{
    return current_user_role() === SYSTEM_ADMIN_ROLE;
}

function is_dealer_user(): bool
{
    return current_user_role() === DEALER_USER_ROLE;
}

function require_system_admin(?PDO $conn = null): void
{
    if ($conn !== null && !isset($_SESSION['role'])) {
        admin_refresh_session_role($conn);
    }

    if (!is_system_admin()) {
        $_SESSION['error_message'] = 'Access denied. System Admin privileges required.';
        header('Location: dashboard.php');
        exit;
    }
}

function require_system_admin_api(): void
{
    if (!is_system_admin()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Access denied. System Admin privileges required.']);
        exit;
    }
}
