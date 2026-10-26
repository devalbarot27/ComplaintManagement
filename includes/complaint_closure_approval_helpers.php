<?php

require_once __DIR__ . '/user_helpers.php';
require_once __DIR__ . '/notification_helpers.php';
require_once __DIR__ . '/complaint_assignment_mail_helpers.php';
require_once __DIR__ . '/complaint_activity_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';

function complaint_closure_approval_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $conn->exec('
        CREATE TABLE IF NOT EXISTS complaint_closure_approvals (
            id SERIAL PRIMARY KEY,
            complaint_id INTEGER NOT NULL,
            approver_user_id INTEGER NOT NULL,
            requested_by_user_id INTEGER NULL,
            status VARCHAR(20) NOT NULL,
            decision_remarks TEXT NULL,
            decided_by_user_id INTEGER NULL,
            decided_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ');
    $conn->exec('
        CREATE INDEX IF NOT EXISTS complaint_closure_approvals_complaint_id_idx
            ON complaint_closure_approvals (complaint_id, id DESC)
    ');

    $ensured = true;
}

function complaint_closure_approval_latest(PDO $conn, int $complaintId): ?array
{
    complaint_closure_approval_ensure_schema($conn);
    if ($complaintId <= 0) {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT *
        FROM complaint_closure_approvals
        WHERE complaint_id = :complaint_id
        ORDER BY id DESC
        LIMIT 1
    ');
    $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function complaint_closure_approval_status_label(?string $status): string
{
    $status = strtolower(trim((string) $status));

    return match ($status) {
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        default => 'Not requested',
    };
}

function complaint_closure_approval_user_label(PDO $conn, int $userId): string
{
    if ($userId <= 0) {
        return '-';
    }

    $user = user_get_by_id($conn, $userId);
    if ($user === null) {
        return '-';
    }

    return user_sales_coordinator_option_label($user);
}

function complaint_closure_approval_mapped_engineer(PDO $conn, int $creatorUserId): ?array
{
    if ($creatorUserId <= 0) {
        return null;
    }

    $creator = user_get_by_id($conn, $creatorUserId);
    if ($creator === null) {
        return null;
    }

    $engineerId = (int) ($creator['complaint_approval_user_id'] ?? 0);
    if (!user_is_valid_elgi_engineer($conn, $engineerId)) {
        return null;
    }

    return user_get_by_id($conn, $engineerId);
}

function complaint_closure_approval_requires_vayu_engineer(PDO $conn, int $complaintId): bool
{
    $creatorId = complaint_closure_approval_creator_id($conn, $complaintId);
    if ($creatorId <= 0) {
        return false;
    }

    $creator = user_get_by_id($conn, $creatorId);
    if ($creator === null || (int) ($creator['role'] ?? 0) !== DEALER_USER_ROLE) {
        return false;
    }

    return complaint_closure_approval_mapped_engineer($conn, $creatorId) !== null;
}

function complaint_closure_approval_ccs_admins(PDO $conn): array
{
    $stmt = $conn->prepare('
        SELECT id, name, username, email
        FROM user_master
        WHERE role = :role
          AND deleted_at IS NULL
        ORDER BY id ASC
    ');
    $stmt->bindValue(':role', CCS_ADMIN_ROLE, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Dealer users without a mapped Vayu Engineer, and every other role,
 * go straight to CCS Admin at Pending With HO.
 *
 * @return array{status: string, message: string, ccs_admins: array<int, array<string, mixed>>}
 */
function complaint_closure_approval_send_to_ccs(PDO $conn, int $complaintId): array
{
    $admins = complaint_closure_approval_ccs_admins($conn);
    complaint_log_activity(
        $conn,
        $complaintId,
        'Closure Approval',
        'Complaint sent to CCS Admin for closure.',
        (int) (current_user_id($conn) ?? 0)
    );

    $message = $admins === []
        ? 'Complaint status is Pending With HO. No CCS Admin user is available to notify.'
        : 'Complaint sent to CCS Admin. Status is Pending With HO.';

    return [
        'status' => 'sent_ccs',
        'message' => $message,
        'ccs_admins' => $admins,
    ];
}

function complaint_closure_approval_notify_ccs(PDO $conn, array $request, int $complaintId): void
{
    if (($request['status'] ?? '') !== 'sent_ccs') {
        return;
    }

    $admins = $request['ccs_admins'] ?? [];
    if (!is_array($admins)) {
        return;
    }

    foreach ($admins as $admin) {
        if (!is_array($admin)) {
            continue;
        }
        $adminId = (int) ($admin['id'] ?? 0);
        $adminName = user_sales_coordinator_option_label($admin);
        complaint_closure_approval_send_mail(
            $conn,
            $complaintId,
            trim((string) ($admin['email'] ?? '')),
            $adminName,
            'Complaint #' . $complaintId . ' is pending with HO for closure',
            'A complaint is pending with HO and is ready for CCS closure.',
            ''
        );
        notification_create(
            $conn,
            $adminId,
            'Complaint pending with HO',
            'Complaint #' . $complaintId . ' is pending with HO for closure.',
            'complaint-entry',
            $complaintId
        );
    }
}

function complaint_closure_approval_creator_id(PDO $conn, int $complaintId): int
{
    $stmt = $conn->prepare('
        SELECT added_by
        FROM complaints
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $complaintId, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

/**
 * Start a closure-approval cycle after service update, or when the creator resends.
 *
 * @return array{status: string, message: string, approval_id: int, approver_id: int, approver_name: string}
 */
function complaint_closure_approval_begin_request(PDO $conn, int $complaintId): array
{
    complaint_closure_approval_ensure_schema($conn);
    $empty = [
        'status' => 'skipped',
        'message' => '',
        'approval_id' => 0,
        'approver_id' => 0,
        'approver_name' => '',
    ];
    if ($complaintId <= 0) {
        return $empty;
    }

    $latest = complaint_closure_approval_latest($conn, $complaintId);
    if ($latest !== null && strtolower((string) ($latest['status'] ?? '')) === 'pending') {
        $empty['status'] = 'pending';
        $empty['approval_id'] = (int) ($latest['id'] ?? 0);
        $empty['approver_id'] = (int) ($latest['approver_user_id'] ?? 0);
        $empty['approver_name'] = complaint_closure_approval_user_label($conn, $empty['approver_id']);
        $empty['message'] = 'Closure approval is already pending with ' . $empty['approver_name'] . '.';

        return $empty;
    }

    $creatorId = complaint_closure_approval_creator_id($conn, $complaintId);
    $engineer = complaint_closure_approval_mapped_engineer($conn, $creatorId);
    if ($engineer === null) {
        complaint_log_activity(
            $conn,
            $complaintId,
            'Closure Approval',
            'Closure approval was not sent because the complaint creator has no mapped Vayu Engineer.',
            current_user_id($conn) ?? $creatorId
        );
        $empty['status'] = 'missing_engineer';
        $empty['message'] = 'Closure approval was not sent because the complaint creator has no mapped Vayu Engineer.';

        return $empty;
    }

    $engineerId = (int) ($engineer['id'] ?? 0);
    $engineerName = user_sales_coordinator_option_label($engineer);
    $insert = $conn->prepare('
        INSERT INTO complaint_closure_approvals (
            complaint_id,
            approver_user_id,
            requested_by_user_id,
            status,
            created_at,
            updated_at
        ) VALUES (
            :complaint_id,
            :approver_user_id,
            :requested_by_user_id,
            :status,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        )
        RETURNING id
    ');
    $insert->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $insert->bindValue(':approver_user_id', $engineerId, PDO::PARAM_INT);
    if ($creatorId > 0) {
        $insert->bindValue(':requested_by_user_id', $creatorId, PDO::PARAM_INT);
    } else {
        $insert->bindValue(':requested_by_user_id', null, PDO::PARAM_NULL);
    }
    $insert->bindValue(':status', 'pending');
    $insert->execute();

    $isResend = $latest !== null && strtolower((string) ($latest['status'] ?? '')) === 'rejected';
    complaint_log_activity(
        $conn,
        $complaintId,
        'Closure Approval',
        ($isResend ? 'Closure approval request resent to ' : 'Closure approval request sent to ')
            . $engineerName
            . '.',
        current_user_id($conn) ?? $creatorId
    );

    return [
        'status' => 'sent',
        'message' => 'Closure approval request sent to ' . $engineerName . '.',
        'approval_id' => (int) $insert->fetchColumn(),
        'approver_id' => $engineerId,
        'approver_name' => $engineerName,
    ];
}

function complaint_closure_approval_notify_request(PDO $conn, array $request, int $complaintId): void
{
    if (($request['status'] ?? '') !== 'sent') {
        return;
    }

    $approverId = (int) ($request['approver_id'] ?? 0);
    $approver = $approverId > 0 ? user_get_by_id($conn, $approverId) : null;
    $approverName = trim((string) ($request['approver_name'] ?? 'Vayu Engineer'));
    complaint_closure_approval_send_mail(
        $conn,
        $complaintId,
        trim((string) ($approver['email'] ?? '')),
        $approverName,
        'Complaint #' . $complaintId . ' is waiting for your closure approval',
        'A complaint closure request is waiting for your approval.',
        ''
    );
    notification_create(
        $conn,
        $approverId,
        'Complaint closure approval',
        'Complaint #' . $complaintId . ' is waiting for your closure approval.',
        'complaint-entry',
        $complaintId
    );
}

function complaint_closure_approval_close_block_message(PDO $conn, int $complaintId): ?string
{
    $latest = complaint_closure_approval_latest($conn, $complaintId);
    $status = strtolower(trim((string) ($latest['status'] ?? '')));
    if ($status === 'approved') {
        return null;
    }
    if ($status === 'pending') {
        return 'Complaint closure is waiting for Vayu Engineer approval.';
    }
    if ($status === 'rejected' && complaint_closure_approval_requires_vayu_engineer($conn, $complaintId)) {
        return 'Complaint closure was rejected by the Vayu Engineer. It must be approved before the CCS team can close it.';
    }
    if (!complaint_closure_approval_requires_vayu_engineer($conn, $complaintId)) {
        return null;
    }

    return 'Complaint closure requires approval from the mapped Vayu Engineer before the CCS team can close it.';
}

/**
 * @return array{ok: bool, message: string, notify_user_id: int, notify_title: string, notify_message: string, email: string, email_subject: string, email_intro: string, remarks: string}
 */
function complaint_closure_approval_decide(
    PDO $conn,
    int $complaintId,
    int $actorId,
    string $decision,
    string $remarks
): array {
    $fail = static function (string $message): array {
        return [
            'ok' => false,
            'message' => $message,
            'notify_user_id' => 0,
            'notify_title' => '',
            'notify_message' => '',
            'email' => '',
            'email_subject' => '',
            'email_intro' => '',
            'remarks' => '',
        ];
    };

    $decision = strtolower(trim($decision));
    if (!in_array($decision, ['approve', 'reject'], true)) {
        return $fail('Invalid closure approval action.');
    }

    $remarks = trim($remarks);
    if ($decision === 'reject' && $remarks === '') {
        return $fail('A rejection remark is required.');
    }
    if (strlen($remarks) > 500) {
        return $fail('Remarks cannot exceed 500 characters.');
    }

    $latest = complaint_closure_approval_latest($conn, $complaintId);
    if ($latest === null || strtolower((string) ($latest['status'] ?? '')) !== 'pending') {
        return $fail('This complaint does not have a pending closure approval request.');
    }
    if ($actorId <= 0 || $actorId !== (int) ($latest['approver_user_id'] ?? 0)) {
        return $fail('Only the mapped Vayu Engineer can decide this closure request.');
    }

    $status = $decision === 'approve' ? 'approved' : 'rejected';
    $update = $conn->prepare('
        UPDATE complaint_closure_approvals
        SET status = :status,
            decision_remarks = :decision_remarks,
            decided_by_user_id = :decided_by_user_id,
            decided_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND status = :pending_status
    ');
    $update->bindValue(':status', $status);
    if ($remarks === '') {
        $update->bindValue(':decision_remarks', null, PDO::PARAM_NULL);
    } else {
        $update->bindValue(':decision_remarks', $remarks);
    }
    $update->bindValue(':decided_by_user_id', $actorId, PDO::PARAM_INT);
    $update->bindValue(':id', (int) $latest['id'], PDO::PARAM_INT);
    $update->bindValue(':pending_status', 'pending');
    $update->execute();
    if ($update->rowCount() < 1) {
        return $fail('This closure approval request was already decided.');
    }

    $engineerName = complaint_closure_approval_user_label($conn, $actorId);
    $creatorId = (int) ($latest['requested_by_user_id'] ?? 0);
    if ($creatorId <= 0) {
        $creatorId = complaint_closure_approval_creator_id($conn, $complaintId);
    }
    $creator = $creatorId > 0 ? user_get_by_id($conn, $creatorId) : null;

    if ($status === 'approved') {
        $statusUpdate = $conn->prepare('
            UPDATE complaints
            SET status = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND deleted_at IS NULL
        ');
        $statusUpdate->bindValue(':status', COMPLAINT_STATUS_PENDING_HO, PDO::PARAM_INT);
        $statusUpdate->bindValue(':id', $complaintId, PDO::PARAM_INT);
        $statusUpdate->execute();

        $description = 'Closure request approved by ' . $engineerName . '. Approval status: Approved. Complaint status updated to Pending With HO.';
        if ($remarks !== '') {
            $description .= ' Remarks: ' . $remarks;
        }
        $title = 'Complaint closure approved';
        $notifyMessage = 'Complaint #' . $complaintId . ' closure request was approved by ' . $engineerName . '.';
        $emailSubject = 'Complaint #' . $complaintId . ' closure request approved';
        $emailIntro = 'Your complaint closure request has been approved.';
        $pageMessage = 'Closure request approved.';
    } else {
        $description = 'Closure request rejected by ' . $engineerName . '. Remarks: ' . $remarks;
        $title = 'Complaint closure rejected';
        $notifyMessage = 'Complaint #' . $complaintId . ' closure request was rejected by ' . $engineerName . '.';
        $emailSubject = 'Complaint #' . $complaintId . ' closure request rejected';
        $emailIntro = 'Your complaint closure request has been rejected.';
        $pageMessage = 'Closure request rejected.';
    }

    complaint_log_activity($conn, $complaintId, 'Closure Approval', $description, $actorId);

    return [
        'ok' => true,
        'message' => $pageMessage,
        'notify_user_id' => $creatorId,
        'notify_title' => $title,
        'notify_message' => $notifyMessage,
        'email' => trim((string) ($creator['email'] ?? '')),
        'email_subject' => $emailSubject,
        'email_intro' => $emailIntro,
        'remarks' => $remarks,
    ];
}

function complaint_closure_approval_notify_decision(PDO $conn, array $result, int $complaintId): void
{
    if (empty($result['ok'])) {
        return;
    }

    $creatorId = (int) ($result['notify_user_id'] ?? 0);
    complaint_closure_approval_send_mail(
        $conn,
        $complaintId,
        (string) ($result['email'] ?? ''),
        complaint_closure_approval_user_label($conn, $creatorId),
        (string) ($result['email_subject'] ?? ''),
        (string) ($result['email_intro'] ?? ''),
        (string) ($result['remarks'] ?? '')
    );
    notification_create(
        $conn,
        $creatorId,
        (string) ($result['notify_title'] ?? 'Complaint closure approval'),
        (string) ($result['notify_message'] ?? ''),
        'complaint-entry',
        $complaintId
    );
}

function complaint_closure_approval_send_mail(
    PDO $conn,
    int $complaintId,
    string $to,
    string $recipientName,
    string $subject,
    string $intro,
    string $remarks
): bool {
    $complaint = complaint_fetch_complaint_for_mail($conn, $complaintId);
    if ($complaint === null) {
        return false;
    }

    $lines = array_merge(
        [
            'Hello ' . ($recipientName !== '' && $recipientName !== '-' ? $recipientName : 'there') . ',',
            '',
            $intro,
            '',
        ],
        complaint_mail_complaint_lines($complaint, $complaintId)
    );
    if (trim($remarks) !== '') {
        $lines[] = 'Remarks: ' . trim($remarks);
    }
    $lines[] = '';
    $lines[] = 'Please log in to Complaint Management to review this complaint.';

    return complaint_mail_send($to, $subject, implode("\r\n", $lines));
}

function complaint_closure_approval_ensure_rbac(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    require_once __DIR__ . '/rbac_module_seed_helpers.php';

    $stmt = $conn->prepare('
        SELECT id
        FROM modules
        WHERE LOWER(TRIM(module_slug)) = :module_slug
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':module_slug', 'complaint-approvals');
    $stmt->execute();
    $existingId = (int) $stmt->fetchColumn();

    rbac_ensure_module_with_defaults(
        $conn,
        'Complaint Approvals',
        'complaint-approvals',
        'Review complaint closure approval requests',
        88
    );

    if ($existingId <= 0) {
        complaint_closure_approval_grant_default_view($conn);
    }

    $done = true;
}

function complaint_closure_approval_grant_default_view(PDO $conn): void
{
    require_once __DIR__ . '/role_helpers.php';

    $permissionStmt = $conn->prepare("
        SELECT p.id
        FROM permissions p
        INNER JOIN modules m ON m.id = p.module_id
        WHERE LOWER(TRIM(m.module_slug)) = 'complaint-approvals'
          AND LOWER(TRIM(p.permission_slug)) = 'view'
          AND p.deleted_at IS NULL
          AND m.deleted_at IS NULL
        LIMIT 1
    ");
    $permissionStmt->execute();
    $permissionId = (int) $permissionStmt->fetchColumn();
    if ($permissionId <= 0) {
        return;
    }

    $existsStmt = $conn->prepare('
        SELECT 1
        FROM role_permissions
        WHERE role_id = :role_id
          AND permission_id = :permission_id
        LIMIT 1
    ');
    $insertStmt = $conn->prepare('
        INSERT INTO role_permissions (role_id, permission_id, created_by, created_at)
        VALUES (:role_id, :permission_id, :created_by, CURRENT_TIMESTAMP)
    ');

    $roleIds = [
        ELGI_ENGINEER_USER_ROLE,
        MANAGEMENT_USER_ROLE,
        SYSTEM_ADMIN_ROLE,
        CCS_ADMIN_ROLE,
    ];
    foreach ($roleIds as $roleId) {
        if (role_get_by_id($conn, $roleId) === null) {
            continue;
        }

        $existsStmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $existsStmt->bindValue(':permission_id', $permissionId, PDO::PARAM_INT);
        $existsStmt->execute();
        if ($existsStmt->fetchColumn()) {
            continue;
        }

        $insertStmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $insertStmt->bindValue(':permission_id', $permissionId, PDO::PARAM_INT);
        $insertStmt->bindValue(':created_by', 'system');
        $insertStmt->execute();
    }
}

function complaint_closure_approval_can_access(PDO $conn): bool
{
    require_once __DIR__ . '/rbac_access_helpers.php';
    complaint_closure_approval_ensure_rbac($conn);

    return rbac_user_can($conn, 'complaint-approvals', 'view');
}

function complaint_closure_approval_require_page_access(PDO $conn): void
{
    require_once __DIR__ . '/rbac_access_helpers.php';
    if (!complaint_closure_approval_can_access($conn)) {
        rbac_access_denied_redirect();
    }
}

/**
 * @return array{where: string, params: array<string, int>}
 */
function complaint_closure_approval_list_scope(PDO $conn): array
{
    require_once __DIR__ . '/admin_access_helpers.php';
    if (!isset($_SESSION['role'])) {
        admin_refresh_session_role($conn);
    }

    if (is_system_admin() || is_management_user() || is_ccs_admin_user()) {
        return [
            'where' => 'c.deleted_at IS NULL',
            'params' => [],
        ];
    }

    $userId = (int) (current_user_id($conn) ?? 0);
    if (is_elgi_engineer_user() && $userId > 0) {
        return [
            'where' => 'c.deleted_at IS NULL AND a.approver_user_id = :approver_user_id',
            'params' => [':approver_user_id' => $userId],
        ];
    }

    return [
        'where' => 'c.deleted_at IS NULL AND c.added_by = :creator_user_id',
        'params' => [':creator_user_id' => $userId],
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function complaint_closure_approval_list(PDO $conn): array
{
    require_once __DIR__ . '/complaint_address_helpers.php';
    complaint_closure_approval_ensure_schema($conn);

    $scope = complaint_closure_approval_list_scope($conn);
    $sql = '
        SELECT
            c.id,
            c.fab_number,
            c.status,
            cm.customer_name,
            a.status AS approval_status,
            a.created_at,
            COALESCE(NULLIF(TRIM(approver.name), \'\'), NULLIF(TRIM(approver.username), \'\'), \'-\') AS approver_name
        FROM complaint_closure_approvals a
        INNER JOIN complaints c ON c.id = a.complaint_id
        ' . complaint_customer_join_sql('c', 'cm') . '
        LEFT JOIN user_master approver
            ON approver.id = a.approver_user_id
           AND approver.deleted_at IS NULL
        WHERE a.id = (
            SELECT MAX(latest.id)
            FROM complaint_closure_approvals latest
            WHERE latest.complaint_id = a.complaint_id
        )
          AND ' . $scope['where'] . '
        ORDER BY a.id DESC
    ';
    $stmt = $conn->prepare($sql);
    foreach ($scope['params'] as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
