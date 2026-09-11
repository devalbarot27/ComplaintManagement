<?php

session_start();

include 'pdo_obconn.php';
require_once 'includes/admin_access_helpers.php';
require_once 'includes/login_helpers.php';

if (empty($_SESSION['usr_name'])) {
    header('Location: login.php');
    exit;
}

require_system_admin($obconn);

$success_message = '';
$error_message = '';

/**
 * @return array<string, array{label: string, icon: string, note: string, count_fields: array<int, array{key: string, label: string}>}>
 */
function flush_module_definitions(): array
{
    return [
        'installed_base' => [
            'label' => 'Installed Base Capture',
            'icon' => 'bi-hdd-stack',
            'note' => 'Also deletes linked service logs and spare parts, and clears Installed Base links on complaints and AMC.',
            'count_fields' => [
                ['key' => 'installed_base', 'label' => 'Installed Base'],
            ],
        ],
        'service_log' => [
            'label' => 'Service Log Capture',
            'icon' => 'bi-journal-text',
            'note' => '',
            'count_fields' => [
                ['key' => 'service_logs', 'label' => 'Service logs'],
                ['key' => 'service_log_part_replacements', 'label' => 'Part replacements'],
                ['key' => 'complaint_service_logs', 'label' => 'Complaint mappings'],
            ],
        ],
        'spare_parts' => [
            'label' => 'Spare Parts Consumption',
            'icon' => 'bi-box-seam',
            'note' => '',
            'count_fields' => [
                ['key' => 'spare_parts_consumption', 'label' => 'Consumption records'],
                ['key' => 'spare_parts_consumption_items', 'label' => 'Line items'],
            ],
        ],
        'order_creation' => [
            'label' => 'Order Creation',
            'icon' => 'bi-cart-check',
            'note' => 'Deletes carts, submitted orders, and order approvals. Also removes FOC/Service Claim LN orders in Recent Orders.',
            'count_fields' => [
                ['key' => 'tbl_vayu_cartitems', 'label' => 'Cart items'],
                ['key' => 'plexecom_customer_units', 'label' => 'Submitted orders'],
                ['key' => 'order_approval_requests', 'label' => 'Approval requests'],
                ['key' => 'order_approval_history', 'label' => 'Approval history'],
                ['key' => 'tbl_vayu_orders_header', 'label' => 'Order headers'],
            ],
        ],
        'complaint' => [
            'label' => 'Complaint Entry',
            'icon' => 'bi-exclamation-octagon',
            'note' => 'Also deletes FOC parts, warranty service claims, and assignments linked to complaints.',
            'count_fields' => [
                ['key' => 'complaints', 'label' => 'Complaints'],
                ['key' => 'complaint_activity_logs', 'label' => 'Activity logs'],
                ['key' => 'complaint_service_updates', 'label' => 'Service updates'],
                ['key' => 'complaint_closures', 'label' => 'Closures'],
                ['key' => 'complaint_nudge_logs', 'label' => 'Nudge logs'],
            ],
        ],
        'assign_complaint' => [
            'label' => 'Assign Complaint',
            'icon' => 'bi-person-check',
            'note' => '',
            'count_fields' => [
                ['key' => 'complaint_assignments', 'label' => 'Assignments'],
            ],
        ],
        'amc' => [
            'label' => 'AMC',
            'icon' => 'bi-calendar2-check',
            'note' => '',
            'count_fields' => [
                ['key' => 'amc_contracts', 'label' => 'Contracts'],
                ['key' => 'amc_visits', 'label' => 'Visits'],
            ],
        ],
        'foc' => [
            'label' => 'FOC Part',
            'icon' => 'bi-gift',
            'note' => '',
            'count_fields' => [
                ['key' => 'foc_claims', 'label' => 'Claims'],
                ['key' => 'foc_claim_items', 'label' => 'Claim items'],
            ],
        ],
        'service_claims' => [
            'label' => 'Warranty Service Claims',
            'icon' => 'bi-shield-check',
            'note' => '',
            'count_fields' => [
                ['key' => 'service_claims', 'label' => 'Service claims'],
            ],
        ],
    ];
}

/**
 * @param array<int, array{key: string, label: string}> $fields
 * @param array<string, int> $counts
 */
