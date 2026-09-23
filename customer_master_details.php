<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/customer_master_helpers.php';
include 'includes/contact_helpers.php';
require_once __DIR__ . '/includes/rbac_access_helpers.php';
require_once __DIR__ . '/includes/after_market_access_helpers.php';
require_once __DIR__ . '/includes/service_log_helpers.php';
require_once __DIR__ . '/includes/service_log_draft_helpers.php';
require_once __DIR__ . '/includes/spare_parts_helpers.php';
require_once __DIR__ . '/includes/amc_helpers.php';
require_once __DIR__ . '/includes/complaint_address_helpers.php';
require_once __DIR__ . '/includes/complaint_category_helpers.php';
require_once __DIR__ . '/includes/complaint_datatable_helpers.php';

admin_ensure_session_role($obconn);
customer_master_ensure_schema($obconn);
customer_master_ensure_rbac($obconn);
contact_ensure_schema($obconn);
contact_ensure_rbac($obconn);
installed_base_ensure_schema($obconn);
amc_ensure_schema($obconn);
complaint_ensure_schema($obconn);

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

$installedBaseRecords = installed_base_list_for_customer($obconn, $id);
$serviceLogs = service_log_list_for_customer($obconn, $id);
$sparePartsRecords = spare_parts_list_for_customer($obconn, $id);
$amcContracts = amc_list_for_customer($obconn, $id);
$complaints = complaint_list_for_customer($obconn, $id);

$installedBasePermissions = installed_base_action_permissions($obconn);
$serviceLogPermissions = service_log_action_permissions($obconn);
$sparePartsPermissions = spare_parts_action_permissions($obconn);
$complaintEntryPermissions = complaint_entry_action_permissions($obconn);

$canViewInstalledBase = !empty($installedBasePermissions['view']);
$canViewServiceLog = !empty($serviceLogPermissions['view']);
$canViewSpareParts = !empty($sparePartsPermissions['view']);
$canViewAmc = rbac_user_can($obconn, 'amc', 'view');
$canViewComplaint = !empty($complaintEntryPermissions['view']);

$installedBaseViewPermissions = installed_base_normalize_action_permissions([
    'view' => $canViewInstalledBase,
]);
$serviceLogViewPermissions = [
    'view' => $canViewServiceLog,
];
$sparePartsViewPermissions = [
    'view' => $canViewSpareParts,
];

$installedBaseCount = count($installedBaseRecords);
$serviceLogCount = count($serviceLogs);
$serviceLogDraftCount = 0;
foreach ($serviceLogs as $serviceLogRow) {
    if (service_log_is_draft_value($serviceLogRow['is_draft'] ?? 0)) {
        $serviceLogDraftCount++;
    }
}
$sparePartsCount = count($sparePartsRecords);
$amcCount = count($amcContracts);
$complaintCount = count($complaints);
$contactCount = count($contacts);

$customerMasterEncodeId = static function (int $recordId): string {
    return htmlspecialchars(base64_encode((string) $recordId), ENT_QUOTES, 'UTF-8');
};

