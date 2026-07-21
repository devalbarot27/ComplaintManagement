<?php
session_start();

include 'pdo_obconn.php';
include 'includes/password_reset_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

/**
 * Resolve a safe same-app redirect target from an allowlist only.
 */
function change_password_resolve_redirect(string $redirect): string
{
    $redirect = trim($redirect);
    if ($redirect === '') {
        return 'index.php';
    }

    if (
        preg_match('#^https?://#i', $redirect)
        || str_starts_with($redirect, '//')
        || str_contains($redirect, '\\')
        || str_contains($redirect, '..')
        || str_contains($redirect, 'change_password.php')
    ) {
        return 'index.php';
    }

    $path = parse_url($redirect, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return 'index.php';
    }

    $path = ltrim($path, '/');
    // Strip optional app folder prefix (e.g. ComplaintManagement/).
    if (str_contains($path, '/')) {
        $path = basename($path);
    }

    $allowedPages = [
        'index.php',
        'dashboard_data.php',
        'installed_base.php',
        'service_log.php',
        'spare_parts_consumption.php',
        'new_complaint.php',
        'dse_lse_complaint_list.php',
        'recent_orders.php',
        'users.php',
        'roles.php',
        'permissions.php',
        'assign_permissions.php',
        'complaint_categories.php',
        'notifications.php',
        'orderbooking.php',
    ];

    foreach ($allowedPages as $allowedPage) {
        if ($allowedPage === $path) {
            return $allowedPage;
        }
    }

    return 'index.php';
}

$redirect = change_password_resolve_redirect((string) ($_POST['redirect_to'] ?? ''));

$username = trim((string) ($_SESSION['usr_name'] ?? ''));
if ($username === '') {
    header('Location: login.php');
    exit;
}

$result = change_password_process(
    $obconn,
    $username,
    (string) ($_POST['current_password'] ?? ''),
    (string) ($_POST['new_password'] ?? ''),
    (string) ($_POST['confirm_password'] ?? '')
);

if ($result['success']) {
    $_SESSION['success_message'] = $result['message'];
} else {
    $_SESSION['error_message'] = $result['error'] ?? 'Failed to change password.';
    $_SESSION['open_change_password_modal'] = true;
}

// Emit Location using only allowlisted string literals (open-redirect safe).
switch ($redirect) {
    case 'dashboard_data.php':
        header('Location: dashboard_data.php');
        break;
    case 'installed_base.php':
        header('Location: installed_base.php');
        break;
    case 'service_log.php':
        header('Location: service_log.php');
        break;
    case 'spare_parts_consumption.php':
        header('Location: spare_parts_consumption.php');
        break;
    case 'new_complaint.php':
        header('Location: new_complaint.php');
        break;
    case 'dse_lse_complaint_list.php':
        header('Location: dse_lse_complaint_list.php');
        break;
    case 'recent_orders.php':
        header('Location: recent_orders.php');
        break;
    case 'users.php':
        header('Location: users.php');
        break;
    case 'roles.php':
        header('Location: roles.php');
        break;
    case 'permissions.php':
        header('Location: permissions.php');
        break;
    case 'assign_permissions.php':
        header('Location: assign_permissions.php');
        break;
    case 'complaint_categories.php':
        header('Location: complaint_categories.php');
        break;
    case 'notifications.php':
        header('Location: notifications.php');
        break;
    case 'orderbooking.php':
        header('Location: orderbooking.php');
        break;
    default:
        header('Location: index.php');
        break;
}
exit;
