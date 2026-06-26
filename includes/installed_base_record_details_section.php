<?php
/**
 * Renders Installed Base Capture details for a single record.
 * Expects: $installedBaseRecord (array)
 * Optional: $installedBaseHideRecordHeader (bool)
 */
$installedBaseHideRecordHeader = !empty($installedBaseHideRecordHeader);
$recordId = (int) ($installedBaseRecord['id'] ?? 0);

$renderInstalledBaseDetailField = static function (
    string $label,
    string $value,
    string $colClass = 'col-md-4',
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
<div class="card border-1 shadow-sm mb-3">
    <?php if (!$installedBaseHideRecordHeader) { ?>
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-hdd-stack text-secondary"></i>
            <strong>Installed Base #<?php echo $recordId; ?></strong>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge border border-dark text-dark">
                <?php echo htmlspecialchars(installed_base_display_value($installedBaseRecord['order_id'] ?? null)); ?>
            </span>
            <span class="badge border border-secondary text-secondary">
                <?php echo htmlspecialchars(installed_base_display_value($installedBaseRecord['fab_number'] ?? null)); ?>
            </span>
            <span class="text-muted small">
                <i class="bi bi-calendar-check"></i>
                Commissioned <?php echo installed_base_format_date($installedBaseRecord['commissioning_date'] ?? null); ?>
            </span>
            <span class="text-muted small">
                <i class="bi bi-clock-history"></i>
                <?php echo htmlspecialchars(installed_base_display_value($installedBaseRecord['running_hours'] ?? null)); ?> hrs
            </span>
        </div>
    </div>
    <?php } ?>

    <div class="card-body complaint-form-body px-3 pt-3 pb-3">
        <section class="complaint-form-section">
            <div class="complaint-form-section__head">
                <span class="complaint-form-section__badge">1</span>
                <div>
                    <h3 class="complaint-form-section__title">Order & Machine</h3>
                    <p class="complaint-form-section__hint">Order reference, machine identity and commissioning dates</p>
                </div>
            </div>
            <div class="row g-3">
                <?php
                $renderInstalledBaseDetailField(
                    'Order ID',
                    installed_base_display_value($installedBaseRecord['order_id'] ?? null),
                    'col-md-4'
                );
                $renderInstalledBaseDetailField(
                    'Fab Number',
                    installed_base_display_value($installedBaseRecord['fab_number'] ?? null),
                    'col-md-4'
                );
                $renderInstalledBaseDetailField(
                    'Machine Model',
                    installed_base_machine_model_label($installedBaseRecord),
                    'col-md-4'
                );
                $renderInstalledBaseDetailField(
                    'Invoice Date',
                    installed_base_format_date($installedBaseRecord['invoice_date'] ?? null),
                    'col-md-4'
                );
                $renderInstalledBaseDetailField(
                    'Commissioning Date',
                    installed_base_format_date($installedBaseRecord['commissioning_date'] ?? null),
                    'col-md-4'
                );
                $renderInstalledBaseDetailField(
                    'Running Hours',
                    installed_base_display_value($installedBaseRecord['running_hours'] ?? null),
                    'col-md-4'
                );
                ?>
            </div>
        </section>

        <section class="complaint-form-section">
            <div class="complaint-form-section__head">
                <span class="complaint-form-section__badge">2</span>
                <div>
                    <h3 class="complaint-form-section__title">Customer Details</h3>
                    <p class="complaint-form-section__hint">Customer, dealer and installation address</p>
                </div>
            </div>
            <div class="row g-3">
                <?php
                $renderInstalledBaseDetailField(
                    'Customer Name',
                    installed_base_display_value($installedBaseRecord['customer_name'] ?? null),
                    'col-md-6'
                );
                $renderInstalledBaseDetailField(
                    'Dealer Name',
                    installed_base_display_value($installedBaseRecord['dealer_name'] ?? null),
                    'col-md-6'
                );
                $renderInstalledBaseDetailField(
                    'Street 1',
                    installed_base_address_display_value($installedBaseRecord, 'street_1'),
                    'col-md-6',
                    true
                );
                $renderInstalledBaseDetailField(
                    'Street 2',
                    installed_base_address_display_value($installedBaseRecord, 'street_2'),
                    'col-md-6'
                );
                $renderInstalledBaseDetailField(
                    'Pincode',
                    installed_base_address_display_value($installedBaseRecord, 'pincode'),
                    'col-md-3'
                );
                $renderInstalledBaseDetailField(
                    'City',
                    installed_base_address_display_value($installedBaseRecord, 'city'),
                    'col-md-3'
                );
                $renderInstalledBaseDetailField(
                    'District',
                    installed_base_address_display_value($installedBaseRecord, 'district'),
                    'col-md-3'
                );
                $renderInstalledBaseDetailField(
                    'State',
                    installed_base_address_display_value($installedBaseRecord, 'state'),
                    'col-md-3'
                );
                $renderInstalledBaseDetailField(
                    'Mobile',
                    installed_base_display_value($installedBaseRecord['mobile'] ?? null),
                    'col-md-6'
                );
                $renderInstalledBaseDetailField(
                    'Email',
                    installed_base_display_value($installedBaseRecord['email'] ?? null),
                    'col-md-6'
                );
                ?>
            </div>
        </section>

        <section class="complaint-form-section mb-0">
            <div class="complaint-form-section__head">
                <span class="complaint-form-section__badge">3</span>
                <div>
                    <h3 class="complaint-form-section__title">Business Details</h3>
                    <p class="complaint-form-section__hint">Industry segment and additional notes</p>
                </div>
            </div>
            <div class="row g-3">
                <?php
                $renderInstalledBaseDetailField(
                    'Industry Segment',
                    installed_base_display_value($installedBaseRecord['industry_segment'] ?? null),
                    'col-md-6'
                );
                $renderInstalledBaseDetailField(
                    'Added By',
                    installed_base_added_by_label($installedBaseRecord),
                    'col-md-6'
                );
                $renderInstalledBaseDetailField(
                    'Remarks',
                    installed_base_display_value($installedBaseRecord['remarks'] ?? null),
                    'col-12',
                    true
                );
                ?>
            </div>
        </section>
    </div>
</div>
