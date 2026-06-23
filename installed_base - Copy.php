<?php
session_start();

include 'pdo_obconn.php';
include 'includes/installed_base_helpers.php';

$active_menu = 'installed_base';
$success_message = '';
$error_message = '';
$industrySegments = installed_base_industry_segments();
$createdBy = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_installed_base'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = installed_base_from_post($_POST);
    $validationError = installed_base_validate($data);

    if ($validationError !== null) {
        $error_message = $validationError;
    } else {
        $orderRefId = (int) $data['order_ref_id'];
        $order = installed_base_get_order($obconn, $orderRefId);

        if (!$order) {
            $error_message = 'Selected Order ID was not found in the system.';
        } elseif ($order['order_id'] !== $data['order_id']) {
            $error_message = 'Order details do not match the selected order.';
        } else {
            $invoiceDateFromFab = ln_invoice_resolve_invoice_date_for_fab($dpconn, $data['fab_number']);

            if ($invoiceDateFromFab === null) {
                $error_message = 'Selected Fab Number was not found in invoice details.';
            } else {
                $data['invoice_date'] = $invoiceDateFromFab;
            }
        }

        if ($error_message === '') {
        try {
            if ($recordId > 0) {
                $checkStmt = $obconn->prepare('
                    SELECT id
                    FROM installed_base
                    WHERE id = :id
                      AND deleted_at IS NULL
                ');
                $checkStmt->bindValue(':id', $recordId, PDO::PARAM_INT);
                $checkStmt->execute();

                if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                    $error_message = 'Record not found or already deleted.';
                } else {
                    $update = $obconn->prepare('
                        UPDATE installed_base
                        SET
                            order_ref_id = :order_ref_id,
                            order_id = :order_id,
                            fab_number = :fab_number,
                            customer_name = :customer_name,
                            street_1 = :street_1,
                            street_2 = :street_2,
                            pincode = :pincode,
                            city = :city,
                            district = :district,
                            state = :state,
                            mobile = :mobile,
                            email = :email,
                            dealer_name = :dealer_name,
                            machine_model = :machine_model,
                            invoice_date = :invoice_date,
                            commissioning_date = :commissioning_date,
                            running_hours = :running_hours,
                            industry_segment = :industry_segment,
                            remarks = :remarks,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id
                          AND deleted_at IS NULL
                    ');

                    $update->bindValue(':order_ref_id', $orderRefId, PDO::PARAM_INT);
                    $update->bindValue(':order_id', $order['order_id']);
                    $update->bindValue(':fab_number', $data['fab_number']);
                    $update->bindValue(':customer_name', $data['customer_name']);
                    $update->bindValue(':street_1', $data['street_1']);
                    $update->bindValue(':street_2', $data['street_2'] !== '' ? $data['street_2'] : null);
                    $update->bindValue(':pincode', $data['pincode']);
                    $update->bindValue(':city', $data['city']);
                    $update->bindValue(':district', $data['district']);
                    $update->bindValue(':state', $data['state']);
                    $update->bindValue(':mobile', $data['mobile']);
                    $update->bindValue(':email', $data['email']);
                    $update->bindValue(':dealer_name', $data['dealer_name']);
                    $update->bindValue(':machine_model', $data['machine_model']);
                    $update->bindValue(':invoice_date', $data['invoice_date']);
                    $update->bindValue(':commissioning_date', $data['commissioning_date']);
                    $update->bindValue(':running_hours', $data['running_hours']);
                    $update->bindValue(':industry_segment', $data['industry_segment']);
                    $update->bindValue(':remarks', $data['remarks'] !== '' ? $data['remarks'] : null);
                    $update->bindValue(':id', $recordId, PDO::PARAM_INT);
                    $update->execute();

                    $success_message = 'Installed base record updated successfully.';
                }
            } else {
                $insert = $obconn->prepare('
                    INSERT INTO installed_base
                    (
                        order_ref_id,
                        order_id,
                        fab_number,
                        customer_name,
                        street_1,
                        street_2,
                        pincode,
                        city,
                        district,
                        state,
                        mobile,
                        email,
                        dealer_name,
                        machine_model,
                        invoice_date,
                        commissioning_date,
                        running_hours,
                        industry_segment,
                        remarks,
                        created_by
                    )
                    VALUES
                    (
                        :order_ref_id,
                        :order_id,
                        :fab_number,
                        :customer_name,
                        :street_1,
                        :street_2,
                        :pincode,
                        :city,
                        :district,
                        :state,
                        :mobile,
                        :email,
                        :dealer_name,
                        :machine_model,
                        :invoice_date,
                        :commissioning_date,
                        :running_hours,
                        :industry_segment,
                        :remarks,
                        :created_by
                    )
                ');

                $insert->bindValue(':order_ref_id', $orderRefId, PDO::PARAM_INT);
                $insert->bindValue(':order_id', $order['order_id']);
                $insert->bindValue(':fab_number', $data['fab_number']);
                $insert->bindValue(':customer_name', $data['customer_name']);
                $insert->bindValue(':street_1', $data['street_1']);
                $insert->bindValue(':street_2', $data['street_2'] !== '' ? $data['street_2'] : null);
                $insert->bindValue(':pincode', $data['pincode']);
                $insert->bindValue(':city', $data['city']);
                $insert->bindValue(':district', $data['district']);
                $insert->bindValue(':state', $data['state']);
                $insert->bindValue(':mobile', $data['mobile']);
                $insert->bindValue(':email', $data['email']);
                $insert->bindValue(':dealer_name', $data['dealer_name']);
                $insert->bindValue(':machine_model', $data['machine_model']);
                $insert->bindValue(':invoice_date', $data['invoice_date']);
                $insert->bindValue(':commissioning_date', $data['commissioning_date']);
                $insert->bindValue(':running_hours', $data['running_hours']);
                $insert->bindValue(':industry_segment', $data['industry_segment']);
                $insert->bindValue(':remarks', $data['remarks'] !== '' ? $data['remarks'] : null);
                $insert->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
                $insert->execute();

                $success_message = 'Installed base record saved successfully.';
            }
        } catch (PDOException $e) {
            $error_message = 'Failed to save installed base record.';
        }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installed Base Capture</title>

    <?php include 'header_css.php'; ?>

    <link href="css/new_complaint.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="css/select2_change.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="css/datatable_custom.css" rel="stylesheet" />

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/validate.js/0.13.1/validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
                    <div class="page-subtitle">
                        Capture installed machine base details linked to system orders.
                    </div>
                </div>

                <div class="header-btn-group">
                    <button class="new-order-btn btn-complaint-primary" id="openInstalledBaseForm" type="button">
                        <i class="bi bi-plus-lg"></i>
                        New Record
                    </button>
                    <button class="close-form-btn cancel-btn" id="closeInstalledBaseForm" type="button">
                        <i class="bi bi-x-lg"></i>
                        Cancel
                    </button>
                </div>
            </div>

            <div class="complaint-form-card" id="installedBaseFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon">
                            <i class="bi bi-hdd-stack"></i>
                        </div>
                        <div>
                            <h2 class="complaint-form-header__title" id="formModeLabel">New Installed Base</h2>
                            <p class="complaint-form-header__subtitle">Register machine installation and customer details.</p>
                        </div>
                        
                        <button type="button" class="submit-btn btn-complaint-primary" id="openCreateOrderModal" style="display:none">
                            <i class="bi bi-plus-lg"></i> New Order
                        </button>
                               
                    </div>
                </div>

                <form method="POST" id="installedBaseForm" novalidate>
                    <input type="hidden" name="record_id" id="installedBaseId" value="">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">1</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Order & Machine</h3>
                                    <p class="complaint-form-section__hint">Select a Vayu order number; invoice date auto-fills when fab number is selected</p>
                              
                                </div>
                               
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4 form-group">
                                    <label class="form-label" for="orderIdSelect">
                                        <i class="bi bi-receipt"></i>
                                        Order ID <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" name="order_ref_id" id="orderIdSelect"
                                        data-placeholder="Search order number">
                                        <option value=""></option>
                                    </select>
                                    <input type="hidden" name="order_id" id="orderIdDisplay">
                                    <div class="text-danger validation-msg" data-field="order_ref_id"></div>
                                </div>
                                
                                <div class="col-md-4 form-group">
                                    <label class="form-label" for="fabNumberSelect">
                                        <i class="bi bi-upc-scan"></i>
                                        Fab Number <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" name="fab_number" id="fabNumberSelect"
                                        data-placeholder="Search fab number">
                                        <option value=""></option>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="fab_number"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-cpu"></i>
                                        Machine Model <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" name="machine_model" maxlength="150"
                                        placeholder="Enter machine model">
                                    <div class="text-danger validation-msg" data-field="machine_model"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-calendar-event"></i>
                                        Invoice Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control address-auto-field" name="invoice_date"
                                        placeholder="Auto-filled from fab number" readonly>
                                    <div class="text-danger validation-msg" data-field="invoice_date"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-calendar-check"></i>
                                        Commissioning Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" name="commissioning_date">
                                    <div class="text-danger validation-msg" data-field="commissioning_date"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-clock-history"></i>
                                        Running Hours <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control" name="running_hours" min="0" step="0.01"
                                        placeholder="Usage hours">
                                    <div class="text-danger validation-msg" data-field="running_hours"></div>
                                </div>
                            </div>
                        </section>

                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">2</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Customer Details</h3>
                                    <p class="complaint-form-section__hint">Customer contact and location information</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-person"></i>
                                        Customer Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" name="customer_name" maxlength="200"
                                        placeholder="Enter customer name">
                                    <div class="text-danger validation-msg" data-field="customer_name"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-shop"></i>
                                        Dealer Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" name="dealer_name" maxlength="200"
                                        placeholder="Enter dealer name">
                                    <div class="text-danger validation-msg" data-field="dealer_name"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-signpost"></i>
                                        Street 1 <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" name="street_1" maxlength="255"
                                        placeholder="House / building / street">
                                    <div class="text-danger validation-msg" data-field="street_1"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-signpost-2"></i>
                                        Street 2
                                    </label>
                                    <input type="text" class="form-control" name="street_2" maxlength="255"
                                        placeholder="Area / landmark (optional)">
                                    <div class="text-danger validation-msg" data-field="street_2"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label" for="installedBasePincodeSelect">
                                        <i class="bi bi-mailbox"></i>
                                        Pincode <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" name="pincode" id="installedBasePincodeSelect"
                                        data-placeholder="Search pincode">
                                        <option value=""></option>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="pincode"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-building"></i>
                                        City <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control address-auto-field" name="city"
                                        maxlength="100" placeholder="Auto-filled from pincode" readonly>
                                    <div class="text-danger validation-msg" data-field="city"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-geo"></i>
                                        District <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control address-auto-field" name="district"
                                        maxlength="100" placeholder="Auto-filled from pincode" readonly>
                                    <div class="text-danger validation-msg" data-field="district"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-map"></i>
                                        State <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control address-auto-field" name="state"
                                        maxlength="100" placeholder="Auto-filled from pincode" readonly>
                                    <div class="text-danger validation-msg" data-field="state"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-phone"></i>
                                        Mobile <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" name="mobile" inputmode="numeric"
                                        maxlength="10" placeholder="10-digit mobile number">
                                    <div class="text-danger validation-msg" data-field="mobile"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-envelope"></i>
                                        Email <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" class="form-control" name="email" maxlength="150"
                                        placeholder="Enter email address">
                                    <div class="text-danger validation-msg" data-field="email"></div>
                                </div>
                            </div>
                        </section>

                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">3</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Business Details</h3>
                                    <p class="complaint-form-section__hint">Industry segment and additional notes</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-briefcase"></i>
                                        Industry Segment <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" name="industry_segment" id="industrySegmentSelect"
                                        data-placeholder="Search industry segment">
                                        <option value=""></option>
                                        <?php foreach ($industrySegments as $segment) { ?>
                                        <option value="<?php echo htmlspecialchars($segment); ?>">
                                            <?php echo htmlspecialchars($segment); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="industry_segment"></div>
                                </div>
                                <div class="col-12 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-card-text"></i>
                                        Remarks
                                    </label>
                                    <textarea class="form-control" name="remarks" rows="3"
                                        placeholder="Additional notes (optional)"></textarea>
                                    <div class="text-danger validation-msg" data-field="remarks"></div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelInstalledBaseForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" name="submit_installed_base"
                            id="submitInstalledBaseBtn">
                            <i class="bi bi-check-lg"></i>
                            Save Record
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title">Installed Base Records</div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="installedBaseTable">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="10%">Order ID</th>
                                <th width="10%">Fab Number</th>
                                <th width="15%">Customer Name</th>
                                <th width="12%">Dealer Name</th>
                                <th width="12%">Machine Model</th>
                                <th width="10%">Commissioning</th>
                                <th width="10%">Created At</th>
                                <th width="8%">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content complaint-form-modal">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-receipt"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title">Create New Order</h2>
                            <p class="complaint-form-header__subtitle">Order ID will be generated as ORD/YYYY/00001</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createOrderForm" class="complaint-form-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6 form-group">
                            <label class="form-label">Fab Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="fab_number" maxlength="20">
                            <div class="text-danger validation-msg" data-field="fab_number"></div>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="form-label">Machine Model <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="machine_model" maxlength="150">
                            <div class="text-danger validation-msg" data-field="machine_model"></div>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="customer_name" maxlength="200">
                            <div class="text-danger validation-msg" data-field="customer_name"></div>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="form-label">Dealer Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="dealer_name" maxlength="200">
                            <div class="text-danger validation-msg" data-field="dealer_name"></div>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="invoice_date">
                            <div class="text-danger validation-msg" data-field="invoice_date"></div>
                        </div>
                    </div>
                    <div class="complaint-form-actions mt-3">
                        <button type="button" class="cancel-btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="submit-btn btn-complaint-primary" id="submitCreateOrderBtn">
                            <i class="bi bi-check-lg"></i> Create Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/static_select2.js"></script>
    <script src="js/pincode_select2.js"></script>
    <script src="js/fabno_select2.js"></script>
    <script src="js/installed_base_fabno_select2.js"></script>
    <script src="js/installed_base_order_select2.js"></script>
    <script src="js/installed_base_validation.js"></script>
    <script src="js/installed_base.js"></script>
    <script>
    $(document).ready(function () {
        initInstalledBasePage();
        initPincodeSelect2('installedBaseForm', 'installedBasePincodeSelect');

        document.getElementById('cancelInstalledBaseForm').addEventListener('click', closeInstalledBaseFormPanel);

        setTimeout(function () {
            $('.alert-success').fadeOut();
        }, 3000);
    });
    </script>
</body>

</html>
