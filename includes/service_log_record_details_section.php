<?php
/**
 * Renders Service Log Capture details for a single record.
 * Expects: $serviceLogRecord (array), $partReplacements (array)
 * Optional: $serviceLogEmbeddedInInstalledBase (bool), $installedBaseRecord (array)
 */
$serviceLogEmbeddedInInstalledBase = !empty($serviceLogEmbeddedInInstalledBase);
$serviceLogId = (int) ($serviceLogRecord['id'] ?? 0);
$serviceLogLink = base64_encode((string) $serviceLogId);
$machineModelLabel = installed_base_machine_model_label([
    'machine_model_code' => $serviceLogRecord['machine_model_code'] ?? '',
    'machine_model' => $serviceLogRecord['machine_model'] ?? '',
]);

if ($machineModelLabel === '-') {
    $machineModelLabel = service_log_display_value($serviceLogRecord['machine_model'] ?? null);
}

$installedBaseLabel = '-';
if (!empty($installedBaseRecord) && is_array($installedBaseRecord)) {
    $installedBaseLabel = '#'
        . (int) ($installedBaseRecord['id'] ?? 0)
        . ' - ' . ($installedBaseRecord['order_id'] ?? '')
        . ' - ' . ($installedBaseRecord['fab_number'] ?? '')
        . ' - ' . ($installedBaseRecord['customer_name'] ?? '');
} elseif (!empty($serviceLogRecord['installed_base_id'])) {
    $installedBaseLabel = '#' . (int) $serviceLogRecord['installed_base_id'];
}

$partReplacedYes = service_log_part_replaced_is_yes((string) ($serviceLogRecord['part_replaced'] ?? ''));
$recordBlockClass = $serviceLogEmbeddedInInstalledBase
    ? 'service-log-record-details mb-4 pb-4 border-bottom'
    : 'card border-1 shadow-sm mb-3';
$headerClass = $serviceLogEmbeddedInInstalledBase
    ? 'd-flex justify-content-between align-items-center flex-wrap gap-2 mb-3'
    : 'card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2';
$bodyClass = $serviceLogEmbeddedInInstalledBase
    ? 'complaint-form-body px-0 pt-0 pb-0'
    : 'card-body complaint-form-body px-3 pt-3 pb-3';
