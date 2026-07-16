<?php
const DEALER_USER_ROLE = 1;
const DEALER_ENGINEER_USER_ROLE = 2;
const ELGI_ENGINEER_USER_ROLE = 3;
const SALES_COORDINATOR_USER_ROLE = 4;
const MANAGEMENT_USER_ROLE = 5;
const SYSTEM_ADMIN_ROLE = 6;    
const CCS_ADMIN_ROLE = 7;       // CCS Admin

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

function admin_ensure_session_role(PDO $conn): void
{
    if (!isset($_SESSION['role']) || current_user_role() <= 0) {
        admin_refresh_session_role($conn);
    }
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
function is_dealer_engineer_user(): bool
{
    return current_user_role() === DEALER_ENGINEER_USER_ROLE;
}
function is_elgi_engineer_user(): bool
{
    return current_user_role() === ELGI_ENGINEER_USER_ROLE;
}
function is_sales_coordinator_user(): bool
{
    return current_user_role() === SALES_COORDINATOR_USER_ROLE;
}
function is_management_user(): bool
{
    return current_user_role() === MANAGEMENT_USER_ROLE;
}
function is_ccs_admin_user(): bool
{
    return current_user_role() === CCS_ADMIN_ROLE;
}   

function require_system_admin(?PDO $conn = null): void
{
    if ($conn !== null) {
        admin_ensure_session_role($conn);
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