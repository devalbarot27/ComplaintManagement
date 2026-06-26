<?php
/**
 * Renders Spare Parts Consumption details for a single record.
 * Expects: $sparePartsRecord (array), $sparePartsItems (array)
 * Optional: $sparePartsHideRecordHeader (bool), $sparePartsEmbeddedInInstalledBase (bool),
 *           $sparePartsRecordNumber (int), $sparePartsRecordTotal (int), $canViewSparePartsDetails (bool)
 */
$sparePartsHideRecordHeader = !empty($sparePartsHideRecordHeader);
$sparePartsEmbeddedInInstalledBase = !empty($sparePartsEmbeddedInInstalledBase);
$isLastSparePartsRecord = !empty($isLastSparePartsRecord);
$recordId = (int) ($sparePartsRecord['id'] ?? 0);
$recordLink = base64_encode((string) $recordId);
$installedBaseId = (int) ($sparePartsRecord['installed_base_id'] ?? 0);
$serviceLogId = (int) ($sparePartsRecord['service_log_id'] ?? 0);
$installedBaseLink = $installedBaseId > 0 ? base64_encode((string) $installedBaseId) : '';
$serviceLogLink = $serviceLogId > 0 ? base64_encode((string) $serviceLogId) : '';
$itemTotals = spare_parts_items_totals($sparePartsItems);

$installedBaseLabel = $installedBaseId > 0
    ? '#' . $installedBaseId
        . ' - ' . spare_parts_display_value($sparePartsRecord['order_id'] ?? null)
        . ' - ' . spare_parts_display_value($sparePartsRecord['fab_number'] ?? null)
        . ' - ' . spare_parts_display_value($sparePartsRecord['customer_name'] ?? null)
    : '-';

$recordBlockClass = $sparePartsEmbeddedInInstalledBase
    ? 'spare-parts-record-details mb-4 pb-4' . ($isLastSparePartsRecord ? '' : ' border-bottom')
    : 'card border-1 shadow-sm mb-3';
$headerClass = $sparePartsEmbeddedInInstalledBase
    ? 'd-flex justify-content-between align-items-center flex-wrap gap-2 mb-3'
    : 'card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2';
$bodyClass = $sparePartsEmbeddedInInstalledBase
    ? 'complaint-form-body px-0 pt-0 pb-0'
    : 'card-body complaint-form-body px-3 pt-3 pb-3';