function flush_module_total(array $fields, array $counts): int
{
    $total = 0;
    foreach ($fields as $field) {
        $total += (int) ($counts[$field['key']] ?? 0);
    }

    return $total;
}

function flush_table_exists(PDO $conn, string $table): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = :table_name
        LIMIT 1
    ");
    $stmt->bindValue(':table_name', $table);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function flush_column_exists(PDO $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = :table_name
          AND column_name = :column_name
        LIMIT 1
    ");
    $stmt->bindValue(':table_name', $table);
    $stmt->bindValue(':column_name', $column);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function flush_table_count(PDO $conn, string $table): int
{
    if (!flush_table_exists($conn, $table)) {
        return 0;
    }

    $stmt = $conn->query('SELECT COUNT(*) FROM ' . $table);

    return (int) $stmt->fetchColumn();
}

function flush_delete_table(PDO $conn, string $table): int
{
    if (!flush_table_exists($conn, $table)) {
        return 0;
    }

    $deleted = (int) $conn->exec('DELETE FROM ' . $table);
    flush_reset_table_sequence($conn, $table, 'id');
    if (flush_column_exists($conn, $table, 'oid')) {
        flush_reset_table_sequence($conn, $table, 'oid');
    }

    return $deleted;
}

function flush_reset_table_sequence(PDO $conn, string $table, string $column): void
{
    if (!flush_column_exists($conn, $table, $column)) {
        return;
    }

    flush_run_best_effort($conn, static function (PDO $conn) use ($table, $column): void {
        $seqStmt = $conn->prepare('SELECT pg_get_serial_sequence(:table_name, :column_name)');
        $seqStmt->bindValue(':table_name', $table);
        $seqStmt->bindValue(':column_name', $column);
        $seqStmt->execute();
        $sequence = trim((string) $seqStmt->fetchColumn());
        if ($sequence !== '') {
            $conn->exec('SELECT setval(' . $conn->quote($sequence) . ', 1, false)');
        }
    });
}

function flush_reset_named_sequence(PDO $conn, string $sequence): void
{
    $sequence = trim($sequence);
    if ($sequence === '') {
        return;
    }

    flush_run_best_effort($conn, static function (PDO $conn) use ($sequence): void {
        $conn->exec('SELECT setval(' . $conn->quote($sequence) . ', 1, false)');
    });
}

/**
 * Run a statement without aborting the outer flush transaction.
 */
function flush_run_best_effort(PDO $conn, callable $callback): void
{
    $savepoint = 'flush_best_effort';
    $conn->exec('SAVEPOINT ' . $savepoint);

    try {
        $callback($conn);
    } catch (PDOException $e) {
        $conn->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
    }

    $conn->exec('RELEASE SAVEPOINT ' . $savepoint);
}

function flush_null_column(PDO $conn, string $table, string $column): void
{
    if (!flush_table_exists($conn, $table) || !flush_column_exists($conn, $table, $column)) {
        return;
    }

    $conn->exec('UPDATE ' . $table . ' SET ' . $column . ' = NULL WHERE ' . $column . ' IS NOT NULL');
}

/**
 * @return array<string, int>
 */
function flush_spare_parts_module(PDO $conn): array
{
    return [
        'spare_parts_consumption_items' => flush_delete_table($conn, 'spare_parts_consumption_items'),
        'spare_parts_consumption' => flush_delete_table($conn, 'spare_parts_consumption'),
    ];
}

/**
 * @return array<string, int>
 */
function flush_service_log_module(PDO $conn): array
{
    flush_null_column($conn, 'spare_parts_consumption', 'service_log_id');

    return [
        'complaint_service_logs' => flush_delete_table($conn, 'complaint_service_logs'),
        'service_log_part_replacements' => flush_delete_table($conn, 'service_log_part_replacements'),
        'service_logs' => flush_delete_table($conn, 'service_logs'),
    ];
}

/**
 * @return array<string, int>
 */
function flush_installed_base_records(PDO $conn): array
{
    flush_null_column($conn, 'complaints', 'installed_base_id');
    flush_null_column($conn, 'amc_contracts', 'installed_base_id');

    return [
        'installed_base' => flush_delete_table($conn, 'installed_base'),
    ];
}

/**
 * @return array<string, int>
 */
function flush_installed_base_module(PDO $conn): array
{
    return array_merge(
        flush_spare_parts_module($conn),
        flush_service_log_module($conn),
        flush_installed_base_records($conn)
    );
}

/**
 * @return array<string, int>
 */
function flush_foc_module(PDO $conn): array
{
    return [
        'foc_claim_items' => flush_delete_table($conn, 'foc_claim_items'),
        'foc_claims' => flush_delete_table($conn, 'foc_claims'),
    ];
}

/**
 * @return array<string, int>
 */
function flush_service_claims_module(PDO $conn): array
{
    flush_null_column($conn, 'complaint_closures', 'service_claim_id');

    return [
        'service_claims' => flush_delete_table($conn, 'service_claims'),
    ];
}

/**
 * @return array<string, int>
 */
function flush_amc_module(PDO $conn): array
{
    return [
        'amc_visits' => flush_delete_table($conn, 'amc_visits'),
        'amc_contracts' => flush_delete_table($conn, 'amc_contracts'),
    ];
}

/**
 * @return array<string, int>
 */
function flush_assign_complaint_module(PDO $conn): array
{
    return [
        'complaint_assignments' => flush_delete_table($conn, 'complaint_assignments'),
    ];
}

/**
 * @return array<string, int>
 */
function flush_order_creation_module(PDO $conn): array
{
    $deleted = [
        'order_approval_history' => flush_delete_table($conn, 'order_approval_history'),
        'order_approval_requests' => flush_delete_table($conn, 'order_approval_requests'),
        'cart_approval_history' => flush_delete_table($conn, 'cart_approval_history'),
        'cart_approval_requests' => flush_delete_table($conn, 'cart_approval_requests'),
        'order_approval_requests_legacy' => flush_delete_table($conn, 'order_approval_requests_legacy'),
        'tbl_vayu_cartitems' => flush_delete_table($conn, 'tbl_vayu_cartitems'),
        'tbl_vayu_orders_line' => flush_delete_table($conn, 'tbl_vayu_orders_line'),
        'tbl_vayu_orders_header' => flush_delete_table($conn, 'tbl_vayu_orders_header'),
        'tbl_vayu_orders' => flush_delete_table($conn, 'tbl_vayu_orders'),
        'orders' => flush_delete_table($conn, 'orders'),
        'plexecom_customer_units' => flush_delete_table($conn, 'plexecom_customer_units'),
    ];

    flush_reset_named_sequence($conn, 'dp_spares');

    return $deleted;
}

/**
 * @return array<string, int>
 */
function flush_complaint_child_tables(PDO $conn): array
{
    return [
        'complaint_nudge_logs' => flush_delete_table($conn, 'complaint_nudge_logs'),
        'complaint_activity_logs' => flush_delete_table($conn, 'complaint_activity_logs'),
        'complaint_service_updates' => flush_delete_table($conn, 'complaint_service_updates'),
        'complaint_closures' => flush_delete_table($conn, 'complaint_closures'),
    ];
}

/**
 * @return array<string, int>
 */
function flush_complaint_entry_module(PDO $conn): array
{
    $childCounts = array_merge(
        flush_foc_module($conn),
        flush_service_claims_module($conn),
        [
            'complaint_service_logs' => flush_delete_table($conn, 'complaint_service_logs'),
        ],
        flush_complaint_child_tables($conn),
        flush_assign_complaint_module($conn)
    );

    return array_merge($childCounts, [
        'complaints' => flush_delete_table($conn, 'complaints'),
    ]);
}

/**
 * @return array<string, int>
 */
function flush_all_modules(PDO $conn): array
{
    return array_merge(
        flush_spare_parts_module($conn),
        flush_service_log_module($conn),
        flush_order_creation_module($conn),
        flush_foc_module($conn),
        flush_service_claims_module($conn),
        flush_assign_complaint_module($conn),
        flush_complaint_child_tables($conn),
        [
            'complaints' => flush_delete_table($conn, 'complaints'),
        ],
        flush_amc_module($conn),
        flush_installed_base_records($conn)
    );
}

/**
 * @param array<string, int> $deleted
 */
function flush_summary_message(string $label, array $deleted): string
{
    $parts = [];
    foreach ($deleted as $table => $count) {
        $parts[] = $table . ': ' . $count;
    }

    return $label . ' records deleted. ' . implode(', ', $parts) . '.';
}

$modules = flush_module_definitions();
$allowedTargets = array_merge(array_keys($modules), ['all']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['flush_module'])) {
    $target = trim((string) ($_POST['flush_target'] ?? ''));
    $confirm = strtoupper(trim((string) ($_POST['confirm_text'] ?? '')));

    if (!in_array($target, $allowedTargets, true)) {
        $error_message = 'Invalid module selection.';
    } elseif ($confirm !== 'FLUSH') {
        $error_message = 'Type FLUSH to confirm before deleting records.';
    } else {
        try {
            $obconn->beginTransaction();
            if ($target === 'spare_parts') {
                $deleted = flush_spare_parts_module($obconn);
                $label = $modules['spare_parts']['label'];
            } elseif ($target === 'service_log') {
                $deleted = flush_service_log_module($obconn);
                $label = $modules['service_log']['label'];
            } elseif ($target === 'installed_base') {
                $deleted = flush_installed_base_module($obconn);
                $label = $modules['installed_base']['label'];
            } elseif ($target === 'complaint') {
                $deleted = flush_complaint_entry_module($obconn);
                $label = $modules['complaint']['label'];
            } elseif ($target === 'assign_complaint') {
                $deleted = flush_assign_complaint_module($obconn);
                $label = $modules['assign_complaint']['label'];
            } elseif ($target === 'amc') {
                $deleted = flush_amc_module($obconn);
                $label = $modules['amc']['label'];
            } elseif ($target === 'foc') {
                $deleted = flush_foc_module($obconn);
                $label = $modules['foc']['label'];
            } elseif ($target === 'service_claims') {
                $deleted = flush_service_claims_module($obconn);
                $label = $modules['service_claims']['label'];
            } elseif ($target === 'order_creation') {
                $deleted = flush_order_creation_module($obconn);
                $label = $modules['order_creation']['label'];
            } else {
                $deleted = flush_all_modules($obconn);
                $label = 'All listed modules';
            }
            $obconn->commit();
            $success_message = flush_summary_message($label, $deleted);
        } catch (Throwable $e) {
            if ($obconn->inTransaction()) {
                $obconn->rollBack();
            }
            error_log('flush_module_tables.php: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $error_message = 'Failed to delete records. ' . $e->getMessage();
        }
    }
}

$counts = [
    'installed_base' => flush_table_count($obconn, 'installed_base'),
    'service_logs' => flush_table_count($obconn, 'service_logs'),
    'service_log_part_replacements' => flush_table_count($obconn, 'service_log_part_replacements'),
    'complaint_service_logs' => flush_table_count($obconn, 'complaint_service_logs'),
    'spare_parts_consumption' => flush_table_count($obconn, 'spare_parts_consumption'),
    'spare_parts_consumption_items' => flush_table_count($obconn, 'spare_parts_consumption_items'),
    'complaints' => flush_table_count($obconn, 'complaints'),
    'complaint_assignments' => flush_table_count($obconn, 'complaint_assignments'),
    'complaint_activity_logs' => flush_table_count($obconn, 'complaint_activity_logs'),
    'complaint_service_updates' => flush_table_count($obconn, 'complaint_service_updates'),
    'complaint_closures' => flush_table_count($obconn, 'complaint_closures'),
    'complaint_nudge_logs' => flush_table_count($obconn, 'complaint_nudge_logs'),
    'amc_contracts' => flush_table_count($obconn, 'amc_contracts'),
    'amc_visits' => flush_table_count($obconn, 'amc_visits'),
    'foc_claims' => flush_table_count($obconn, 'foc_claims'),
    'foc_claim_items' => flush_table_count($obconn, 'foc_claim_items'),
    'service_claims' => flush_table_count($obconn, 'service_claims'),
    'tbl_vayu_cartitems' => flush_table_count($obconn, 'tbl_vayu_cartitems'),
    'plexecom_customer_units' => flush_table_count($obconn, 'plexecom_customer_units'),
    'order_approval_requests' => flush_table_count($obconn, 'order_approval_requests'),
    'order_approval_history' => flush_table_count($obconn, 'order_approval_history'),
    'tbl_vayu_orders_header' => flush_table_count($obconn, 'tbl_vayu_orders_header'),
];

$allTotal = 0;
foreach ($counts as $countValue) {
    $allTotal += (int) $countValue;
}

$selectedTarget = trim((string) ($_POST['flush_target'] ?? ''));
if (!in_array($selectedTarget, $allowedTargets, true)) {
    $selectedTarget = '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flush Module Records</title>
    <?php include 'header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet">
    <link href="css/complaint_buttons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .flush-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}

.flush-page-kicker {
    margin: 0 0 4px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #F44611;
}

.flush-page-title {
    margin: 0 0 6px;
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.25;
}

.flush-admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    padding: 8px 12px;
    border-radius: 999px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #c2410c;
    font-size: 12px;
    font-weight: 700;
}

.flush-warning {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    margin-bottom: 18px;
    border-radius: 12px;
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #991b1b;
    font-size: 13px;
    line-height: 1.5;
}

.flush-warning i {
    font-size: 20px;
    margin-top: 1px;
    flex-shrink: 0;
}

.flush-modules-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}

