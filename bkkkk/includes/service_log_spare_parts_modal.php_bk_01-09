<?php
/**
 * Spare Parts Consumption modal for Service Log listing.
 * Expects: $sparePartsWarrantyTypes, $sparePartsReasons
 */
?>
<div class="modal fade" id="serviceLogSparePartsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content complaint-form-modal">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon"><i class="bi bi-gear"></i></div>
                    <div>
                        <h2 class="complaint-form-header__title">Add Spare Parts Consumption</h2>
                        <p class="complaint-form-header__subtitle">Add one or more consumed spare parts linked to the selected machine or service log.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="serviceLogSparePartsForm" novalidate>
                <div class="complaint-form-body p-4">
                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">1</span>
                            <div>
                                <h3 class="complaint-form-section__title">Service Log & Machine Link</h3>
                                <p class="complaint-form-section__hint">Auto-linked from the selected service log record</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6 form-group">
                                <label class="form-label"><i class="bi bi-clipboard-pulse"></i> Service Log <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="slSparePartsServiceLogLabel" readonly>
                                <input type="hidden" name="service_log_id" id="slSparePartsServiceLogId">
                                <div class="text-danger validation-msg" data-field="service_log_id"></div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label"><i class="bi bi-hdd-stack"></i> Installed Base <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="slSparePartsInstalledBaseLabel" readonly>
                                <input type="hidden" name="installed_base_id" id="slSparePartsInstalledBaseId">
                                <div class="text-danger validation-msg" data-field="installed_base_id"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label"><i class="bi bi-person"></i> Customer Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="slSparePartsCustomerName" name="customer_name" readonly>
                                <div class="text-danger validation-msg" data-field="customer_name"></div>
                            </div>
                            <div class="col-md-4 form-group d-none">
                                <label class="form-label"><i class="bi bi-receipt"></i> Order ID</label>
                                <input type="hidden" name="order_id" value="">
                                <div class="text-danger validation-msg" data-field="order_id"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label"><i class="bi bi-upc-scan"></i> Fab Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="fab_number" readonly>
                                <div class="text-danger validation-msg" data-field="fab_number"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label"><i class="bi bi-upc"></i> Serial Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="serial_number" maxlength="50" readonly>
                                <div class="text-danger validation-msg" data-field="serial_number"></div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label"><i class="bi bi-cpu"></i> Machine Model <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="slSparePartsMachineModel" name="machine_model" readonly>
                                <div class="text-danger validation-msg" data-field="machine_model"></div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label"><i class="bi bi-clock-history"></i> Running Hours <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="running_hours" min="0.01" step="0.01" readonly>
                                <div class="text-danger validation-msg" data-field="running_hours"></div>
                            </div>
                        </div>
                    </section>

                    <section class="complaint-form-section" id="slSparePartsServiceDetailsSection">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">2</span>
                            <div>
                                <h3 class="complaint-form-section__title">Complaint / Service Details</h3>
                                <p class="complaint-form-section__hint">From the linked service log record</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-3 form-group">
                                <label class="form-label"><i class="bi bi-calendar-x"></i> Log Date</label>
                                <input type="text" class="form-control" id="slSparePartsComplaintDate" readonly>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label"><i class="bi bi-calendar-event"></i> Visit Date</label>
                                <input type="text" class="form-control" id="slSparePartsVisitDate" readonly>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label"><i class="bi bi-calendar-check"></i> Closure Date</label>
                                <input type="text" class="form-control" id="slSparePartsClosureDate" readonly>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label"><i class="bi bi-person-gear"></i> Engineer Name</label>
                                <input type="text" class="form-control" id="slSparePartsEngineerName" readonly>
                            </div>
                            <div class="col-12 form-group">
                                <label class="form-label"><i class="bi bi-exclamation-circle"></i> Issue / Service Description</label>
                                <textarea class="form-control" id="slSparePartsIssueDescription" rows="2" readonly></textarea>
                            </div>
                            <div class="col-12 form-group">
                                <label class="form-label"><i class="bi bi-tools"></i> Action Taken</label>
                                <textarea class="form-control" id="slSparePartsActionTaken" rows="2" readonly></textarea>
                            </div>
                        </div>
                    </section>

                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">3</span>
                            <div>
                                <h3 class="complaint-form-section__title">Add Spare Parts Details</h3>
                                <p class="complaint-form-section__hint">Add one or more consumed spare parts before saving</p>
                            </div>
                        </div>
                        <input type="hidden" name="spare_parts_multi" value="1">
                        <div class="row g-3">
                            <div class="col-md-4 form-group">
                                <label class="form-label"><i class="bi bi-calendar-event"></i> Consumption Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="consumption_date">
                                <div class="text-danger validation-msg" data-field="consumption_date"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label"><i class="bi bi-shield-check"></i> Warranty / Chargeable <span class="text-danger">*</span></label>
                                <select class="form-control" name="warranty_chargeable" id="slSparePartsWarrantySelect"
                                    data-placeholder="Search warranty type">
                                    <option value=""></option>
                                    <?php foreach ($sparePartsWarrantyTypes as $type) { ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                    <?php } ?>
                                </select>
                                <div class="text-danger validation-msg" data-field="warranty_chargeable"></div>
                            </div>
                        </div>

                        <div class="mt-3 mb-3" id="slSparePartsAddItemWrapper">
                            <button type="button" class="btn btn-sm btn-outline-dark" id="slSparePartsAddItemBtn">
                                <i class="bi bi-plus-lg"></i> Add Spare Part
                            </button>
                        </div>

                        <div id="slSparePartsItemEntries"></div>
                        <div class="text-danger validation-msg mb-3" data-field="spare_parts_items"></div>

                        <div class="row g-3">
                            <div class="col-12 form-group">
                                <label class="form-label"><i class="bi bi-card-text"></i> Remarks</label>
                                <textarea class="form-control" name="remarks" rows="2" placeholder="Additional notes (optional)"></textarea>
                                <div class="text-danger validation-msg" data-field="remarks"></div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="complaint-form-actions">
                    <button type="button" class="cancel-btn" data-bs-dismiss="modal">Cancel</button>
                    <button class="submit-btn btn-complaint-primary" type="submit" id="submitSlSparePartsBtn">
                        <i class="bi bi-check-lg"></i> Save Spare Parts
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script type="application/json" id="slSparePartsReasonOptionsJson"><?php echo json_encode(array_values($sparePartsReasons), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>