?>
<div class="<?php echo $recordBlockClass; ?>">
    <?php if (!$serviceLogEmbeddedInInstalledBase) { ?>
    <div class="<?php echo $headerClass; ?>">
        <strong>Service Log Capture #<?php echo $serviceLogId; ?></strong>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge border border-dark text-dark">
                <?php echo htmlspecialchars(service_log_display_value($serviceLogRecord['warranty_chargeable'] ?? null)); ?>
            </span>
            <span class="text-muted small">
                <?php echo service_log_format_datetime($serviceLogRecord['created_at'] ?? null); ?>
            </span>
            <?php if (!empty($canViewServiceLogDetails)) { ?>
            <a href="service_log_details.php?id=<?php echo htmlspecialchars($serviceLogLink, ENT_QUOTES, 'UTF-8'); ?>"
                class="btn btn-sm btn-outline-dark">
                Open Full View
            </a>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

    <div class="<?php echo $bodyClass; ?>">
        <section class="complaint-form-section">
            <div class="complaint-form-section__head">
                <span class="complaint-form-section__badge">1</span>
                <div>
                    <h3 class="complaint-form-section__title">Machine & Order</h3>
                    <p class="complaint-form-section__hint">Linked installed base details</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <strong><i class="bi bi-link-45deg"></i> Installed Base</strong><br>
                    <?php echo htmlspecialchars($installedBaseLabel); ?>
                </div>
                <div class="col-md-3">
                    <strong><i class="bi bi-receipt"></i> Order ID</strong><br>
                    <?php echo htmlspecialchars(service_log_display_value($serviceLogRecord['order_id'] ?? null)); ?>
                </div>
                <div class="col-md-3">
                    <strong><i class="bi bi-upc-scan"></i> Fab Number</strong><br>
                    <?php echo htmlspecialchars(service_log_display_value($serviceLogRecord['fab_number'] ?? null)); ?>
                </div>
                <div class="col-md-3">
                    <strong><i class="bi bi-cpu"></i> Machine Model</strong><br>
                    <?php echo htmlspecialchars($machineModelLabel); ?>
                </div>
                <div class="col-md-3">
                    <strong><i class="bi bi-upc"></i> Serial Number</strong><br>
                    <?php echo htmlspecialchars(service_log_display_value($serviceLogRecord['serial_number'] ?? null)); ?>
                </div>
                <div class="col-md-3">
                    <strong><i class="bi bi-shield-check"></i> Warranty / Chargeable</strong><br>
                    <?php echo htmlspecialchars(service_log_display_value($serviceLogRecord['warranty_chargeable'] ?? null)); ?>
                </div>
                <div class="col-md-3">
                    <strong><i class="bi bi-calendar-x"></i> Complaint Date</strong><br>
                    <?php echo service_log_format_date($serviceLogRecord['complaint_date'] ?? null); ?>
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
                <div class="col-12">
                    <strong><i class="bi bi-exclamation-circle"></i> Issue Description</strong><br>
                    <?php echo nl2br(htmlspecialchars(service_log_display_value($serviceLogRecord['issue_description'] ?? null))); ?>
                </div>
                <div class="col-md-4">
                    <strong><i class="bi bi-person-gear"></i> Engineer Name</strong><br>
                    <?php echo htmlspecialchars(service_log_display_value($serviceLogRecord['engineer_name'] ?? null)); ?>
                </div>
                <div class="col-md-4">
                    <strong><i class="bi bi-calendar-event"></i> Visit Date</strong><br>
                    <?php echo service_log_format_date($serviceLogRecord['visit_date'] ?? null); ?>
                </div>
                <div class="col-md-4">
                    <strong><i class="bi bi-calendar-check"></i> Closure Date</strong><br>
                    <?php echo service_log_format_date($serviceLogRecord['closure_date'] ?? null); ?>
                </div>
                <div class="col-12">
                    <strong><i class="bi bi-tools"></i> Action Taken</strong><br>
                    <?php echo nl2br(htmlspecialchars(service_log_display_value($serviceLogRecord['action_taken'] ?? null))); ?>
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
                <div class="col-md-3">
                    <strong><i class="bi bi-gear-wide"></i> Part Replaced</strong><br>
                    <?php echo htmlspecialchars(service_log_display_value($serviceLogRecord['part_replaced'] ?? null)); ?>
                </div>
            </div>

            <?php if ($partReplacedYes) { ?>
            <div class="mt-3">
                <?php if (!empty($partReplacements)) { ?>
                    <?php foreach (array_values($partReplacements) as $entryIndex => $entry) { ?>
                    <div class="border rounded p-3 mb-3 bg-white">
                        <div class="mb-3">
                            <strong>Entry <?php echo $entryIndex + 1; ?></strong>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <strong><i class="bi bi-cpu"></i> Machine Model / Part</strong><br>
                                <?php echo htmlspecialchars(service_log_part_model_label($entry)); ?>
                            </div>
                            <div class="col-md-4">
                                <strong><i class="bi bi-speedometer2"></i> Running Hours</strong><br>
                                <?php echo htmlspecialchars(service_log_display_value($entry['running_hours'] ?? null)); ?>
                            </div>
                            <div class="col-md-4">
                                <strong><i class="bi bi-hourglass-split"></i> Loaded Hours</strong><br>
                                <?php echo htmlspecialchars(service_log_display_value($entry['loaded_hours'] ?? null)); ?>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="border rounded p-3 mb-3 bg-white">
                        <div class="mb-3">
                            <strong>Entry 1</strong>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <strong><i class="bi bi-speedometer2"></i> Running Hours</strong><br>
                                <?php echo htmlspecialchars(service_log_display_value($serviceLogRecord['running_hours'] ?? null)); ?>
                            </div>
                            <div class="col-md-4">
                                <strong><i class="bi bi-hourglass-split"></i> Loaded Hours</strong><br>
                                <?php echo htmlspecialchars(service_log_display_value($serviceLogRecord['loaded_hours'] ?? null)); ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>

                <div class="row g-3">
                    <div class="col-md-4">
                        <strong><i class="bi bi-chat-quote"></i> Customer Feedback</strong><br>
                        <?php echo htmlspecialchars(service_log_display_value($serviceLogRecord['customer_feedback'] ?? null)); ?>
                    </div>
                    <div class="col-12">
                        <strong><i class="bi bi-card-text"></i> Remarks</strong><br>
                        <?php
                        $serviceLogRemarks = service_log_display_value($serviceLogRecord['remarks'] ?? null);
                        echo $serviceLogRemarks === '-'
                            ? '-'
                            : nl2br(htmlspecialchars($serviceLogRemarks));
                        ?>
                    </div>
                </div>
            </div>
            <?php } ?>
        </section>

        <section class="complaint-form-section mb-0">
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
                <div class="col-md-6">
                    <strong><i class="bi bi-calendar-event"></i> <?php echo htmlspecialchars($consumable['label']); ?> Remaining Date</strong><br>
                    <?php echo service_log_format_date($serviceLogRecord[$dateField] ?? null); ?>
                </div>
                <div class="col-md-6">
                    <strong><i class="bi bi-clock-history"></i> <?php echo htmlspecialchars($consumable['label']); ?> Remaining Hours</strong><br>
                    <?php echo htmlspecialchars(service_log_display_value($serviceLogRecord[$hoursField] ?? null)); ?>
                </div>
                <?php } ?>
            </div>
        </section>
    </div>
</div>
