<?php

require_once __DIR__ . '/user_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';

function complaint_elgi_engineer_role_id(): int
{
    return 3;
}

function complaint_fetch_elgi_engineer_assignees(PDO $conn): array
{
    $stmt = $conn->prepare('
        SELECT id, username, name, email, mobile_number
        FROM user_master
        WHERE role = :role
          AND deleted_at IS NULL
        ORDER BY name ASC, username ASC
    ');
    $stmt->bindValue(':role', complaint_elgi_engineer_role_id(), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function complaint_assignee_option_value(array $user): string
{
    return trim((string) ($user['name'] ?? ''));
}

function complaint_assignee_option_label(array $user): string
{
    return complaint_assignee_option_value($user);
}

function complaint_is_valid_elgi_engineer_assignee(PDO $conn, string $assignTo): bool
{
    $assignTo = trim($assignTo);
    if ($assignTo === '') {
        return false;
    }

    foreach (complaint_fetch_elgi_engineer_assignees($conn) as $user) {
        if (complaint_assignee_option_value($user) === $assignTo) {
            return true;
        }
    }

    return false;
}

function complaint_validate_elgi_engineer_assignee(PDO $conn, string $assignTo): ?string
{
    if (!complaint_is_valid_elgi_engineer_assignee($conn, $assignTo)) {
        return 'Selected assignee must be an active ELGi Engineer.';
    }

    return null;
}

function complaint_render_assignee_options(array $assignees, ?string $selectedValue = null): string
{
    $html = '<option value=""></option>';

    foreach ($assignees as $assignee) {
        $value = complaint_assignee_option_value($assignee);
        if ($value === '') {
            continue;
        }

        $label = complaint_assignee_option_label($assignee);
        $selected = $selectedValue !== null && $selectedValue === $value ? ' selected' : '';

        $html .= '<option value="'
            . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"'
            . $selected . '>'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</option>';
    }

    return $html;
}

function complaint_fetch_assignee_by_name(PDO $conn, string $assignTo): ?array
{
    $assignTo = trim($assignTo);
    if ($assignTo === '') {
        return null;
    }

    foreach (complaint_fetch_elgi_engineer_assignees($conn) as $user) {
        if (complaint_assignee_option_value($user) === $assignTo) {
            return $user;
        }
    }

    return null;
}

function complaint_fetch_latest_assignment(PDO $conn, int $complaintId): ?array
{
    if ($complaintId <= 0) {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT assign_complaint, assign_complaint_datetime, remarks
        FROM complaint_assignments
        WHERE complaint_id = :complaint_id
        ORDER BY assign_complaint_datetime DESC, id DESC
        LIMIT 1
    ');
    $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function complaint_resolve_assignee_user_id(PDO $conn, string $assignTo): int
{
    $assignee = complaint_fetch_assignee_by_name($conn, $assignTo);

    return $assignee ? (int) ($assignee['id'] ?? 0) : 0;
}

function complaint_assigned_list_join_sql(): string
{
    return '
        INNER JOIN LATERAL (
            SELECT
                ca_inner.id,
                ca_inner.assign_complaint,
                ca_inner.assign_complaint_datetime,
                ca_inner.remarks,
                ca_inner.is_service_updated,
                ca_inner.assigned_to
            FROM complaint_assignments ca_inner
            WHERE ca_inner.complaint_id = c.id
            ORDER BY ca_inner.assign_complaint_datetime DESC, ca_inner.id DESC
            LIMIT 1
        ) ca ON TRUE
    ';
}

function complaint_assigned_list_where_sql(): string
{
    return 'c.deleted_at IS NULL
        AND ca.is_service_updated = 0
        AND c.status IN (:status_in_progress, :status_reopen)
        AND TRIM(ca.assign_complaint) = :assignee_name';
}

function complaint_assigned_list_params(): array
{
    return [
        ':status_in_progress' => 2,
        ':status_reopen' => 4,
        ':assignee_name' => current_assignee_name(),
    ];
}
