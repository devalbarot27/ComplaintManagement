<?php
session_start();

include 'pdo_obconn.php';
include 'includes/password_reset_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$redirect = trim((string) ($_POST['redirect_to'] ?? ''));
if (
    $redirect === ''
    || preg_match('#^https?://#i', $redirect)
    || str_starts_with($redirect, '//')
    || str_contains($redirect, '\\')
    || str_contains($redirect, 'change_password.php')
    || !preg_match('#^[a-zA-Z0-9_\-./?=&%]+$#', $redirect)
) {
    $redirect = 'index.php';
}

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

header('Location: ' . $redirect);
exit;
