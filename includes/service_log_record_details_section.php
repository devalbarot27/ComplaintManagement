<?php
/**
 * Renders Service Log Capture details for a single record.
 * Expects: $serviceLogRecord (array), $partReplacements (array)
 * Optional: $serviceLogEmbeddedInInstalledBase (bool), $installedBaseRecord (array)
 */
$serviceLogEmbeddedInInstalledBase = !empty($serviceLogEmbeddedInInstalledBase);
$serviceLogId = (int) ($serviceLogRecord['id'] ?? 0);
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
        <?php if ($serviceLogEmbeddedInInstalledBase && !empty($serviceLogRecordNumber) && !empty($serviceLogRecordTotal) && (int) $serviceLogRecordTotal > 1) { ?>
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
                    service_log_display_value($serviceLogRecord['serial_number'] ?? null),
                    'col-md-4'
                );
                $renderServiceLogDetailField(
                    'Warranty / Chargeable',
                    service_log_display_value($serviceLogRecord['warranty_chargeable'] ?? null),
                    'col-md-4'
                );
                $renderServiceLogDetailField(
                    'Complaint Date',
                    service_log_format_date($serviceLogRecord['complaint_date'] ?? null),
                    'col-md-4'
                );
                if (!$serviceLogEmbeddedInInstalledBase && !empty($installedBaseRecord) && is_array($installedBaseRecord)) {
                    $renderServiceLogDetailField(
                        'Customer Name',
                        installed_base_display_value($installedBaseRecord['customer_name'] ?? null),
                        'col-md-3'
                    );
                    $renderServiceLogDetailField(
                        'Dealer Name',
                        installed_base_display_value($installedBaseRecord['dealer_name'] ?? null),
                        'col-md-3'
                    );
                }
                ?>
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
                <?php
                $renderServiceLogDetailField(
                    'Issue Description',
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
                    'col-md-3'
                );
                ?>
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
                            <?php
                            $renderServiceLogDetailField(
                                'Machine Model / Part',
                                service_log_part_model_label($entry),
                                'col-md-4'
                            );
                            $renderServiceLogDetailField(
                                'Running Hours',
                                service_log_display_value($entry['running_hours'] ?? null),
                                'col-md-4'
                            );
                            $renderServiceLogDetailField(
                                'Loaded Hours',
                                service_log_display_value($entry['loaded_hours'] ?? null),
                                'col-md-4'
                            );
                            ?>
                        </div>
                    </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="border rounded p-3 mb-3 bg-white">
                        <div class="mb-3">
                            <strong>Entry 1</strong>
                        </div>
                        <div class="row g-3">
                            <?php
                            $renderServiceLogDetailField(
                                'Running Hours',
                                service_log_display_value($serviceLogRecord['running_hours'] ?? null),
                                'col-md-4'
                            );
                            $renderServiceLogDetailField(
                                'Loaded Hours',
                                service_log_display_value($serviceLogRecord['loaded_hours'] ?? null),
                                'col-md-4'
                            );
                            ?>
                        </div>
                    </div>
                <?php } ?>

                <div class="row g-3">
                    <?php
                    $renderServiceLogDetailField(
                        'Customer Feedback',
                        service_log_display_value($serviceLogRecord['customer_feedback'] ?? null),
                        'col-md-4'
                    );
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
    </div>
</div>
