<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/pdo_obconn.php';
require_once __DIR__ . '/includes/admin_access_helpers.php';
admin_refresh_session_role($obconn);
require_once __DIR__ . '/includes/rbac_access_helpers.php';
rbac_require_page_access($obconn);
/*
$username = $_SESSION['usr_name'];

$getCustomerName = $obconn->prepare("SELECT customer_number FROM user_master WHERE username=:username limit 1");
$getCustomerName->execute([':username' => $username]);
$fetchCustomer = $getCustomerName->fetch(PDO::FETCH_ASSOC);

if ($fetchCustomer && !empty(trim($fetchCustomer['customer_number']))) {
    $_SESSION['customer_number_vayu'] = $fetchCustomer['customer_number'];
} else {
    session_unset();
    $_SESSION['error'] = "Customer code is not mapped";
    header("Location: /vayupower/login.php");
    exit;
}
*/

if (empty($_SESSION['usr_name'])) {
    header('Location: login.php');
    exit;
}





/*
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usr_name'])) {
?>
    <div style="
        max-width:700px;
        margin:50px auto;
        padding:20px;
        border:1px solid #dc3545;
        border-radius:5px;
        background:#fff5f5;
        text-align:center;
        font-family:Verdana, Arial, sans-serif;
    ">
        <h3 style="color:#dc3545; margin:0 0 10px;">
            Unauthorized Access / Session Expired
        </h3>

        <p style="margin:0; color:#333;">
            Please
            <a href="https://dp.elgi.com/index.php">
                login
            </a>
            using a valid User ID and Password.
        </p>
    </div>
<?php
    exit;
}
*/
