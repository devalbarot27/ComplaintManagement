<?php

require_once __DIR__ . '/admin_access_helpers.php';
require_once __DIR__ . '/rbac_access_helpers.php';
require_once __DIR__ . '/login_helpers.php';

if (isset($obconn)) {
    login_enforce_session_version($obconn);
    admin_ensure_session_role($obconn);
    rbac_require_page_access($obconn);
}