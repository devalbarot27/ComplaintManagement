<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/outlook_email_helpers.php';

header('Content-Type: application/json; charset=utf-8');

admin_api_require_system_admin($obconn);
outlook_email_ensure_schema($obconn);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode([
        'valid' => false,
        'errors' => [
            'outlook_email' => ['Method not allowed. Use POST.'],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$recordId = (int) ($_POST['record_id'] ?? 0);
$email = trim((string) ($_POST['outlook_email'] ?? ''));
$errors = [];

if ($email !== '' && outlook_email_address_taken($obconn, $email, $recordId)) {
    $errors['outlook_email'] = ['This Outlook email address is already assigned.'];
}

echo json_encode([
    'valid' => empty($errors),
    'errors' => $errors,
], JSON_UNESCAPED_UNICODE);
