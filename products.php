<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/product_helpers.php';

require_system_admin($obconn);

$success_message = '';
$error_message = '';
$createdByUserId = current_user_id($obconn);
$ynOptions = product_yn_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_product'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = product_from_post($_POST);
    $isEdit = $recordId > 0;
    $validationError = product_validate($data);

    if ($validationError !== null) {
        $error_message = $validationError;
    } elseif (product_tplcode_exists($obconn, $data['tplcode'], $recordId)) {
        $error_message = 'TPL Code already exists. Please choose a different TPL Code.';
    } else {
        try {
            if ($isEdit) {
                if (!product_get_by_id($obconn, $recordId)) {
                    $error_message = 'Product not found or already deleted.';
                } else {
                    product_update($obconn, $recordId, $data, $createdByUserId);
                    $success_message = 'Product updated successfully.';
                }
            } else {
                product_insert($obconn, $data, $createdByUserId);
                $success_message = 'Product saved successfully.';
            }
        } catch (PDOException $e) {
            $error_message = $isEdit ? 'Failed to update product.' : 'Failed to save product.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products</title>
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
                    <div class="page-subtitle">Manage products records for system configuration.</div>
                </div>
                <div class="header-btn-group">
                    <button class="new-order-btn btn-complaint-primary" id="openProductForm" type="button">
                        <i class="bi bi-plus-lg"></i> Add Product
                    </button>
                    <button class="close-form-btn cancel-btn" id="closeProductForm" type="button">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                </div>
            </div>

            <div class="complaint-form-card" id="productFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-box-seam"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="productFormModeLabel">Add Product</h2>
                            <p class="complaint-form-header__subtitle">Enter product details. Fields marked * are required.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="productForm" novalidate>
                    <input type="hidden" name="record_id" id="productRecordId" value="">
                    <input type="hidden" name="submit_product" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="row g-3">
                                <div class="col-md-3 form-group">
                                    <label class="form-label">DPST <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="dpst" maxlength="20" placeholder="e.g. Y0001">
                                    <div class="text-danger validation-msg" data-field="dpst"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">Product Group <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="product_group" maxlength="50" placeholder="e.g. SPARES">
                                    <div class="text-danger validation-msg" data-field="product_group"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">TPL Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="tplcode" maxlength="20" placeholder="e.g. S016701">
                                    <div class="text-danger validation-msg" data-field="tplcode"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">TPL Description <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="tpldesc" maxlength="60" placeholder="Product description">
                                    <div class="text-danger validation-msg" data-field="tpldesc"></div>
                                </div>

                                <div class="col-md-3 form-group">
                                    <label class="form-label">Dealer Price</label>
                                    <input type="text" class="form-control" name="dealer_price" maxlength="20" placeholder="0" inputmode="decimal" autocomplete="off">
                                    <div class="text-danger validation-msg" data-field="dealer_price"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">TOD Flag</label>
                                    <select class="form-control" name="tod_flag">
                                        <?php foreach ($ynOptions as $value => $label) { ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $value === 'N' ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="tod_flag"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">Excisable</label>
                                    <select class="form-control" name="excisable">
                                        <?php foreach ($ynOptions as $value => $label) { ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $value === 'N' ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="excisable"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">Valid <span class="text-danger">*</span></label>
                                    <select class="form-control" name="valid">
                                        <option value="">Select</option>
                                        <?php foreach ($ynOptions as $value => $label) { ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="valid"></div>
                                </div>

                                <div class="col-md-3 form-group">
                                    <label class="form-label">MC</label>
                                    <input type="text" class="form-control" name="mc" maxlength="20" placeholder="0" inputmode="decimal" autocomplete="off">
                                    <div class="text-danger validation-msg" data-field="mc"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">VC</label>
                                    <input type="text" class="form-control" name="vc" maxlength="20" placeholder="0" inputmode="decimal" autocomplete="off">
                                    <div class="text-danger validation-msg" data-field="vc"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">FC</label>
                                    <input type="text" class="form-control" name="fc" maxlength="20" placeholder="0" inputmode="decimal" autocomplete="off">
                                    <div class="text-danger validation-msg" data-field="fc"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">COS (Price) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="cos" maxlength="20" placeholder="0" inputmode="decimal" autocomplete="off">
                                    <div class="text-danger validation-msg" data-field="cos"></div>
                                </div>

                                <div class="col-md-3 form-group">
                                    <label class="form-label">Company <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="company" maxlength="50" placeholder="Company">
                                    <div class="text-danger validation-msg" data-field="company"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="warehouse" maxlength="20" placeholder="e.g. 257">
                                    <div class="text-danger validation-msg" data-field="warehouse"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">Payment Term</label>
                                    <input type="text" class="form-control" name="payment_term" maxlength="100" placeholder="Payment term">
                                    <div class="text-danger validation-msg" data-field="payment_term"></div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelProductForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" id="submitProductBtn">
                            <i class="bi bi-check-lg"></i> Save Product
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="booking-title">Product List</div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <select class="form-control form-control-sm" id="productValidFilter" style="width:auto; min-width:130px;">
                            <option value="">All Valid</option>
                            <?php foreach ($ynOptions as $value => $label) { ?>
                            <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="productsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>DPST</th>
                                <th>Product Group</th>
                                <th>TPL Code</th>
                                <th>TPL Description</th>
                                <th>COS (Price)</th>
                                <th>Valid</th>
                                <th>Company</th>
                                <th>Warehouse</th>
                                <th>Added Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="js/products.js"></script>
</body>

</html>
