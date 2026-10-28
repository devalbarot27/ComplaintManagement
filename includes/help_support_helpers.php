<?php

require_once __DIR__ . '/rbac_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';

function help_support_category_options(): array
{
    return [
        'brochure' => 'Brochure',
        'user_manual' => 'User Manual',
        'faq' => 'FAQ',
        'video_tutorial' => 'Video Tutorial',
        'contact_support' => 'Contact Support',
    ];
}

function help_support_category_label(string $category): string
{
    $options = help_support_category_options();

    return $options[$category] ?? '-';
}

function help_support_can_view_all(): bool
{
    require_once __DIR__ . '/admin_access_helpers.php';

    return is_system_admin() || is_ccs_admin_user() || is_management_user();
}

/**
 * CCS Admin, Management, and System Admin see every inquiry.
 * Every other role sees only inquiries they added.
 *
 * @return array{sql: string, params: array<string, string>}
 */
function help_support_visibility_sql(string $alias = ''): array
{
    if (help_support_can_view_all()) {
        return ['sql' => '', 'params' => []];
    }

    $username = current_username();
    if ($username === '') {
        return ['sql' => ' AND 1 = 0', 'params' => []];
    }

    $column = ($alias !== '' ? $alias . '.' : '') . 'created_by';

    return [
        'sql' => ' AND ' . $column . ' = :owner_username',
        'params' => [':owner_username' => $username],
    ];
}

function help_support_bind_scope(PDOStatement $stmt, array $scope): void
{
    foreach ($scope['params'] as $key => $value) {
        $stmt->bindValue($key, $value);
    }
}

function help_support_added_by_label(array $row): string
{
    $name = trim((string) ($row['added_by_name'] ?? ''));

    return $name !== '' ? $name : '-';
}

function help_support_allowed_extensions(): array
{
    return ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'mp4', 'webm'];
}

function help_support_max_file_size(): int
{
    return 10 * 1024 * 1024;
}

function help_support_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $conn->exec("
        CREATE TABLE IF NOT EXISTS help_support (
            id SERIAL PRIMARY KEY,
            category VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            qty INTEGER NOT NULL,
            attachment_path VARCHAR(500) NULL,
            attachment_name VARCHAR(255) NULL,
            resource_url VARCHAR(500) NULL,
            created_by VARCHAR(150) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        )
    ");

    help_support_add_column($conn, 'approval_status', "VARCHAR(20) NOT NULL DEFAULT 'pending'");
    help_support_add_column($conn, 'l2_approver_id', 'INTEGER NULL');
    help_support_add_column($conn, 'approval_remarks', 'TEXT NULL');
    help_support_add_column($conn, 'approved_by', 'VARCHAR(150) NULL');
    help_support_add_column($conn, 'approved_at', 'TIMESTAMP NULL');

    $ensured = true;
}

function help_support_add_column(PDO $conn, string $column, string $definition): void
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'help_support'
          AND column_name = :column
        LIMIT 1
    ");
    $stmt->bindValue(':column', $column);
    $stmt->execute();
    if ($stmt->fetchColumn()) {
        return;
    }

    $conn->exec('ALTER TABLE help_support ADD COLUMN ' . $column . ' ' . $definition);
}

function help_support_status_label(string $status): string
{
    $labels = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    return $labels[$status] ?? 'Pending';
}

function help_support_from_post(array $post): array
{
    return [
        'category' => strtolower(trim((string) ($post['category'] ?? ''))),
        'title' => trim((string) ($post['title'] ?? '')),
        'description' => trim((string) ($post['description'] ?? '')),
        'qty' => trim((string) ($post['qty'] ?? '')),
        'resource_type' => strtolower(trim((string) ($post['resource_type'] ?? ''))),
        'resource_url' => trim((string) ($post['resource_url'] ?? '')),
        'remove_attachment' => !empty($post['remove_attachment']),
    ];
}

