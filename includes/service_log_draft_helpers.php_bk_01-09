<?php

require_once __DIR__ . '/service_log_helpers.php';
require_once __DIR__ . '/customer_feedback_rating_helpers.php';
require_once __DIR__ . '/after_market_access_helpers.php';

function service_log_is_draft_value($value): bool
{
    return (int) $value === 1;
}

function service_log_draft_nullable_value(string $value)
{
    return trim($value) === '' ? null : $value;
}

function service_log_validate_part_replacement_entries_for_draft(array $entries): ?string
{
    foreach ($entries as $index => $entry) {
        $quantity = trim($entry['quantity'] ?? '');
        if ($quantity === '') {
            continue;
        }

        if (!ctype_digit($quantity) || (int) $quantity < 1) {
            $label = 'Part replacement entry ' . ($index + 1);

            return $label . ': Quantity must be a positive whole number (minimum 1).';
        }
    }

    return null;
}

function service_log_validate_draft(PDO $conn, array $data, int $recordId = 0): ?string
{
    if ($data['installed_base_id'] === '' || (int) $data['installed_base_id'] <= 0) {
        return 'Installed base record is required.';
    }

    if ($data['fab_number'] === '') {
        return 'Fab Number is required.';
    }

    if ($recordId > 0 && $data['serial_number'] === '') {
        return 'Serial Number is required.';
    }

    if ($data['machine_model'] === '') {
        return 'Machine Model is required.';
    }

    if ($data['warranty_chargeable'] === '') {
        return 'Warranty / Chargeable is required.';
    }

    if (!scm_option_exists($conn, 'warranty_chargeable', $data['warranty_chargeable'])) {
        return 'Invalid Warranty / Chargeable selection.';
    }

    if ($data['complaint_date'] === '') {
        return 'Log Date is required.';
    }

    if ($data['issue_description'] === '') {
        return 'Issue / Service Description is required.';
    }

    if ($data['engineer_name'] === '') {
        return 'Engineer Name is required.';
    }

    if (!preg_match('/^[A-Za-z]+(?:\s+[A-Za-z]+)*$/', $data['engineer_name'])) {
        return 'Engineer Name can contain only alphabetic characters and spaces.';
    }

    if (strlen($data['engineer_name']) > 150) {
        return 'Engineer Name cannot exceed 150 characters.';
    }

    if ($data['visit_date'] === '') {
        return 'Visit Date is required.';
    }

    if ($data['action_taken'] === '') {
        return 'Action Taken is required.';
    }

    if ($data['part_replaced'] === '') {
        return 'Part Replaced is required.';
    }

    if (!scm_option_exists($conn, 'part_replaced', $data['part_replaced'])) {
        return 'Invalid Part Replaced selection.';
    }

    if (!empty($data['part_replacement_multi']) && service_log_part_replaced_is_yes($data['part_replaced'])) {
        if ($data['running_hours'] !== '') {
            if (!is_numeric($data['running_hours']) || (float) $data['running_hours'] <= 0) {
                return 'Running Hours must be greater than 0.';
            }
        }

        $entryError = service_log_validate_part_replacement_entries_for_draft($data['part_replacement_entries'] ?? []);
        if ($entryError !== null) {
            return $entryError;
        }

        $customerFeedback = trim($data['customer_feedback']);
        if ($customerFeedback !== '' && !customer_feedback_rating_is_valid($customerFeedback)) {
            return 'Please select a customer feedback rating between 1 and 10.';
        }

        if (strlen($data['remarks']) > 1000) {
            return 'Remarks cannot exceed 1000 characters.';
        }
    }

    if (strtotime($data['visit_date']) < strtotime($data['complaint_date'])) {
        return 'Visit Date cannot be earlier than Log Date.';
    }

    if ($data['closure_date'] !== '' && strtotime($data['closure_date']) < strtotime($data['visit_date'])) {
        return 'Closure Date cannot be earlier than Visit Date.';
    }

    foreach (service_log_remaining_consumable_fields() as $item) {
        $hoursKey = $item['key'] . '_remaining_hours';

        if ($data[$hoursKey] !== ''
            && (!is_numeric($data[$hoursKey]) || (float) $data[$hoursKey] < 0)) {
            return $item['label'] . ' Remaining Hours must be a valid non-negative number.';
        }
    }

    return null;
}

