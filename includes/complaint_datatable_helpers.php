<?php

require_once __DIR__ . '/complaint_status.php';

function dt_parse_request(array $allowedOrderColumns, string $defaultOrderColumn = 'id'): array
{
    $draw = (int) ($_REQUEST['draw'] ?? 1);
    $start = max(0, (int) ($_REQUEST['start'] ?? 0));
    $length = (int) ($_REQUEST['length'] ?? 10);

    if ($length < 1) {
        $length = 10;
    }
    if ($length > 100) {
        $length = 100;
    }

    $searchValue = trim($_REQUEST['search']['value'] ?? '');

    $orderColumnIndex = (int) ($_REQUEST['order'][0]['column'] ?? 0);
    $orderDir = (isset($_REQUEST['order'][0]['dir']) && strtolower($_REQUEST['order'][0]['dir']) === 'asc')
        ? 'ASC'
        : 'DESC';

    $orderColumn = $allowedOrderColumns[$orderColumnIndex] ?? $defaultOrderColumn;
    if (!in_array($orderColumn, $allowedOrderColumns, true)) {
        $orderColumn = $defaultOrderColumn;
    }

    return [
        'draw' => $draw,
        'start' => $start,
        'length' => $length,
        'searchValue' => $searchValue,
        'orderColumn' => $orderColumn,
        'orderDir' => $orderDir,
    ];
}

function dt_json_response(int $draw, int $recordsTotal, int $recordsFiltered, array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Build global search SQL including text columns and status label matching.
 */
function dt_complaint_search_filter(string $searchValue, array $textColumns, string $statusColumn): array
{
    $parts = [];
    $params = [':search' => '%' . $searchValue . '%'];

    foreach ($textColumns as $column) {
        $parts[] = "{$column} ILIKE :search";
    }

    $statusIds = dt_match_status_ids($searchValue);

    if (!empty($statusIds)) {
        $statusPlaceholders = [];

        foreach ($statusIds as $index => $statusId) {
            $paramKey = ':status_search_' . $index;
            $statusPlaceholders[] = $paramKey;
            $params[$paramKey] = $statusId;
        }

        $parts[] = $statusColumn . ' IN (' . implode(', ', $statusPlaceholders) . ')';
    }

    return [
        'sql' => '(' . implode(' OR ', $parts) . ')',
        'params' => $params,
    ];
}

function dt_normalize_closure_value(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    $normalized = trim((string) $value);

    if (strcasecmp($normalized, 'Yes') === 0) {
        return 'Yes';
    }

    if (strcasecmp($normalized, 'No') === 0) {
        return 'No';
    }

    return $normalized;
}

function dt_parse_closure_row_flags(array $row): array
{
    $hasServiceUpdate = !empty($row['has_service_update'])
        && ($row['has_service_update'] === true
            || $row['has_service_update'] === 't'
            || $row['has_service_update'] === '1');

    $hasReassignAfterClosureNo = !empty($row['has_reassign_after_closure_no'])
        && ($row['has_reassign_after_closure_no'] === true
            || $row['has_reassign_after_closure_no'] === 't'
            || $row['has_reassign_after_closure_no'] === '1');

    $latestClosure = dt_normalize_closure_value(isset($row['latest_closure']) ? (string) $row['latest_closure'] : null);

    $hasServiceAfterClosureNo = !empty($row['has_service_after_closure_no'])
        && ($row['has_service_after_closure_no'] === true
            || $row['has_service_after_closure_no'] === 't'
            || $row['has_service_after_closure_no'] === '1');

    $status = (int) ($row['status'] ?? 0);
    $isClosureNo = ($latestClosure === 'No');
    $isClosureYes = ($latestClosure === 'Yes');

    $needsReassign = $isClosureNo && !$hasReassignAfterClosureNo;

    $canClose = $status === COMPLAINT_STATUS_PENDING_HO
        && $hasServiceUpdate
        && !$isClosureYes
        && (
            $latestClosure === null
            || ($isClosureNo && $hasServiceAfterClosureNo)
        );

    return [
        'has_service_update' => $hasServiceUpdate,
        'latest_closure' => $latestClosure,
        'needs_reassign' => $needsReassign,
        'can_close' => $canClose,
    ];
}

function complaint_entry_actions(
    int $id,
    int $status,
    bool $needsReassign = false,
    bool $canClose = false
): string {
    $encodedId = base64_encode((string) $id);
    $html = '<div class="complaint-action-cell">';

    if ($status === COMPLAINT_STATUS_OPEN) {
        $html .= '<button type="button" class="btn-complaint-action manual-assign-btn" '
            . 'data-id="' . $id . '" title="Assign Complaint" data-bs-toggle="modal" data-bs-target="#assignModal">'
            . '<i class="bi bi-person-plus-fill"></i></button>';
    }

    if ($canClose) {
        $html .= '<button type="button" class="btn-complaint-action closure-btn" '
            . 'data-id="' . $id . '" title="Complaint Closure" data-bs-toggle="modal" data-bs-target="#closureModal">'
            . '<i class="bi bi-check2-square"></i></button>';
    }

    $html .= '<a href="complaint_details.php?id=' . $encodedId . '&from=entry" '
        . 'class="btn-complaint-action" title="View">'
        . '<i class="bi bi-eye-fill"></i></a>';

    if ($status !== COMPLAINT_STATUS_RESOLVED) {
        $html .= '<a href="delete_complaint.php?id=' . $encodedId . '" '
            . 'class="btn-complaint-action" title="Delete" '
            . 'onclick="return confirm(\'Delete this complaint?\')">'
            . '<i class="bi bi-trash-fill"></i></a>';
    }

    $html .= '</div>';

    return $html;
}

function complaint_assigned_actions(int $id, int $status, bool $hasServiceUpdate = false): string
{
    $encodedId = base64_encode((string) $id);
    $html = '<div class="complaint-action-cell">';

    $html .= '<a href="complaint_details.php?id=' . $encodedId . '&from=list" '
        . 'class="btn-complaint-action" title="View">'
        . '<i class="bi bi-eye-fill"></i></a>';

    if (in_array($status, [COMPLAINT_STATUS_IN_PROGRESS, COMPLAINT_STATUS_REOPEN], true) && !$hasServiceUpdate) {
        $html .= '<button type="button" class="btn-complaint-action service-update-btn" '
            . 'data-id="' . $id . '" title="Service Update" data-bs-toggle="modal" data-bs-target="#serviceUpdateModal">'
            . '<i class="bi bi-tools"></i></button>';
    }

    $html .= '</div>';

    return $html;
}
