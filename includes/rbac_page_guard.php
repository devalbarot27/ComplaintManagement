<?php

require_once __DIR__ . '/admin_access_helpers.php';
require_once __DIR__ . '/rbac_access_helpers.php';

if (isset($obconn)) {
    if (!isset($_SESSION['role'])) {
        admin_refresh_session_role($obconn);
    }
    rbac_require_page_access($obconn);
}
