<?php

require_once dirname(__DIR__) . '/includes/login_helpers.php';
login_start_php_session();

if (!empty($_SESSION['usr_name'])) {
    login_destroy_session();
}

http_response_code(204);