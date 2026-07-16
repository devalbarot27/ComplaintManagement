<?php
session_start();

require_once __DIR__ . '/pdo_obconn.php';
require_once __DIR__ . '/includes/admin_access_helpers.php';
require_once __DIR__ . '/includes/rbac_access_helpers.php';

if (!isset($_SESSION['role'])) {
    admin_refresh_session_role($obconn);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <?php include 'header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <div class="booking-card">
                <div class="p-5 text-center">
                    <div class="mb-3">
                        <i class="bi bi-shield-exclamation" style="font-size: 3rem; color: #dc3545;"></i>
                    </div>
                    <h4 class="mb-2">Access Denied</h4>
                    <p class="text-muted mb-4">
                        You do not have permission to access this module. Please contact your administrator
                        if you believe this is an error.
                    </p>
                    <a href="dashboard.php" class="btn btn-dark">
                        <i class="bi bi-house"></i> Go to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
