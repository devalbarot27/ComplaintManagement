<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/cdoc_helpers.php';
cdoc_ensure_schema($obconn);
if (empty($_SESSION['usr_name'])) {
    header('Location: login.php');
    exit;
}
require_once 'includes/rbac_page_guard.php';

$id = cdoc_decoded_id($_GET['id'] ?? '');
$mode = strtolower(trim((string) ($_GET['mode'] ?? 'download')));
if ($mode !== 'inline') {
    $mode = 'download';
}

$cdocPermissions = cdoc_action_permissions($obconn);
$document = $id > 0 ? cdoc_find_by_id($obconn, $id) : null;

if (!$document || !cdoc_user_can_access_document($document, $cdocPermissions)) {
    http_response_code(404);
    echo 'Document not found or is not available.';
    exit;
}

$path = cdoc_file_path($document);
if ($path === null) {
    http_response_code(404);
    echo 'The document file is no longer available.';
    exit;
}

$downloadName = basename((string) ($document['original_filename'] ?? ''));
if ($downloadName === '') {
    $downloadName = 'document.' . strtolower((string) ($document['file_extension'] ?? 'bin'));
}

$mime = trim((string) ($document['file_mime'] ?? ''));
if ($mime === '') {
    $mime = 'application/octet-stream';
}

$dispositionType = $mode === 'inline' ? 'inline' : 'attachment';
$encodedName = rawurlencode($downloadName);

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: ' . $dispositionType . '; filename="' . str_replace('"', '', $downloadName) . '"; filename*=UTF-8\'\'' . $encodedName);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($path);
exit;
