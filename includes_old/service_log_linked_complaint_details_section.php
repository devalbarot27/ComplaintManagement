<?php
/**
 * Renders Complaint Details linked to a Service Log.
 * Shows only when a raised/linked complaint exists.
 * Expects: $serviceLogLinkedComplaint (array from complaint_service_log_linked_complaint_context)
 * Optional: $serviceLogLinkedComplaintEmbedded (bool) — compact layout when nested in service log card
 */
require_once __DIR__ . '/complaint_status.php';
require_once __DIR__ . '/complaint_category_helpers.php';
require_once __DIR__ . '/complaint_address_helpers.php';

$serviceLogLinkedComplaint = is_array($serviceLogLinkedComplaint ?? null)
    ? $serviceLogLinkedComplaint
    : [
        'associated' => false,
        'complaint' => null,
    ];

$isAssociated = !empty($serviceLogLinkedComplaint['associated']);
$complaint = is_array($serviceLogLinkedComplaint['complaint'] ?? null)
    ? $serviceLogLinkedComplaint['complaint']
    : null;

// Show only for raised/linked complaints with a resolvable complaint record
if (!$isAssociated || !$complaint) {
    return;
}

$serviceLogLinkedComplaintEmbedded = !empty($serviceLogLinkedComplaintEmbedded);

$renderLinkedComplaintField = static function (
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

$wrapperClass = $serviceLogLinkedComplaintEmbedded
    ? 'service-log-linked-complaint-card service-log-linked-complaint-card--highlight service-log-linked-complaint-card--embedded mt-3'
    : 'card mb-3 service-log-linked-complaint-card service-log-linked-complaint-card--highlight mt-3';
?>
<div class="<?php echo htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($serviceLogLinkedComplaintEmbedded) { ?>
    <div class="service-log-linked-complaint-card__inner">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <strong>Complaint Details</strong>
            <?php if (!empty($serviceLogLinkedComplaint['complaint_view_url'])) { ?>
            <a href="<?php echo htmlspecialchars((string) $serviceLogLinkedComplaint['complaint_view_url'], ENT_QUOTES, 'UTF-8'); ?>"
                class="btn btn-sm btn-outline-dark">
                View Full Complaint
            </a>
            <?php } ?>
        </div>
    <?php } else { ?>
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <strong>Complaint Details</strong>
        <?php if (!empty($serviceLogLinkedComplaint['complaint_view_url'])) { ?>
        <a href="<?php echo htmlspecialchars((string) $serviceLogLinkedComplaint['complaint_view_url'], ENT_QUOTES, 'UTF-8'); ?>"
            class="btn btn-sm btn-outline-dark">
            View Full Complaint
        </a>
        <?php } ?>
    </div>
    <div class="card-body complaint-form-body px-3 pt-3 pb-3">
    <?php } ?>
        <section class="complaint-form-section mb-0">
            <div class="row g-3">
                <?php
                $renderLinkedComplaintField(
                    'Complaint Number',
                    '#' . (int) ($complaint['id'] ?? 0),
                    'col-md-3'
                );
                $renderLinkedComplaintField(
                    'Complaint Date',
                    !empty($complaint['created_at'])
                        ? date('d M Y h:i A', strtotime((string) $complaint['created_at']))
                        : '',
                    'col-md-3'
                );
                $renderLinkedComplaintField(
                    'Complaint Status',
                    complaint_status_label((int) ($complaint['status'] ?? 0)),
                    'col-md-3'
                );
                $renderLinkedComplaintField(
                    'Complaint Category',
                    complaint_category_display_name($complaint),
                    'col-md-3'
                );
                $renderLinkedComplaintField(
                    'Customer Name',
                    (string) ($complaint['customer_name'] ?? ''),
                    'col-md-4'
                );
                $renderLinkedComplaintField(
                    'Assigned Engineer',
                    (string) ($serviceLogLinkedComplaint['assigned_engineer'] ?? ''),
                    'col-md-4'
                );
                $renderLinkedComplaintField(
                    'Complaint Closure Date',
                    !empty($serviceLogLinkedComplaint['closure_datetime'])
                        ? date('d M Y h:i A', strtotime((string) $serviceLogLinkedComplaint['closure_datetime']))
                        : '',
                    'col-md-4'
                );
                $renderLinkedComplaintField(
                    'Machine FAB Number',
                    (string) ($complaint['fab_number'] ?? ''),
                    'col-md-4'
                );
                $renderLinkedComplaintField(
                    'Machine Model',
                    (string) ($serviceLogLinkedComplaint['machine_model'] ?? ''),
                    'col-md-4'
                );
                $renderLinkedComplaintField(
                    'Created By',
                    (string) ($complaint['added_by_name'] ?? ($complaint['username'] ?? '')),
                    'col-md-4'
                );
                $renderLinkedComplaintField(
                    'Complaint Description',
                    (string) ($complaint['complaint_description'] ?? ''),
                    'col-12',
                    true
                );
                ?>
            </div>
        </section>
    <?php if ($serviceLogLinkedComplaintEmbedded) { ?>
    </div>
    <?php } else { ?>
    </div>
    <?php } ?>
</div>