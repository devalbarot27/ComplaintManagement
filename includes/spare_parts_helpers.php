<?php

require_once __DIR__ . '/system_config_master_helpers.php';

function spare_parts_warranty_types(PDO $conn): array
{
    return scm_get_active_names($conn, 'warranty_chargeable');
}

function spare_parts_reasons(PDO $conn): array
{
    return scm_get_active_names($conn, 'reason');
}

function spare_parts_items_from_post(array $post): array
{
    $raw = $post['spare_parts_items'] ?? [];
    if (!is_array($raw)) {
        return [];
    }

    $items = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }

        $items[] = [
            'id' => trim((string) ($row['id'] ?? '')),
            'spare_kit_number' => trim((string) ($row['spare_kit_number'] ?? '')),
            'reason' => trim((string) ($row['reason'] ?? '')),
            'quantity' => trim((string) ($row['quantity'] ?? '')),
            'order_value' => trim((string) ($row['order_value'] ?? '')),
        ];
    }

    return $items;
}

function spare_parts_resolve_items(array $data): array
{
    if (!empty($data['spare_parts_multi'])) {
        return $data['spare_parts_items'] ?? [];
    }

    if (($data['spare_kit_number'] ?? '') === '') {
        return [];
    }

    return [[
        'id' => '',
        'spare_kit_number' => $data['spare_kit_number'],
        'reason' => $data['reason'],
        'quantity' => $data['quantity'],
        'order_value' => $data['order_value'],
    ]];
}

function spare_parts_from_post(array $post): array
{
    $data = [
        'installed_base_id' => trim((string) ($post['installed_base_id'] ?? '')),
        'service_log_id' => trim((string) ($post['service_log_id'] ?? '')),
        'order_id' => trim((string) ($post['order_id'] ?? '')),
        'fab_number' => trim((string) ($post['fab_number'] ?? '')),
        'serial_number' => trim((string) ($post['serial_number'] ?? '')),
        'consumption_date' => trim((string) ($post['consumption_date'] ?? '')),
        'warranty_chargeable' => trim((string) ($post['warranty_chargeable'] ?? '')),
        'running_hours' => trim((string) ($post['running_hours'] ?? '')),
        'remarks' => trim((string) ($post['remarks'] ?? '')),
        'spare_kit_number' => trim((string) ($post['spare_kit_number'] ?? '')),
        'quantity' => trim((string) ($post['quantity'] ?? '')),
        'order_value' => trim((string) ($post['order_value'] ?? '')),
        'reason' => trim((string) ($post['reason'] ?? '')),
    ];

    if (!empty($post['spare_parts_multi'])) {
        $data['spare_parts_multi'] = true;
        $data['spare_parts_items'] = spare_parts_items_from_post($post);
    }

    return $data;
}

