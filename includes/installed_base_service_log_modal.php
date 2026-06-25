<?php
/**
 * Service Log Capture modal for Installed Base listing.
 * Expects: $serviceLogWarrantyTypes, $partReplacedOptions, $feedbackOptions
 */
?>
<div class="modal fade" id="installedBaseServiceLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content complaint-form-modal">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon"><i class="bi bi-clipboard-pulse"></i></div>
                    <div>
                        <h2 class="complaint-form-header__title">Add Service Log Capture</h2>
                        <p class="complaint-form-header__subtitle">Capture service visit details for the selected installed base.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="installedBaseServiceLogForm" novalidate>
                <input type="hidden" name="part_replacement_multi" value="1">
                <input type="hidden" name="from_installed_base_modal" value="1">
                <div class="complaint-form-body p-4">
                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">1</span>
                            <div>
                                <h3 class="complaint-form-section__title">Machine & Order</h3>
                                <p class="complaint-form-section__hint">Linked installed base details</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6 form-group">
                                <label class="form-label"><i class="bi bi-link-45deg"></i> Installed Base <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ibServiceLogInstalledBaseLabel" readonly>
                                <input type="hidden" name="installed_base_id" id="ibServiceLogInstalledBaseId">
                                <div class="text-danger validation-msg" data-field="installed_base_id"></div>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label"><i class="bi bi-receipt"></i> Order ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="order_id" readonly>
                                <div class="text-danger validation-msg" data-field="order_id"></div>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label"><i class="bi bi-upc-scan"></i> Fab Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="fab_number" readonly>
                                <div class="text-danger validation-msg" data-field="fab_number"></div>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label"><i class="bi bi-cpu"></i> Machine Model <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="machine_model" maxlength="150" readonly>
                                <div class="text-danger validation-msg" data-field="machine_model"></div>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label"><i class="bi bi-upc"></i> Serial Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="serial_number" maxlength="50" placeholder="Enter serial number">
                                <div class="text-danger validation-msg" data-field="serial_number"></div>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label"><i class="bi bi-shield-check"></i> Warranty / Chargeable <span class="text-danger">*</span></label>
                                <select class="form-control" name="warranty_chargeable" id="ibServiceLogWarrantySelect"
                                    data-placeholder="Search service type">
                                    <option value=""></option>
                                    <?php foreach ($serviceLogWarrantyTypes as $type) { ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                    <?php } ?>
                                </select>
                                <div class="text-danger validation-msg" data-field="warranty_chargeable"></div>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label"><i class="bi bi-calendar-x"></i> Complaint Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="complaint_date">
                                <div class="text-danger validation-msg" data-field="complaint_date"></div>
                            </div>
                        </div>
                    </section>

                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">2</span>
                            <div>
                                <h3 class="complaint-form-section__title">Issue & Service</h3>
                                <p class="complaint-form-section__hint">Problem description and service visit details</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-12 form-group">
                                <label class="form-label"><i class="bi bi-exclamation-circle"></i> Issue Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="issue_description" rows="2" placeholder="Problem description"></textarea>
                                <div class="text-danger validation-msg" data-field="issue_description"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label"><i class="bi bi-person-gear"></i> Engineer Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="engineer_name" maxlength="150" placeholder="Enter engineer name">
                                <div class="text-danger validation-msg" data-field="engineer_name"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label"><i class="bi bi-calendar-event"></i> Visit Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="visit_date">
                                <div class="text-danger validation-msg" data-field="visit_date"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label"><i class="bi bi-calendar-check"></i> Closure Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="closure_date">
                                <div class="text-danger validation-msg" data-field="closure_date"></div>
                            </div>
                            <div class="col-12 form-group">
                                <label class="form-label"><i class="bi bi-tools"></i> Action Taken <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="action_taken" rows="2" placeholder="Resolution details"></textarea>
                                <div class="text-danger validation-msg" data-field="action_taken"></div>
                            </div>
                        </div>
                    </section>

                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">3</span>
                            <div>
                                <h3 class="complaint-form-section__title">Usage & Feedback</h3>
                                <p class="complaint-form-section__hint">Machine hours and customer feedback</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-3 form-group">
                                <label class="form-label"><i class="bi bi-gear-wide"></i> Part Replaced <span class="text-danger">*</span></label>
                                <select class="form-control" name="part_replaced" id="ibServiceLogPartReplacedSelect"
                                    data-placeholder="Search part replaced">
                                    <option value=""></option>
                                    <?php foreach ($partReplacedOptions as $option) { ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                    <?php } ?>
                                </select>
                                <div class="text-danger validation-msg" data-field="part_replaced"></div>
                            </div>
                        </div>

                        <div id="ibServiceLogPartReplacementWrapper" class="d-none mt-3">
                            <div class="mb-3">
                                <button type="button" class="btn btn-sm btn-outline-dark" id="ibServiceLogAddPartReplacementBtn">
                                    <i class="bi bi-plus-lg"></i> Add More
                                </button>
                            </div>
                            <div id="ibServiceLogPartReplacementEntries"></div>
                            <div class="text-danger validation-msg mb-2" data-field="part_replacement_entries"></div>

                            <div class="row g-3">
                                <div class="col-md-4 form-group">
                                    <label class="form-label"><i class="bi bi-chat-quote"></i> Customer Feedback <span class="text-danger">*</span></label>
                                    <select class="form-control" name="customer_feedback" id="ibServiceLogFeedbackSelect"
                                        data-placeholder="Search customer feedback">
                                        <option value=""></option>
                                        <?php foreach ($feedbackOptions as $feedback) { ?>
                                        <option value="<?php echo htmlspecialchars($feedback); ?>"><?php echo htmlspecialchars($feedback); ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="customer_feedback"></div>
                                </div>
                                <div class="col-12 form-group">
                                    <label class="form-label"><i class="bi bi-card-text"></i> Remarks</label>
                                    <textarea class="form-control" name="remarks" rows="2" placeholder="Additional notes (optional)"></textarea>
                                    <div class="text-danger validation-msg" data-field="remarks"></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">4</span>
                            <div>
                                <h3 class="complaint-form-section__title">Remaining Consumables Details</h3>
                                <p class="complaint-form-section__hint">Optional remaining life for consumable parts</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <?php foreach (service_log_remaining_consumable_fields() as $consumable) {
                                $dateField = $consumable['key'] . '_remaining_date';
                                $hoursField = $consumable['key'] . '_remaining_hours';
                                ?>
                            <div class="col-md-6 form-group">
                                <label class="form-label">
                                    <i class="bi bi-calendar-event"></i>
                                    <?php echo htmlspecialchars($consumable['label']); ?> Remaining Date
                                </label>
                                <input type="date" class="form-control" name="<?php echo htmlspecialchars($dateField); ?>">
                                <div class="text-danger validation-msg" data-field="<?php echo htmlspecialchars($dateField); ?>"></div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">
                                    <i class="bi bi-clock-history"></i>
                                    <?php echo htmlspecialchars($consumable['label']); ?> Remaining Hours
                                </label>
                                <input type="number" class="form-control" name="<?php echo htmlspecialchars($hoursField); ?>"
                                    min="0" step="0.01" placeholder="Optional hours">
                                <div class="text-danger validation-msg" data-field="<?php echo htmlspecialchars($hoursField); ?>"></div>
                            </div>
                            <?php } ?>
                        </div>
                    </section>
                </div>

                <div class="complaint-form-actions">
                    <button type="button" class="cancel-btn" data-bs-dismiss="modal">Cancel</button>
                    <button class="submit-btn btn-complaint-primary" type="submit" id="submitIbServiceLogBtn">
                        <i class="bi bi-check-lg"></i> Save Service Log
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
