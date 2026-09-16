<?php
/**
 * AMC contract create modal for Installed Base grid and details.
 */
?>
<style>
    #installedBaseAmcModal .warranty-status-badge {
        border: 1px solid transparent;
        border-radius: 999px;
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.2;
        display: none;
    }
    #installedBaseAmcModal .warranty-status--standard {
        background: #dcfce7;
        color: #166534;
        border-color: #86efac;
    }
    #installedBaseAmcModal .warranty-status--uptime {
        background: #e0f2fe;
        color: #075985;
        border-color: #7dd3fc;
    }
    #installedBaseAmcModal .warranty-status--out {
        background: #fee2e2;
        color: #991b1b;
        border-color: #fca5a5;
    }
    #installedBaseAmcModal .warranty-status--unknown {
        background: #f1f5f9;
        color: #475569;
        border-color: #cbd5e1;
    }
    .address-auto-field[readonly] {
    background-color: #f8fafc;
    cursor: default;
}
</style>
<div class="modal fade" id="installedBaseAmcModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content complaint-form-modal">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon"><i class="bi bi-file-earmark-plus"></i></div>
                    <div>
                        <h2 class="complaint-form-header__title">New AMC Contract</h2>
                        <p class="complaint-form-header__subtitle">Create an AMC contract for the selected installed base machine.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="installedBaseAmcForm" novalidate>
                <input type="hidden" name="installed_base_id" id="ibAmcInstalledBaseId" value="">
                <div class="complaint-form-body p-4">
                    <div class="alert alert-danger d-none" id="ibAmcFormError" role="alert"></div>

                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">1</span>
                            <div>
                                <h3 class="complaint-form-section__title">Installed Base</h3>
                                <p class="complaint-form-section__hint">Filled automatically from the selected machine.</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6 form-group">
                                <label class="form-label">Installed Base <span class="text-danger">*</span></label>
                                <input type="text" class="form-control address-auto-field" id="ibAmcInstalledBaseLabel" readonly>
                                <div class="text-danger validation-msg" data-field="installed_base_id"></div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">FAB Number</label>
                                <input type="text" class="form-control address-auto-field" id="ibAmcFabNumber" readonly>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">Equipment Model</label>
                                <input type="text" class="form-control address-auto-field" id="ibAmcEquipmentModel" readonly>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">Warranty Status</label>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <input type="text" class="form-control address-auto-field" id="ibAmcWarrantyStatus" readonly>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">2</span>
                            <div>
                                <h3 class="complaint-form-section__title">Customer Details</h3>
                                <p class="complaint-form-section__hint">Populated from the customer linked to this installed base.</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4 form-group">
                                <label class="form-label">Customer Name</label>
                                <input type="text" class="form-control address-auto-field" id="ibAmcCustomerName" readonly>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">Mobile</label>
                                <input type="text" class="form-control address-auto-field" id="ibAmcCustomerMobile" readonly>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">Email</label>
                                <input type="text" class="form-control address-auto-field" id="ibAmcCustomerEmail" readonly>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">Street 1</label>
                                <input type="text" class="form-control address-auto-field" id="ibAmcCustomerStreet1" readonly>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">Street 2</label>
                                <input type="text" class="form-control address-auto-field" id="ibAmcCustomerStreet2" readonly>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">Pincode</label>
                                <input type="text" class="form-control address-auto-field" id="ibAmcCustomerPincode" readonly>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control address-auto-field" id="ibAmcCustomerCity" readonly>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">District</label>
                                <input type="text" class="form-control address-auto-field" id="ibAmcCustomerDistrict" readonly>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control address-auto-field" id="ibAmcCustomerState" readonly>
                            </div>
                        </div>
                    </section>

                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">3</span>
                            <div>
                                <h3 class="complaint-form-section__title">AMC Details</h3>
                                <p class="complaint-form-section__hint">Enter the contract type, value, dates and visit plan.</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4 form-group">
                                <label class="form-label" for="ibAmcType">
                                    AMC Type <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" name="amc_type" id="ibAmcType" required>
                                    <option value="">Select AMC type</option>
                                    <?php foreach (AMC_TYPE_OPTIONS as $val => $label): ?>
                                    <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="text-danger validation-msg" data-field="amc_type"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">AMC Value <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="amc_value" required>
                                <div class="text-danger validation-msg" data-field="amc_value"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">Number of Visits <span class="text-danger">*</span></label>
                                <input type="number" min="1" max="52" step="1" class="form-control" name="no_of_visits" required>
                                <div class="text-danger validation-msg" data-field="no_of_visits"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">AMC Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="amc_start_date" required>
                                <div class="text-danger validation-msg" data-field="amc_start_date"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">AMC End Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="amc_end_date" required>
                                <div class="text-danger validation-msg" data-field="amc_end_date"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">Visit Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="visit_start_date" required>
                                <div class="text-danger validation-msg" data-field="visit_start_date"></div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="complaint-form-footer d-flex justify-content-end gap-2 p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-complaint-primary" id="ibAmcSubmitBtn">
                        <i class="bi bi-send"></i> Register AMC
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>