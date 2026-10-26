<?php

if (!isset($obconn) || !($obconn instanceof PDO)) {
    return;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dealer - Complaint Approvals</title>
    <?php include dirname(__DIR__) . '/header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="css/datatable_custom.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include dirname(__DIR__) . '/sidebar.php'; ?>

        <div class="content">
            <?php if (isset($_SESSION['success_message'])) { ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars((string) $_SESSION['success_message'], ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); } ?>

            <?php if (isset($_SESSION['error_message'])) { ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars((string) $_SESSION['error_message'], ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); } ?>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title">Complaint Approvals</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="complaintApprovalsTable">
                        <thead>
                            <tr>
                                <th>Complaint</th>
                                <th>FAB Number</th>
                                <th>Customer</th>
                                <th>Complaint Status</th>
                                <th>Approval Status</th>
                                <th>Vayu Engineer</th>
                                <th>Requested On</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="js/complaint_closure_approvals.js"></script>
</body>

</html>
