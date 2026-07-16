<?php
/**
 * Renders complaint record details.
 * Expects: $complaint (array), $statusMap (array)
 */

$renderComplaintDetailField = static function (
    string $label,
    string $value,
    string $colClass = 'col-md-4',
    bool $multiline = false
): void {
    $display = trim($value) !== '' ? $value : '-';
    ?>
    <div class="<?php echo htmlspecialchars($colClass, ENT_QUOTES, 'UTF-8'); ?> complaint-detail-field">
        <div class="complaint-detail-field__label"><?php echo htmlspecialchars($label); ?></div>
        <div class="complaint-detail-field__value<?php echo $multiline ? ' complaint-detail-field__value--multiline' : ''; ?>">
            <?php
            if ($multiline && $display !== '-') {
                echo nl2br(htmlspecialchars($display));
            } else {
                echo htmlspecialchars($display);
            }
            ?>
        </div>
    </div>
    <?php
};

$complaintStatusLabel = $statusMap[$complaint['status']] ?? 'Unknown';
?>
<div class="card border-1 shadow-sm mb-3">
    <div class="card-body complaint-form-body px-3 pt-3 pb-3">
        <section class="complaint-form-section">
            <div class="complaint-form-section__head">
                <span class="complaint-form-section__badge">1</span>
                <div>
                    <h3 class="complaint-form-section__title">Machine & Reference</h3>
                    <p class="complaint-form-section__hint">Equipment reference and complaint classification</p>
                </div>
            </div>
            <div class="row g-3">
                <?php
                $renderComplaintDetailField(
                    'Fab Number',
                    (string) ($complaint['fab_number'] ?? ''),
                    'col-md-4'
                );
                $renderComplaintDetailField(
                    'Complaint Category',
                    complaint_category_display_name($complaint),
                    'col-md-4'
                );
                $renderComplaintDetailField(
                    'Status',
                    $complaintStatusLabel,
                    'col-md-4'
                );
                ?>
            </div>
        </section>

        <section class="complaint-form-section">
            <div class="complaint-form-section__head">
                <span class="complaint-form-section__badge">2</span>
                <div>
                    <h3 class="complaint-form-section__title">Customer & Address</h3>
                    <p class="complaint-form-section__hint">Customer identity and installation location</p>
                </div>
            </div>
            <div class="row g-3">
                <?php
                $renderComplaintDetailField(
                    'Customer Name',
                    (string) ($complaint['customer_name'] ?? ''),
                    'col-md-6'
                );
                $renderComplaintDetailField(
                    'Street 1',
                    complaint_address_display_value($complaint, 'street_1'),
                    'col-md-6',
                    true
                );
                $renderComplaintDetailField(
                    'Street 2',
                    complaint_address_display_value($complaint, 'street_2'),
                    'col-md-6'
                );
                $renderComplaintDetailField(
                    'Pincode',
                    complaint_address_display_value($complaint, 'pincode'),
                    'col-md-3'
                );
                $renderComplaintDetailField(
                    'City',
                    complaint_address_display_value($complaint, 'city'),
                    'col-md-3'
                );
                $renderComplaintDetailField(
                    'District',
                    complaint_address_display_value($complaint, 'district'),
                    'col-md-3'
                );
                $renderComplaintDetailField(
                    'State',
                    complaint_address_display_value($complaint, 'state'),
                    'col-md-3'
                );
                ?>
            </div>
        </section>

        <section class="complaint-form-section mb-0">
            <div class="complaint-form-section__head">
                <span class="complaint-form-section__badge">3</span>
                <div>
                    <h3 class="complaint-form-section__title">Complaint Details</h3>
                    <p class="complaint-form-section__hint">Issue description and record metadata</p>
                </div>
            </div>
            <div class="row g-3">
                <?php
                $renderComplaintDetailField(
                    'Complaint Description',
                    (string) ($complaint['complaint_description'] ?? ''),
                    'col-12',
                    true
                );
                $renderComplaintDetailField(
                    'Created By',
                    (string) ($complaint['added_by_name'] ?? '-'),
                    'col-md-6'
                );
                $renderComplaintDetailField(
                    'Created At',
                    !empty($complaint['created_at'])
                        ? date('d M Y h:i A', strtotime($complaint['created_at']))
                        : '-',
                    'col-md-6'
                );
                ?>
            </div>
        </section>
    </div>
</div>