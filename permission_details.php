<?php
session_start();
include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/permission_helpers.php';

require_system_admin($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid permission record.');
}

$record = permission_get_by_id($obconn, $id);

if (!$record) {
    die('Permission not found.');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permission Details #<?php echo (int) $record['id']; ?></title>
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
                    <h5 class="mb-1">Permission #<?php echo (int) $record['id']; ?></h5>
                </div>
                <div>
                    <a href="permissions.php" class="btn btn-light border">Back to Permission List</a>
                </div>
            </div>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title"><?php echo htmlspecialchars($record['permission_name']); ?></div>
                </div>
                <div class="p-3">
                    <div class="row g-3">
                        <div class="col-md-6"><strong>Module:</strong><br><?php echo htmlspecialchars($record['module_name']); ?></div>
                        <div class="col-md-6"><strong>Permission Slug:</strong><br><?php echo htmlspecialchars($record['permission_slug']); ?></div>
                        <div class="col-12"><strong>Description:</strong><br><?php echo htmlspecialchars(rbac_display_value($record['description'])); ?></div>
                        <div class="col-md-6"><strong>Created By:</strong><br><?php echo htmlspecialchars(rbac_display_value($record['created_by'])); ?></div>
                        <div class="col-md-6"><strong>Created At:</strong><br><?php echo rbac_format_datetime($record['created_at']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
