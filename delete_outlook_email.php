<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/outlook_email_helpers.php';

require_system_admin($obconn);
outlook_email_ensure_schema($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid record.';
    header('Location: outlook_emails.php');
    exit;
}

try {
    if (!outlook_email_get_by_id($obconn, $id)) {
        $_SESSION['error_message'] = 'Record not found or already deleted.';
        header('Location: outlook_emails.php');
        exit;
    }

    outlook_email_soft_delete($obconn, $id);
    $_SESSION['success_message'] = 'Outlook email deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete Outlook email.';
}

header('Location: outlook_emails.php');
exit;
