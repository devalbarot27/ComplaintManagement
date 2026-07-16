<?php

require_once __DIR__ . '/complaint_service_log_helpers.php';
require_once __DIR__ . '/service_log_draft_helpers.php';

function complaint_service_log_validate_draft(PDO $conn, array $data, int $recordId = 0): ?string
{
    unset($recordId);

    if ($data['installed_base_id'] === '' || (int) $data['installed_base_id'] <= 0) {
        return 'Installed base record is required.';
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

    if ($data['machine_model'] === '') {
        return 'Machine Model is required.';
    }

    if ($data['warranty_chargeable'] === '') {
        return 'Warranty / Chargeable is required.';
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

    return null;
}

function complaint_service_log_save_draft_record(
    PDO $conn,
    array $post,
    string $username,
    int $createdBy
): array {
    if (empty($post['from_complaint_modal'])) {
        return ['success' => false, 'message' => 'Invalid complaint service log draft request.'];
    }

    $complaintId = (int) ($post['complaint_id'] ?? 0);
    if ($complaintId <= 0) {
        return ['success' => false, 'message' => 'Complaint reference is required.'];
    }

    if (!complaint_user_can_access_assigned_complaint($conn, $complaintId)) {
        return ['success' => false, 'message' => 'Access denied. You do not have permission to update this complaint.'];
    }

    $permissions = complaint_service_log_action_permissions($conn);
    $recordId = (int) ($post['record_id'] ?? 0);
    $data = service_log_from_post($post);
    $complaintCreateContext = null;

    if ($recordId > 0) {
        if (empty($permissions['edit'])) {
            return ['success' => false, 'message' => 'Access denied. You do not have permission to edit service log records.'];
        }

        complaint_service_log_ensure_schema($conn);

        if (!complaint_service_log_is_linked($conn, $complaintId, $recordId)) {
            return ['success' => false, 'message' => 'Record not found or already deleted.'];
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
    } else {
        if (empty($permissions['add'])) {
            return ['success' => false, 'message' => 'Access denied. You do not have permission to add service log records.'];
        }

        $complaintCreateContext = complaint_service_log_context_for_create($conn, $complaintId);
        if (empty($complaintCreateContext['success'])) {
            return [
                'success' => false,
                'message' => $complaintCreateContext['message'] ?? 'Unable to create service log for this complaint.',
            ];
        }
    }

    if ($createdBy <= 0) {
        return ['success' => false, 'message' => 'Unable to resolve logged-in user.'];
    }

    $installedBaseId = (int) $data['installed_base_id'];
    $installedBase = $installedBaseId > 0
        ? complaint_service_log_get_installed_base_row($conn, $installedBaseId)
        : null;

    if (!$installedBase) {
        return ['success' => false, 'message' => 'Selected installed base record was not found.'];
    }

    $data['machine_model'] = service_log_machine_model_from_installed_base($installedBase);

    if ($recordId > 0) {
        $preserveError = service_log_preserve_serial_number($conn, $data, $recordId);
        if ($preserveError !== null) {
            return ['success' => false, 'message' => $preserveError];
        }
    }

    service_log_apply_part_replacement_fields_for_draft_save($data);

    $validationError = complaint_service_log_validate_draft($conn, $data, $recordId);
    if ($validationError !== null) {
        return ['success' => false, 'message' => $validationError];
    }

    if ($installedBase['order_id'] !== $data['order_id']) {
        return ['success' => false, 'message' => 'Order ID does not match the selected installed base record.'];
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
                'complaint_id' => $complaintId,
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

        if (is_array($complaintCreateContext)) {
            complaint_service_log_create_mapping_for_service_log(
                $conn,
                $complaintId,
                $newServiceLogId,
                $complaintCreateContext,
                $createdBy
            );
        }

        $conn->commit();

        return [
            'success' => true,
            'message' => 'Service log saved as draft successfully.',
            'service_log_id' => $newServiceLogId,
            'complaint_id' => $complaintId,
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