<?php
/**
 * Add Customer modal for Installed Base Capture and Complaint Entry.
 * Fields and validation match Customer Master.
 */
require_once __DIR__ . '/customer_master_helpers.php';

$cmModalDealerContext = null;
if (isset($obconn) && $obconn instanceof PDO) {
    $cmModalDealerContext = customer_master_logged_in_dealer_context($obconn);
}
?>
<div class="modal fade" id="installedBaseAddCustomerModal" tabindex="-1" aria-hidden="true"
    aria-labelledby="installedBaseAddCustomerModalTitle">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content complaint-form-modal">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon"><i class="bi bi-person-vcard"></i></div>
                    <div>
                        <h2 class="complaint-form-header__title" id="installedBaseAddCustomerModalTitle">Add Customer</h2>
                        <p class="complaint-form-header__subtitle">Enter customer name, contact, and address details.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" id="installedBaseAddCustomerForm" novalidate>
                <div class="complaint-form-body p-4">
                    <div id="installedBaseAddCustomerAlert" class="alert alert-danger d-none mb-3" role="alert"></div>
                    <div class="row  g-3">
                        <div class="col-md-4">
                            <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="customer_name" maxlength="150"
                                placeholder="Enter customer name">
                            <div class="text-danger validation-msg" data-field="customer_name"></div>
                        </div>
                        <div class="col-md-4 ">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" maxlength="150"
                                placeholder="Enter email">
                            <div class="text-danger validation-msg" data-field="email"></div>
                        </div>
                        <div class="col-md-4 ">
                            <label class="form-label">Mobile <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="mobile" maxlength="10"
                                placeholder="10-digit mobile number" inputmode="numeric">
                            <div class="text-danger validation-msg" data-field="mobile"></div>
                        </div>
                        <div class="col-md-6 ">
                            <label class="form-label">Street 1 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="street_1" maxlength="255"
                                placeholder="House / building / street">
                            <div class="text-danger validation-msg" data-field="street_1"></div>
                        </div>
                        <div class="col-md-6 ">
                            <label class="form-label">Street 2 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="street_2" maxlength="255"
                                placeholder="Area / landmark">
                            <div class="text-danger validation-msg" data-field="street_2"></div>
                        </div>
                        <div class="col-md-6 ">
                            <label class="form-label" for="installedBaseCustomerModalPincodeSelect">
                                Pincode <span class="text-danger">*</span>
                            </label>
                            <select class="form-control" name="pincode" id="installedBaseCustomerModalPincodeSelect"
                                data-placeholder="Search pincode" style="width:100%;">
                                <option value=""></option>
                            </select>
                            <div class="text-danger validation-msg" data-field="pincode"></div>
                        </div>
                        <div class="col-md-6 ">
                            <label class="form-label">City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="city" maxlength="100" readonly
                                style="background-color: #f8f9fa;" placeholder="Auto-filled from pincode">
                            <div class="text-danger validation-msg" data-field="city"></div>
                        </div>
                        <div class="col-md-6 ">
                            <label class="form-label">District <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="district" maxlength="100" readonly
                                style="background-color: #f8f9fa;" placeholder="Auto-filled from pincode">
                            <div class="text-danger validation-msg" data-field="district"></div>
                        </div>
                        <div class="col-md-6 ">
                            <label class="form-label">State <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="state" maxlength="100" readonly
                                style="background-color: #f8f9fa;" placeholder="Auto-filled from pincode">
                            <div class="text-danger validation-msg" data-field="state"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="installedBaseCustomerModalDealerSelect">
                                Dealer Name <span class="text-danger">*</span>
                            </label>
                            <select class="form-control" name="dealer_code" id="installedBaseCustomerModalDealerSelect"
                                data-placeholder="Search dealer" style="width:100%;">
                                <option value=""></option>
                            </select>
                            <input type="hidden" name="dealer_name" id="installedBaseCustomerModalDealerName" value="">
                            <div class="text-danger validation-msg" data-field="dealer_code"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">GST Number</label>
                            <input type="text" class="form-control" name="gst_number" maxlength="15"
                                placeholder="GST number" style="text-transform: uppercase;">
                            <div class="text-danger validation-msg" data-field="gst_number"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">PAN Number</label>
                            <input type="text" class="form-control" name="pan_number" maxlength="10"
                                placeholder="PAN number" style="text-transform: uppercase;">
                            <div class="text-danger validation-msg" data-field="pan_number"></div>
                        </div>
                    </div>
                </div>
                <div class="complaint-form-actions px-4 pb-4">
                    <button type="button" class="cancel-btn" data-bs-dismiss="modal">Cancel</button>
                    <button class="submit-btn btn-complaint-primary" type="submit" id="installedBaseAddCustomerSubmitBtn">
                        <i class="bi bi-check-lg"></i> Save Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
window.customerMasterDealerContext = <?php
    echo $cmModalDealerContext ? json_encode($cmModalDealerContext, JSON_UNESCAPED_UNICODE) : 'null';
?>;
</script>