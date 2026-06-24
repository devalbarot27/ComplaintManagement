<?php

/** Complaint status IDs */
const COMPLAINT_STATUS_OPEN = 1;
const COMPLAINT_STATUS_IN_PROGRESS = 2;
const COMPLAINT_STATUS_PENDING_HO = 3;
const COMPLAINT_STATUS_REOPEN = 4;
const COMPLAINT_STATUS_RESOLVED = 5;

function complaint_status_map(): array
{
    return [
        COMPLAINT_STATUS_OPEN => 'Open',
        COMPLAINT_STATUS_IN_PROGRESS => 'In Progress',
        COMPLAINT_STATUS_PENDING_HO => 'Pending With HO',
        COMPLAINT_STATUS_REOPEN => 'Re-Open',
        COMPLAINT_STATUS_RESOLVED => 'Resolved',
    ];
}

function complaint_status_label(int $status): string
{
    return complaint_status_map()[$status] ?? 'Unknown';
}

function complaint_status_badge_class(int $status): string
{
    $classes = [
        COMPLAINT_STATUS_OPEN => 'border border-dark',
        COMPLAINT_STATUS_IN_PROGRESS => 'border border-dark',
        COMPLAINT_STATUS_PENDING_HO => 'border border-dark',
        COMPLAINT_STATUS_REOPEN => 'border border-dark',
        COMPLAINT_STATUS_RESOLVED => 'border border-dark',
    ];

    return $classes[$status] ?? 'status-badge--default';
}

function complaint_status_badge(int $status): string
{
    $label = htmlspecialchars(complaint_status_label($status), ENT_QUOTES, 'UTF-8');
    $class = complaint_status_badge_class($status);

    return '<span class="status-badge ' . $class . '">' . $label . '</span>';
}

function complaint_status_counts(PDO $conn, bool $assignedOnly = false, string $username = ''): array
{
    $counts = [
        'open' => 0,
        'in_progress' => 0,
        'pending_ho' => 0,
        'reopen' => 0,
        'resolved' => 0,
    ];

    $username = trim($username);
    $usernameFilter = $username !== '' ? ' AND username = :username' : '';

    if ($assignedOnly) {
        require_once __DIR__ . '/complaint_assignment_helpers.php';

        $sql = "
            SELECT c.status, COUNT(DISTINCT c.id) AS total
            FROM complaints c
            " . complaint_assigned_list_join_sql() . "
            WHERE " . complaint_assigned_list_where_sql() . "
            GROUP BY c.status
        ";
    } else {
        $sql = "
            SELECT status, COUNT(*) AS total
            FROM complaints
            WHERE deleted_at IS NULL{$usernameFilter}
            GROUP BY status
        ";
    }

    $stmt = $conn->prepare($sql);
    if ($assignedOnly) {
        foreach (complaint_assigned_list_params() as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    } elseif ($username !== '') {
        $stmt->bindValue(':username', $username);
    }
    $stmt->execute();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        switch ((int) $row['status']) {
            case COMPLAINT_STATUS_OPEN:
                $counts['open'] = (int) $row['total'];
                break;
            case COMPLAINT_STATUS_IN_PROGRESS:
                $counts['in_progress'] = (int) $row['total'];
                break;
            case COMPLAINT_STATUS_PENDING_HO:
                $counts['pending_ho'] = (int) $row['total'];
                break;
            case COMPLAINT_STATUS_REOPEN:
                $counts['reopen'] = (int) $row['total'];
                break;
            case COMPLAINT_STATUS_RESOLVED:
                $counts['resolved'] = (int) $row['total'];
                break;
        }
    }

    return $counts;
}

function dt_match_status_ids(string $searchValue): array
{
    $search = strtolower(trim($searchValue));

    if ($search === '') {
        return [];
    }

    $matched = [];

    foreach (complaint_status_map() as $id => $label) {
        $labelLower = strtolower($label);

        if (
            $search === $labelLower
            || str_contains($labelLower, $search)
            || str_contains($search, $labelLower)
        ) {
            $matched[] = (int) $id;
        }
    }

    if (preg_match('/\bopen\b/', $search) && !preg_match('/\bre[\s-]?open\b/', $search)) {
        $matched[] = COMPLAINT_STATUS_OPEN;
    }

    if (preg_match('/\b(in[\s-]?progress|progress)\b/', $search)) {
        $matched[] = COMPLAINT_STATUS_IN_PROGRESS;
    }

    if (preg_match('/\b(pending[\s-]?with[\s-]?ho|pending|ho)\b/', $search)) {
        $matched[] = COMPLAINT_STATUS_PENDING_HO;
    }

    if (preg_match('/\b(re[\s-]?open|reopen)\b/', $search)) {
        $matched[] = COMPLAINT_STATUS_REOPEN;
    }

    if (preg_match('/\b(resolved|closed)\b/', $search)) {
        $matched[] = COMPLAINT_STATUS_RESOLVED;
    }

    return array_values(array_unique($matched));
}
