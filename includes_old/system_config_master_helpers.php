<?php

require_once __DIR__ . '/rbac_helpers.php';

function scm_registry(): array
{
    return [
        'industry_segment' => [
            'table' => 'industry_segments',
            'label' => 'Industry Segment',
            'label_plural' => 'Industry Segments',
            'page' => 'industry_segments.php',
            'details_page' => 'industry_segment_details.php',
            'delete_page' => 'delete_industry_segment.php',
            'submit_key' => 'submit_industry_segment',
            'icon' => 'bi-building',
            'subtitle' => 'Manage industry segment options for installed base.',
            'name_placeholder' => 'e.g. Manufacturing',
        ],
        'warranty_chargeable' => [
            'table' => 'warranty_chargeable_types',
            'label' => 'Warranty / Chargeable',
            'label_plural' => 'Warranty / Chargeable Types',
            'page' => 'warranty_chargeable.php',
            'details_page' => 'warranty_chargeable_details.php',
            'delete_page' => 'delete_warranty_chargeable.php',
            'submit_key' => 'submit_warranty_chargeable',
            'icon' => 'bi-shield-check',
            'subtitle' => 'Manage warranty and chargeable type options.',
            'name_placeholder' => 'e.g. Warranty',
        ],
        'part_replaced' => [
            'table' => 'part_replaced_masters',
            'label' => 'Part Replaced',
            'label_plural' => 'Part Replaced Options',
            'page' => 'part_replaced.php',
            'details_page' => 'part_replaced_details.php',
            'delete_page' => 'delete_part_replaced.php',
            'submit_key' => 'submit_part_replaced',
            'icon' => 'bi-tools',
            'subtitle' => 'Manage part replaced options for service logs.',
            'name_placeholder' => 'e.g. Yes',
        ],
        'customer_feedback' => [
            'table' => 'customer_feedback_options',
            'label' => 'Customer Feedback',
            'label_plural' => 'Customer Feedback Options',
            'page' => 'customer_feedback.php',
            'details_page' => 'customer_feedback_details.php',
            'delete_page' => 'delete_customer_feedback.php',
            'submit_key' => 'submit_customer_feedback',
            'icon' => 'bi-chat-left-text',
            'subtitle' => 'Manage customer feedback rating options.',
            'name_placeholder' => 'e.g. Excellent',
        ],
        'reason' => [
            'table' => 'reason_masters',
            'label' => 'Reason',
            'label_plural' => 'Reason Masters',
            'page' => 'reasons.php',
            'details_page' => 'reason_details.php',
            'delete_page' => 'delete_reason.php',
            'submit_key' => 'submit_reason',
            'icon' => 'bi-list-check',
            'subtitle' => 'Manage reason options for spare parts consumption.',
            'name_placeholder' => 'e.g. PM',
        ],
    ];
}

function scm_config(string $type): array
{
    $registry = scm_registry();
    if (!isset($registry[$type])) {
        throw new InvalidArgumentException('Unknown system configuration master type.');
    }

    return $registry[$type];
}

function scm_safe_table(string $type): string
{
    // Return only string literals so SQL builders never interpolate untrusted input.
    switch ($type) {
        case 'industry_segment':
            return 'industry_segments';
        case 'warranty_chargeable':
            return 'warranty_chargeable_types';
        case 'part_replaced':
            return 'part_replaced_masters';
        case 'customer_feedback':
            return 'customer_feedback_options';
        case 'reason':
            return 'reason_masters';
        default:
            throw new InvalidArgumentException('Unknown system configuration master type.');
    }
}

function scm_from_post(array $post): array
{
    return [
        'name' => trim((string) ($post['name'] ?? '')),
        'status' => strtolower(trim((string) ($post['status'] ?? 'active'))),
    ];
}

function scm_validate(array $data, string $label): ?string
{
    if ($data['name'] === '') {
        return $label . ' name is required.';
    }

    if (strlen($data['name']) > 100) {
        return $label . ' name cannot exceed 100 characters.';
    }

    if ($error = rbac_validate_status($data['status'])) {
        return $error;
    }

    return null;
}