.flush-module-card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-height: 100%;
    padding: 16px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}

.flush-module-card:hover {
    border-color: #fdba74;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
}

.flush-module-card.is-selected,
.flush-module-card:has(input:checked) {
    border-color: #F44611;
    box-shadow: 0 0 0 3px rgba(244, 70, 17, 0.14);
    background: #fffaf8;
}

.flush-module-card.is-all {
    grid-column: 1 / -1;
    flex-direction: row;
    align-items: center;
    gap: 16px;
    background: #0f172a;
    border-color: #0f172a;
    color: #fff;
}

.flush-module-card.is-all:hover,
.flush-module-card.is-all:has(input:checked) {
    border-color: #F44611;
    background: #1e293b;
    box-shadow: 0 0 0 3px rgba(244, 70, 17, 0.2);
}

.flush-module-card input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.flush-module-card__icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fff7f4;
    color: #F44611;
    font-size: 18px;
    flex-shrink: 0;
    margin-bottom: 12px;
}

.flush-module-card.is-all .flush-module-card__icon {
    margin-bottom: 0;
    background: rgba(244, 70, 17, 0.18);
    color: #fdba74;
}

.flush-module-card__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
}

.flush-module-card__title {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.3;
}

.flush-module-card.is-all .flush-module-card__title {
    color: #fff;
}

