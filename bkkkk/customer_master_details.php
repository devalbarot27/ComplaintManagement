<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/customer_master_helpers.php';
include 'includes/contact_helpers.php';
require_once __DIR__ . '/includes/rbac_access_helpers.php';

admin_ensure_session_role($obconn);
customer_master_ensure_schema($obconn);
customer_master_ensure_rbac($obconn);
contact_ensure_schema($obconn);
contact_ensure_rbac($obconn);

if (!customer_master_action_permissions($obconn)['view']) {
    rbac_access_denied_redirect();
}

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid record.');
}

$record = customer_master_get_by_id($obconn, $id);

if (!$record) {
    die('Customer not found.');
}

if (!customer_master_user_can_access_record($obconn, $record)) {
    rbac_access_denied_redirect();
}

$contacts = contact_list_by_customer_id($obconn, $id);
$contactPermissions = contact_action_permissions($obconn);
$canAddContact = $contactPermissions['add'];
$isDealerUser = is_dealer_user();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Master Details #<?php echo htmlspecialchars((string) (int) $record['id'], ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include 'header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">Customer #<?php echo htmlspecialchars((string) (int) $record['id'], ENT_QUOTES, 'UTF-8'); ?></h5>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($canAddContact) { ?>
                    <a href="contact.php?open_form=1&customer_id=<?php echo (int) $record['id']; ?>"
                        class="btn btn-complaint-primary">
                        <i class="bi bi-person-plus"></i> Add Contact
                    </a>
                    <?php } ?>
                    <a href="customer_master.php" class="btn btn-light border">Back to List</a>
                </div>
            </div>

            <div class="booking-card mb-3">
                <div class="p-3">
                    <div class="row g-3">
                        <div class="col-md-4"><strong>Customer Name:</strong><br><?php echo htmlspecialchars((string) ($record['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>Email:</strong><br><?php echo htmlspecialchars((string) ($record['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>Mobile:</strong><br><?php echo htmlspecialchars((string) ($record['mobile'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if (!$isDealerUser) { ?>
                        <div class="col-md-4"><strong>Dealer Name:</strong><br><?php echo htmlspecialchars(customer_master_dealer_display_label($record), ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php } ?>
                        <div class="col-md-4"><strong>GST Number:</strong><br><?php echo htmlspecialchars(trim((string) ($record['gst_number'] ?? '')) !== '' ? (string) $record['gst_number'] : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>PAN Number:</strong><br><?php echo htmlspecialchars(trim((string) ($record['pan_number'] ?? '')) !== '' ? (string) $record['pan_number'] : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>Pincode:</strong><br><?php echo htmlspecialchars((string) ($record['pincode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>Street 1:</strong><br><?php echo htmlspecialchars((string) ($record['street_1'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>Street 2:</strong><br><?php echo htmlspecialchars((string) ($record['street_2'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>City:</strong><br><?php echo htmlspecialchars((string) ($record['city'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>District:</strong><br><?php echo htmlspecialchars((string) ($record['district'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>State:</strong><br><?php echo htmlspecialchars((string) ($record['state'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>Added By:</strong><br><?php echo htmlspecialchars(customer_master_created_by_label($record), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>Created At:</strong><br><?php echo htmlspecialchars(rbac_format_datetime($record['created_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>Contacts:</strong><br><?php echo count($contacts); ?></div>
                    </div>
                </div>
            </div>

            <div class="booking-card">
                <div class="booking-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="booking-title">Contact List</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100">
                        <thead>
                            <tr>
                                <th width="8%">ID</th>
                                <th width="16%">First Name</th>
                                <th width="16%">Last Name</th>
                                <th width="22%">Email</th>
                                <th width="14%">Mobile</th>
                                <th width="14%">Created At</th>
                                <th width="10%">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($contacts)) { ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No contacts found for this customer.</td>
                            </tr>
                            <?php } else { ?>
                            <?php foreach ($contacts as $contact) { ?>
                            <tr>
                                <td>#<?php echo (int) $contact['id']; ?></td>
                                <td><?php echo htmlspecialchars((string) ($contact['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($contact['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($contact['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($contact['mobile'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(rbac_format_datetime($contact['created_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo contact_entry_actions((int) $contact['id'], $contactPermissions); ?></td>
                            </tr>
                            <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