function service_log_apply_part_replacement_fields_for_draft_save(array &$data): void
{
    if (empty($data['part_replacement_multi'])) {
        return;
    }

    if (!service_log_part_replaced_is_yes($data['part_replaced'])) {
        $data['part_replacement_entries'] = [];
        $data['running_hours'] = '';
        $data['customer_feedback'] = '';
        $data['remarks'] = '';
        return;
    }

    $data['part_replacement_entries'] = array_values(array_filter(
        $data['part_replacement_entries'] ?? [],
        static function (array $entry): bool {
            return trim($entry['machine_model_code'] ?? '') !== ''
                || trim($entry['machine_model'] ?? '') !== ''
                || trim($entry['quantity'] ?? '') !== '';
        }
    ));

    $commonRunningHours = $data['running_hours'];
    foreach ($data['part_replacement_entries'] as &$entry) {
        $entry['running_hours'] = $commonRunningHours;
    }
    unset($entry);
}

function service_log_draft_should_sync_part_replacements(array $data): bool
{
    if (empty($data['part_replacement_multi'])) {
        return false;
    }

    if (!service_log_part_replaced_is_yes($data['part_replaced'])) {
        return false;
    }

    return ($data['part_replacement_entries'] ?? []) !== [];
}

function service_log_draft_sync_part_replacements(PDO $conn, int $serviceLogId, array $data): void
{
    if (!service_log_draft_should_sync_part_replacements($data)) {
        service_log_soft_delete_part_replacements($conn, $serviceLogId);
        return;
    }

    service_log_sync_part_replacements($conn, $serviceLogId, $data);
}

function service_log_bind_draft_fields(PDOStatement $stmt, array $data, array $installedBase): void
{
    $stmt->bindValue(':installed_base_id', (int) $data['installed_base_id'], PDO::PARAM_INT);
    $stmt->bindValue(':order_ref_id', '0', PDO::PARAM_INT);
    $stmt->bindValue(':order_id', '0', PDO::PARAM_INT);
    $stmt->bindValue(':fab_number', $data['fab_number']);
    $stmt->bindValue(':serial_number', service_log_draft_nullable_value($data['serial_number']));
    $stmt->bindValue(':machine_model', service_log_draft_nullable_value($data['machine_model']));
    $stmt->bindValue(':warranty_chargeable', service_log_draft_nullable_value($data['warranty_chargeable']));
    $stmt->bindValue(':complaint_date', service_log_draft_nullable_value($data['complaint_date']));
    $stmt->bindValue(':issue_description', service_log_draft_nullable_value($data['issue_description']));
    $stmt->bindValue(':engineer_name', service_log_draft_nullable_value($data['engineer_name']));
    $stmt->bindValue(':visit_date', service_log_draft_nullable_value($data['visit_date']));
    $stmt->bindValue(':action_taken', service_log_draft_nullable_value($data['action_taken']));
    $stmt->bindValue(':closure_date', service_log_draft_nullable_value($data['closure_date']));
    $stmt->bindValue(':part_replaced', service_log_draft_nullable_value($data['part_replaced']));
    $stmt->bindValue(':running_hours', $data['running_hours'] !== '' ? $data['running_hours'] : null);
    $stmt->bindValue(':customer_feedback', $data['customer_feedback'] !== '' ? $data['customer_feedback'] : null);
    $stmt->bindValue(':remarks', $data['remarks'] !== '' ? $data['remarks'] : null);
    service_log_bind_remaining_consumables($stmt, $data);
}

