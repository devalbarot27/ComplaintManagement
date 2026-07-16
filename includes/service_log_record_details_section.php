<?php
/**
 * Renders Service Log Capture details for a single record.
 * Expects: $serviceLogRecord (array), $partReplacements (array)
 * Optional: $serviceLogEmbeddedInInstalledBase (bool), $installedBaseRecord (array)
 */
require_once __DIR__ . '/service_log_draft_helpers.php';

$serviceLogEmbeddedInInstalledBase = !empty($serviceLogEmbeddedInInstalledBase);
$serviceLogId = (int) ($serviceLogRecord['id'] ?? 0);
$serviceLogIsDraft = service_log_is_draft_value($serviceLogRecord['is_draft'] ?? 0);
$serviceLogLink = base64_encode((string) $serviceLogId);
$linkedInstalledBaseFields = service_log_linked_installed_base_display_fields(
    !empty($installedBaseRecord) && is_array($installedBaseRecord) ? $installedBaseRecord : null,
    $serviceLogRecord
);
$machineModelLabel = $linkedInstalledBaseFields['machine_model'];

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
$serviceLogHideRecordHeader = !empty($serviceLogHideRecordHeader);
$isLastServiceLogRecord = !empty($isLastServiceLogRecord);
$recordBlockClass = $serviceLogEmbeddedInInstalledBase
    ? 'service-log-record-details mb-4 pb-4' . ($isLastServiceLogRecord ? '' : ' border-bottom')
    : 'card border-1 shadow-sm mb-3';
if ($serviceLogIsDraft) {
    $recordBlockClass .= ' service-log-draft-record';
}
$headerClass = $serviceLogEmbeddedInInstalledBase
    ? 'd-flex justify-content-between align-items-center flex-wrap gap-2 mb-3'
    : 'card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2';
$bodyClass = $serviceLogEmbeddedInInstalledBase
    ? 'complaint-form-body px-0 pt-0 pb-0'
    : 'card-body complaint-form-body px-3 pt-3 pb-3';

