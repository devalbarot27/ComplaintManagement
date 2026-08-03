<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/module_create_guards/part_replaced_create_guard.php';

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_POST['submit_part_replaced'])
    && (int) ($_POST['record_id'] ?? 0) === 0
) {
    $createGuardError = part_replaced_enforce_create_request($_POST);
    if ($createGuardError !== null) {
        $_SESSION['error_message'] = $createGuardError;
        unset($_POST['submit_part_replaced']);
    }
}

$scmType = 'part_replaced';
require __DIR__ . '/includes/system_config_master_page.php';