.flush-count-pill {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.flush-module-card.is-all .flush-count-pill {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
}

.flush-module-card__rows {
    margin: 0 0 10px;
    padding: 0;
    list-style: none;
    flex: 1 1 auto;
}

.flush-module-card__rows li {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 13px;
    color: #475569;
    padding: 3px 0;
}

.flush-module-card__rows strong {
    color: #0f172a;
    font-weight: 700;
}

.flush-module-card.is-all .flush-module-card__rows li,
.flush-module-card.is-all .flush-module-card__rows strong {
    color: #e2e8f0;
}

.flush-module-card__note {
    margin: 0;
    font-size: 12px;
    line-height: 1.45;
    color: #64748b;
}

.flush-module-card.is-all .flush-module-card__note {
    color: #cbd5e1;
}

.flush-action-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 20px 22px;
}

.flush-action-card__title {
    margin: 0 0 4px;
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
}

.flush-action-card__hint {
    margin: 0 0 16px;
    font-size: 13px;
    color: #64748b;
}

.flush-submit-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

@media (max-width: 992px) {
    .flush-modules-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 767px) {
    .flush-page-header {
        flex-direction: column;
    }

    .flush-modules-grid {
        grid-template-columns: 1fr;
    }

    .flush-module-card.is-all {
        flex-direction: column;
        align-items: flex-start;
    }
}
        </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <?php if ($success_message !== '') { ?>
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php } ?>
            <?php if ($error_message !== '') { ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php } ?>

            <div class="flush-page-header">
                <div>
                    <p class="flush-page-kicker">System Admin</p>
                    <h2 class="flush-page-title">Flush Module Records</h2>
                    <p class="page-subtitle mb-0">Select a module, type FLUSH, then permanently delete its records.</p>
                </div>
                <span class="flush-admin-badge">
                    <i class="bi bi-shield-lock"></i> System Admin only
                </span>
            </div>

            <div class="flush-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    This permanently deletes transactional data from the complaint management database.
                    Master data such as users, products, and customers is not deleted. This cannot be undone.
                </div>
            </div>

            <form method="post" id="flushForm" novalidate>
                <div class="flush-modules-grid">
                    <?php foreach ($modules as $moduleKey => $module) {
                        $moduleTotal = flush_module_total($module['count_fields'], $counts);
                        $isSelected = ($selectedTarget === $moduleKey);
                    ?>
                    <label class="flush-module-card<?php echo $isSelected ? ' is-selected' : ''; ?>">
                        <input type="radio" name="flush_target" value="<?php echo htmlspecialchars($moduleKey); ?>"
                            data-label="<?php echo htmlspecialchars($module['label']); ?>"
                            <?php echo $isSelected ? ' checked' : ''; ?> required>
                        <div class="flush-module-card__icon">
                            <i class="bi <?php echo htmlspecialchars($module['icon']); ?>"></i>
                        </div>
                        <div class="flush-module-card__top">
                            <h3 class="flush-module-card__title"><?php echo htmlspecialchars($module['label']); ?></h3>
                            <span class="flush-count-pill"><?php echo (int) $moduleTotal; ?></span>
                        </div>
                        <ul class="flush-module-card__rows">
                            <?php foreach ($module['count_fields'] as $field) { ?>
                            <li>
                                <span><?php echo htmlspecialchars($field['label']); ?></span>
                                <strong><?php echo (int) ($counts[$field['key']] ?? 0); ?></strong>
                            </li>
                            <?php } ?>
                        </ul>
                        <?php if (trim((string) $module['note']) !== '') { ?>
                        <p class="flush-module-card__note"><?php echo htmlspecialchars($module['note']); ?></p>
                        <?php } ?>
                    </label>
                    <?php } ?>

                    <label class="flush-module-card is-all<?php echo ($selectedTarget === 'all') ? ' is-selected' : ''; ?>">
                        <input type="radio" name="flush_target" value="all" data-label="All listed modules"
                            <?php echo ($selectedTarget === 'all') ? ' checked' : ''; ?> required>
                        <div class="flush-module-card__icon">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div>
                            <div class="flush-module-card__top">
                                <h3 class="flush-module-card__title">All listed modules</h3>
                                <span class="flush-count-pill"><?php echo (int) $allTotal; ?></span>
                            </div>
                            <p class="flush-module-card__note mb-0">
                                Deletes every module above in a safe order. Use this only when you intend to wipe all transactional records.
                            </p>
                        </div>
                    </label>
                </div>

                <div class="flush-action-card">
                    <h3 class="flush-action-card__title">Confirm deletion</h3>
                    <p class="flush-action-card__hint">
                        Choose a module card, then type <strong>FLUSH</strong> to enable the delete button.
                    </p>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label" for="confirmText">Type FLUSH to confirm</label>
                            <input type="text" class="form-control" name="confirm_text" id="confirmText"
                                autocomplete="off" required placeholder="FLUSH">
                        </div>
                        <div class="col-md-6">
                            <button type="submit" name="flush_module" value="1" id="flushSubmitBtn"
                                class="btn btn-danger flush-submit-btn" disabled>
                                <i class="bi bi-trash"></i> Delete records
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script>
    (function () {
        var form = document.getElementById('flushForm');
        var confirmInput = document.getElementById('confirmText');
        var submitBtn = document.getElementById('flushSubmitBtn');
        if (!form || !confirmInput || !submitBtn) {
            return;
        }

        function selectedInput() {
            return form.querySelector('input[name="flush_target"]:checked');
        }

        function syncSubmit() {
            var ready = selectedInput() !== null
                && confirmInput.value.trim().toUpperCase() === 'FLUSH';
            submitBtn.disabled = !ready;
        }

        form.addEventListener('change', syncSubmit);
        confirmInput.addEventListener('input', syncSubmit);
        form.addEventListener('submit', function (event) {
            var selected = selectedInput();
            var label = selected ? selected.getAttribute('data-label') : 'the selected module';
            if (!window.confirm('Permanently delete ' + label + ' records? This cannot be undone.')) {
                event.preventDefault();
            }
        });
        syncSubmit();
    })();
    </script>
</body>

</html>
