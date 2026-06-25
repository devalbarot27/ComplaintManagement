<?php
session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/user_helpers.php';

require_system_admin($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid user record.');
}

$record = user_get_by_id($obconn, $id);

if (!$record) {
    die('User not found.');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details #<?php echo (int) $record['id']; ?></title>
    <?php include 'header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">User #<?php echo (int) $record['id']; ?></h5>
                    <span class="badge border border-dark text-dark">
                        <?php echo htmlspecialchars(user_role_label($obconn, $record['role'])); ?>
                    </span>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="users.php" class="btn btn-light border">Back to User List</a>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3">
                <div class="card-header bg-white"><strong>Account Information</strong></div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <strong>Role:</strong>
                        <?php echo htmlspecialchars(user_role_label($obconn, $record['role'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Username:</strong>
                        <?php echo htmlspecialchars(user_display_value($record['username'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Name:</strong>
                        <?php echo htmlspecialchars(user_display_value($record['name'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Email:</strong>
                        <?php echo htmlspecialchars(user_display_value($record['email'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Mobile Number:</strong>
                        <?php echo htmlspecialchars(user_display_value($record['mobile_number'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Created By:</strong>
                        <?php echo htmlspecialchars(user_display_value($record['created_by'])); ?>
                    </div>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3">
                <div class="card-header bg-white"><strong>Activity</strong></div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <strong>Created At:</strong>
                        <?php echo user_format_datetime($record['created_at']); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Updated At:</strong>
                        <?php echo user_format_datetime($record['updated_at']); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Last Login:</strong>
                        <?php echo user_format_datetime($record['last_login_at']); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>