function help_support_valid_url(string $url): bool
{
    if ($url === '' || strlen($url) > 500) {
        return false;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

    return $scheme === 'http' || $scheme === 'https';
}

function help_support_upload_error(?array $file): ?string
{
    if ($file === null || !isset($file['error'])) {
        return null;
    }

    $error = (int) $file['error'];
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return 'Attachment must be 10 MB or smaller.';
    }
    if ($error !== UPLOAD_ERR_OK) {
        return 'Unable to upload the attachment.';
    }

    $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, help_support_allowed_extensions(), true)) {
        return 'Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, MP4, WEBM.';
    }
    if ((int) ($file['size'] ?? 0) > help_support_max_file_size()) {
        return 'Attachment must be 10 MB or smaller.';
    }
    if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        return 'Unable to upload the attachment.';
    }

    return null;
}

function help_support_has_upload(?array $file): bool
{
    return $file !== null
        && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
        && is_uploaded_file((string) ($file['tmp_name'] ?? ''));
}

function help_support_validate(array $data, bool $hasFile, bool $hasExistingFile): ?string
{
    if (!array_key_exists($data['category'], help_support_category_options())) {
        return 'Category is required.';
    }
    if ($data['title'] === '') {
        return 'Title is required.';
    }
    if (strlen($data['title']) > 255) {
        return 'Title cannot exceed 255 characters.';
    }
    if ($data['description'] === '') {
        return 'Description is required.';
    }
    if (strlen($data['description']) > 4000) {
        return 'Description cannot exceed 4000 characters.';
    }
    if ($data['qty'] === '' || !preg_match('/^\d+$/', $data['qty'])) {
        return 'Qty must be a whole number greater than or equal to 0.';
    }
    if ((int) $data['qty'] > 999999) {
        return 'Qty cannot exceed 999999.';
    }

    $resourceType = (string) ($data['resource_type'] ?? '');
    if ($resourceType === 'url') {
        if ($data['resource_url'] === '') {
            return 'URL is required.';
        }
        if (!help_support_valid_url($data['resource_url'])) {
            return 'Enter a valid URL starting with http:// or https://.';
        }

        return null;
    }

    if ($resourceType !== 'attachment') {
        return 'Select Attachment or URL.';
    }
    if (!$hasFile && !$hasExistingFile) {
        return 'Attachment is required.';
    }

    return null;
}

function help_support_store_upload(array $file): array
{
    $extension = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $directory = dirname(__DIR__) . '/uploads/help_support';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the upload directory.');
    }

    $storedName = 'help_support_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $target = $directory . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        throw new RuntimeException('Unable to save the attachment.');
    }

    $originalName = basename(str_replace(["\0", '\\', '/'], '', (string) $file['name']));
    if ($originalName === '' || $originalName === '.' || $originalName === '..') {
        $originalName = $storedName;
    }
    if (strlen($originalName) > 255) {
        $originalName = substr($originalName, -255);
    }

    return [
        'attachment_path' => 'uploads/help_support/' . $storedName,
        'attachment_name' => $originalName,
    ];
}

