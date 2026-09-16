<?php
/**
 * Renders AMC Details on the Service Log details page.
 * Expects: $obconn (PDO), $serviceLogAmcContext from amc_context_for_service_log()
 */
require_once __DIR__ . '/amc_helpers.php';

$serviceLogAmcContext = is_array($serviceLogAmcContext ?? null) ? $serviceLogAmcContext : [];
$amcContract = is_array($serviceLogAmcContext['contract'] ?? null) ? $serviceLogAmcContext['contract'] : null;
$amcVisit = is_array($serviceLogAmcContext['visit'] ?? null) ? $serviceLogAmcContext['visit'] : null;

$renderAmcDetailField = static function (
    string $label,
    string $value,
    string $colClass = 'col-md-3',
    bool $allowHtml = false
): void {
    $display = trim($value) !== '' ? $value : '-';
    ?>
    <div class="<?php echo htmlspecialchars($colClass, ENT_QUOTES, 'UTF-8'); ?>">
        <strong><?php echo htmlspecialchars($label); ?>:</strong><br>
        <?php echo $allowHtml ? $display : htmlspecialchars($display); ?>
    </div>
    <?php
};

$canOpenAmc = false;
$amcDetailsUrl = '';
if ($amcContract && isset($obconn) && $obconn instanceof PDO) {
    $canOpenAmc = rbac_user_can($obconn, 'amc', 'view') && amc_user_can_access_record($obconn, $amcContract);
    if ($canOpenAmc) {
        $amcDetailsUrl = 'amc_details.php?id=' . rawurlencode(base64_encode((string) (int) $amcContract['id']));
    }
}
?>
<div class="card border-1 shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-calendar2-check text-secondary"></i>
            <strong>AMC Details</strong>
        </div>
        <?php if ($amcDetailsUrl !== '') { ?>
        <a href="<?php echo htmlspecialchars($amcDetailsUrl, ENT_QUOTES, 'UTF-8'); ?>"
            class="btn btn-sm btn-outline-dark">
            View Full AMC
        </a>
        <?php } ?>
    </div>
    <div class="card-body">
        <?php if (!$amcContract) { ?>
        <div class="border rounded p-4 bg-white text-center text-muted">
            <i class="bi bi-calendar2-x fs-4 d-block mb-2"></i>
            No AMC contract is linked to this service log or machine.
        </div>
        <?php } else { ?>
        <div class="row g-3">
            <?php
            $contractNumber = trim((string) ($amcContract['contract_number'] ?? ''));
            if ($contractNumber === '') {
                $contractNumber = '#' . (int) ($amcContract['id'] ?? 0);
            }
            if ($amcDetailsUrl !== '') {
                $contractNumberHtml = '<a href="'
                    . htmlspecialchars($amcDetailsUrl, ENT_QUOTES, 'UTF-8')
                    . '">'
                    . htmlspecialchars($contractNumber)
                    . '</a>';
                $renderAmcDetailField('Contract Number', $contractNumberHtml, 'col-md-3', true);
            } else {
                $renderAmcDetailField('Contract Number', $contractNumber);
            }
            $statusHtml = '<span class="status-badge border border-dark">'
                . htmlspecialchars(amc_display_status($amcContract))
                . '</span>';
            $renderAmcDetailField('Status', $statusHtml, 'col-md-3', true);
            $renderAmcDetailField(
                'AMC Type',
                (string) (AMC_TYPE_OPTIONS[$amcContract['amc_type']] ?? ($amcContract['amc_type'] ?? '-'))
            );
            $renderAmcDetailField(
                'AMC Value',
                number_format((float) ($amcContract['amc_value'] ?? 0), 2)
            );
            $renderAmcDetailField(
                'AMC Start Date',
                amc_format_date($amcContract['amc_start_date'] ?? null)
            );
            $renderAmcDetailField(
                'AMC End Date',
                amc_format_date($amcContract['amc_end_date'] ?? null)
            );
            $renderAmcDetailField(
                'Visit Start Date',
                amc_format_date($amcContract['visit_start_date'] ?? null)
            );
            $renderAmcDetailField(
                'Number of Visits',
                (string) ((int) ($amcContract['no_of_visits'] ?? 0))
            );
            if ($amcVisit) {
                $visitStatusHtml = '<span class="status-badge border border-dark">'
                    . htmlspecialchars((string) ($amcVisit['visit_status'] ?? '-'))
                    . '</span>';
                $renderAmcDetailField(
                    'Linked Visit',
                    '#' . (int) ($amcVisit['visit_number'] ?? 0)
                );
                $renderAmcDetailField(
                    'Visit Date',
                    amc_format_date($amcVisit['visit_date'] ?? null)
                );
                $renderAmcDetailField('Visit Status', $visitStatusHtml, 'col-md-3', true);
                $renderAmcDetailField(
                    'Completed Date',
                    amc_format_date($amcVisit['completed_date'] ?? null)
                );
            }
            ?>
        </div>
        <?php } ?>
    </div>
</div>
