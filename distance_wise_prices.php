<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/distance_wise_price_helpers.php';
require_once __DIR__ . '/includes/current_username_helpers.php';

require_system_admin($obconn);
distance_wise_price_ensure_schema($obconn);

$success_message = '';
$error_message = '';
$createdBy = current_username();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_distance_wise_price'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = distance_wise_price_from_post($_POST);
    $isEdit = $recordId > 0;
    $validationError = distance_wise_price_validate($data);

    if ($validationError !== null) {
        $error_message = $validationError;
    } elseif (distance_wise_price_range_overlaps($obconn, $data, $recordId)) {
        $error_message = 'This KM range overlaps an existing slab. Please choose a different range.';
    } else {
        try {
            if ($isEdit) {
                if (!distance_wise_price_get_by_id($obconn, $recordId)) {
                    $error_message = 'Record not found or already deleted.';
                } else {
                    distance_wise_price_update($obconn, $recordId, $data);
                    $success_message = 'Distance wise price updated successfully.';
                }
            } else {
                distance_wise_price_insert($obconn, $data, $createdBy);
                $success_message = 'Distance wise price saved successfully.';
            }
        } catch (PDOException $e) {
            $error_message = $isEdit
                ? 'Failed to update distance wise price.'
                : 'Failed to save distance wise price.';
        }
    }
}

$statusOptions = rbac_status_options();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distance Wise Price Management</title>
    <?php include 'header_css.php'; ?>
    <link href="css/new_complaint.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="css/datatable_custom.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/validate.js/0.13.1/validate.min.js"></script>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <?php if (!empty($success_message)) { ?>
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php } ?>
            <?php if (!empty($error_message)) { ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php } ?>
            <?php if (isset($_SESSION['success_message'])) { ?>
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); } ?>
            <?php if (isset($_SESSION['error_message'])) { ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); } ?>

            <div class="page-header">
                <div>
                    <div class="page-subtitle">Manage slabs for distance wise price.</div>
                </div>
                <div class="header-btn-group">
                    <button class="new-order-btn btn-complaint-primary" id="openDistanceWisePriceForm" type="button">
                        <i class="bi bi-plus-lg"></i> Add Distance Wise Price
                    </button>
                    <button class="close-form-btn cancel-btn" id="closeDistanceWisePriceForm" type="button">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                </div>
            </div>

            <div class="complaint-form-card" id="distanceWisePriceFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-signpost-split"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="distanceWisePriceFormModeLabel">Add Distance Wise Price</h2>
                            <p class="complaint-form-header__subtitle">Choose a range type, then enter KM and price.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="distanceWisePriceForm" novalidate>
                    <input type="hidden" name="record_id" id="distanceWisePriceRecordId" value="">
                    <input type="hidden" name="submit_distance_wise_price" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="row g-3">
                                <div class="col-md form-group">
                                    <label class="form-label">Range Type <span class="text-danger">*</span></label>
                                    <select class="form-control" name="range_type" id="distanceWisePriceRangeType">
                                        <?php foreach (distance_wise_price_range_type_options() as $value => $label) { ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $value === 'between' ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="range_type"></div>
                                </div>
                                <div class="col-md form-group" id="distanceWisePriceFromGroup">
                                    <label class="form-label">From KM <span class="text-danger" id="distanceWisePriceFromRequired">*</span></label>
                                    <input type="number" class="form-control" name="from_km" min="0" step="1" placeholder="e.g. 51">
                                    <div class="text-danger validation-msg" data-field="from_km"></div>
                                </div>
                                <div class="col-md form-group" id="distanceWisePriceToGroup">
                                    <label class="form-label">To KM <span class="text-danger" id="distanceWisePriceToRequired">*</span></label>
                                    <input type="number" class="form-control" name="to_km" min="0" step="1" placeholder="e.g. 100">
                                    <div class="text-danger validation-msg" data-field="to_km"></div>
                                </div>
                                <div class="col-md form-group">
                                    <label class="form-label">Price(&#8377;) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="price" min="0" step="0.01" placeholder="e.g. 149">
                                    <div class="text-danger validation-msg" data-field="price"></div>
                                </div>
                                <div class="col-md form-group">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-control" name="status">
                                        <?php foreach ($statusOptions as $value => $label) { ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="status"></div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelDistanceWisePriceForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" id="submitDistanceWisePriceBtn">
                            <i class="bi bi-check-lg"></i> Save Distance Wise Price
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title">Distance Wise Price List</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="distanceWisePricesTable">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="20%">KM Range</th>
                                <th width="10%">Price</th>
                                <th width="10%">Status</th>
                                <th width="10%">Created At</th>
                                <th width="10%">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="js/distance_wise_prices.js"></script>
</body>

</html>