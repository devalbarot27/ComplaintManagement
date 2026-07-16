<?php

require_once __DIR__ . '/current_username_helpers.php';

function rbac_status_options(): array
{
    return [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];
}

function rbac_status_label(string $status): string
{
    $options = rbac_status_options();

    return $options[strtolower(trim($status))] ?? 'Unknown';
}

function rbac_status_search_values(string $searchValue): array
{
    $searchValue = strtolower(trim($searchValue));
    if ($searchValue === '') {
        return [];
    }

    $matches = [];
    foreach (rbac_status_options() as $key => $label) {
        if (stripos($label, $searchValue) !== false || stripos($key, $searchValue) !== false) {
            $matches[] = $key;
        }
    }

    return $matches;
}

function rbac_status_badge(string $status): string
{
    $label = rbac_status_label($status);
    $class = strtolower(trim($status)) === 'active' ? 'success' : 'secondary';

    return '<span class="badge bg-' . $class . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

function rbac_format_datetime(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y h:i A', strtotime($value));
}

function rbac_display_value($value): string
{
    if ($value === null || trim((string) $value) === '') {
        return '-';
    }

    return trim((string) $value);
}

function rbac_validate_slug(string $slug): ?string
{
    if ($slug === '') {
        return 'Slug is required.';
    }

    if (!preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $slug)) {
        return 'Slug may only contain lowercase letters, numbers, hyphens, and underscores.';
    }

    if (strlen($slug) > 100) {
        return 'Slug cannot exceed 100 characters.';
    }

    return null;
}

function rbac_validate_status(string $status): ?string
{
    $status = strtolower(trim($status));
    if ($status === '' || !array_key_exists($status, rbac_status_options())) {
        return 'Status is required.';
    }

    return null;
}

function rbac_search_filter(string $searchValue, array $textColumns, ?callable $extraMatcher = null): array
{
    $parts = [];
    $params = [':search' => '%' . $searchValue . '%'];

    foreach ($textColumns as $column) {
        $parts[] = "{$column} ILIKE :search";
    }

    $statusValues = rbac_status_search_values($searchValue);
    if (!empty($statusValues)) {
        $statusPlaceholders = [];
        foreach ($statusValues as $index => $statusValue) {
            $paramKey = ':status_search_' . $index;
            $statusPlaceholders[] = $paramKey;
            $params[$paramKey] = $statusValue;
        }
        $parts[] = 'status IN (' . implode(', ', $statusPlaceholders) . ')';
    }

    if ($extraMatcher !== null) {
        $extra = $extraMatcher($searchValue);
        if (!empty($extra['sql'])) {
            $parts[] = $extra['sql'];
            $params = array_merge($params, $extra['params'] ?? []);
        }
    }

    return [
        'sql' => '(' . implode(' OR ', $parts) . ')',
        'params' => $params,
    ];
}

function rbac_form_panel_script(string $prefix): string
{
    return '';
}
