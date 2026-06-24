<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/complaint_category_helpers.php';

require_system_admin($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);
$showDeleted = isset($_GET['deleted']) && $_GET['deleted'] === '1';

if ($id <= 0) {
    die('Invalid record.');
}

$record = $showDeleted
    ? complaint_category_get_deleted_by_id($obconn, $id)
    : complaint_category_get_by_id($obconn, $id);

if (!$record) {
    die('Complaint category not found.');
}

if ($showDeleted) {
    $record['created_by_username'] = null;
    $record['created_by_name'] = null;
    if (!empty($record['created_by'])) {
        $userStmt = $obconn->prepare('SELECT username, name FROM user_master WHERE id = :id LIMIT 1');
        $userStmt->bindValue(':id', (int) $record['created_by'], PDO::PARAM_INT);
        $userStmt->execute();
        $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
        if ($userRow) {
            $record['created_by_username'] = $userRow['username'];
            $record['created_by_name'] = $userRow['name'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Category Details #<?php echo (int) $record['id']; ?></title>
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
                    <h5 class="mb-1">Complaint Category #<?php echo (int) $record['id']; ?></h5>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($showDeleted) { ?>
                    <a href="restore_complaint_category.php?id=<?php echo htmlspecialchars(base64_encode((string) $record['id']), ENT_QUOTES, 'UTF-8'); ?>"
                        class="btn btn-dark"
                        onclick="return confirm('Restore this complaint category?');">
                        <i class="bi bi-arrow-counterclockwise"></i> Restore
                    </a>
                    <?php } ?>
                    <a href="complaint_categories.php" class="btn btn-light border">Back to List</a>
                </div>
            </div>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title"><?php echo htmlspecialchars($record['name']); ?></div>
                </div>
                <div class="p-3">
                    <div class="row g-3">
                        <div class="col-md-6"><strong>Name:</strong><br><?php echo htmlspecialchars($record['name']); ?></div>
                        <div class="col-md-6"><strong>Status:</strong><br><?php echo rbac_status_badge($record['status']); ?></div>
                        <div class="col-md-6"><strong>Created By (User ID):</strong><br><?php echo htmlspecialchars(complaint_category_created_by_label($record)); ?></div>
                        <div class="col-md-6"><strong>Created At:</strong><br><?php echo rbac_format_datetime($record['created_at']); ?></div>
                        <div class="col-md-6"><strong>Updated At:</strong><br><?php echo rbac_format_datetime($record['updated_at']); ?></div>
                        <?php if ($showDeleted) { ?>
                        <div class="col-md-6"><strong>Deleted At:</strong><br><?php echo rbac_format_datetime($record['deleted_at']); ?></div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
