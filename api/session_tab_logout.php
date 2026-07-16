<?php

require_once dirname(__DIR__) . '/includes/login_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['usr_name'])) {
    login_destroy_session();
}

http_response_code(204);