function spare_parts_get_installed_base(PDO $conn, int $installedBaseId): ?array
{
    require_once __DIR__ . '/after_market_access_helpers.php';

    if (!after_market_user_can_access_record($conn, 'installed_base', $installedBaseId)) {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id, order_ref_id, order_id, fab_number, customer_name, machine_model, running_hours
        FROM installed_base
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $installedBaseId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function spare_parts_get_service_log(PDO $conn, int $serviceLogId): ?array
{
    require_once __DIR__ . '/after_market_access_helpers.php';

    if (!after_market_user_can_access_record($conn, 'service_logs', $serviceLogId)) {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id, installed_base_id, order_id, serial_number
        FROM service_logs
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $serviceLogId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function spare_parts_validate_item(PDO $conn, array $item, int $entryNumber): ?string
{
    $label = 'Spare part item ' . $entryNumber;

    if (($item['spare_kit_number'] ?? '') === '') {
        return $label . ': Spare Kit Number is required.';
    }

    if (($item['reason'] ?? '') === '') {
        return $label . ': Reason is required.';
    }

    if (!scm_option_exists($conn, 'reason', $item['reason'])) {
        return $label . ': Invalid Reason selected.';
    }

    if (($item['quantity'] ?? '') === '') {
        return $label . ': Quantity is required.';
    }

    if (!is_numeric($item['quantity']) || (float) $item['quantity'] <= 0) {
        return $label . ': Quantity must be greater than zero.';
    }

    if (($item['order_value'] ?? '') === '') {
        return $label . ': Order Value is required.';
    }

    if (!is_numeric($item['order_value']) || (float) $item['order_value'] < 0) {
        return $label . ': Order Value must be a valid non-negative number.';
    }

    return null;
}

function spare_parts_validate(PDO $conn, array $data): ?string
{
    if ($data['installed_base_id'] === '' || (int) $data['installed_base_id'] <= 0) {
        return 'Machine (installed base) is required.';
    }

    if ($data['order_id'] === '') {
        return 'Order ID is required.';
    }

    if ($data['fab_number'] === '') {
        return 'Fab Number is required.';
    }

    if ($data['serial_number'] === '') {
        return 'Serial Number is required.';
    }

    if ($data['consumption_date'] === '') {
        return 'Consumption Date is required.';
    }

    if ($data['warranty_chargeable'] === '') {
        return 'Warranty / Chargeable is required.';
    }

    if (!scm_option_exists($conn, 'warranty_chargeable', $data['warranty_chargeable'])) {
        return 'Invalid Warranty / Chargeable selection.';
    }

    $items = spare_parts_resolve_items($data);
    if ($items === []) {
        return 'At least one spare part item is required.';
    }

    foreach ($items as $index => $item) {
        $itemError = spare_parts_validate_item($conn, $item, $index + 1);
        if ($itemError !== null) {
            return $itemError;
        }
    }

    if ($data['running_hours'] === '') {
        return 'Running Hours is required.';
    }

    if (!is_numeric($data['running_hours']) || (float) $data['running_hours'] <= 0) {
        return 'Running Hours must be greater than 0.';
    }

    if (strlen($data['remarks']) > 1000) {
        return 'Remarks cannot exceed 1000 characters.';
    }

    return null;
}

function spare_parts_insert_parent(
    PDO $conn,
    array $data,
    int $installedBaseId,
    int $serviceLogId,
    int $createdBy,
    string $username
): int {
    $insert = $conn->prepare('
        INSERT INTO spare_parts_consumption (
            installed_base_id, service_log_id, serial_number, fab_number, consumption_date,
            warranty_chargeable, running_hours, remarks, created_by, username
        ) VALUES (
            :installed_base_id, :service_log_id, :serial_number, :fab_number, :consumption_date,
            :warranty_chargeable, :running_hours, :remarks, :created_by, :username
        )
        RETURNING id
    ');

    $insert->bindValue(':installed_base_id', $installedBaseId, PDO::PARAM_INT);
    if ($serviceLogId > 0) {
        $insert->bindValue(':service_log_id', $serviceLogId, PDO::PARAM_INT);
    } else {
        $insert->bindValue(':service_log_id', null, PDO::PARAM_NULL);
    }
    $insert->bindValue(':serial_number', $data['serial_number']);
    $insert->bindValue(':fab_number', $data['fab_number']);
    $insert->bindValue(':consumption_date', $data['consumption_date']);
    $insert->bindValue(':warranty_chargeable', $data['warranty_chargeable']);
    $insert->bindValue(':running_hours', $data['running_hours']);
    $insert->bindValue(':remarks', $data['remarks'] !== '' ? $data['remarks'] : null);
    $insert->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    $insert->bindValue(':username', $username);
    $insert->execute();

    return (int) $insert->fetchColumn();
}

function spare_parts_update_parent(
    PDO $conn,
    int $consumptionId,
    array $data,
    int $installedBaseId,
    int $serviceLogId
): bool {
    $update = $conn->prepare('
        UPDATE spare_parts_consumption SET
            installed_base_id = :installed_base_id,
            service_log_id = :service_log_id,
            serial_number = :serial_number,
            fab_number = :fab_number,
            consumption_date = :consumption_date,
            warranty_chargeable = :warranty_chargeable,
            running_hours = :running_hours,
            remarks = :remarks,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id AND deleted_at IS NULL
    ');

    $update->bindValue(':installed_base_id', $installedBaseId, PDO::PARAM_INT);
    if ($serviceLogId > 0) {
        $update->bindValue(':service_log_id', $serviceLogId, PDO::PARAM_INT);
    } else {
        $update->bindValue(':service_log_id', null, PDO::PARAM_NULL);
    }
    $update->bindValue(':serial_number', $data['serial_number']);
    $update->bindValue(':fab_number', $data['fab_number']);
    $update->bindValue(':consumption_date', $data['consumption_date']);
    $update->bindValue(':warranty_chargeable', $data['warranty_chargeable']);
    $update->bindValue(':running_hours', $data['running_hours']);
    $update->bindValue(':remarks', $data['remarks'] !== '' ? $data['remarks'] : null);
    $update->bindValue(':id', $consumptionId, PDO::PARAM_INT);
    $update->execute();

    return $update->rowCount() > 0;
}

function spare_parts_insert_items(PDO $conn, int $consumptionId, array $items): void
{
    if ($consumptionId <= 0 || $items === []) {
        return;
    }

    $insert = $conn->prepare('
        INSERT INTO spare_parts_consumption_items (
            spare_parts_consumption_id, spare_kit_number, reason, quantity, order_value
        ) VALUES (
            :spare_parts_consumption_id, :spare_kit_number, :reason, :quantity, :order_value
        )
    ');

    foreach ($items as $item) {
        $insert->bindValue(':spare_parts_consumption_id', $consumptionId, PDO::PARAM_INT);
        $insert->bindValue(':spare_kit_number', $item['spare_kit_number']);
        $insert->bindValue(':reason', $item['reason']);
        $insert->bindValue(':quantity', $item['quantity']);
        $insert->bindValue(':order_value', $item['order_value']);
        $insert->execute();
    }
}

function spare_parts_items_for_consumption(PDO $conn, int $consumptionId): array
{
    if ($consumptionId <= 0) {
        return [];
    }

    $stmt = $conn->prepare('
        SELECT id, spare_kit_number, reason, quantity, order_value
        FROM spare_parts_consumption_items
        WHERE spare_parts_consumption_id = :id
          AND deleted_at IS NULL
        ORDER BY id
    ');
    $stmt->bindValue(':id', $consumptionId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function spare_parts_list_for_installed_base(PDO $conn, int $installedBaseId): array
{
    require_once __DIR__ . '/after_market_access_helpers.php';

    if ($installedBaseId <= 0) {
        return [];
    }

    if (!after_market_user_can_access_record($conn, 'installed_base', $installedBaseId)) {
        return [];
    }

    $scope = after_market_list_scope($conn);
    $scopeWhere = after_market_scope_where_for_alias($scope['where'], 'sp');

    $stmt = $conn->prepare("
        SELECT
            sp.*,
            ib.order_id,
            ib.customer_name,
            ib.machine_model
        FROM spare_parts_consumption sp
        LEFT JOIN installed_base ib
            ON ib.id = sp.installed_base_id
           AND ib.deleted_at IS NULL
        WHERE sp.installed_base_id = :installed_base_id
          AND {$scopeWhere}
        ORDER BY sp.created_at DESC, sp.id DESC
    ");
    $stmt->bindValue(':installed_base_id', $installedBaseId, PDO::PARAM_INT);
    foreach ($scope['params'] as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    $unique = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $unique[(int) $row['id']] = $row;
    }

    return array_values($unique);
}

function spare_parts_soft_delete_items_for_consumption(PDO $conn, int $consumptionId): void
{
    if ($consumptionId <= 0) {
        return;
    }

    $stmt = $conn->prepare('
        UPDATE spare_parts_consumption_items
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE spare_parts_consumption_id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $consumptionId, PDO::PARAM_INT);
    $stmt->execute();
}

function spare_parts_sync_items(PDO $conn, int $consumptionId, array $items): void
{
    if ($consumptionId <= 0) {
        return;
    }

    if ($items === []) {
        spare_parts_soft_delete_items_for_consumption($conn, $consumptionId);
        return;
    }

    $keptIds = [];

    $updateStmt = $conn->prepare('
        UPDATE spare_parts_consumption_items
        SET
            spare_kit_number = :spare_kit_number,
            reason = :reason,
            quantity = :quantity,
            order_value = :order_value,
            deleted_at = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND spare_parts_consumption_id = :spare_parts_consumption_id
          AND deleted_at IS NULL
    ');

    $insertStmt = $conn->prepare('
        INSERT INTO spare_parts_consumption_items (
            spare_parts_consumption_id, spare_kit_number, reason, quantity, order_value
        ) VALUES (
            :spare_parts_consumption_id, :spare_kit_number, :reason, :quantity, :order_value
        )
    ');

    foreach ($items as $item) {
        $itemId = (int) ($item['id'] ?? 0);

        if ($itemId > 0) {
            $updateStmt->bindValue(':spare_kit_number', $item['spare_kit_number']);
            $updateStmt->bindValue(':reason', $item['reason']);
            $updateStmt->bindValue(':quantity', $item['quantity']);
            $updateStmt->bindValue(':order_value', $item['order_value']);
            $updateStmt->bindValue(':id', $itemId, PDO::PARAM_INT);
            $updateStmt->bindValue(':spare_parts_consumption_id', $consumptionId, PDO::PARAM_INT);
            $updateStmt->execute();

            if ($updateStmt->rowCount() > 0) {
                $keptIds[] = $itemId;
                continue;
            }
        }

        $insertStmt->bindValue(':spare_parts_consumption_id', $consumptionId, PDO::PARAM_INT);
        $insertStmt->bindValue(':spare_kit_number', $item['spare_kit_number']);
        $insertStmt->bindValue(':reason', $item['reason']);
        $insertStmt->bindValue(':quantity', $item['quantity']);
        $insertStmt->bindValue(':order_value', $item['order_value']);
        $insertStmt->execute();
        $keptIds[] = (int) $conn->lastInsertId();
    }

    if ($keptIds === []) {
        spare_parts_soft_delete_items_for_consumption($conn, $consumptionId);
        return;
    }

    $placeholders = [];
    $params = [':spare_parts_consumption_id' => $consumptionId];
    foreach ($keptIds as $index => $keptId) {
        $paramKey = ':kept_id_' . $index;
        $placeholders[] = $paramKey;
        $params[$paramKey] = $keptId;
    }

    $deleteSql = '
        UPDATE spare_parts_consumption_items
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE spare_parts_consumption_id = :spare_parts_consumption_id
          AND deleted_at IS NULL
          AND id NOT IN (' . implode(', ', $placeholders) . ')
    ';
    $deleteStmt = $conn->prepare($deleteSql);
    foreach ($params as $key => $value) {
        $deleteStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $deleteStmt->execute();
}

function spare_parts_save_consumption(
    PDO $conn,
    array $data,
    int $installedBaseId,
    int $serviceLogId,
    int $createdBy,
    string $username
): int {
    $consumptionId = spare_parts_insert_parent(
        $conn,
        $data,
        $installedBaseId,
        $serviceLogId,
        $createdBy,
        $username
    );
    spare_parts_insert_items($conn, $consumptionId, spare_parts_resolve_items($data));

    return $consumptionId;
}

function spare_parts_update_consumption(
    PDO $conn,
    int $consumptionId,
    array $data,
    int $installedBaseId,
    int $serviceLogId
): bool {
    if (!spare_parts_update_parent($conn, $consumptionId, $data, $installedBaseId, $serviceLogId)) {
        return false;
    }

    spare_parts_sync_items($conn, $consumptionId, $data['spare_parts_items'] ?? []);

    return true;
}

function spare_parts_format_kit_summary(string $firstKit, int $itemCount): string
{
    if ($itemCount <= 1) {
        return $firstKit;
    }

    return $firstKit . ' (+' . ($itemCount - 1) . ' more)';
}

function spare_parts_items_totals(array $items): array
{
    $totalQty = 0.0;
    $totalValue = 0.0;

    foreach ($items as $item) {
        $totalQty += (float) ($item['quantity'] ?? 0);
        $totalValue += (float) ($item['order_value'] ?? 0);
    }

    return [
        'count' => count($items),
        'quantity' => $totalQty,
        'order_value' => $totalValue,
    ];
}

function spare_parts_format_date(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y', strtotime($value));
}

function spare_parts_format_datetime(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y h:i A', strtotime($value));
}

function spare_parts_format_currency($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return '₹' . number_format((float) $value, 2);
}

function spare_parts_format_quantity($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float) $value, 2, '.', '');
}

function spare_parts_display_value($value): string
{
    if ($value === null || trim((string) $value) === '') {
        return '-';
    }

    return trim((string) $value);
}

function spare_parts_create_record(PDO $conn, array $post, string $username, int $createdBy = 1): array
{
    $data = spare_parts_from_post($post);
    $validationError = spare_parts_validate($conn, $data);
    $installedBaseId = (int) $data['installed_base_id'];
    $installedBase = $installedBaseId > 0
        ? spare_parts_get_installed_base($conn, $installedBaseId)
        : null;
    $serviceLogId = $data['service_log_id'] !== '' ? (int) $data['service_log_id'] : 0;
    $serviceLog = $serviceLogId > 0
        ? spare_parts_get_service_log_for_user($conn, $serviceLogId, $username)
        : null;

    if ($validationError !== null) {
        return ['success' => false, 'message' => $validationError];
    }

    if (!$installedBase) {
        return ['success' => false, 'message' => 'Selected machine was not found in installed base records.'];
    }

    if ($serviceLogId > 0 && (!$serviceLog || (int) $serviceLog['installed_base_id'] !== $installedBaseId)) {
        return ['success' => false, 'message' => 'Selected service record does not belong to the selected machine.'];
    }

    if ($installedBase['order_id'] !== $data['order_id']) {
        return ['success' => false, 'message' => 'Order ID does not match the selected machine.'];
    }

    if (trim((string) ($installedBase['fab_number'] ?? '')) !== $data['fab_number']) {
        return ['success' => false, 'message' => 'Fab Number does not match the selected machine.'];
    }

    try {
        spare_parts_save_consumption(
            $conn,
            $data,
            $installedBaseId,
            $serviceLogId,
            $createdBy,
            $username
        );

        return ['success' => true, 'message' => 'Spare parts record saved successfully.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Failed to save spare parts record.'];
    }
}

function spare_parts_get_service_log_for_user(PDO $conn, int $serviceLogId, string $username = ''): ?array
{
    return spare_parts_get_service_log($conn, $serviceLogId);
}

function spare_parts_entry_actions(int $id, array $permissions = []): string
{
    $permissions = array_merge([
        'view' => false,
        'edit' => false,
        'delete' => false,
    ], $permissions);
    $encodedId = base64_encode((string) $id);

    $html = '<div class="d-flex gap-1">';

    if ($permissions['view']) {
        $html .= '
            <a href="spare_parts_consumption_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>';
    }

    if ($permissions['edit']) {
        $html .= '
            <button type="button" class="btn btn-sm btn-outline-dark edit-spare-parts-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>';
    }

    if ($permissions['delete']) {
        $html .= '
            <a href="delete_spare_parts_consumption.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this spare parts record?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>';
    }

    $html .= '</div>';

    return $html;
}
