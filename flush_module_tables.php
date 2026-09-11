<?php

session_start();

include 'pdo_obconn.php';
require_once 'includes/admin_access_helpers.php';

require_system_admin($obconn);

$success_message = '';
$error_message = '';

/**
 * @return array<string, array{label: string, tables: array<int, string>}>
 */
function flush_module_definitions(): array
{
    return [
        'installed_base' => [
            'label' => 'Installed Base Capture',
            'tables' => ['installed_base'],
        ],
        'service_log' => [
            'label' => 'Service Log Capture',
            'tables' => ['service_logs', 'service_log_part_replacements', 'complaint_service_logs'],
        ],
        'spare_parts' => [
            'label' => 'Spare Parts Consumption',
            'tables' => ['spare_parts_consumption', 'spare_parts_consumption_items'],
        ],
        'order_creation' => [
            'label' => 'Order Creation',
            'tables' => [
                'tbl_vayu_cartitems',
                'plexecom_customer_units',
                'order_approval_requests',
                'order_approval_history',
            ],
        ],
        'complaint' => [
            'label' => 'Complaint Entry',
            'tables' => [
                'complaints',
                'complaint_activity_logs',
                'complaint_service_updates',
                'complaint_closures',
                'complaint_nudge_logs',
            ],
        ],
        'assign_complaint' => [
            'label' => 'Assign Complaint',
            'tables' => ['complaint_assignments'],
        ],
        'amc' => [
            'label' => 'AMC',
            'tables' => ['amc_contracts', 'amc_visits'],
        ],
        'foc' => [
            'label' => 'FOC Part',
            'tables' => ['foc_claims', 'foc_claim_items'],
        ],
        'service_claims' => [
            'label' => 'Warranty Service Claims',
            'tables' => ['service_claims'],
        ],
    ];
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flush Module Tables</title>
    <?php include 'header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet">
    <link href="css/complaint_form.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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

            <div class="complaint-form-header mb-3">
                <div>
                    <p class="complaint-form-header__kicker mb-1">System Admin</p>
                    <h2 class="complaint-form-header__title">Flush / Delete Module Records</h2>
                    <p class="complaint-form-header__subtitle mb-0">
                        Permanently delete records from the selected after-market and complaint modules.
                        This cannot be undone.
                    </p>
                </div>
            </div>

            <div class="order-form-card">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <h5 class="mb-2">Installed Base Capture</h5>
                            <p class="mb-1">installed_base: <strong><?php echo (int) $counts['installed_base']; ?></strong></p>
                            <small class="text-muted">Also deletes linked service logs and spare parts, and clears Installed Base links on complaints and AMC.</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <h5 class="mb-2">Service Log Capture</h5>
                            <p class="mb-1">service_logs: <strong><?php echo (int) $counts['service_logs']; ?></strong></p>
                            <p class="mb-1">service_log_part_replacements: <strong><?php echo (int) $counts['service_log_part_replacements']; ?></strong></p>
                            <p class="mb-0">complaint_service_logs: <strong><?php echo (int) $counts['complaint_service_logs']; ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <h5 class="mb-2">Spare Parts Consumption</h5>
                            <p class="mb-1">spare_parts_consumption: <strong><?php echo (int) $counts['spare_parts_consumption']; ?></strong></p>
                            <p class="mb-0">spare_parts_consumption_items: <strong><?php echo (int) $counts['spare_parts_consumption_items']; ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <h5 class="mb-2">Complaint Entry</h5>
                            <p class="mb-1">complaints: <strong><?php echo (int) $counts['complaints']; ?></strong></p>
                            <p class="mb-1">complaint_activity_logs: <strong><?php echo (int) $counts['complaint_activity_logs']; ?></strong></p>
                            <p class="mb-1">complaint_service_updates: <strong><?php echo (int) $counts['complaint_service_updates']; ?></strong></p>
                            <p class="mb-1">complaint_closures: <strong><?php echo (int) $counts['complaint_closures']; ?></strong></p>
                            <p class="mb-0">complaint_nudge_logs: <strong><?php echo (int) $counts['complaint_nudge_logs']; ?></strong></p>
                            <small class="text-muted">Also deletes FOC parts, warranty service claims, and assignments linked to complaints.</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <h5 class="mb-2">Assign Complaint</h5>
                            <p class="mb-0">complaint_assignments: <strong><?php echo (int) $counts['complaint_assignments']; ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <h5 class="mb-2">AMC</h5>
                            <p class="mb-1">amc_contracts: <strong><?php echo (int) $counts['amc_contracts']; ?></strong></p>
                            <p class="mb-0">amc_visits: <strong><?php echo (int) $counts['amc_visits']; ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <h5 class="mb-2">FOC Part</h5>
                            <p class="mb-1">foc_claims: <strong><?php echo (int) $counts['foc_claims']; ?></strong></p>
                            <p class="mb-0">foc_claim_items: <strong><?php echo (int) $counts['foc_claim_items']; ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <h5 class="mb-2">Warranty Service Claims</h5>
                            <p class="mb-0">service_claims: <strong><?php echo (int) $counts['service_claims']; ?></strong></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <h5 class="mb-2">Order Creation</h5>
                            <p class="mb-1">tbl_vayu_cartitems: <strong><?php echo (int) $counts['tbl_vayu_cartitems']; ?></strong></p>
                            <p class="mb-1">plexecom_customer_units: <strong><?php echo (int) $counts['plexecom_customer_units']; ?></strong></p>
                            <p class="mb-1">order_approval_requests: <strong><?php echo (int) $counts['order_approval_requests']; ?></strong></p>
                            <p class="mb-1">order_approval_history: <strong><?php echo (int) $counts['order_approval_history']; ?></strong></p>
                            <p class="mb-0">tbl_vayu_orders_header: <strong><?php echo (int) $counts['tbl_vayu_orders_header']; ?></strong></p>
                            <small class="text-muted">Deletes carts, submitted orders (Recent Orders), and order approvals. Also removes FOC/Service Claim LN orders stored in Recent Orders.</small>
                        </div>
                    </div>
                </div>

                <form method="post" onsubmit="return confirm('This permanently deletes the selected records. Continue?');">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label" for="flushTarget">Module</label>
                            <select class="form-control" name="flush_target" id="flushTarget" required>
                                <option value="">Select a module</option>
                                <option value="installed_base">Installed Base Capture</option>
                                <option value="service_log">Service Log Capture</option>
                                <option value="spare_parts">Spare Parts Consumption</option>
                                <option value="complaint">Complaint Entry</option>
                                <option value="assign_complaint">Assign Complaint</option>
                                <option value="amc">AMC</option>
                                <option value="foc">FOC Part</option>
                                <option value="service_claims">Warranty Service Claims</option>
                                <option value="order_creation">Order Creation</option>
                                <option value="all">All listed modules</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="confirmText">Type FLUSH to confirm</label>
                            <input type="text" class="form-control" name="confirm_text" id="confirmText" autocomplete="off" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="flush_module" value="1" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Delete records
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>