function service_log_save_draft_record(
    PDO $conn,
    array $post,
    string $username,
    int $createdBy,
    bool $canAdd,
    bool $canEdit
): array {
    $recordId = (int) ($post['record_id'] ?? 0);
    $data = service_log_from_post($post);

    if ($recordId > 0) {
        if (!$canEdit) {
            return ['success' => false, 'message' => 'Access denied. You do not have permission to edit service log records.'];
        }

        if (!after_market_user_can_access_record($conn, 'service_logs', $recordId)) {
            return ['success' => false, 'message' => 'Access denied. You do not have permission to edit this record.'];
        }

        $existingStmt = $conn->prepare('
            SELECT is_draft
            FROM service_logs
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ');
        $existingStmt->bindValue(':id', $recordId, PDO::PARAM_INT);
        $existingStmt->execute();
        $existingRow = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingRow) {
            return ['success' => false, 'message' => 'Record not found or already deleted.'];
        }

        if (!service_log_is_draft_value($existingRow['is_draft'] ?? 0)) {
            return ['success' => false, 'message' => 'Only draft service logs can be saved as draft.'];
        }
    } elseif (!$canAdd) {
        return ['success' => false, 'message' => 'Access denied. You do not have permission to add service log records.'];
    }

    if ($createdBy === null || $createdBy <= 0) {
        return ['success' => false, 'message' => 'Unable to resolve logged-in user.'];
    }

    $installedBaseId = (int) $data['installed_base_id'];
    $installedBase = $installedBaseId > 0
        ? service_log_get_installed_base($conn, $installedBaseId, $username)
        : null;

    if (!$installedBase) {
        return ['success' => false, 'message' => 'Selected installed base record was not found or is not assigned to your account.'];
    }

    $data['machine_model'] = service_log_machine_model_from_installed_base($installedBase);

    if ($recordId > 0) {
        $preserveError = service_log_preserve_serial_number($conn, $data, $recordId);
        if ($preserveError !== null) {
            return ['success' => false, 'message' => $preserveError];
        }
    }

    service_log_apply_part_replacement_fields_for_draft_save($data);

    $validationError = service_log_validate_draft($conn, $data, $recordId);
    if ($validationError !== null) {
        return ['success' => false, 'message' => $validationError];
    }

    if (trim((string) ($installedBase['fab_number'] ?? '')) !== $data['fab_number']) {
        return ['success' => false, 'message' => 'Fab Number does not match the selected installed base record.'];
    }

    try {
        if ($recordId > 0) {
            $update = $conn->prepare('
                UPDATE service_logs SET
                    installed_base_id = :installed_base_id,
                    order_ref_id = :order_ref_id,
                    order_id = :order_id,
                    fab_number = :fab_number,
                    serial_number = :serial_number,
                    machine_model = :machine_model,
                    warranty_chargeable = :warranty_chargeable,
                    complaint_date = :complaint_date,
                    issue_description = :issue_description,
                    engineer_name = :engineer_name,
                    visit_date = :visit_date,
                    action_taken = :action_taken,
                    closure_date = :closure_date,
                    part_replaced = :part_replaced,
                    running_hours = :running_hours,
                    customer_feedback = :customer_feedback,
                    remarks = :remarks,
                    ' . service_log_remaining_consumable_set_clause() . ',
                    is_draft = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
                  AND deleted_at IS NULL
                  AND is_draft = 1
            ');
            service_log_bind_draft_fields($update, $data, $installedBase);
            $update->bindValue(':id', $recordId, PDO::PARAM_INT);
            $update->execute();

            if ($update->rowCount() === 0) {
                return ['success' => false, 'message' => 'Record not found or already deleted.'];
            }

            service_log_draft_sync_part_replacements($conn, $recordId, $data);

            return [
                'success' => true,
                'message' => 'Service log draft updated successfully.',
                'service_log_id' => $recordId,
            ];
        }

        $conn->beginTransaction();
        $data['serial_number'] = service_log_allocate_serial_number($conn);

        $insert = $conn->prepare('
            INSERT INTO service_logs (
                installed_base_id, order_ref_id, order_id, fab_number, serial_number, machine_model,
                warranty_chargeable, complaint_date, issue_description, engineer_name,
                visit_date, action_taken, closure_date, part_replaced,
                running_hours, customer_feedback, remarks,
                ' . service_log_remaining_consumable_insert_columns() . ',
                is_draft, created_by, username
            ) VALUES (
                :installed_base_id, :order_ref_id, :order_id, :fab_number, :serial_number, :machine_model,
                :warranty_chargeable, :complaint_date, :issue_description, :engineer_name,
                :visit_date, :action_taken, :closure_date, :part_replaced,
                :running_hours, :customer_feedback, :remarks,
                ' . service_log_remaining_consumable_insert_placeholders() . ',
                :is_draft, :created_by, :username
            )
        ');
        service_log_bind_draft_fields($insert, $data, $installedBase);
        $insert->bindValue(':is_draft', 1, PDO::PARAM_INT);
        $insert->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
        $insert->bindValue(':username', $username);
        $insert->execute();

        $newServiceLogId = (int) $conn->lastInsertId();
        service_log_draft_sync_part_replacements($conn, $newServiceLogId, $data);

        $conn->commit();

        return [
            'success' => true,
            'message' => 'Service log saved as draft successfully.',
            'service_log_id' => $newServiceLogId,
        ];
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        return ['success' => false, 'message' => 'Failed to save service log draft.'];
    } catch (RuntimeException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function service_log_draft_badge_html(): string
{
    return '<span class="badge service-log-draft-badge service-log-draft-page-badge">'
        . '<i class="bi bi-file-earmark-text me-1"></i>Draft'
        . '</span>';
}

function service_log_grid_id_cell_html(int $id, bool $isDraft): string
{
    $label = '#' . $id;

    if (!$isDraft) {
        return htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    }

    return '<div class="service-log-grid-id-cell">'
        . '<span class="service-log-grid-id-value">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
        . '<span class="badge service-log-draft-grid-badge"><i class="bi bi-file-earmark-text me-1"></i> Draft</span>'
        . '</div>';
}

function service_log_draft_status_badge(bool $isDraft): string
{
    if (!$isDraft) {
        return '';
    }

    return service_log_draft_badge_html();
}

function service_log_installed_base_details_url(int $installedBaseId, array $query = []): string
{
    $params = array_merge(['id' => base64_encode((string) $installedBaseId)], $query);

    return 'installed_base_details.php?' . http_build_query($params);
}

function service_log_edit_draft_url(int $serviceLogId, int $installedBaseId): string
{
    return 'service_log.php?' . http_build_query([
        'edit_draft' => base64_encode((string) $serviceLogId),
        'return_ib' => base64_encode((string) $installedBaseId),
    ]);
}

function service_log_maybe_redirect_to_installed_base_details(array $post, string $successQueryKey): void
{
    $returnInstalledBaseId = (int) ($post['return_installed_base_id'] ?? 0);
    if ($returnInstalledBaseId <= 0) {
        return;
    }


    header('Location: ' . service_log_installed_base_details_url($returnInstalledBaseId, [$successQueryKey => '1']));
    exit;
}

function service_log_resolve_installed_base_draft_edit_context(PDO $conn, array $query): array
{
    $serviceLogId = (int) base64_decode((string) ($query['edit_draft'] ?? ''), true);
    $installedBaseId = (int) base64_decode((string) ($query['return_ib'] ?? ''), true);

    if ($serviceLogId <= 0 || $installedBaseId <= 0) {
        return [
            'service_log_id' => 0,
            'installed_base_id' => 0,
            'error' => null,
        ];
    }

    if (!after_market_user_can_access_record($conn, 'service_logs', $serviceLogId)) {
        return [
            'service_log_id' => 0,
            'installed_base_id' => 0,
            'error' => 'Draft service log record not found.',
        ];
    }

    $stmt = $conn->prepare('
        SELECT id, is_draft, installed_base_id
        FROM service_logs
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $serviceLogId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row
        || !service_log_is_draft_value($row['is_draft'] ?? 0)
        || (int) ($row['installed_base_id'] ?? 0) !== $installedBaseId) {
        return [
            'service_log_id' => 0,
            'installed_base_id' => 0,
            'error' => 'Draft service log record not found.',
        ];
    }

    if (!after_market_user_can_access_record($conn, 'installed_base', $installedBaseId)) {
        return [
            'service_log_id' => 0,
            'installed_base_id' => 0,
            'error' => 'Installed base record not found.',
        ];
    }

    return [
        'service_log_id' => $serviceLogId,
        'installed_base_id' => $installedBaseId,
        'error' => null,
    ];
}