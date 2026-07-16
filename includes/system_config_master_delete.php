<?php

if (!isset($scmType) || $scmType === '') {
    die('System configuration page type is not defined.');
}

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/system_config_master_helpers.php';

require_system_admin($obconn);

$config = scm_config($scmType);
$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid record.';
    header('Location: ' . $config['page']);
    exit;
}

try {
    if (!scm_get_by_id($obconn, $scmType, $id)) {
        $_SESSION['error_message'] = $config['label'] . ' not found or already deleted.';
        header('Location: ' . $config['page']);
        exit;
    }

    scm_soft_delete($obconn, $scmType, $id);
    $_SESSION['success_message'] = $config['label'] . ' deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete ' . strtolower($config['label']) . '.';
}

header('Location: ' . $config['page']);
exit;