$customerMasterRelatedEmpty = static function (string $icon, string $message): void {
    ?>
    <div class="customer-master-related-empty">
        <i class="bi <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?> fs-4 d-block mb-2"></i>
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Master Details #<?php echo htmlspecialchars((string) (int) $record['id'], ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include 'header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .customer-master-related-card .card-header {
            padding: 14px 18px;
        }

        .customer-master-related-card .booking-table {
            min-width: 0;
        }

        .customer-master-related-empty {
            border: 1px dashed #e2e8f0;
            border-radius: 10px;
            padding: 2rem 1rem;
            text-align: center;
            color: #64748b;
            background: #f8fafc;
        }

        .customer-master-related-link {
            color: inherit;
        }

        .customer-master-related-link:hover {
            color: #0d6efd;
        }

        @media (max-width: 768px) {
            .customer-master-related-card .table-responsive {
                overflow: visible;
            }

            .customer-master-related-table thead {
                display: none;
            }

            .customer-master-related-table,
            .customer-master-related-table tbody,
            .customer-master-related-table tr,
            .customer-master-related-table td {
                display: block;
                width: 100%;
            }

            .customer-master-related-table tbody tr {
                border-bottom: 1px solid #e2e8f0;
                padding: 12px 0;
            }

            .customer-master-related-table tbody tr:last-child {
                border-bottom: none;
            }

            .customer-master-related-table tbody td {
                padding: 6px 0;
                border: none;
            }

            .customer-master-related-table tbody td::before {
                content: attr(data-label);
                display: block;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.03em;
                color: #94a3b8;
                margin-bottom: 2px;
            }
        }
    </style>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="mb-2">Customer #<?php echo htmlspecialchars((string) (int) $record['id'], ENT_QUOTES, 'UTF-8'); ?></h5>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php if ($installedBaseCount > 0) { ?>
                        <span class="badge border border-secondary text-secondary">
                            <?php echo (int) $installedBaseCount; ?>
                            installed base<?php echo $installedBaseCount === 1 ? '' : 's'; ?>
                        </span>
                        <?php } ?>
                        <?php if ($serviceLogCount > 0) { ?>
                        <span class="badge border border-secondary text-secondary">
                            <?php echo (int) $serviceLogCount; ?>
                            service log<?php echo $serviceLogCount === 1 ? '' : 's'; ?>
                        </span>
                        <?php } ?>
                        <?php if ($serviceLogDraftCount > 0) { ?>
                        <span class="badge service-log-draft-badge">
                            <?php echo (int) $serviceLogDraftCount; ?>
                            draft<?php echo $serviceLogDraftCount === 1 ? '' : 's'; ?>
                        </span>
                        <?php } ?>
                        <?php if ($sparePartsCount > 0) { ?>
                        <span class="badge border border-secondary text-secondary">
                            <?php echo (int) $sparePartsCount; ?>
                            spare parts record<?php echo $sparePartsCount === 1 ? '' : 's'; ?>
                        </span>
                        <?php } ?>
                        <?php if ($amcCount > 0) { ?>
                        <span class="badge border border-secondary text-secondary">
                            <?php echo (int) $amcCount; ?>
                            AMC<?php echo $amcCount === 1 ? '' : 's'; ?>
                        </span>
                        <?php } ?>
                        <?php if ($complaintCount > 0) { ?>
                        <span class="badge border border-secondary text-secondary">
                            <?php echo (int) $complaintCount; ?>
                            complaint<?php echo $complaintCount === 1 ? '' : 's'; ?>
                        </span>
                        <?php } ?>
                    </div>
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
                        <div class="col-md-4"><strong>Contacts:</strong><br><?php echo (int) $contactCount; ?></div>
                    </div>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3 customer-master-related-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-people text-secondary"></i>
                        <strong>Contact List</strong>
                    </div>
                    <?php if ($contactCount > 0) { ?>
                    <span class="badge border border-secondary text-secondary">
                        <?php echo (int) $contactCount; ?>
                        record<?php echo $contactCount === 1 ? '' : 's'; ?>
                    </span>
                    <?php } ?>
                </div>
                <div class="card-body px-3 pt-3 pb-3">
                    <?php if ($contacts === []) { ?>
                    <?php $customerMasterRelatedEmpty('bi-person-x', 'No contacts found for this customer.'); ?>
                    <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-hover booking-table customer-master-related-table w-100 mb-0">
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
                                <?php foreach ($contacts as $contact) { ?>
                                <tr>
                                    <td data-label="ID">#<?php echo (int) $contact['id']; ?></td>
                                    <td data-label="First Name"><?php echo htmlspecialchars((string) ($contact['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Last Name"><?php echo htmlspecialchars((string) ($contact['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Email"><?php echo htmlspecialchars((string) ($contact['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Mobile"><?php echo htmlspecialchars((string) ($contact['mobile'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Created At"><?php echo htmlspecialchars(rbac_format_datetime($contact['created_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Action"><?php echo contact_entry_actions((int) $contact['id'], $contactPermissions); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3 customer-master-related-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-hdd-stack text-secondary"></i>
                        <strong>Installed Base Capture</strong>
                    </div>
                    <?php if ($installedBaseCount > 0) { ?>
                    <span class="badge border border-secondary text-secondary">
                        <?php echo (int) $installedBaseCount; ?>
                        record<?php echo $installedBaseCount === 1 ? '' : 's'; ?>
                    </span>
                    <?php } ?>
                </div>
                <div class="card-body px-3 pt-3 pb-3">
                    <?php if ($installedBaseRecords === []) { ?>
                    <?php $customerMasterRelatedEmpty('bi-hdd', 'No Installed Base Capture records linked to this customer yet.'); ?>
                    <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-hover booking-table customer-master-related-table w-100 mb-0">
                            <thead>
                                <tr>
                                    <th width="8%">ID</th>
                                    <th width="14%">Fab Number</th>
                                    <th width="18%">Machine Model</th>
                                    <?php if (!$isDealerUser) { ?>
                                    <th width="16%">Dealer Name</th>
                                    <?php } ?>
                                    <th width="14%">Commissioning</th>
                                    <th width="12%">Running Hours</th>
                                    <th width="12%">Created At</th>
                                    <th width="10%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($installedBaseRecords as $installedBaseRow) {
                                    $installedBaseId = (int) ($installedBaseRow['id'] ?? 0);
                                    $fabNumber = installed_base_display_value($installedBaseRow['fab_number'] ?? null);
                                    $machineModel = installed_base_machine_model_label($installedBaseRow);
                                    ?>
                                <tr>
                                    <td data-label="ID">#<?php echo $installedBaseId; ?></td>
                                    <td data-label="Fab Number">
                                        <?php if ($canViewInstalledBase && $installedBaseId > 0) { ?>
                                        <a href="installed_base_details.php?id=<?php echo $customerMasterEncodeId($installedBaseId); ?>"
                                            class="text-primary fw-semibold text-decoration-none customer-master-related-link">
                                            <?php echo htmlspecialchars($fabNumber, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                        <?php } else { ?>
                                        <?php echo htmlspecialchars($fabNumber, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php } ?>
                                    </td>
                                    <td data-label="Machine Model"><?php echo htmlspecialchars($machineModel, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <?php if (!$isDealerUser) { ?>
                                    <td data-label="Dealer Name"><?php echo htmlspecialchars(installed_base_display_value($installedBaseRow['dealer_name'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <?php } ?>
                                    <td data-label="Commissioning"><?php echo htmlspecialchars(installed_base_format_date($installedBaseRow['commissioning_date'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Running Hours"><?php echo htmlspecialchars(installed_base_display_value($installedBaseRow['running_hours'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Created At"><?php echo htmlspecialchars(installed_base_format_datetime($installedBaseRow['created_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Action"><?php echo installed_base_entry_actions($installedBaseId, $installedBaseViewPermissions); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3 customer-master-related-card">
                <div class="card-header bg-white d-flex flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-clipboard-pulse text-secondary"></i>
                        <strong>Service Log Capture</strong>
                    </div>
                    <div class="d-flex justify-content-end align-items-center gap-2 flex-grow-1">
                        <?php if ($serviceLogDraftCount > 0) { ?>
                        <span class="badge service-log-draft-badge">
                            <?php echo (int) $serviceLogDraftCount; ?>
                            draft<?php echo $serviceLogDraftCount === 1 ? '' : 's'; ?>
                        </span>
                        <?php } ?>
                        <?php if ($serviceLogCount > 0) { ?>
                        <span class="badge border border-secondary text-secondary">
                            <?php echo (int) $serviceLogCount; ?>
                            record<?php echo $serviceLogCount === 1 ? '' : 's'; ?>
                        </span>
                        <?php } ?>
                    </div>
                </div>
                <div class="card-body px-3 pt-3 pb-3">
                    <?php if ($serviceLogs === []) { ?>
                    <?php $customerMasterRelatedEmpty('bi-clipboard-x', 'No Service Log Capture records linked to this customer yet.'); ?>
                    <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-hover booking-table customer-master-related-table w-100 mb-0">
                            <thead>
                                <tr>
                                    <th width="10%">ID</th>
                                    <th width="12%">Serial No.</th>
                                    <th width="12%">Fab Number</th>
                                    <th width="16%">Machine Model</th>
                                    <th width="12%">Complaint Category</th>
                                    <th width="14%">Engineer</th>
                                    <th width="12%">Visit Date</th>
                                    <th width="12%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($serviceLogs as $serviceLogRow) {
                                    $serviceLogId = (int) ($serviceLogRow['id'] ?? 0);
                                    $serviceLogIsDraft = service_log_is_draft_value($serviceLogRow['is_draft'] ?? 0);
                                    $installedBaseId = (int) ($serviceLogRow['installed_base_id'] ?? 0);
                                    $fabNumber = service_log_display_value($serviceLogRow['fab_number'] ?? null);
                                    $machineModel = installed_base_machine_model_label([
                                        'machine_model_code' => $serviceLogRow['ib_machine_model_code'] ?? '',
                                        'machine_model' => $serviceLogRow['ib_machine_model'] ?? ($serviceLogRow['machine_model'] ?? ''),
                                    ]);
                                    if ($machineModel === '-') {
                                        $machineModel = service_log_display_value($serviceLogRow['machine_model'] ?? null);
                                    }
                                    ?>
                                <tr class="<?php echo $serviceLogIsDraft ? 'service-log-draft-row' : ''; ?>">
                                    <td data-label="ID"><?php echo service_log_grid_id_cell_html($serviceLogId, $serviceLogIsDraft); ?></td>
                                    <td data-label="Serial No."><?php echo htmlspecialchars(service_log_format_serial_number_for_display($serviceLogRow['serial_number'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Fab Number">
                                        <?php if ($canViewInstalledBase && $installedBaseId > 0) { ?>
                                        <a href="installed_base_details.php?id=<?php echo $customerMasterEncodeId($installedBaseId); ?>"
                                            class="text-primary fw-semibold text-decoration-none">
                                            <?php echo htmlspecialchars($fabNumber, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                        <?php } else { ?>
                                        <?php echo htmlspecialchars($fabNumber, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php } ?>
                                    </td>
                                    <td data-label="Machine Model"><?php echo htmlspecialchars($machineModel, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Complaint Category"><?php echo htmlspecialchars(service_log_display_value($serviceLogRow['warranty_chargeable'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Engineer"><?php echo htmlspecialchars(service_log_display_value($serviceLogRow['engineer_name'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Visit Date"><?php echo htmlspecialchars(service_log_format_date($serviceLogRow['visit_date'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Action"><?php echo service_log_entry_actions($serviceLogId, $serviceLogViewPermissions); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3 customer-master-related-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-gear text-secondary"></i>
                        <strong>Spare Parts Consumption</strong>
                    </div>
                    <?php if ($sparePartsCount > 0) { ?>
                    <span class="badge border border-secondary text-secondary">
                        <?php echo (int) $sparePartsCount; ?>
                        record<?php echo $sparePartsCount === 1 ? '' : 's'; ?>
                    </span>
                    <?php } ?>
                </div>
                <div class="card-body px-3 pt-3 pb-3">
                    <?php if ($sparePartsRecords === []) { ?>
                    <?php $customerMasterRelatedEmpty('bi-inbox', 'No Spare Parts Consumption records linked to this customer yet.'); ?>
                    <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-hover booking-table customer-master-related-table w-100 mb-0">
                            <thead>
                                <tr>
                                    <th width="8%">ID</th>
                                    <th width="12%">Serial No.</th>
                                    <th width="12%">Fab Number</th>
                                    <th width="12%">Date</th>
                                    <th width="12%">Type</th>
                                    <th width="14%">Kit Number</th>
                                    <th width="8%">Qty</th>
                                    <th width="10%">Order Value</th>
                                    <th width="10%">Service Log</th>
                                    <th width="10%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sparePartsRecords as $sparePartsRow) {
                                    $sparePartsId = (int) ($sparePartsRow['id'] ?? 0);
                                    $installedBaseId = (int) ($sparePartsRow['installed_base_id'] ?? 0);
                                    $serviceLogId = (int) ($sparePartsRow['service_log_id'] ?? 0);
                                    $fabNumber = spare_parts_display_value($sparePartsRow['fab_number'] ?? null);
                                    $itemCount = (int) ($sparePartsRow['item_count'] ?? 0);
                                    $firstKit = trim((string) ($sparePartsRow['spare_kit_number'] ?? ''));
                                    $kitDisplay = $firstKit !== ''
                                        ? spare_parts_format_kit_summary($firstKit, $itemCount)
                                        : '-';
                                    ?>
                                <tr>
                                    <td data-label="ID">#<?php echo $sparePartsId; ?></td>
                                    <td data-label="Serial No."><?php echo htmlspecialchars(spare_parts_display_value($sparePartsRow['serial_number'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Fab Number">
                                        <?php if ($canViewInstalledBase && $installedBaseId > 0) { ?>
                                        <a href="installed_base_details.php?id=<?php echo $customerMasterEncodeId($installedBaseId); ?>"
                                            class="text-primary fw-semibold text-decoration-none">
                                            <?php echo htmlspecialchars($fabNumber, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                        <?php } else { ?>
                                        <?php echo htmlspecialchars($fabNumber, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php } ?>
                                    </td>
                                    <td data-label="Date"><?php echo htmlspecialchars(spare_parts_format_date($sparePartsRow['consumption_date'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Type"><?php echo htmlspecialchars(spare_parts_display_value($sparePartsRow['warranty_chargeable'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Kit Number"><?php echo htmlspecialchars($kitDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Qty"><?php echo htmlspecialchars(spare_parts_format_quantity($sparePartsRow['quantity'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Order Value"><?php echo htmlspecialchars(spare_parts_format_currency($sparePartsRow['order_value'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Service Log">
                                        <?php if ($serviceLogId > 0 && $canViewServiceLog) { ?>
                                        <a href="service_log_details.php?id=<?php echo $customerMasterEncodeId($serviceLogId); ?>"
                                            class="text-primary fw-semibold text-decoration-none">
                                            #<?php echo $serviceLogId; ?>
                                        </a>
                                        <?php } elseif ($serviceLogId > 0) { ?>
                                        #<?php echo $serviceLogId; ?>
                                        <?php } else { ?>
                                        -
                                        <?php } ?>
                                    </td>
                                    <td data-label="Action"><?php echo spare_parts_entry_actions($sparePartsId, $sparePartsViewPermissions); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3 customer-master-related-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-text text-secondary"></i>
                        <strong>AMC Details</strong>
                    </div>
                    <?php if ($amcCount > 0) { ?>
                    <span class="badge border border-secondary text-secondary">
                        <?php echo (int) $amcCount; ?>
                        record<?php echo $amcCount === 1 ? '' : 's'; ?>
                    </span>
                    <?php } ?>
                </div>
                <div class="card-body px-3 pt-3 pb-3">
                    <?php if ($amcContracts === []) { ?>
                    <?php $customerMasterRelatedEmpty('bi-file-earmark-x', 'No AMC contracts linked to this customer yet.'); ?>
                    <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-hover booking-table customer-master-related-table w-100 mb-0">
                            <thead>
                                <tr>
                                    <th width="14%">Contract No.</th>
                                    <th width="12%">Fab Number</th>
                                    <th width="16%">Equipment Model</th>
                                    <th width="12%">AMC Type</th>
                                    <th width="12%">Start Date</th>
                                    <th width="12%">End Date</th>
                                    <th width="8%">Visits</th>
                                    <th width="10%">Status</th>
                                    <th width="8%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($amcContracts as $amcRow) {
                                    $amcId = (int) ($amcRow['id'] ?? 0);
                                    $encodedAmcId = $customerMasterEncodeId($amcId);
                                    $installedBaseId = (int) ($amcRow['installed_base_id'] ?? 0);
                                    $fabNumber = trim((string) ($amcRow['fab_number'] ?? ''));
                                    $amcContractNumber = trim((string) ($amcRow['contract_number'] ?? ''));
                                    if ($amcContractNumber === '') {
                                        $amcContractNumber = '-';
                                    }
                                    $canOpenAmc = $canViewAmc && amc_user_can_access_record($obconn, $amcRow);
                                    $amcTypeLabel = AMC_TYPE_OPTIONS[$amcRow['amc_type'] ?? ''] ?? (trim((string) ($amcRow['amc_type'] ?? '')) !== '' ? (string) $amcRow['amc_type'] : '-');
                                    ?>
                                <tr>
                                    <td data-label="Contract No.">
                                        <?php if ($canOpenAmc) { ?>
                                        <a href="amc_details.php?id=<?php echo $encodedAmcId; ?>"
                                            class="text-primary fw-semibold text-decoration-none">
                                            <?php echo htmlspecialchars($amcContractNumber, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                        <?php } else { ?>
                                        <?php echo htmlspecialchars($amcContractNumber, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php } ?>
                                    </td>
                                    <td data-label="Fab Number">
                                        <?php if ($canViewInstalledBase && $installedBaseId > 0 && $fabNumber !== '') { ?>
                                        <a href="installed_base_details.php?id=<?php echo $customerMasterEncodeId($installedBaseId); ?>"
                                            class="text-primary fw-semibold text-decoration-none">
                                            <?php echo htmlspecialchars($fabNumber, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                        <?php } else { ?>
                                        <?php echo htmlspecialchars($fabNumber !== '' ? $fabNumber : '-', ENT_QUOTES, 'UTF-8'); ?>
                                        <?php } ?>
                                    </td>
                                    <td data-label="Equipment Model"><?php echo htmlspecialchars(trim((string) ($amcRow['product_model'] ?? '')) !== '' ? (string) $amcRow['product_model'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="AMC Type"><?php echo htmlspecialchars($amcTypeLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Start Date"><?php echo htmlspecialchars(installed_base_format_date($amcRow['amc_start_date'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="End Date"><?php echo htmlspecialchars(installed_base_format_date($amcRow['amc_end_date'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Visits"><?php echo (int) ($amcRow['no_of_visits'] ?? 0); ?></td>
                                    <td data-label="Status">
                                        <span class="status-badge border border-dark">
                                            <?php echo htmlspecialchars(amc_display_status($amcRow), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td data-label="Action">
                                        <?php if ($canOpenAmc) { ?>
                                        <a href="amc_details.php?id=<?php echo $encodedAmcId; ?>"
                                            class="btn btn-sm btn-outline-dark" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php } else { ?>
                                        <span class="text-muted">-</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3 customer-master-related-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-octagon text-secondary"></i>
                        <strong>Complaint Details</strong>
                    </div>
                    <?php if ($complaintCount > 0) { ?>
                    <span class="badge border border-secondary text-secondary">
                        <?php echo (int) $complaintCount; ?>
                        record<?php echo $complaintCount === 1 ? '' : 's'; ?>
                    </span>
                    <?php } ?>
                </div>
                <div class="card-body px-3 pt-3 pb-3">
                    <?php if ($complaints === []) { ?>
                    <?php $customerMasterRelatedEmpty('bi-inbox', 'No complaints linked to this customer yet.'); ?>
                    <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-hover booking-table customer-master-related-table w-100 mb-0">
                            <thead>
                                <tr>
                                    <th width="8%">ID</th>
                                    <th width="14%">Fab Number</th>
                                    <th width="18%">Category</th>
                                    <th width="22%">Address</th>
                                    <th width="14%">Status</th>
                                    <th width="14%">Created At</th>
                                    <th width="10%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($complaints as $complaintRow) {
                                    $complaintId = (int) ($complaintRow['id'] ?? 0);
                                    $complaintStatus = (int) ($complaintRow['status'] ?? 0);
                                    $installedBaseId = (int) ($complaintRow['installed_base_id'] ?? 0);
                                    $fabNumber = trim((string) ($complaintRow['fab_number'] ?? ''));
                                    $complaintAddress = complaint_format_address($complaintRow);
                                    if ($complaintAddress === '') {
                                        $complaintAddress = '-';
                                    }
                                    ?>
                                <tr>
                                    <td data-label="ID">#<?php echo $complaintId; ?></td>
                                    <td data-label="Fab Number">
                                        <?php if ($canViewInstalledBase && $installedBaseId > 0 && $fabNumber !== '') { ?>
                                        <a href="installed_base_details.php?id=<?php echo $customerMasterEncodeId($installedBaseId); ?>"
                                            class="text-primary fw-semibold text-decoration-none">
                                            <?php echo htmlspecialchars($fabNumber, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                        <?php } else { ?>
                                        <?php echo htmlspecialchars($fabNumber !== '' ? $fabNumber : '-', ENT_QUOTES, 'UTF-8'); ?>
                                        <?php } ?>
                                    </td>
                                    <td data-label="Category"><?php echo htmlspecialchars(complaint_category_display_name($complaintRow), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Address"><?php echo htmlspecialchars($complaintAddress, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Status"><?php echo complaint_status_badge($complaintStatus); ?></td>
                                    <td data-label="Created At"><?php echo htmlspecialchars(rbac_format_datetime($complaintRow['created_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-label="Action"><?php echo complaint_entry_actions(
                                        $complaintId,
                                        $complaintStatus,
                                        false,
                                        false,
                                        [
                                            'view' => $canViewComplaint,
                                        ]
                                    ); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>