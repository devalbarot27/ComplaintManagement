<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usr_name'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['role'])) {
    require_once __DIR__ . '/pdo_obconn.php';
    require_once __DIR__ . '/includes/admin_access_helpers.php';
    admin_refresh_session_role($obconn);
}

require_once __DIR__ . '/pdo_obconn.php';
require_once __DIR__ . '/includes/admin_access_helpers.php';
require_once __DIR__ . '/includes/rbac_access_helpers.php';
rbac_require_page_access($obconn);