function help_support_delete_stored_file(?string $relativePath): void
{
    $relativePath = str_replace('\\', '/', trim((string) $relativePath));
    if ($relativePath === '' || strpos($relativePath, '..') !== false) {
        return;
    }
    if (strpos($relativePath, 'uploads/help_support/') !== 0) {
        return;
    }

    $fullPath = dirname(__DIR__) . '/' . $relativePath;
    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

function help_support_get_by_id(PDO $conn, int $id): ?array
{
    $scope = help_support_visibility_sql('h');
    $stmt = $conn->prepare('
        SELECT
            h.id,
            h.category,
            h.title,
            h.description,
            h.qty,
            h.attachment_path,
            h.attachment_name,
            h.resource_url,
            h.created_by,
            h.created_at,
            h.updated_at,
            h.approval_status,
            h.l2_approver_id,
            h.approval_remarks,
            h.approved_by,
            h.approved_at,
            um.name AS added_by_name
        FROM help_support h
        LEFT JOIN user_master um
            ON TRIM(um.username) = TRIM(h.created_by)
           AND um.deleted_at IS NULL
        WHERE h.id = :id
          AND h.deleted_at IS NULL
          ' . $scope['sql'] . '
        LIMIT 1
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    help_support_bind_scope($stmt, $scope);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function help_support_search_filter(string $searchValue): array
{
    $like = '%' . trim($searchValue) . '%';
    $parts = [
        'h.title ILIKE :search_title',
        'h.description ILIKE :search_description',
        'h.attachment_name ILIKE :search_file',
        'h.resource_url ILIKE :search_url',
        'CAST(h.id AS TEXT) ILIKE :search_id',
        'CAST(h.qty AS TEXT) ILIKE :search_qty',
    ];
    $params = [
        ':search_title' => $like,
        ':search_description' => $like,
        ':search_file' => $like,
        ':search_url' => $like,
        ':search_id' => $like,
        ':search_qty' => $like,
    ];

    $categoryMatches = [];
    $needle = strtolower(trim($searchValue));
    foreach (help_support_category_options() as $slug => $label) {
        if ($needle !== '' && (strpos(strtolower($label), $needle) !== false || strpos($slug, $needle) !== false)) {
            $categoryMatches[] = $slug;
        }
    }
    foreach ($categoryMatches as $index => $slug) {
        $paramKey = ':search_category_' . $index;
        $parts[] = 'h.category = ' . $paramKey;
        $params[$paramKey] = $slug;
    }

    $statusLabels = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];
    $statusIndex = 0;
    foreach ($statusLabels as $slug => $label) {
        if ($needle !== '' && (strpos(strtolower($label), $needle) !== false || strpos($slug, $needle) !== false)) {
            $paramKey = ':search_status_' . $statusIndex;
            $parts[] = 'h.approval_status = ' . $paramKey;
            $params[$paramKey] = $slug;
            $statusIndex++;
        }
    }

    if (help_support_can_view_all()) {
        $parts[] = 'h.created_by ILIKE :search_added_by';
        $parts[] = 'um.name ILIKE :search_added_by_name';
        $params[':search_added_by'] = $like;
        $params[':search_added_by_name'] = $like;
    }

    return [
        'sql' => '(' . implode(' OR ', $parts) . ')',
        'params' => $params,
    ];
}

function help_support_resource_html(array $row): string
{
    $parts = [];
    $path = trim((string) ($row['attachment_path'] ?? ''));
    $name = trim((string) ($row['attachment_name'] ?? ''));
    if ($path !== '' && $name !== '' && strpos($path, 'uploads/help_support/') === 0) {
        $parts[] = '<a href="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">'
            . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    $url = trim((string) ($row['resource_url'] ?? ''));
    if ($url !== '' && help_support_valid_url($url)) {
        $parts[] = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">'
            . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    return $parts === [] ? '-' : implode('<br>', $parts);
}

function help_support_entry_actions(int $id, array $permissions = []): string
{
    $permissions = help_support_normalize_action_permissions($permissions);
    $encodedId = base64_encode((string) $id);
    $html = '<div class="d-flex gap-1">';

    if ($permissions['view']) {
        $html .= '
            <a href="help_support_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>';
    }

    if ($permissions['edit']) {
        $html .= '
            <button type="button" class="btn btn-sm btn-outline-dark edit-help-support-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>';
    }

    if ($permissions['delete']) {
        $html .= '
            <a href="delete_help_support.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this Help &amp; Support item?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * @return array{view: bool, add: bool, edit: bool, delete: bool}
 */
function help_support_normalize_action_permissions(array $permissions): array
{
    return [
        'view' => !empty($permissions['view']),
        'add' => !empty($permissions['add']),
        'edit' => !empty($permissions['edit']),
        'delete' => !empty($permissions['delete']),
    ];
}

/**
 * @return array{view: bool, add: bool, edit: bool, delete: bool}
 */
function help_support_action_permissions(PDO $conn): array
{
    require_once __DIR__ . '/rbac_access_helpers.php';
    help_support_ensure_rbac($conn);

    return help_support_normalize_action_permissions([
        'view' => rbac_user_can($conn, 'help-support', 'view'),
        'add' => rbac_user_can($conn, 'help-support', 'add'),
        'edit' => rbac_user_can($conn, 'help-support', 'edit'),
        'delete' => rbac_user_can($conn, 'help-support', 'delete'),
    ]);
}

function help_support_ensure_rbac(PDO $conn): void
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
    $stmt->bindValue(':module_slug', 'help-support');
    $stmt->execute();
    $existingId = (int) $stmt->fetchColumn();

    rbac_ensure_module_with_defaults(
        $conn,
        'Help & Support',
        'help-support',
        'Manage brochures, manuals, FAQs, videos, and contact support',
        230
    );

    if ($existingId <= 0) {
        help_support_grant_default_permissions($conn);
    }

    $done = true;
}

function help_support_grant_default_permissions(PDO $conn): void
{
    require_once __DIR__ . '/role_helpers.php';
    require_once __DIR__ . '/admin_access_helpers.php';

    $permissionStmt = $conn->prepare("
        SELECT p.id
        FROM permissions p
        INNER JOIN modules m ON m.id = p.module_id
        WHERE LOWER(TRIM(m.module_slug)) = 'help-support'
          AND LOWER(TRIM(p.permission_slug)) = :permission_slug
          AND p.deleted_at IS NULL
          AND m.deleted_at IS NULL
        LIMIT 1
    ");
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

    if (role_get_by_id($conn, SYSTEM_ADMIN_ROLE) === null) {
        return;
    }

    foreach (['view', 'add', 'edit', 'delete'] as $permissionSlug) {
        $permissionStmt->bindValue(':permission_slug', $permissionSlug);
        $permissionStmt->execute();
        $permissionId = (int) $permissionStmt->fetchColumn();
        if ($permissionId <= 0) {
            continue;
        }

        $existsStmt->bindValue(':role_id', SYSTEM_ADMIN_ROLE, PDO::PARAM_INT);
        $existsStmt->bindValue(':permission_id', $permissionId, PDO::PARAM_INT);
        $existsStmt->execute();
        if ($existsStmt->fetchColumn()) {
            continue;
        }

        $insertStmt->bindValue(':role_id', SYSTEM_ADMIN_ROLE, PDO::PARAM_INT);
        $insertStmt->bindValue(':permission_id', $permissionId, PDO::PARAM_INT);
        $insertStmt->bindValue(':created_by', 'system');
        $insertStmt->execute();
    }
}

function help_support_require_page_access(PDO $conn): void
{
    require_once __DIR__ . '/admin_access_helpers.php';
    require_once __DIR__ . '/login_helpers.php';
    require_once __DIR__ . '/rbac_access_helpers.php';

    login_enforce_session_version($conn);
    admin_ensure_session_role($conn);
    help_support_ensure_rbac($conn);

    if (help_support_action_permissions($conn)['view']) {
        return;
    }

    rbac_access_denied_redirect();
}

function help_support_insert(PDO $conn, array $data, string $createdBy): int
{
    $approverId = help_support_l2_approver_id_for_username($conn, $createdBy);
    $stmt = $conn->prepare('
        INSERT INTO help_support (
            category, title, description, qty, attachment_path, attachment_name, resource_url,
            created_by, l2_approver_id, approval_status, created_at
        ) VALUES (
            :category, :title, :description, :qty, :attachment_path, :attachment_name, :resource_url,
            :created_by, :l2_approver_id, \'pending\', CURRENT_TIMESTAMP
        )
        RETURNING id
    ');
    help_support_bind_record($stmt, $data);
    $stmt->bindValue(':created_by', $createdBy);
    if ($approverId !== null) {
        $stmt->bindValue(':l2_approver_id', $approverId, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':l2_approver_id', null, PDO::PARAM_NULL);
    }
    $stmt->execute();
    $id = (int) $stmt->fetchColumn();

    if ($id > 0 && $approverId !== null) {
        try {
            help_support_notify_l2_approver($conn, $id, $approverId, (string) ($data['title'] ?? ''));
        } catch (Throwable $e) {
            error_log('help_support_insert notification: ' . $e->getMessage());
        }
    }

    return $id;
}

function help_support_update(PDO $conn, int $id, array $data): void
{
    $scope = help_support_visibility_sql();
    $stmt = $conn->prepare('
        UPDATE help_support SET
            category = :category,
            title = :title,
            description = :description,
            qty = :qty,
            attachment_path = :attachment_path,
            attachment_name = :attachment_name,
            resource_url = :resource_url,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
          ' . $scope['sql'] . '
    ');
    help_support_bind_record($stmt, $data);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    help_support_bind_scope($stmt, $scope);
    $stmt->execute();
}

function help_support_bind_record(PDOStatement $stmt, array $data): void
{
    $stmt->bindValue(':category', $data['category']);
    $stmt->bindValue(':title', $data['title']);
    $stmt->bindValue(':description', $data['description']);
    $stmt->bindValue(':qty', (int) $data['qty'], PDO::PARAM_INT);
    if (($data['attachment_path'] ?? '') === '') {
        $stmt->bindValue(':attachment_path', null, PDO::PARAM_NULL);
        $stmt->bindValue(':attachment_name', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':attachment_path', $data['attachment_path']);
        $stmt->bindValue(':attachment_name', $data['attachment_name']);
    }
    if (($data['resource_url'] ?? '') === '') {
        $stmt->bindValue(':resource_url', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':resource_url', $data['resource_url']);
    }
}

function help_support_soft_delete(PDO $conn, int $id): void
{
    $scope = help_support_visibility_sql();
    $stmt = $conn->prepare('
        UPDATE help_support
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
          ' . $scope['sql'] . '
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    help_support_bind_scope($stmt, $scope);
    $stmt->execute();
}

function help_support_creator_user(PDO $conn, string $username): ?array
{
    $username = trim($username);
    if ($username === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id, username, name, email
        FROM user_master
        WHERE LOWER(TRIM(username)) = LOWER(TRIM(:username))
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':username', $username);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function help_support_l2_approver_id_for_username(PDO $conn, string $username): ?int
{
    require_once __DIR__ . '/user_helpers.php';

    $creator = help_support_creator_user($conn, $username);
    if ($creator === null) {
        return null;
    }

    return user_assigned_approver_id($conn, (int) $creator['id'], 'level_2');
}

function help_support_notify_l2_approver(PDO $conn, int $inquiryId, int $approverId, string $title): void
{
    require_once __DIR__ . '/notification_helpers.php';

    $title = trim($title);
    notification_create(
        $conn,
        $approverId,
        'Help & Support Level 2 Approval',
        'Help & Support inquiry "' . ($title !== '' ? $title : ('#' . $inquiryId)) . '" is waiting for your Level 2 approval.',
        'help-support',
        $inquiryId
    );
}

function help_support_approval_can_access(PDO $conn): bool
{
    $userId = current_user_id($conn);
    if ($userId === null || $userId <= 0) {
        return false;
    }

    help_support_ensure_schema($conn);
    $stmt = $conn->prepare("
        SELECT 1
        FROM help_support
        WHERE deleted_at IS NULL
          AND approval_status = 'pending'
          AND l2_approver_id = :approver_id
        LIMIT 1
    ");
    $stmt->bindValue(':approver_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

/**
 * @return array<int, array<string, mixed>>
 */
function help_support_pending_approval_items(PDO $conn, ?int $approverId): array
{
    if ($approverId === null || $approverId <= 0) {
        return [];
    }

    help_support_ensure_schema($conn);
    $stmt = $conn->prepare("
        SELECT
            h.id,
            h.category,
            h.title,
            h.description,
            h.qty,
            h.attachment_path,
            h.attachment_name,
            h.resource_url,
            h.created_at,
            COALESCE(NULLIF(TRIM(um.name), ''), '-') AS added_by_name
        FROM help_support h
        LEFT JOIN user_master um
            ON LOWER(TRIM(um.username)) = LOWER(TRIM(h.created_by))
           AND um.deleted_at IS NULL
        WHERE h.deleted_at IS NULL
          AND h.approval_status = 'pending'
          AND h.l2_approver_id = :approver_id
        ORDER BY h.created_at DESC
    ");
    $stmt->bindValue(':approver_id', $approverId, PDO::PARAM_INT);
    $stmt->execute();

    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = [
            'id' => (int) $row['id'],
            'claim_type' => 'help_support',
            'complaint_id' => 0,
            'fab_number' => '',
            'customer_name' => '',
            'customer_id' => 0,
            'details' => (string) ($row['title'] ?? ''),
            'warranty_label' => '-',
            'foc_value' => (string) (int) ($row['qty'] ?? 0),
            'stage_label' => 'Pending',
            'overall_status' => 'Pending',
            'created_by' => (string) ($row['added_by_name'] ?? '-'),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'level' => 'level_2',
            'action_url' => 'approvals.php',
            'decision_field' => 'help_support_decision',
            'remarks_field' => 'approval_remarks',
            'action_type' => 'approve',
            'can_decide' => true,
            'category_label' => help_support_category_label((string) ($row['category'] ?? '')),
            'hs_title' => (string) ($row['title'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'qty' => (string) (int) ($row['qty'] ?? 0),
            'resource_html' => help_support_resource_html($row),
        ];
    }

    return $items;
}

/**
 * @return array{ok: bool, error: string, email_sent: bool, email_note: string}
 */
function help_support_apply_l2_decision(
    PDO $conn,
    int $id,
    string $decision,
    string $remarks,
    int $actorUserId,
    string $actorName
): array {
    $failed = [
        'ok' => false,
        'error' => 'This inquiry is not waiting for your approval.',
        'email_sent' => false,
        'email_note' => '',
    ];
    $normalized = strtolower(trim($decision));
    if ($normalized === 'approved') {
        $status = 'approved';
    } elseif ($normalized === 'rejected') {
        $status = 'rejected';
    } else {
        $failed['error'] = 'Choose Approve or Reject.';
        return $failed;
    }
    if ($id <= 0 || $actorUserId <= 0) {
        return $failed;
    }
    $remarks = trim($remarks);
    if ($status === 'rejected' && $remarks === '') {
        $failed['error'] = 'A comment is required to reject.';
        return $failed;
    }

    help_support_ensure_schema($conn);
    $stmt = $conn->prepare("
        SELECT id, title, category, created_by, l2_approver_id, approval_status
        FROM help_support
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (string) ($row['approval_status'] ?? '') !== 'pending') {
        return $failed;
    }
    if ((int) ($row['l2_approver_id'] ?? 0) !== $actorUserId) {
        return $failed;
    }

    $update = $conn->prepare("
        UPDATE help_support
        SET approval_status = :status,
            approval_remarks = :remarks,
            approved_by = :approved_by,
            approved_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
          AND approval_status = 'pending'
          AND l2_approver_id = :approver_id
    ");
    $update->bindValue(':status', $status);
    $update->bindValue(':remarks', $remarks !== '' ? $remarks : null, $remarks !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $actorName = trim($actorName);
    $update->bindValue(':approved_by', $actorName !== '' ? $actorName : null, $actorName !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $update->bindValue(':id', $id, PDO::PARAM_INT);
    $update->bindValue(':approver_id', $actorUserId, PDO::PARAM_INT);
    $update->execute();
    if ($update->rowCount() < 1) {
        return $failed;
    }

    help_support_notify_creator_decision($conn, $row, $status, $remarks);
    $email = help_support_send_decision_email($conn, $row, $status, $remarks, $actorUserId);

    return [
        'ok' => true,
        'error' => '',
        'email_sent' => $email['sent'],
        'email_note' => $email['note'],
    ];
}

function help_support_notify_creator_decision(PDO $conn, array $row, string $status, string $remarks): void
{
    require_once __DIR__ . '/notification_helpers.php';

    $creator = help_support_creator_user($conn, (string) ($row['created_by'] ?? ''));
    if ($creator === null) {
        return;
    }

    $titleText = trim((string) ($row['title'] ?? ''));
    $label = $titleText !== '' ? $titleText : ('#' . (int) ($row['id'] ?? 0));
    $approved = $status === 'approved';
    $message = 'Your Help & Support inquiry "' . $label . '" has been ' . ($approved ? 'approved' : 'rejected') . '.';
    $remarks = trim($remarks);
    if ($remarks !== '') {
        $message .= ' Remarks: ' . $remarks;
    }

    notification_create(
        $conn,
        (int) $creator['id'],
        $approved ? 'Help & Support Approved' : 'Help & Support Rejected',
        $message,
        'help-support',
        (int) ($row['id'] ?? 0)
    );
}

/**
 * @return array{sent: bool, note: string}
 */
function help_support_send_decision_email(
    PDO $conn,
    array $row,
    string $status,
    string $remarks,
    int $approverUserId
): array {
    require_once __DIR__ . '/outlook_email_helpers.php';

    $creator = help_support_creator_user($conn, (string) ($row['created_by'] ?? ''));
    if ($creator === null) {
        return ['sent' => false, 'note' => 'The inquiry creator does not have an email address.'];
    }
    $toEmail = trim((string) ($creator['email'] ?? ''));
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['sent' => false, 'note' => 'The inquiry creator does not have an email address.'];
    }

    $fromEmail = outlook_email_address_for_user($conn, $approverUserId);
    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return ['sent' => false, 'note' => 'No Outlook email address is configured for your user.'];
    }

    $approverStmt = $conn->prepare('
        SELECT name
        FROM user_master
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $approverStmt->bindValue(':id', $approverUserId, PDO::PARAM_INT);
    $approverStmt->execute();
    $fromName = trim((string) $approverStmt->fetchColumn());
    if ($fromName === '') {
        $fromName = 'Help & Support';
    }

    $approved = $status === 'approved';
    $inquiryTitle = trim((string) ($row['title'] ?? ''));
    $creatorName = trim((string) ($creator['name'] ?? ''));
    $lines = [
        'Hello ' . ($creatorName !== '' ? $creatorName : 'there') . ',',
        '',
        'Your Help & Support inquiry has been ' . ($approved ? 'approved' : 'rejected') . '.',
        '',
        'Title: ' . ($inquiryTitle !== '' ? $inquiryTitle : '-'),
        'Category: ' . help_support_category_label((string) ($row['category'] ?? '')),
    ];
    $remarks = trim($remarks);
    if ($remarks !== '') {
        $lines[] = 'Remarks: ' . $remarks;
    }
    $lines[] = '';
    $lines[] = 'This message was sent from the Outlook email address configured for the approver.';

    $fromEmail = str_replace(["\r", "\n"], '', $fromEmail);
    $fromName = str_replace(["\r", "\n"], '', $fromName);
    $headers = 'From: ' . $fromName . ' <' . $fromEmail . ">\r\n"
        . 'Reply-To: ' . $fromEmail . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8';
    $subject = $approved ? 'Help & Support inquiry approved' : 'Help & Support inquiry rejected';
    $sent = (bool) @mail($toEmail, $subject, implode("\r\n", $lines), $headers);

    return [
        'sent' => $sent,
        'note' => $sent ? '' : 'The decision email could not be sent.',
    ];
}
