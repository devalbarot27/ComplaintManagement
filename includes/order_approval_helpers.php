<?php

require_once __DIR__ . '/order_cart_schema.php';
require_once __DIR__ . '/user_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';
require_once __DIR__ . '/notification_helpers.php';
require_once __DIR__ . '/rbac_helpers.php';

/**
 * APPROVAL MODULE: Order-level L1 / L2 approval stored in order_approval_requests
 */
function order_approval_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    cart_ensure_schema($conn);
    user_ensure_schema($conn);

    $conn->exec("
        ALTER TABLE tbl_vayu_cartitems
        ADD COLUMN IF NOT EXISTS approval_status VARCHAR(20) NULL DEFAULT 'not_required'
    ");

    order_approval_rename_legacy_tables($conn);

    $conn->exec("
        CREATE TABLE IF NOT EXISTS order_approval_requests (
            id SERIAL PRIMARY KEY,
            cart_item_id INTEGER NULL,
            approval_level VARCHAR(20) NOT NULL,
            price_type VARCHAR(50) NOT NULL,
            unit_price NUMERIC NULL,
            qty NUMERIC NULL,
            item_code VARCHAR(50) NULL,
            item_name VARCHAR(255) NULL,
            dpst VARCHAR(20) NULL,
            order_type INTEGER NULL,
            requested_by VARCHAR(100) NULL,
            requested_by_user_id INTEGER NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            remarks TEXT NULL,
            decided_by VARCHAR(100) NULL,
            decided_by_user_id INTEGER NULL,
            decided_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS order_approval_history (
            id SERIAL PRIMARY KEY,
            request_id INTEGER NOT NULL,
            action VARCHAR(20) NOT NULL,
            remarks TEXT NULL,
            acted_by VARCHAR(100) NULL,
            acted_by_user_id INTEGER NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->exec("
        ALTER TABLE order_approval_requests
        ADD COLUMN IF NOT EXISTS assigned_to_user_id INTEGER NULL
    ");
    $conn->exec("
        ALTER TABLE order_approval_requests
        ADD COLUMN IF NOT EXISTS order_seqid INTEGER NULL
    ");
    $conn->exec("
        ALTER TABLE order_approval_requests
        ADD COLUMN IF NOT EXISTS order_refno VARCHAR(50) NULL
    ");
    $conn->exec("
        ALTER TABLE order_approval_requests
        ADD COLUMN IF NOT EXISTS current_level VARCHAR(20) NULL DEFAULT 'level_1'
    ");
    $conn->exec("
        ALTER TABLE order_approval_requests
        ADD COLUMN IF NOT EXISTS l2_required BOOLEAN NULL DEFAULT FALSE
    ");

    order_approval_migrate_legacy_rows($conn);

    $conn->exec("
        ALTER TABLE plexecom_customer_units
        ADD COLUMN IF NOT EXISTS l1_approved_user_id INTEGER NULL
    ");
    $conn->exec("
        ALTER TABLE plexecom_customer_units
        ADD COLUMN IF NOT EXISTS l1_approved_at TIMESTAMP NULL
    ");
    $conn->exec("
        ALTER TABLE plexecom_customer_units
        ADD COLUMN IF NOT EXISTS l2_approved_user_id INTEGER NULL
    ");
    $conn->exec("
        ALTER TABLE plexecom_customer_units
        ADD COLUMN IF NOT EXISTS l2_approved_at TIMESTAMP NULL
    ");
    $conn->exec("
        ALTER TABLE plexecom_customer_units
        ADD COLUMN IF NOT EXISTS approval_status VARCHAR(30) NULL
    ");
    $conn->exec("
        ALTER TABLE plexecom_customer_units
        ADD COLUMN IF NOT EXISTS l2_required BOOLEAN NULL DEFAULT FALSE
    ");
    $conn->exec("
        ALTER TABLE plexecom_customer_units
        ADD COLUMN IF NOT EXISTS approval_remarks TEXT NULL
    ");

    $ensured = true;
}

function order_approval_table_exists(PDO $conn, string $table): bool
{
    $stmt = $conn->prepare("SELECT to_regclass(:name)");
    $stmt->bindValue(':name', 'public.' . $table);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function order_approval_rename_table_if_needed(PDO $conn, string $from, string $to): void
{
    if (!order_approval_table_exists($conn, $from)) {
        return;
    }
    if (order_approval_table_exists($conn, $to)) {
        $legacy = $to . '_legacy';
        if (!order_approval_table_exists($conn, $legacy)) {
            $conn->exec('ALTER TABLE ' . $to . ' RENAME TO ' . $legacy);
        } else {
            return;
        }
    }
    $conn->exec('ALTER TABLE ' . $from . ' RENAME TO ' . $to);
}

function order_approval_rename_legacy_tables(PDO $conn): void
{
    try {
        order_approval_rename_table_if_needed($conn, 'cart_approval_requests', 'order_approval_requests');
        order_approval_rename_table_if_needed($conn, 'cart_approval_history', 'order_approval_history');
    } catch (PDOException $e) {
        // Rename is best-effort; CREATE / ALTER below still apply.
    }
}

function order_approval_migrate_legacy_rows(PDO $conn): void
{
    try {
        if (order_approval_table_exists($conn, 'cart_approval_requests')
            && order_approval_table_exists($conn, 'order_approval_requests')
        ) {
            $conn->exec("
                INSERT INTO order_approval_requests (
                    cart_item_id, approval_level, price_type, unit_price, qty,
                    item_code, item_name, dpst, order_type, requested_by, requested_by_user_id,
                    status, remarks, decided_by, decided_by_user_id, decided_at,
                    assigned_to_user_id, order_seqid, order_refno, current_level, l2_required,
                    created_at, updated_at
                )
                SELECT
                    c.cart_item_id, c.approval_level, c.price_type, c.unit_price, c.qty,
                    c.item_code, c.item_name, c.dpst, c.order_type, c.requested_by, c.requested_by_user_id,
                    c.status, c.remarks, c.decided_by, c.decided_by_user_id, c.decided_at,
                    c.assigned_to_user_id, c.order_seqid, c.order_refno, c.current_level, c.l2_required,
                    c.created_at, c.updated_at
                FROM cart_approval_requests c
                WHERE NOT EXISTS (
                    SELECT 1 FROM order_approval_requests o WHERE o.id = c.id
                )
            ");
        }
    } catch (PDOException $e) {
        // cart_approval_requests may already have been renamed.
    }

    try {
        if (!order_approval_table_exists($conn, 'order_approval_requests_legacy')) {
            return;
        }
        $conn->exec("
            INSERT INTO order_approval_requests (
                order_refno, approval_level, current_level, l2_required,
                assigned_to_user_id, requested_by, requested_by_user_id,
                status, remarks, price_type, item_code, created_at, updated_at
            )
            SELECT
                o.refno,
                COALESCE(NULLIF(TRIM(o.current_level), ''), 'level_1'),
                COALESCE(NULLIF(TRIM(o.current_level), ''), 'level_1'),
                COALESCE(o.l2_required, FALSE),
                o.assigned_to_user_id,
                o.requested_by,
                o.requested_by_user_id,
                COALESCE(NULLIF(TRIM(o.status), ''), 'pending'),
                o.remarks,
                CASE WHEN COALESCE(o.l2_required, FALSE) THEN 'level_2' ELSE 'level_1' END,
                o.refno,
                o.created_at,
                o.updated_at
            FROM order_approval_requests_legacy o
            WHERE COALESCE(NULLIF(TRIM(o.refno), ''), '') <> ''
              AND NOT EXISTS (
                  SELECT 1
                  FROM order_approval_requests c
                  WHERE c.order_refno = o.refno
                    AND c.status = COALESCE(NULLIF(TRIM(o.status), ''), 'pending')
                    AND COALESCE(c.cart_item_id, 0) = 0
              )
        ");
    } catch (PDOException $e) {
        // Legacy table may use a different column layout.
    }
}

function order_approval_order_row_sql(): string
{
    return "COALESCE(NULLIF(TRIM(order_refno), ''), '') <> ''
        AND COALESCE(cart_item_id, 0) = 0";
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function order_approval_hydrate_request(array $row): array
{
    $row['refno'] = trim((string) ($row['order_refno'] ?? $row['refno'] ?? ''));
    $level = trim((string) ($row['current_level'] ?? $row['approval_level'] ?? 'level_1'));
    if ($level !== 'level_1' && $level !== 'level_2') {
        $level = 'level_1';
    }
    $row['current_level'] = $level;
    $row['approval_level'] = $level;

    return $row;
}

function order_approval_needs_approval(?string $priceType): bool
{
    $normalized = cart_normalize_price_type($priceType);

    return $normalized === 'level_1' || $normalized === 'level_2';
}

function order_approval_level_from_price_type(?string $priceType): ?string
{
    $normalized = cart_normalize_price_type($priceType);
    if ($normalized === 'level_1' || $normalized === 'level_2') {
        return $normalized;
    }

    return null;
}

function order_approval_level_label(?string $level): string
{
    if ($level === 'level_1') {
        return 'Level 1 Approval';
    }
    if ($level === 'level_2') {
        return 'Level 2 Approval';
    }

    return '-';
}

function order_approval_status_label(?string $status): string
{
    $key = strtolower(trim((string) $status));
    $labels = [
        'pending' => 'Pending',
        'pending_l1' => 'Pending Level 1',
        'pending_l2' => 'Pending Level 2',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        'not_required' => 'Not Required',
        'submitted' => 'Submitted',
    ];

    return $labels[$key] ?? 'Pending';
}

function order_approval_status_table_html(?string $status): string
{
    $key = strtolower(trim((string) $status));
    if ($key === '' || $key === 'not_required') {
        return '-';
    }

    return '<span class="status-badge border border-dark">'
        . htmlspecialchars(order_approval_status_label($key), ENT_QUOTES, 'UTF-8')
        . '</span>';
}

/**
 * Level 1 / Level 2 display fields for recent order details.
 *
 * @param array<string, mixed> $headerRow
 * @return array<string, string>
 */
function order_approval_order_level_details(PDO $conn, array $headerRow, string $refno): array
{
    $status = strtolower(trim((string) ($headerRow['approval_status'] ?? '')));
    $l2Required = user_bool_from_value($headerRow['l2_required'] ?? false);
    $l1UserId = (int) ($headerRow['l1_approved_user_id'] ?? 0);
    $l2UserId = (int) ($headerRow['l2_approved_user_id'] ?? 0);
    $sharedRemarks = trim((string) ($headerRow['approval_remarks'] ?? ''));

    $l1Status = 'Not Required';
    $l2Status = 'Not Required';
    if ($status === 'pending_l1') {
        $l1Status = 'Pending';
        $l2Status = $l2Required ? 'Pending' : 'Not Required';
    } elseif ($status === 'pending_l2') {
        $l1Status = 'Approved';
        $l2Status = 'Pending';
    } elseif ($status === 'approved') {
        $l1Status = 'Approved';
        $l2Status = $l2Required ? 'Approved' : 'Not Required';
    } elseif ($status === 'rejected') {
        if ($l1UserId <= 0) {
            $l1Status = 'Rejected';
            $l2Status = $l2Required ? '-' : 'Not Required';
        } else {
            $l1Status = 'Approved';
            $l2Status = 'Rejected';
        }
    }

    $history = order_approval_history_levels_for_refno($conn, $refno);
    $l1History = $history[0] ?? null;
    $l2History = $history[1] ?? null;

    $l1RemarksFallback = '';
    $l2RemarksFallback = '';
    if ($status === 'pending_l2' || ($status === 'approved' && !$l2Required) || ($status === 'rejected' && $l1UserId <= 0)) {
        $l1RemarksFallback = $sharedRemarks;
    }
    if (($status === 'approved' && $l2Required) || ($status === 'rejected' && $l1UserId > 0)) {
        $l2RemarksFallback = $sharedRemarks;
    }

    $l1 = [
        'l1_status' => $l1Status,
        'l1_approved_by' => order_approval_level_actor_name($conn, $l1UserId, $l1History),
        'l1_approved_at' => order_approval_level_acted_at($headerRow['l1_approved_at'] ?? null, $l1History),
        'l1_remarks' => order_approval_level_remarks($l1History, $l1RemarksFallback),
    ];
    $l2 = [
        'l2_status' => $l2Status,
        'l2_approved_by' => order_approval_level_actor_name($conn, $l2UserId, $l2History),
        'l2_approved_at' => order_approval_level_acted_at($headerRow['l2_approved_at'] ?? null, $l2History),
        'l2_remarks' => order_approval_level_remarks($l2History, $l2RemarksFallback),
    ];

    if (!in_array($l1Status, ['Approved', 'Rejected'], true)) {
        $l1['l1_approved_by'] = '-';
        $l1['l1_approved_at'] = '-';
        if ($l1Status !== 'Pending') {
            $l1['l1_remarks'] = '-';
        }
    }
    if (!in_array($l2Status, ['Approved', 'Rejected'], true)) {
        $l2['l2_approved_by'] = '-';
        $l2['l2_approved_at'] = '-';
        if ($l2Status !== 'Pending') {
            $l2['l2_remarks'] = '-';
        }
    }

    return array_merge($l1, $l2);
}

/**
 * @return array<int, array<string, mixed>>
 */
function order_approval_history_levels_for_refno(PDO $conn, string $refno): array
{
    $refno = trim($refno);
    if ($refno === '') {
        return [];
    }

    try {
        $stmt = $conn->prepare("
            SELECT h.action, h.remarks, h.acted_by, h.acted_by_user_id, h.created_at
            FROM order_approval_history h
            INNER JOIN order_approval_requests r ON r.id = h.request_id
            WHERE r.order_refno = :refno
              AND COALESCE(r.cart_item_id, 0) = 0
              AND h.action IN ('approved', 'rejected')
            ORDER BY h.id ASC
        ");
        $stmt->bindValue(':refno', $refno);
        $stmt->execute();

        $levels = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $levels[] = $row;
            if (count($levels) >= 2) {
                break;
            }
        }

        return $levels;
    } catch (Throwable $e) {
        return [];
    }
}

function order_approval_level_actor_name(PDO $conn, int $userId, ?array $historyRow): string
{
    $name = user_approver_display_name($conn, $userId);
    if ($name !== '-') {
        return $name;
    }
    if (!is_array($historyRow)) {
        return '-';
    }

    $historyUserId = (int) ($historyRow['acted_by_user_id'] ?? 0);
    $historyName = user_approver_display_name($conn, $historyUserId);
    if ($historyName !== '-') {
        return $historyName;
    }

    $actedBy = trim((string) ($historyRow['acted_by'] ?? ''));

    return $actedBy !== '' ? $actedBy : '-';
}

function order_approval_level_acted_at($timestamp, ?array $historyRow): string
{
    $raw = trim((string) ($timestamp ?? ''));
    if ($raw !== '') {
        return user_format_datetime($raw);
    }
    if (is_array($historyRow)) {
        $historyAt = trim((string) ($historyRow['created_at'] ?? ''));
        if ($historyAt !== '') {
            return user_format_datetime($historyAt);
        }
    }

    return '-';
}

function order_approval_level_remarks(?array $historyRow, string $fallback): string
{
    if (is_array($historyRow)) {
        $remarks = trim((string) ($historyRow['remarks'] ?? ''));
        if ($remarks !== '') {
            return $remarks;
        }
    }
    $fallback = trim($fallback);

    return $fallback !== '' ? $fallback : '-';
}

function order_approval_status_badge(?string $status): string
{
    $key = strtolower(trim((string) $status));
    if ($key === '' || $key === 'not_required') {
        return '';
    }

    $class = 'secondary';
    if ($key === 'pending') {
        $class = 'warning';
    } elseif ($key === 'approved') {
        $class = 'success';
    } elseif ($key === 'rejected') {
        $class = 'danger';
    }

    return '<span class="badge bg-' . $class . ' order-approval-badge">'
        . htmlspecialchars(order_approval_status_label($key), ENT_QUOTES, 'UTF-8')
        . '</span>';
}

function order_approval_format_money($value): string
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return '-';
    }

    return number_format((float) $value, 2, '.', '');
}

function order_approval_can_access(PDO $conn): bool
{
    if (is_system_admin()) {
        return true;
    }

    $flags = user_current_approval_flags($conn);
    if (!empty($flags['l1']) || !empty($flags['l2'])) {
        return true;
    }

    $userId = current_user_id($conn);
    if ($userId === null || $userId <= 0) {
        return false;
    }

    order_approval_ensure_schema($conn);
    $stmt = $conn->prepare("
        SELECT 1
        FROM order_approval_requests
        WHERE status = 'pending'
          AND assigned_to_user_id = :assigned_to_user_id
          AND " . order_approval_order_row_sql() . "
        LIMIT 1
    ");
    $stmt->bindValue(':assigned_to_user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function order_approval_can_act_on_level(PDO $conn, string $level): bool
{
    if (is_system_admin()) {
        return true;
    }

    $flags = user_current_approval_flags($conn);
    if ($level === 'level_1') {
        return !empty($flags['l1']);
    }
    if ($level === 'level_2') {
        return !empty($flags['l2']);
    }

    return false;
}

/**
 * @return array<int, string>
 */
function order_approval_visible_levels(PDO $conn): array
{
    if (is_system_admin()) {
        return ['level_1', 'level_2'];
    }

    $flags = user_current_approval_flags($conn);
    $levels = [];
    if (!empty($flags['l1'])) {
        $levels[] = 'level_1';
    }
    if (!empty($flags['l2'])) {
        $levels[] = 'level_2';
    }

    return $levels;
}

function order_approval_submittable_sql(): string
{
    return "(COALESCE(price_type, 'clp') NOT IN ('level_1', 'level_2')
        OR COALESCE(approval_status, 'not_required') = 'approved')";
}

function order_approval_pending_sql(): string
{
    return "(COALESCE(price_type, 'clp') IN ('level_1', 'level_2')
        AND COALESCE(approval_status, 'not_required') <> 'approved'
        AND COALESCE(approval_status, 'not_required') <> 'rejected')";
}

/**
 * APPROVAL MODULE: Added Level 1 and Level 2 cart approval logic
 * Blocks submit when any L1/L2 cart line is pending or rejected.
 */
function order_approval_blocked_sql(): string
{
    return "(COALESCE(price_type, 'clp') IN ('level_1', 'level_2')
        AND LOWER(COALESCE(NULLIF(TRIM(approval_status), ''), 'pending')) IN ('pending', 'rejected', 'not_required'))";
}

function order_approval_submit_block_message(PDO $conn, string $createdBy): ?string
{
    return null;
}

function order_approval_fetch_cart_item(PDO $conn, int $cartItemId): ?array
{
    $stmt = $conn->prepare('SELECT * FROM tbl_vayu_cartitems WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $cartItemId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function order_approval_user_id_by_username(PDO $conn, string $username): ?int
{
    $username = trim($username);
    if ($username === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id
        FROM user_master
        WHERE TRIM(username) = :username
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':username', $username);
    $stmt->execute();
    $id = (int) $stmt->fetchColumn();

    return $id > 0 ? $id : null;
}

function order_approval_username_display_name(PDO $conn, string $username): string
{
    $username = trim($username);
    if ($username === '') {
        return '';
    }

    $userId = order_approval_user_id_by_username($conn, $username);
    $name = user_approver_display_name($conn, $userId);
    if ($name !== '-') {
        return $name;
    }

    return $username;
}

/**
 * @param array<string, mixed> $request
 * @param array<string, mixed> $headerRow
 */
function order_approval_requester_display_name(PDO $conn, array $request, array $headerRow): string
{
    $name = user_approver_display_name($conn, (int) ($request['requested_by_user_id'] ?? 0));
    if ($name !== '-') {
        return $name;
    }

    $username = trim((string) ($request['requested_by'] ?? ''));
    if ($username === '') {
        $username = trim((string) ($headerRow['usr_name'] ?? ''));
    }

    $resolved = order_approval_username_display_name($conn, $username);

    return $resolved !== '' ? $resolved : '-';
}

function order_approval_requester_user_id(PDO $conn, array $cartItem): ?int
{
    $fromCart = order_approval_user_id_by_username($conn, (string) ($cartItem['created_by'] ?? ''));
    if ($fromCart !== null) {
        return $fromCart;
    }

    $current = current_user_id($conn);

    return ($current !== null && $current > 0) ? $current : null;
}

function order_approval_is_assigned_to_current_user(PDO $conn, array $request): bool
{
    if (is_system_admin()) {
        return true;
    }

    $currentId = current_user_id($conn);
    $assignedId = (int) ($request['assigned_to_user_id'] ?? 0);

    return $currentId !== null && $currentId > 0 && $assignedId === $currentId;
}

function order_approval_bind_assigned_to(PDOStatement $stmt, ?int $assignedToUserId): void
{
    if ($assignedToUserId !== null && $assignedToUserId > 0) {
        $stmt->bindValue(':assigned_to_user_id', $assignedToUserId, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':assigned_to_user_id', null, PDO::PARAM_NULL);
    }
}

function order_approval_append_history(
    PDO $conn,
    int $requestId,
    string $action,
    ?string $remarks,
    ?string $actedBy,
    ?int $actedByUserId
): void {
    $stmt = $conn->prepare('
        INSERT INTO order_approval_history (
            request_id, action, remarks, acted_by, acted_by_user_id, created_at
        ) VALUES (
            :request_id, :action, :remarks, :acted_by, :acted_by_user_id, CURRENT_TIMESTAMP
        )
    ');
    $stmt->bindValue(':request_id', $requestId, PDO::PARAM_INT);
    $stmt->bindValue(':action', $action);
    $trimmedRemarks = $remarks !== null ? trim($remarks) : '';
    $stmt->bindValue(
        ':remarks',
        $trimmedRemarks !== '' ? $trimmedRemarks : null,
        $trimmedRemarks !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL
    );
    $trimmedActor = $actedBy !== null ? trim($actedBy) : '';
    $stmt->bindValue(
        ':acted_by',
        $trimmedActor !== '' ? $trimmedActor : null,
        $trimmedActor !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL
    );
    if ($actedByUserId !== null && $actedByUserId > 0) {
        $stmt->bindValue(':acted_by_user_id', $actedByUserId, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':acted_by_user_id', null, PDO::PARAM_NULL);
    }
    $stmt->execute();
}

function order_approval_notify_assigned_approver(
    PDO $conn,
    int $requestId,
    string $level,
    string $itemCode,
    ?int $assignedToUserId,
    ?int $excludeUserId = null,
    string $entityLabel = 'Order'
): void {
    if ($assignedToUserId === null || $assignedToUserId <= 0) {
        return;
    }
    if ($excludeUserId !== null && $assignedToUserId === $excludeUserId) {
        return;
    }

    $levelLabel = order_approval_level_label($level);
    $entity = trim($entityLabel) !== '' ? trim($entityLabel) : 'Order';
    notification_create(
        $conn,
        $assignedToUserId,
        $levelLabel . ' request',
        $entity . ' ' . $itemCode . ' is waiting for ' . $levelLabel . '.',
        'order-approval',
        $requestId
    );
}

/**
 * APPROVAL MODULE: Order-level only. Cart item rows are not used for approval.
 */
function order_approval_sync_for_cart_item(PDO $conn, array $cartItem, string $requestedBy): void
{
}

function order_approval_set_cart_status(PDO $conn, int $cartItemId, string $status): void
{
    if ($cartItemId <= 0) {
        return;
    }
    $stmt = $conn->prepare('
        UPDATE tbl_vayu_cartitems
        SET approval_status = :approval_status
        WHERE id = :id
    ');
    $stmt->bindValue(':approval_status', $status);
    $stmt->bindValue(':id', $cartItemId, PDO::PARAM_INT);
    $stmt->execute();
}

function order_approval_create_for_order_line(
    PDO $conn,
    array $orderLine,
    string $requestedBy,
    ?int $cartItemId = null
): void {
}

function order_approval_sync_open_cart(PDO $conn, string $createdBy): void
{
}

function order_approval_cancel_for_cart_item(PDO $conn, int $cartItemId, ?string $actedBy = null): void
{
    order_approval_ensure_schema($conn);
    $stmt = $conn->prepare("
        SELECT id
        FROM order_approval_requests
        WHERE cart_item_id = :cart_item_id
          AND status = 'pending'
    ");
    $stmt->bindValue(':cart_item_id', $cartItemId, PDO::PARAM_INT);
    $stmt->execute();
    $actedByUserId = current_user_id($conn);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $requestId = (int) $row['id'];
        $update = $conn->prepare("
            UPDATE order_approval_requests
            SET status = 'cancelled',
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $update->bindValue(':id', $requestId, PDO::PARAM_INT);
        $update->execute();
        order_approval_append_history($conn, $requestId, 'cancelled', 'Cart item removed', $actedBy, $actedByUserId);
    }
}

function order_approval_get_by_id(PDO $conn, int $id): ?array
{
    order_approval_ensure_schema($conn);
    $stmt = $conn->prepare('
        SELECT *
        FROM order_approval_requests
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row = order_approval_hydrate_request($row);
    if ($row['refno'] === '' || (int) ($row['cart_item_id'] ?? 0) > 0) {
        return null;
    }

    return $row;
}

/**
 * @return array<int, array<string, mixed>>
 */
function order_approval_history(PDO $conn, int $requestId): array
{
    $stmt = $conn->prepare('
        SELECT *
        FROM order_approval_history
        WHERE request_id = :request_id
        ORDER BY id ASC
    ');
    $stmt->bindValue(':request_id', $requestId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array<int, array<string, mixed>>
 */
/**
 * APPROVAL MODULE: Order-level L1 / L2 approval (entire order, then AO via LN)
 *
 * @param array<int, array<string, mixed>> $lines
 */
function order_approval_lines_need_approval(array $lines): bool
{
    return $lines !== [];
}

/**
 * @param array<int, array<string, mixed>> $lines
 */
function order_approval_lines_require_l2(array $lines): bool
{
    foreach ($lines as $line) {
        if (cart_normalize_price_type($line['price_type'] ?? 'clp') === 'level_2') {
            return true;
        }
    }

    return false;
}

function order_approval_bind_bool(PDOStatement $stmt, string $param, bool $value): void
{
    $stmt->bindValue($param, $value, PDO::PARAM_BOOL);
}

function order_approval_start(PDO $conn, string $refno, string $requestedBy, bool $l2Required): void
{
    order_approval_ensure_schema($conn);
    $refno = trim($refno);
    if ($refno === '') {
        return;
    }

    $requestedByUserId = order_approval_user_id_by_username($conn, $requestedBy);
    $assignedToUserId = ($requestedByUserId !== null)
        ? user_assigned_approver_id($conn, $requestedByUserId, 'level_1')
        : null;
    $priceType = $l2Required ? 'level_2' : 'level_1';

    $existing = $conn->prepare("
        SELECT id
        FROM order_approval_requests
        WHERE order_refno = :order_refno
          AND status = 'pending'
          AND COALESCE(cart_item_id, 0) = 0
        ORDER BY id DESC
        LIMIT 1
    ");
    $existing->bindValue(':order_refno', $refno);
    $existing->execute();
    $existingId = (int) $existing->fetchColumn();
    if ($existingId > 0) {
        $update = $conn->prepare('
            UPDATE order_approval_requests SET
                order_refno = :order_refno,
                approval_level = :approval_level,
                current_level = :current_level,
                l2_required = :l2_required,
                price_type = :price_type,
                item_code = :item_code,
                assigned_to_user_id = :assigned_to_user_id,
                requested_by = :requested_by,
                requested_by_user_id = :requested_by_user_id,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $update->bindValue(':order_refno', $refno);
        $update->bindValue(':approval_level', 'level_1');
        $update->bindValue(':current_level', 'level_1');
        order_approval_bind_bool($update, ':l2_required', $l2Required);
        $update->bindValue(':price_type', $priceType);
        $update->bindValue(':item_code', $refno);
        order_approval_bind_assigned_to($update, $assignedToUserId);
        $update->bindValue(':requested_by', $requestedBy);
        if ($requestedByUserId !== null && $requestedByUserId > 0) {
            $update->bindValue(':requested_by_user_id', $requestedByUserId, PDO::PARAM_INT);
        } else {
            $update->bindValue(':requested_by_user_id', null, PDO::PARAM_NULL);
        }
        $update->bindValue(':id', $existingId, PDO::PARAM_INT);
        $update->execute();
        $requestId = $existingId;
    } else {
        $stmt = $conn->prepare('
            INSERT INTO order_approval_requests (
                cart_item_id, order_refno, approval_level, current_level, l2_required,
                assigned_to_user_id, requested_by, requested_by_user_id,
                status, price_type, item_code, created_at, updated_at
            ) VALUES (
                NULL, :order_refno, :approval_level, :current_level, :l2_required,
                :assigned_to_user_id, :requested_by, :requested_by_user_id,
                :status, :price_type, :item_code, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
            RETURNING id
        ');
        $stmt->bindValue(':order_refno', $refno);
        $stmt->bindValue(':approval_level', 'level_1');
        $stmt->bindValue(':current_level', 'level_1');
        order_approval_bind_bool($stmt, ':l2_required', $l2Required);
        order_approval_bind_assigned_to($stmt, $assignedToUserId);
        $stmt->bindValue(':requested_by', $requestedBy);
        if ($requestedByUserId !== null && $requestedByUserId > 0) {
            $stmt->bindValue(':requested_by_user_id', $requestedByUserId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':requested_by_user_id', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':status', 'pending');
        $stmt->bindValue(':price_type', $priceType);
        $stmt->bindValue(':item_code', $refno);
        $stmt->execute();
        $requestId = (int) $stmt->fetchColumn();
        order_approval_append_history($conn, $requestId, 'submitted', null, $requestedBy, $requestedByUserId);
    }

    order_approval_notify_assigned_approver(
        $conn,
        $requestId,
        'level_1',
        $refno,
        $assignedToUserId,
        $requestedByUserId,
        'Order'
    );
}

function order_approval_stamp_order_lines(
    PDO $conn,
    string $refno,
    string $approvalStatus,
    bool $l2Required
): void {
    $stmt = $conn->prepare('
        UPDATE plexecom_customer_units SET
            approval_status = :approval_status,
            l2_required = :l2_required,
            l1_approved_user_id = NULL,
            l1_approved_at = NULL,
            l2_approved_user_id = NULL,
            l2_approved_at = NULL,
            approval_remarks = NULL
        WHERE refno = :refno
    ');
    $stmt->bindValue(':approval_status', $approvalStatus);
    order_approval_bind_bool($stmt, ':l2_required', $l2Required);
    $stmt->bindValue(':refno', $refno);
    $stmt->execute();
}

function order_approval_order_lines(PDO $conn, string $refno): array
{
    $stmt = $conn->prepare('
        SELECT *
        FROM plexecom_customer_units
        WHERE refno = :refno
        ORDER BY oid ASC
    ');
    $stmt->bindValue(':refno', $refno);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function order_approval_products_summary(array $lines): array
{
    $parts = [];
    $htmlParts = [];
    $hasL2 = false;
    foreach ($lines as $line) {
        $code = trim((string) ($line['tplcode'] ?? ''));
        $name = trim((string) ($line['tpldesc'] ?? ''));
        $qty = order_approval_format_money($line['qty'] ?? null);
        $normalized = cart_normalize_price_type($line['price_type'] ?? 'clp');
        if ($normalized === 'level_2') {
            $hasL2 = true;
        }
        $price = order_approval_format_money($line['price'] ?? null);
        $clp = order_approval_format_money($line['cos'] ?? null);
        $label = $code !== '' ? $code : 'Item';
        if ($name !== '') {
            $label .= ' - ' . $name;
        }
        $label .= ' x ' . $qty;
        if ($price !== '-') {
            $label .= ' @ ' . $price;
        }
        $label .= ' - ';
        if ($clp !== '-') {
            $label .= ' ' . $clp;
        }
        $parts[] = $label;
        $htmlParts[] = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    }

    $warranty = '';
    if ($hasL2) {
        $warranty = 'Level 2 Approval Price';
    }

    return [
        'text' => implode('; ', $parts),
        'html' => implode('<br>', $htmlParts),
        'warranty' => $warranty,
    ];
}

/**
 * End-customer delivery fields from a plexecom_customer_units header row.
 *
 * @param array<string, mixed> $headerRow
 * @return array{
 *     is_end_customer: bool,
 *     name: string,
 *     email: string,
 *     street1: string,
 *     street2: string,
 *     pincode: string,
 *     city: string,
 *     district: string,
 *     state: string
 * }
 */
function order_approval_end_customer_from_header(array $headerRow): array
{
    $deliveryCode = trim((string) ($headerRow['delivery_code'] ?? ''));
    $storedEmail = trim((string) ($headerRow['email'] ?? ''));
    $isEndCustomer = ($storedEmail !== '' || $deliveryCode === '');

    $details = [
        'is_end_customer' => $isEndCustomer,
        'name' => '',
        'email' => '',
        'street1' => '',
        'street2' => '',
        'pincode' => '',
        'city' => '',
        'district' => '',
        'state' => '',
    ];

    if (!$isEndCustomer) {
        return $details;
    }

    $pincode = trim((string) ($headerRow['pincode'] ?? ''));
    if ($pincode === '0' || $pincode === '000') {
        $pincode = '';
    }

    $details['name'] = trim((string) ($headerRow['cuname'] ?? ''));
    $details['email'] = $storedEmail;
    $details['pincode'] = $pincode;
    $details['district'] = trim((string) ($headerRow['district'] ?? ''));
    $details['state'] = trim((string) ($headerRow['state'] ?? ''));

    $rawAddr = trim((string) ($headerRow['deladdr'] ?? ''));
    if ($rawAddr === '' || stripos($rawAddr, '<br') === false) {
        $rawInv = trim((string) ($headerRow['invaddr'] ?? ''));
        $rawAddr = ($rawInv !== '' && preg_match('/<br\s*\/?>\s*-\s*<br\s*\/?>/i', $rawInv))
            ? $rawInv
            : '';
    }
    if ($rawAddr === '') {
        return $details;
    }

    $normalized = preg_replace('/<br\s*\/?>\s*-\s*<br\s*\/?>/i', '||', $rawAddr);
    $normalized = preg_replace('/<br\s*\/?>/i', "\n", (string) $normalized);
    $normalized = html_entity_decode(strip_tags((string) $normalized), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $addrParts = array_map(
        static fn($part) => trim((string) $part, " \t\n\r\0\x0B-"),
        explode('||', (string) $normalized)
    );

    $details['street1'] = $addrParts[0] ?? '';
    $details['street2'] = $addrParts[1] ?? '';
    $details['city'] = $addrParts[2] ?? '';
    if (trim((string) ($addrParts[3] ?? '')) !== '') {
        $details['state'] = trim((string) $addrParts[3]);
    }
    if (trim((string) ($addrParts[4] ?? '')) !== '' && $details['district'] === '') {
        $details['district'] = trim((string) $addrParts[4]);
    }
    if (trim((string) ($addrParts[5] ?? '')) !== '' && $details['pincode'] === '') {
        $details['pincode'] = trim((string) $addrParts[5]);
    }

    return $details;
}

function order_approval_format_address_text($value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/<br\s*\/?>/i', "\n", $value);
    $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $lines = [];
    foreach (preg_split('/\R+/', (string) $value) as $line) {
        $line = trim((string) $line);
        $line = trim($line, "- \t");
        if ($line === '' || $line === '-') {
            continue;
        }
        if (!in_array($line, $lines, true)) {
            $lines[] = $line;
        }
    }

    return implode("\n", $lines);
}

function order_approval_lookup_customer_address_text(PDO $conn, string $adrCode): string
{
    $adrCode = trim($adrCode);
    if ($adrCode === '') {
        return '';
    }

    try {
        $stmt = $conn->prepare("
            SELECT cuname, st1, st2, city, pin, state, country, custaddr
            FROM customer_address
            WHERE TRIM(adr_code) = :code
            LIMIT 1
        ");
        $stmt->execute([':code' => $adrCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return '';
        }

        $fromStored = order_approval_format_address_text($row['custaddr'] ?? '');
        if ($fromStored !== '') {
            return $fromStored;
        }

        $parts = array_filter([
            trim((string) ($row['cuname'] ?? '')),
            trim((string) ($row['st1'] ?? '')),
            trim((string) ($row['st2'] ?? '')),
            trim((string) ($row['city'] ?? '')),
            trim((string) ($row['state'] ?? '')),
            trim((string) ($row['pin'] ?? '')),
            trim((string) ($row['country'] ?? '')),
        ], static fn($value) => $value !== '' && $value !== '-');

        return implode("\n", $parts);
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Dealer delivery address for Approval popup (hidden when End Customer).
 *
 * @param array<string, mixed> $headerRow
 */
function order_approval_dealer_address_from_header(array $headerRow, PDO $conn, ?PDO $dpconn = null): string
{
    $deliveryCode = trim((string) ($headerRow['delivery_code'] ?? ''));
    $storedEmail = trim((string) ($headerRow['email'] ?? ''));
    $isEndCustomer = ($storedEmail !== '' || $deliveryCode === '');
    if ($isEndCustomer) {
        return '';
    }

    $codes = [];
    foreach ([$deliveryCode, trim((string) ($headerRow['adrcode'] ?? '')), trim((string) ($headerRow['deladdr'] ?? ''))] as $code) {
        $code = trim($code);
        if ($code === '' || stripos($code, '<br') !== false) {
            continue;
        }
        if (!in_array($code, $codes, true)) {
            $codes[] = $code;
        }
    }

    $lookups = [];
    if ($dpconn instanceof PDO) {
        $lookups[] = $dpconn;
    }
    $lookups[] = $conn;

    foreach ($codes as $code) {
        foreach ($lookups as $lookupConn) {
            $address = order_approval_lookup_customer_address_text($lookupConn, $code);
            if ($address !== '') {
                return $address;
            }
        }
    }

    return order_approval_format_address_text($headerRow['invaddr'] ?? '');
}

/**
 * @return array<int, array<string, mixed>>
 */
function order_approval_pending_items(PDO $conn, ?PDO $dpconn = null): array
{
    order_approval_ensure_schema($conn);

    $seeAll = is_system_admin();
    $assignedToUserId = current_user_id($conn);
    if (!$seeAll && ($assignedToUserId === null || $assignedToUserId <= 0)) {
        return [];
    }

    if ($seeAll) {
        $stmt = $conn->query("
            SELECT r.*
            FROM order_approval_requests r
            WHERE r.status = 'pending'
              AND " . order_approval_order_row_sql() . "
            ORDER BY r.created_at DESC, r.id DESC
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT r.*
            FROM order_approval_requests r
            WHERE r.status = 'pending'
              AND r.assigned_to_user_id = :assigned_to_user_id
              AND " . order_approval_order_row_sql() . "
            ORDER BY r.created_at DESC, r.id DESC
        ");
        $stmt->bindValue(':assigned_to_user_id', $assignedToUserId, PDO::PARAM_INT);
        $stmt->execute();
    }

    if (!$stmt) {
        return [];
    }

    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row = order_approval_hydrate_request($row);
        $refno = trim((string) ($row['refno'] ?? ''));
        if ($refno === '') {
            continue;
        }
        $lines = order_approval_order_lines($conn, $refno);
        if ($lines === [] || !order_approval_lines_need_approval($lines)) {
            continue;
        }
        $header = $lines[0];
        $orderNumber = trim((string) ($header['order_number'] ?? ''));
        if ($orderNumber !== '') {
            continue;
        }

        $level = (string) ($row['current_level'] ?? 'level_1');
        if ($level === 'level_2' && empty($header['l1_approved_user_id'])) {
            continue;
        }

        $summary = order_approval_products_summary($lines);
        $endCustomer = order_approval_end_customer_from_header($header);
        $dealerAddress = $endCustomer['is_end_customer']
            ? ''
            : order_approval_dealer_address_from_header($header, $conn, $dpconn);
        $customer = trim((string) ($header['cuname'] ?? ''));
        $cuno = trim((string) ($header['cuno'] ?? ''));
        if ($customer === '') {
            $customer = $cuno !== '' ? $cuno : '-';
        } elseif ($cuno !== '') {
            $customer .= ' [' . $cuno . ']';
        }
        $indentDate = (string) ($header['indent_date'] ?? $row['created_at'] ?? '');
        $orderTime = trim((string) ($header['order_time'] ?? ''));
        $createdAt = trim($indentDate . ($orderTime !== '' ? ' ' . $orderTime : ''));
        $l1Details = $level === 'level_2'
            ? order_approval_order_level_details($conn, $header, $refno)
            : [
                'l1_status' => '',
                'l1_approved_by' => '',
                'l1_approved_at' => '',
                'l1_remarks' => '',
            ];

        $items[] = [
            'claim_type' => 'cart',
            'id' => (int) ($row['id'] ?? 0),
            'complaint_id' => 0,
            'fab_number' => $refno,
            'customer_name' => $customer,
            'details' => $summary['text'],
            'details_html' => $summary['html'],
            'parts_html' => $summary['html'],
            'fab_html' => htmlspecialchars($refno, ENT_QUOTES, 'UTF-8'),
            'warranty_label' => $summary['warranty'],
            'warranty_class' => '',
            'justification' => '',
            'stage_label' => order_approval_level_label($level) . ': Pending',
            'overall_status' => 'Pending',
            'created_by' => order_approval_requester_display_name($conn, $row, $header),
            'created_at' => $createdAt !== '' ? $createdAt : (string) ($row['created_at'] ?? ''),
            'level' => $level,
            'action_url' => 'approvals.php',
            'decision_field' => 'order_decision',
            'remarks_field' => 'order_remarks',
            'action_type' => 'approve',
            'can_decide' => order_approval_is_assigned_to_current_user($conn, $row),
            'item_code' => $refno,
            'item_name' => $summary['text'],
            'price_type_label' => $summary['warranty'],
            'unit_price' => '-',
            'qty' => (string) count($lines),
            'order_type_label' => trim((string) ($header['indent_number'] ?? '')) !== ''
                ? (string) $header['indent_number']
                : '-',
            'approval_level_label' => order_approval_level_label($level),
            'delivery_address_type' => $endCustomer['is_end_customer'] ? 'end_customer' : 'dealer',
            'dealer_address' => $dealerAddress,
            'end_customer_name' => $endCustomer['name'],
            'end_customer_email' => $endCustomer['email'],
            'end_customer_street1' => $endCustomer['street1'],
            'end_customer_street2' => $endCustomer['street2'],
            'end_customer_pincode' => $endCustomer['pincode'],
            'end_customer_city' => $endCustomer['city'],
            'end_customer_district' => $endCustomer['district'],
            'end_customer_state' => $endCustomer['state'],
            'l1_status' => (string) ($l1Details['l1_status'] ?? ''),
            'l1_approved_by' => (string) ($l1Details['l1_approved_by'] ?? ''),
            'l1_approved_at' => (string) ($l1Details['l1_approved_at'] ?? ''),
            'l1_remarks' => (string) ($l1Details['l1_remarks'] ?? ''),
        ];
    }

    return $items;
}

/**
 * @return array{error:?string, generate_ao:bool, refno:string, message:string}
 */
function order_approval_decide(
    PDO $conn,
    array $request,
    string $decision,
    string $remarks,
    string $actedBy
): array {
    $empty = ['error' => null, 'generate_ao' => false, 'refno' => '', 'message' => ''];
    $request = order_approval_hydrate_request($request);
    $requestId = (int) ($request['id'] ?? 0);
    $refno = trim((string) ($request['refno'] ?? ''));
    $status = strtolower(trim((string) ($request['status'] ?? '')));
    $currentLevel = (string) ($request['current_level'] ?? 'level_1');
    $l2Required = !empty($request['l2_required']) && $request['l2_required'] !== 'f' && $request['l2_required'] !== 'false';

    if ($requestId <= 0 || $refno === '' || $status !== 'pending') {
        return array_merge($empty, ['error' => 'This request is no longer pending.']);
    }
    if (!order_approval_is_assigned_to_current_user($conn, $request)) {
        return array_merge($empty, ['error' => 'You are not assigned for this approval request.']);
    }

    $decision = strtolower(trim($decision));
    if ($decision !== 'approved' && $decision !== 'rejected') {
        return array_merge($empty, ['error' => 'Invalid approval action.']);
    }
    if ($decision === 'rejected' && trim($remarks) === '') {
        return array_merge($empty, ['error' => 'Remarks are required to reject this request.']);
    }

    $lines = order_approval_order_lines($conn, $refno);
    if ($lines === []) {
        return array_merge($empty, ['error' => 'Order not found.']);
    }
    $header = $lines[0];
    if (trim((string) ($header['order_number'] ?? '')) !== '') {
        return array_merge($empty, ['error' => 'AO Number has already been generated for this order.']);
    }

    $actedByUserId = current_user_id($conn);
    if ($actedByUserId === null || $actedByUserId <= 0) {
        return array_merge($empty, ['error' => 'Unable to identify the logged-in user.']);
    }

    if ($currentLevel !== 'level_1' && $currentLevel !== 'level_2') {
        return array_merge($empty, ['error' => 'Invalid approval level.']);
    }
    if ($currentLevel === 'level_2') {
        if (!$l2Required) {
            return array_merge($empty, ['error' => 'This order does not require Level 2 approval.']);
        }
        if (empty($header['l1_approved_user_id'])) {
            return array_merge($empty, ['error' => 'Level 2 approval is not allowed before Level 1 approval is completed.']);
        }
    }

    $trimmedRemarks = trim($remarks);

    if ($decision === 'rejected') {
        $updReq = $conn->prepare("
            UPDATE order_approval_requests SET
                status = 'rejected',
                remarks = :remarks,
                decided_by = :decided_by,
                decided_by_user_id = :decided_by_user_id,
                decided_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND status = 'pending'
        ");
        $updReq->bindValue(':remarks', $trimmedRemarks !== '' ? $trimmedRemarks : null, $trimmedRemarks !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $updReq->bindValue(':decided_by', $actedBy);
        if ($actedByUserId !== null && $actedByUserId > 0) {
            $updReq->bindValue(':decided_by_user_id', $actedByUserId, PDO::PARAM_INT);
        } else {
            $updReq->bindValue(':decided_by_user_id', null, PDO::PARAM_NULL);
        }
        $updReq->bindValue(':id', $requestId, PDO::PARAM_INT);
        $updReq->execute();
        if ($updReq->rowCount() < 1) {
            return array_merge($empty, ['error' => 'This request is no longer pending.']);
        }
        order_approval_append_history($conn, $requestId, 'rejected', $trimmedRemarks, $actedBy, $actedByUserId);

        $updOrd = $conn->prepare('
            UPDATE plexecom_customer_units SET
                approval_status = :approval_status,
                approval_remarks = :remarks
            WHERE refno = :refno
        ');
        $updOrd->bindValue(':approval_status', 'rejected');
        $updOrd->bindValue(':remarks', $trimmedRemarks !== '' ? $trimmedRemarks : null, $trimmedRemarks !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $updOrd->bindValue(':refno', $refno);
        $updOrd->execute();

        return [
            'error' => null,
            'generate_ao' => false,
            'refno' => $refno,
            'message' => 'Order ' . $refno . ' has been rejected.',
        ];
    }

    if ($currentLevel === 'level_1' && $l2Required) {
        $requesterUserId = (int) ($request['requested_by_user_id'] ?? 0);
        if ($requesterUserId <= 0) {
            $requesterUserId = (int) (order_approval_user_id_by_username($conn, (string) ($request['requested_by'] ?? '')) ?? 0);
        }
        $l2Assignee = $requesterUserId > 0
            ? user_assigned_approver_id($conn, $requesterUserId, 'level_2')
            : null;
        if ($l2Assignee === null) {
            return array_merge($empty, ['error' => 'Level 2 Approval user is not assigned for this dealer.']);
        }

        $updOrd = $conn->prepare('
            UPDATE plexecom_customer_units SET
                l1_approved_user_id = :user_id,
                l1_approved_at = CURRENT_TIMESTAMP,
                l2_approved_user_id = NULL,
                l2_approved_at = NULL,
                approval_status = :approval_status,
                approval_remarks = :remarks
            WHERE refno = :refno
        ');
        $updOrd->bindValue(':user_id', $actedByUserId, PDO::PARAM_INT);
        $updOrd->bindValue(':approval_status', 'pending_l2');
        $updOrd->bindValue(':remarks', $trimmedRemarks !== '' ? $trimmedRemarks : null, $trimmedRemarks !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $updOrd->bindValue(':refno', $refno);
        $updOrd->execute();

        $updReq = $conn->prepare("
            UPDATE order_approval_requests SET
                current_level = 'level_2',
                approval_level = 'level_2',
                assigned_to_user_id = :assigned_to_user_id,
                remarks = :remarks,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND status = 'pending'
        ");
        order_approval_bind_assigned_to($updReq, $l2Assignee);
        $updReq->bindValue(':remarks', $trimmedRemarks !== '' ? $trimmedRemarks : null, $trimmedRemarks !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $updReq->bindValue(':id', $requestId, PDO::PARAM_INT);
        $updReq->execute();
        order_approval_append_history($conn, $requestId, 'approved', $trimmedRemarks, $actedBy, $actedByUserId);

        order_approval_notify_assigned_approver(
            $conn,
            $requestId,
            'level_2',
            $refno,
            $l2Assignee,
            $actedByUserId,
            'Order'
        );

        return [
            'error' => null,
            'generate_ao' => false,
            'refno' => $refno,
            'message' => 'Order ' . $refno . ' has been approved at Level 1 and sent for Level 2 approval.',
        ];
    }

    if ($currentLevel === 'level_1') {
        $updOrd = $conn->prepare('
            UPDATE plexecom_customer_units SET
                l1_approved_user_id = :user_id,
                l1_approved_at = CURRENT_TIMESTAMP,
                l2_approved_user_id = NULL,
                l2_approved_at = NULL,
                approval_status = :approval_status,
                approval_remarks = :remarks
            WHERE refno = :refno
        ');
        $updOrd->bindValue(':user_id', $actedByUserId, PDO::PARAM_INT);
        $updOrd->bindValue(':approval_status', 'approved');
        $updOrd->bindValue(':remarks', $trimmedRemarks !== '' ? $trimmedRemarks : null, $trimmedRemarks !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $updOrd->bindValue(':refno', $refno);
        $updOrd->execute();
    } else {
        $updOrd = $conn->prepare('
            UPDATE plexecom_customer_units SET
                l2_approved_user_id = :user_id,
                l2_approved_at = CURRENT_TIMESTAMP,
                approval_status = :approval_status,
                approval_remarks = :remarks
            WHERE refno = :refno
        ');
        $updOrd->bindValue(':user_id', $actedByUserId, PDO::PARAM_INT);
        $updOrd->bindValue(':approval_status', 'approved');
        $updOrd->bindValue(':remarks', $trimmedRemarks !== '' ? $trimmedRemarks : null, $trimmedRemarks !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $updOrd->bindValue(':refno', $refno);
        $updOrd->execute();
    }

    $updReq = $conn->prepare("
        UPDATE order_approval_requests SET
            status = 'approved',
            remarks = :remarks,
            decided_by = :decided_by,
            decided_by_user_id = :decided_by_user_id,
            decided_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND status = 'pending'
    ");
    $updReq->bindValue(':remarks', $trimmedRemarks !== '' ? $trimmedRemarks : null, $trimmedRemarks !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $updReq->bindValue(':decided_by', $actedBy);
    if ($actedByUserId !== null && $actedByUserId > 0) {
        $updReq->bindValue(':decided_by_user_id', $actedByUserId, PDO::PARAM_INT);
    } else {
        $updReq->bindValue(':decided_by_user_id', null, PDO::PARAM_NULL);
    }
    $updReq->bindValue(':id', $requestId, PDO::PARAM_INT);
    $updReq->execute();
    if ($updReq->rowCount() < 1) {
        return array_merge($empty, ['error' => 'This request is no longer pending.']);
    }
    order_approval_append_history($conn, $requestId, 'approved', $trimmedRemarks, $actedBy, $actedByUserId);

    $levelLabel = order_approval_level_label($currentLevel);

    return [
        'error' => null,
        'generate_ao' => true,
        'refno' => $refno,
        'message' => 'Order ' . $refno . ' has been approved at ' . $levelLabel . '. AO Number will be generated.',
    ];
}

function order_approval_ao_block_reason(array $header): ?string
{
    $approvalStatus = strtolower(trim((string) ($header['approval_status'] ?? '')));
    if ($approvalStatus === '') {
        return null;
    }

    $l2Required = !empty($header['l2_required']) && $header['l2_required'] !== 'f' && $header['l2_required'] !== 'false';
    if ($approvalStatus === 'pending_l1' || $approvalStatus === 'pending_l2' || $approvalStatus === 'rejected') {
        return 'AO Number cannot be generated until required approvals are completed.';
    }
    if ($approvalStatus === 'not_required') {
        return null;
    }
    if ($approvalStatus === 'approved') {
        if (empty($header['l1_approved_user_id'])) {
            return 'Level 1 approval is required before AO Number generation.';
        }
        if ($l2Required && empty($header['l2_approved_user_id'])) {
            return 'Level 2 approval is required before AO Number generation.';
        }

        return null;
    }

    return 'AO Number cannot be generated until required approvals are completed.';
}