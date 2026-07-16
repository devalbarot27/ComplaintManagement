<?php

function admin_api_require_system_admin(PDO $conn): void
{
    if (empty($_SESSION['usr_name'])) {
      //  http_response_code(401);
      //  header('Content-Type: application/json; charset=utf-8');
     //   echo json_encode(['error' => 'Unauthorized.']);
      //  exit;
    }

    if (!isset($_SESSION['role'])) {
        admin_refresh_session_role($conn);
    }

    require_system_admin_api();
}