$renderServiceLogDetailField = static function (
    string $label,
    string $value,
    string $colClass = 'col-md-3',
    bool $multiline = false
): void {
    ?>
    <div class="<?php echo htmlspecialchars($colClass, ENT_QUOTES, 'UTF-8'); ?>">
        <strong><?php echo htmlspecialchars($label); ?>:</strong>
        <?php
        if ($multiline && $value !== '-') {
            echo nl2br(htmlspecialchars($value));
        } else {
            echo htmlspecialchars($value);
        }
        ?>
    </div>
    <?php
};
?>
<div class="<?php echo $recordBlockClass; ?>">
    <?php if (!$serviceLogEmbeddedInInstalledBase && !$serviceLogHideRecordHeader) { ?>
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
        <?php if ($serviceLogEmbeddedInInstalledBase) { ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <strong>Service Log Capture #<?php echo $serviceLogId; ?></strong>
                <?php if ($serviceLogIsDraft) { ?>
                <?php echo service_log_draft_badge_html(); ?>
                <?php } ?>
                <span class="text-muted small">
                    <?php echo service_log_format_datetime($serviceLogRecord['created_at'] ?? null); ?>
                </span>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php if (!empty($canViewServiceLogDetails)) { ?>
            <a href="service_log_details.php?id=<?php echo htmlspecialchars($serviceLogLink, ENT_QUOTES, 'UTF-8'); ?>"
                class="btn btn-sm btn-outline-dark">
                Open Full View
            </a>
            <?php } ?>
            <?php if ($serviceLogIsDraft && $serviceLogEmbeddedInInstalledBase && !empty($canEditServiceLog)) {
                $editDraftInstalledBaseId = (int) (($installedBaseRecord['id'] ?? 0) ?: ($serviceLogRecord['installed_base_id'] ?? 0));
                if ($editDraftInstalledBaseId > 0) { ?>
            <a href="<?php echo htmlspecialchars(service_log_edit_draft_url($serviceLogId, $editDraftInstalledBaseId), ENT_QUOTES, 'UTF-8'); ?>"
                class="btn btn-sm btn-outline-dark">
                <i class="bi bi-pencil-square"></i> Edit Draft
            </a>
            <?php }
            } ?>
            </div>
        </div>
        <?php if (!empty($serviceLogRecordNumber) && !empty($serviceLogRecordTotal) && (int) $serviceLogRecordTotal > 1) { ?>
        <p class="text-muted small mb-3">
            Record <?php echo (int) $serviceLogRecordNumber; ?>
            of <?php echo (int) $serviceLogRecordTotal; ?>
        </p>
        <?php } ?>
        <?php } elseif (!empty($serviceLogRecordNumber) && !empty($serviceLogRecordTotal) && (int) $serviceLogRecordTotal > 1) { ?>
        <p class="text-muted small mb-3">
            Service Log Capture <?php echo (int) $serviceLogRecordNumber; ?>
            of <?php echo (int) $serviceLogRecordTotal; ?>
        </p>
        <?php } ?>

        <section class="complaint-form-section">
            <div class="complaint-form-section__head">
                <span class="complaint-form-section__badge">1</span>
                <div>
                    <h3 class="complaint-form-section__title">Machine & Order</h3>
                    <p class="complaint-form-section__hint">Linked installed base details</p>
                </div>
            </div>
            <div class="row g-3">
                <?php
                if (!empty($installedBaseRecord) && is_array($installedBaseRecord)) {
                    $installedBaseLink = base64_encode((string) ($installedBaseRecord['id'] ?? 0));
                    $installedBaseLabelHtml = '<a href="installed_base_details.php?id='
                        . htmlspecialchars($installedBaseLink, ENT_QUOTES, 'UTF-8')
                        . '">'
                        . htmlspecialchars($installedBaseLabel)
                        . '</a>';
                } else {
                    $installedBaseLabelHtml = htmlspecialchars($installedBaseLabel);
                }
                ?>
                
                <?php
               // $renderServiceLogDetailField('Order ID', $linkedInstalledBaseFields['order_id'], 'col-md-3');
               // $renderServiceLogDetailField('Fab Number', $linkedInstalledBaseFields['fab_number'], 'col-md-3');
               // $renderServiceLogDetailField('Machine Model', $machineModelLabel, 'col-md-3');
                $renderServiceLogDetailField(
                    'Serial Number',
                    service_log_format_serial_number_for_display($serviceLogRecord['serial_number'] ?? null),
                    'col-md-4'
                );
                $renderServiceLogDetailField(
                    'Warranty / Chargeable',
                    service_log_display_value($serviceLogRecord['warranty_chargeable'] ?? null),
                    'col-md-4'
                );
                $renderServiceLogDetailField(
                    'Log Date',
                    service_log_format_date($serviceLogRecord['complaint_date'] ?? null),
                    'col-md-4'
                );
                if (!$serviceLogEmbeddedInInstalledBase && !empty($installedBaseRecord) && is_array($installedBaseRecord)) {
                    $renderServiceLogDetailField(
                        'Customer Name',
                        installed_base_display_value($installedBaseRecord['customer_name'] ?? null),
                        'col-md-4'
                    );
                    $renderServiceLogDetailField(
                        'Dealer Name',
                        installed_base_display_value($installedBaseRecord['dealer_name'] ?? null),
                        'col-md-4'
                    );
                }
                ?>
            </div>
        </section>

        <section class="complaint-form-section">
            <div class="complaint-form-section__head">
                <span class="complaint-form-section__badge">2</span>
                <div>
                    <h3 class="complaint-form-section__title">Issue / Services</h3>
                    <p class="complaint-form-section__hint">Problem description and service visit details</p>
                </div>
            </div>
            <div class="row g-3">
                <?php
                $renderServiceLogDetailField(
                    'Issue / Service Description',
                    service_log_display_value($serviceLogRecord['issue_description'] ?? null),
                    'col-12',
                    true
                );
                $renderServiceLogDetailField(
                    'Engineer Name',
                    service_log_display_value($serviceLogRecord['engineer_name'] ?? null),
                    'col-md-4'
                );
                $renderServiceLogDetailField(
                    'Visit Date',
                    service_log_format_date($serviceLogRecord['visit_date'] ?? null),
                    'col-md-4'
                );
                $renderServiceLogDetailField(
                    'Closure Date',
                    service_log_format_date($serviceLogRecord['closure_date'] ?? null),
                    'col-md-4'
                );
                $renderServiceLogDetailField(
                    'Action Taken',
                    service_log_display_value($serviceLogRecord['action_taken'] ?? null),
                    'col-12',
                    true
                );
                ?>
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
                <?php
                $renderServiceLogDetailField(
                    'Part Replaced',
                    service_log_display_value($serviceLogRecord['part_replaced'] ?? null),
                    'col-md-4'
                );
                ?>
                 <?php if ($partReplacedYes) { ?>
          
                <?php
                $renderServiceLogDetailField(
                    'Running Hours / Load Hours',
                    service_log_display_value($serviceLogRecord['running_hours'] ?? null),
                    'col-md-4'
                );
                ?>
            </div>
      

           

            <div class="mt-3">
                <div class="mb-2">
                    <strong>Part Replaced Details</strong>
                </div>
                <?php if (!empty($partReplacements)) { ?>
                    <?php foreach (array_values($partReplacements) as $entryIndex => $entry) { ?>
                    <div class="border rounded p-3 mb-3 bg-white">
                        <div class="mb-3">
                            <strong>Entry <?php echo $entryIndex + 1; ?></strong>
                        </div>
                        <div class="row g-3">
                            <?php
                            $renderServiceLogDetailField(
                                'Machine Model / Part',
                                service_log_part_model_label($entry),
                                'col-md-6'
                            );
                            $renderServiceLogDetailField(
                                'Quantity',
                                service_log_display_value($entry['quantity'] ?? null),
                                'col-md-6'
                            );
                            ?>
                        </div>
                    </div>
                    <?php } ?>
                <?php } ?>

                <div class="row g-3">
                    <div class="col-md-4">
                        <strong>Customer Feedback:</strong>
                        <?php echo customer_feedback_rating_display($serviceLogRecord['customer_feedback'] ?? null); ?>
                    </div>
                    <?php
                    $renderServiceLogDetailField(
                        'Remarks',
                        service_log_display_value($serviceLogRecord['remarks'] ?? null),
                        'col-12',
                        true
                    );
                    ?>
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
                    $renderServiceLogDetailField(
                        $consumable['label'] . ' Remaining Date',
                        service_log_format_date($serviceLogRecord[$dateField] ?? null),
                        'col-md-6'
                    );
                    $renderServiceLogDetailField(
                        $consumable['label'] . ' Remaining Hours',
                        service_log_display_value($serviceLogRecord[$hoursField] ?? null),
                        'col-md-6'
                    );
                } ?>
            </div>            
        </section>
        <?php
        // Complaint Details for raised/linked complaints only (service_log_details + installed_base_details)
        if (!isset($obconn) || !($obconn instanceof PDO)) {
            // no-op when connection is unavailable
        } else {
            $serviceLogIdForComplaint = (int) ($serviceLogRecord['id'] ?? 0);
            if ($serviceLogIdForComplaint > 0) {
                require_once __DIR__ . '/complaint_service_log_helpers.php';
                $serviceLogLinkedComplaint = complaint_service_log_linked_complaint_context(
                    $obconn,
                    $serviceLogIdForComplaint
                );
                $serviceLogLinkedComplaintEmbedded = !empty($serviceLogEmbeddedInInstalledBase)
                    || !empty($serviceLogHideRecordHeader);
                include __DIR__ . '/service_log_linked_complaint_details_section.php';
            }
        }
        ?>
    </div>    
</div>