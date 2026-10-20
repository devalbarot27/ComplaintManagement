<?php
session_start();

include __DIR__ . '/pdo_obconn.php';
require_once __DIR__ . '/includes/rbac_page_guard.php';
require_once __DIR__ . '/includes/service_log_helpers.php';
require_once __DIR__ . '/includes/after_market_access_helpers.php';

if (empty($_SESSION['usr_name'])) {
    header('Location: login.php');
    exit;
}

$attachmentId = (int) ($_GET['id'] ?? 0);
if ($attachmentId <= 0) {
    http_response_code(404);
    echo 'Attachment not found.';
    exit;
}

service_log_ensure_attachment_schema($obconn);
$stmt = $obconn->prepare('
    SELECT id, service_log_id, stored_name, original_name
    FROM service_log_attachments
    WHERE id = :id
    LIMIT 1
');
$stmt->bindValue(':id', $attachmentId, PDO::PARAM_INT);
$stmt->execute();
$attachment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attachment) {
    http_response_code(404);
    echo 'Attachment not found.';
    exit;
}

$serviceLogId = (int) ($attachment['service_log_id'] ?? 0);
if (!after_market_user_can_access_record($obconn, 'service_logs', $serviceLogId)) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$storedName = basename((string) ($attachment['stored_name'] ?? ''));
$originalName = basename((string) ($attachment['original_name'] ?? 'attachment'));
if ($originalName === '' || $originalName === '.' || $originalName === '..') {
    $originalName = 'attachment';
}

$path = service_log_attachment_upload_dir() . DIRECTORY_SEPARATOR . $storedName;
if ($storedName === '' || !is_file($path)) {
    http_response_code(404);
    echo 'Attachment not found.';
    exit;
}

$extension = strtolower((string) pathinfo($storedName, PATHINFO_EXTENSION));
$mimeTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$mime = $mimeTypes[$extension] ?? 'application/octet-stream';
$download = (string) ($_GET['download'] ?? '') === '1';
$disposition = $download ? 'attachment' : 'inline';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
header(
    'Content-Disposition: ' . $disposition . '; filename="'
    . str_replace(['"', "\r", "\n"], '', $originalName) . '"'
);
readfile($path);
exit;