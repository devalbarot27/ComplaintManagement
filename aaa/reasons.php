<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/module_create_guards/reasons_create_guard.php';

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_POST['submit_reason'])
    && (int) ($_POST['record_id'] ?? 0) === 0
) {
    $createGuardError = reasons_enforce_create_request($_POST);
    if ($createGuardError !== null) {
        $_SESSION['error_message'] = $createGuardError;
        unset($_POST['submit_reason']);
    }
}

$scmType = 'reason';
require __DIR__ . '/includes/system_config_master_page.php';