function scm_name_exists(PDO $conn, string $type, string $name, int $excludeId = 0): bool
{
    $table = scm_safe_table($type);

    $sql = "
        SELECT id
        FROM {$table}
        WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name))
          AND deleted_at IS NULL
    ";
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':name', $name);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function scm_search_filter(string $searchValue): array
{
    return rbac_search_filter($searchValue, ['name']);
}

function scm_get_by_id(PDO $conn, string $type, int $id): ?array
{
    $table = scm_safe_table($type);

    $stmt = $conn->prepare("
        SELECT *
        FROM {$table}
        WHERE id = :id
          AND deleted_at IS NULL
    ");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function scm_get_active_names(PDO $conn, string $type): array
{
    // Fully literal SQL per type (no table-name interpolation).
    switch ($type) {
        case 'industry_segment':
            $stmt = $conn->query("
                SELECT name
                FROM industry_segments
                WHERE deleted_at IS NULL
                  AND status = 'active'
                ORDER BY created_at ASC, id ASC
            ");
            break;
        case 'warranty_chargeable':
            $stmt = $conn->query("
                SELECT name
                FROM warranty_chargeable_types
                WHERE deleted_at IS NULL
                  AND status = 'active'
                ORDER BY created_at ASC, id ASC
            ");
            break;
        case 'part_replaced':
            $stmt = $conn->query("
                SELECT name
                FROM part_replaced_masters
                WHERE deleted_at IS NULL
                  AND status = 'active'
                ORDER BY created_at ASC, id ASC
            ");
            break;
        case 'customer_feedback':
            $stmt = $conn->query("
                SELECT name
                FROM customer_feedback_options
                WHERE deleted_at IS NULL
                  AND status = 'active'
                ORDER BY created_at ASC, id ASC
            ");
            break;
        case 'reason':
            $stmt = $conn->query("
                SELECT name
                FROM reason_masters
                WHERE deleted_at IS NULL
                  AND status = 'active'
                ORDER BY created_at ASC, id ASC
            ");
            break;
        default:
            throw new InvalidArgumentException('Unknown system configuration master type.');
    }

    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
}

function scm_option_exists(PDO $conn, string $type, string $name): bool
{
    $table = scm_safe_table($type);

    $stmt = $conn->prepare("
        SELECT id
        FROM {$table}
        WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name))
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->bindValue(':name', $name);
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function scm_entry_actions(string $type, int $id): string
{
    $config = scm_config($type);
    $encodedId = base64_encode((string) $id);
    $detailsPage = $config['details_page'];
    $deletePage = $config['delete_page'];
    $editClass = 'edit-scm-btn';

    return '
        <div class="d-flex gap-1">
            <a href="' . htmlspecialchars($detailsPage, ENT_QUOTES, 'UTF-8') . '?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark ' . $editClass . '"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="' . htmlspecialchars($deletePage, ENT_QUOTES, 'UTF-8') . '?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this record?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}

function scm_insert(PDO $conn, string $type, array $data, string $createdBy): void
{
    $table = scm_safe_table($type);

    $stmt = $conn->prepare("
        INSERT INTO {$table} (name, status, created_by, created_at)
        VALUES (:name, :status, :created_by, CURRENT_TIMESTAMP)
    ");
    $stmt->bindValue(':name', $data['name']);
    $stmt->bindValue(':status', $data['status']);
    $stmt->bindValue(':created_by', $createdBy);
    $stmt->execute();
}

function scm_update(PDO $conn, string $type, int $id, array $data): void
{
    $table = scm_safe_table($type);

    $stmt = $conn->prepare("
        UPDATE {$table} SET
            name = :name,
            status = :status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ");
    $stmt->bindValue(':name', $data['name']);
    $stmt->bindValue(':status', $data['status']);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function scm_soft_delete(PDO $conn, string $type, int $id): void
{
    $table = scm_safe_table($type);

    $stmt = $conn->prepare("
        UPDATE {$table}
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function scm_page_js_config(string $type): array
{
    $config = scm_config($type);

    return [
        'type' => $type,
        'label' => $config['label'],
        'labelPlural' => $config['label_plural'],
        'apiDatatable' => 'api/system_config_datatable.php',
        'apiGet' => 'api/system_config_get.php',
    ];
}

function scm_system_config_pages(): array
{
    $pages = [];
    foreach (scm_registry() as $config) {
        $pages[] = $config['page'];
        $pages[] = $config['details_page'];
    }

    return $pages;
}