$renderSparePartsDetailField = static function (
    string $label,
    string $value,
    string $colClass = 'col-md-3',
    bool $multiline = false,
    bool $allowHtml = false
): void {
    ?>
    <div class="<?php echo htmlspecialchars($colClass, ENT_QUOTES, 'UTF-8'); ?>">
        <strong><?php echo htmlspecialchars($label); ?>:</strong>
        <?php
        if ($allowHtml) {
            echo $value;
        } elseif ($multiline && $value !== '-') {
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
    <?php if (!$sparePartsHideRecordHeader) { ?>
    <div class="<?php echo $headerClass; ?>">
        <div class="d-flex align-items-center gap-2">
            <?php if (!$sparePartsEmbeddedInInstalledBase) { ?>
            <i class="bi bi-gear text-secondary"></i>
            <?php } ?>
            <strong>Spare Parts Consumption #<?php echo $recordId; ?></strong>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            
            <?php if ($itemTotals['count'] > 0) { ?>
            <span class="badge border border-secondary text-secondary">
                <?php echo (int) $itemTotals['count']; ?>
                item<?php echo $itemTotals['count'] === 1 ? '' : 's'; ?>
            </span>
            <?php } ?>
        
            
        </div>
    </div>
    <?php } ?>

    <div class="<?php echo $bodyClass; ?>">
        <?php if ($sparePartsEmbeddedInInstalledBase && !empty($sparePartsRecordNumber) && !empty($sparePartsRecordTotal) && (int) $sparePartsRecordTotal > 1) { ?>
        <p class="text-muted small mb-3">
            Spare Parts Consumption <?php echo (int) $sparePartsRecordNumber; ?>
            of <?php echo (int) $sparePartsRecordTotal; ?>
        </p>
        <?php } ?>

        <?php if (!$sparePartsEmbeddedInInstalledBase) { ?>
        <section class="complaint-form-section">
            <div class="complaint-form-section__head">
                <span class="complaint-form-section__badge">1</span>
                <div>
                    <h3 class="complaint-form-section__title">Machine & Service Link</h3>
                    <p class="complaint-form-section__hint">Linked installed base and optional service record</p>
                </div>
            </div>
            <div class="row g-3">
                <?php
                if ($installedBaseLink !== '') {
                    $installedBaseHtml = '<a href="installed_base_details.php?id='
                        . htmlspecialchars($installedBaseLink, ENT_QUOTES, 'UTF-8')
                        . '">'
                        . htmlspecialchars($installedBaseLabel)
                        . '</a>';
                } else {
                    $installedBaseHtml = htmlspecialchars($installedBaseLabel);
                }

                $renderSparePartsDetailField('Installed Base', $installedBaseHtml, 'col-md-6', false, true);
                $renderSparePartsDetailField('Order ID', spare_parts_display_value($sparePartsRecord['order_id'] ?? null), 'col-md-3');
                $renderSparePartsDetailField('Fab Number', spare_parts_display_value($sparePartsRecord['fab_number'] ?? null), 'col-md-3');
                $renderSparePartsDetailField('Serial Number', spare_parts_display_value($sparePartsRecord['serial_number'] ?? null), 'col-md-3');
                $renderSparePartsDetailField('Machine Model', spare_parts_display_value($sparePartsRecord['machine_model'] ?? null), 'col-md-3');
                $renderSparePartsDetailField('Customer Name', spare_parts_display_value($sparePartsRecord['customer_name'] ?? null), 'col-md-3');

                if ($serviceLogLink !== '') {
                    $serviceLogHtml = '<a href="service_log_details.php?id='
                        . htmlspecialchars($serviceLogLink, ENT_QUOTES, 'UTF-8')
                        . '">#'
                        . $serviceLogId
                        . '</a>';
                    $renderSparePartsDetailField('Service Record', $serviceLogHtml, 'col-md-3', false, true);
                } else {
                    $renderSparePartsDetailField('Service Record', '-', 'col-md-3');
                }
                ?>
            </div>
        </section>
        <?php } elseif ($serviceLogLink !== '') { ?>
        <div class="row g-3 mb-3">
            <?php
            $serviceLogHtml = '<a href="service_log_details.php?id='
                . htmlspecialchars($serviceLogLink, ENT_QUOTES, 'UTF-8')
                . '">#'
                . $serviceLogId
                . '</a>';
            //$renderSparePartsDetailField('Service Record', $serviceLogHtml, 'col-md-4', false, true);
            $renderSparePartsDetailField(
                'Serial Number',
                spare_parts_display_value($sparePartsRecord['serial_number'] ?? null),
                'col-md-4'
            );
            $renderSparePartsDetailField(
                'Recorded By',
                spare_parts_display_value($sparePartsRecord['username'] ?? null),
                'col-md-4'
            );
            ?>
        </div>
        <?php } ?>

        <section class="complaint-form-section">
            <div class="complaint-form-section__head">
                <span class="complaint-form-section__badge"><?php echo $sparePartsEmbeddedInInstalledBase ? '1' : '2'; ?></span>
                <div>
                    <h3 class="complaint-form-section__title">Consumption Details</h3>
                    <p class="complaint-form-section__hint">Date, warranty type and machine usage at time of consumption</p>
                </div>
            </div>
            <div class="row g-3">
                <?php
                $renderSparePartsDetailField(
                    'Consumption Date',
                    spare_parts_format_date($sparePartsRecord['consumption_date'] ?? null),
                    'col-md-4'
                );
                $renderSparePartsDetailField(
                    'Warranty / Chargeable',
                    spare_parts_display_value($sparePartsRecord['warranty_chargeable'] ?? null),
                    'col-md-4'
                );
                $renderSparePartsDetailField(
                    'Running Hours',
                    spare_parts_display_value($sparePartsRecord['running_hours'] ?? null),
                    'col-md-4'
                );
                if (!$sparePartsEmbeddedInInstalledBase || $serviceLogLink === '') {
                    $renderSparePartsDetailField(
                        'Recorded By',
                        spare_parts_display_value($sparePartsRecord['username'] ?? null),
                        'col-md-3'
                    );
                }
                $renderSparePartsDetailField(
                    'Remarks',
                    spare_parts_display_value($sparePartsRecord['remarks'] ?? null),
                    'col-12',
                    true
                );
                ?>
            </div>
        </section>

        <section class="complaint-form-section mb-0">
            <div class="complaint-form-section__head">
                <span class="complaint-form-section__badge"><?php echo $sparePartsEmbeddedInInstalledBase ? '2' : '3'; ?></span>
                <div>
                    <h3 class="complaint-form-section__title">Spare Parts Items</h3>
                    <p class="complaint-form-section__hint">Kit numbers, quantities and order values consumed</p>
                </div>
            </div>

            <?php if ($sparePartsItems === []) { ?>
            <div class="border rounded p-4 bg-white text-center text-muted">
                <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                No spare part items recorded.
            </div>
            <?php } else { ?>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 bg-white h-100">
                        <div class="text-muted small mb-1">
                            <i class="bi bi-box-seam"></i> Total Items
                        </div>
                        <div class="fs-5 fw-semibold text-dark"><?php echo (int) $itemTotals['count']; ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 bg-white h-100">
                        <div class="text-muted small mb-1">
                            <i class="bi bi-123"></i> Total Quantity
                        </div>
                        <div class="fs-5 fw-semibold text-dark">
                            <?php echo spare_parts_format_quantity($itemTotals['quantity']); ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 bg-white h-100">
                        <div class="text-muted small mb-1">
                            <i class="bi bi-currency-rupee"></i> Total Order Value
                        </div>
                        <div class="fs-5 fw-semibold text-dark">
                            <?php echo spare_parts_format_currency($itemTotals['order_value']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="booking-card border rounded overflow-hidden bg-white">
                <div class="table-responsive">
                    <table class="table table-hover booking-table mb-0">
                        <thead>
                            <tr>
                                <th width="6%">#</th>
                                <th width="28%">Spare Kit Number</th>
                                <th width="22%">Reason</th>
                                <th width="16%">Quantity</th>
                                <th width="18%">Order Value (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sparePartsItems as $index => $item) { ?>
                            <tr>
                                <td class="text-muted"><?php echo (int) ($index + 1); ?></td>
                                <td class="fw-semibold">
                                    <?php echo htmlspecialchars(spare_parts_display_value($item['spare_kit_number'])); ?>
                                </td>
                                <td>
                                    <span class="badge border border-secondary text-secondary fw-normal">
                                        <?php echo htmlspecialchars(spare_parts_display_value($item['reason'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(spare_parts_display_value($item['quantity'])); ?></td>
                                <td><?php echo spare_parts_format_currency($item['order_value']); ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                        <?php if ($itemTotals['count'] > 1) { ?>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3" class="text-end fw-semibold">Totals</td>
                                <td class="fw-semibold">
                                    <?php echo spare_parts_format_quantity($itemTotals['quantity']); ?>
                                </td>
                                <td class="fw-semibold">
                                    <?php echo spare_parts_format_currency($itemTotals['order_value']); ?>
                                </td>
                            </tr>
                        </tfoot>
                        <?php } ?>
                    </table>
                </div>
            </div>
            <?php } ?>
        </section>
    </div>
</div>
