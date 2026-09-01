<?php

/**
 * Dealer Portal Documentation (CDOC publish copy).
 * MVP 2: no CDOC API. Dealers view/download public approved documents only.
 */

require_once __DIR__ . '/current_username_helpers.php';
require_once __DIR__ . '/rbac_access_helpers.php';

const CDOC_MODULE_SLUG = 'documentation';

const CDOC_VISIBILITY_PUBLIC = 'public';
const CDOC_VISIBILITY_INTERNAL = 'internal';

const CDOC_APPROVAL_APPROVED = 'approved';
const CDOC_APPROVAL_DRAFT = 'draft';

const CDOC_STATUS_ACTIVE = 'active';
const CDOC_STATUS_INACTIVE = 'inactive';

const CDOC_MAX_FILE_SIZE = 10485760;

function cdoc_visibility_options(): array
{
    return [
        CDOC_VISIBILITY_PUBLIC => 'Public',
        CDOC_VISIBILITY_INTERNAL => 'Internal',
    ];
}

function cdoc_approval_options(): array
{
    return [
        CDOC_APPROVAL_APPROVED => 'Approved',
        CDOC_APPROVAL_DRAFT => 'Draft',
    ];
}

function cdoc_status_options(): array
{
    return [
        CDOC_STATUS_ACTIVE => 'Active',
        CDOC_STATUS_INACTIVE => 'Inactive',
    ];
}

function cdoc_document_type_options(): array
{
    return [
        'Product Manual' => 'Product Manual',
        'Service Manual' => 'Service Manual',
        'Installation Guide' => 'Installation Guide',
        'Datasheet' => 'Datasheet',
        'Catalogue' => 'Catalogue',
        'Brochure' => 'Brochure',
        'Spare Parts List' => 'Spare Parts List',
        'Warranty Policy' => 'Warranty Policy',
        'Other' => 'Other',
    ];
}

function cdoc_product_groups(PDO $conn): array
{
    try {
        $stmt = $conn->query("
            SELECT DISTINCT TRIM(product_group) AS product_group
            FROM product_master_vayu
            WHERE TRIM(COALESCE(product_group, '')) <> ''
            ORDER BY 1
        ");
    } catch (PDOException $e) {
        return [];
    }

    $groups = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $group = trim((string) ($row['product_group'] ?? ''));
        if ($group !== '') {
            $groups[$group] = $group;
        }
    }

    return $groups;
}

function cdoc_product_by_code(PDO $conn, string $tplcode): ?array
{
    $tplcode = trim($tplcode);
    if ($tplcode === '') {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT TRIM(tplcode) AS tplcode,
               TRIM(COALESCE(tpldesc, '')) AS tpldesc,
               TRIM(COALESCE(product_group, '')) AS product_group
        FROM product_master_vayu
        WHERE TRIM(tplcode) = :tplcode
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->bindValue(':tplcode', $tplcode);
    try {
        $stmt->execute();
    } catch (PDOException $e) {
        $stmt = $conn->prepare("
            SELECT TRIM(tplcode) AS tplcode,
                   TRIM(COALESCE(tpldesc, '')) AS tpldesc,
                   TRIM(COALESCE(product_group, '')) AS product_group
            FROM product_master_vayu
            WHERE TRIM(tplcode) = :tplcode
            LIMIT 1
        ");
        $stmt->bindValue(':tplcode', $tplcode);
        $stmt->execute();
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return [
        'tplcode' => trim((string) ($row['tplcode'] ?? '')),
        'tpldesc' => trim((string) ($row['tpldesc'] ?? '')),
        'product_group' => trim((string) ($row['product_group'] ?? '')),
    ];
}

function cdoc_product_code_from_stored(string $stored): string
{
    $stored = trim($stored);
    if ($stored === '') {
        return '';
    }

    $parts = explode(' - ', $stored, 2);

    return trim($parts[0]);
}

function cdoc_product_display_label(array $product): string
{
    $code = trim((string) ($product['tplcode'] ?? ''));
    $desc = trim((string) ($product['tpldesc'] ?? ''));
    if ($code === '') {
        return $desc;
    }

    return $desc !== '' ? $code . ' - ' . $desc : $code;
}

function cdoc_apply_product_master(PDO $conn, array $data): array
{
    $code = cdoc_product_code_from_stored((string) ($data['product_name'] ?? ''));
    if ($code === '') {
        return $data;
    }

    $product = cdoc_product_by_code($conn, $code);
    if ($product === null) {
        return $data;
    }

    $label = cdoc_product_display_label($product);
    if (strlen($label) > 200) {
        $label = $product['tplcode'];
    }
    $data['product_name'] = $label;
    if (trim((string) ($data['product_group'] ?? '')) === '' && $product['product_group'] !== '') {
        $data['product_group'] = $product['product_group'];
    }

    return $data;
}

function cdoc_search_products(PDO $conn, string $term, string $productGroup = '', int $limit = 25): array
{
    $term = trim($term);
    $productGroup = trim($productGroup);
    $limit = max(1, min(50, $limit));

    $sql = '
        SELECT TRIM(tplcode) AS tplcode,
               TRIM(COALESCE(tpldesc, \'\')) AS tpldesc,
               TRIM(COALESCE(product_group, \'\')) AS product_group
        FROM product_master_vayu
        WHERE TRIM(COALESCE(tplcode, \'\')) <> \'\'
    ';
    if ($term !== '') {
        $sql .= '
          AND (
                tplcode ILIKE :term
             OR tpldesc ILIKE :term
          )
        ';
    }
    if ($productGroup !== '') {
        $sql .= ' AND TRIM(product_group) = :product_group';
    }
    $sql .= '
        ORDER BY tplcode
        LIMIT :limit
    ';

    $stmt = $conn->prepare($sql);
    if ($term !== '') {
        $stmt->bindValue(':term', '%' . $term . '%');
    }
    if ($productGroup !== '') {
        $stmt->bindValue(':product_group', $productGroup);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $seen = [];
    $results = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $code = trim((string) ($row['tplcode'] ?? ''));
        if ($code === '' || isset($seen[strtoupper($code)])) {
            continue;
        }
        $seen[strtoupper($code)] = true;
        $results[] = [
            'tplcode' => $code,
            'tpldesc' => trim((string) ($row['tpldesc'] ?? '')),
            'product_group' => trim((string) ($row['product_group'] ?? '')),
        ];
    }

    return $results;
}

function cdoc_product_to_select2_result(array $row): array
{
    $label = cdoc_product_display_label($row);

    return [
        'id' => (string) ($row['tplcode'] ?? ''),
        'text' => $label,
        'tplcode' => (string) ($row['tplcode'] ?? ''),
        'tpldesc' => (string) ($row['tpldesc'] ?? ''),
        'product_group' => (string) ($row['product_group'] ?? ''),
    ];
}

function cdoc_allowed_extensions(): array
{
    return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
}

function cdoc_allowed_mime_types(): array
{
    return [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'application/octet-stream',
    ];
}

function cdoc_upload_dir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'cdoc';
}

function cdoc_ensure_upload_dir(): string
{
    $dir = cdoc_upload_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create document upload directory.');
    }

    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }

    return $dir;
}

function cdoc_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'cdoc_documents'
        LIMIT 1
    ");
    $stmt->execute();

    if (!$stmt->fetchColumn()) {
        $conn->exec("
            CREATE TABLE cdoc_documents (
                id SERIAL PRIMARY KEY,
                document_number VARCHAR(80) NULL,
                title VARCHAR(200) NOT NULL,
                product_group VARCHAR(100) NULL,
                product_name VARCHAR(200) NULL,
                document_type VARCHAR(50) NULL,
                version_number VARCHAR(30) NULL,
                description TEXT NULL,
                visibility VARCHAR(20) NOT NULL DEFAULT 'public',
                approval_status VARCHAR(20) NOT NULL DEFAULT 'approved',
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                original_filename VARCHAR(255) NULL,
                stored_filename VARCHAR(255) NULL,
                file_mime VARCHAR(120) NULL,
                file_size INTEGER NULL,
                file_extension VARCHAR(20) NULL,
                published_at TIMESTAMP NULL,
                created_by INTEGER NULL,
                created_by_name VARCHAR(150) NULL,
                updated_by VARCHAR(150) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL
            )
        ");
        $conn->exec('CREATE INDEX IF NOT EXISTS idx_cdoc_documents_visibility ON cdoc_documents (visibility)');
        $conn->exec('CREATE INDEX IF NOT EXISTS idx_cdoc_documents_approval ON cdoc_documents (approval_status)');
        $conn->exec('CREATE INDEX IF NOT EXISTS idx_cdoc_documents_deleted ON cdoc_documents (deleted_at)');
    }

    try {
        $conn->exec("UPDATE cdoc_documents SET document_number = NULL WHERE TRIM(COALESCE(document_number, '')) = ''");
        $conn->exec('CREATE UNIQUE INDEX IF NOT EXISTS cdoc_documents_document_number_key ON cdoc_documents (document_number)');
    } catch (PDOException $e) {
        // Leave numbering usable even if older rows prevent a unique index.
    }

    cdoc_ensure_module($conn);
    $ensured = true;
}

function cdoc_ensure_module(PDO $conn): void
{
    static $moduleEnsured = false;
    if ($moduleEnsured) {
        return;
    }

    $tableStmt = $conn->prepare("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'modules'
        LIMIT 1
    ");
    $tableStmt->execute();
    if (!$tableStmt->fetchColumn()) {
        $moduleEnsured = true;
        return;
    }

    require_once __DIR__ . '/module_helpers.php';
    require_once __DIR__ . '/permission_helpers.php';

    $stmt = $conn->prepare("
        SELECT id
        FROM modules
        WHERE LOWER(TRIM(module_slug)) = :slug
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->bindValue(':slug', CDOC_MODULE_SLUG);
    $stmt->execute();
    $moduleId = (int) $stmt->fetchColumn();
    $isNewModule = $moduleId <= 0;

    if ($isNewModule) {
        $moduleId = module_insert($conn, [
            'module_name' => 'Documentation',
            'module_slug' => CDOC_MODULE_SLUG,
            'description' => 'Dealer access to publicly approved product documents published from CDOC.',
            'ordering' => 80,
            'status' => 'active',
            'create_default_permissions' => false,
        ], 'system');
    }

    if ($moduleId <= 0) {
        $moduleEnsured = true;
        return;
    }

    $permissions = [
        ['permission_name' => 'View', 'permission_slug' => 'view', 'description' => 'View and download published documents'],
        ['permission_name' => 'Add', 'permission_slug' => 'add', 'description' => 'Publish approved CDOC documents to the dealer portal'],
        ['permission_name' => 'Edit', 'permission_slug' => 'edit', 'description' => 'Update published document details'],
        ['permission_name' => 'Delete', 'permission_slug' => 'delete', 'description' => 'Remove published documents from the dealer portal'],
    ];

    $permissionIds = [];
    foreach ($permissions as $permission) {
        if (!permission_slug_exists($conn, $moduleId, $permission['permission_slug'])) {
            permission_insert($conn, [
                'module_id' => $moduleId,
                'permission_name' => $permission['permission_name'],
                'permission_slug' => $permission['permission_slug'],
                'description' => $permission['description'],
                'status' => 'active',
            ], 'system');
        }

        $permStmt = $conn->prepare("
            SELECT id
            FROM permissions
            WHERE module_id = :module_id
              AND LOWER(TRIM(permission_slug)) = :slug
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $permStmt->bindValue(':module_id', $moduleId, PDO::PARAM_INT);
        $permStmt->bindValue(':slug', $permission['permission_slug']);
        $permStmt->execute();
        $permissionId = (int) $permStmt->fetchColumn();
        if ($permissionId > 0) {
            $permissionIds[$permission['permission_slug']] = $permissionId;
        }
    }

    if ($isNewModule && $permissionIds !== []) {
        cdoc_grant_default_role_permissions($conn, $permissionIds);
    }

    $moduleEnsured = true;
}

function cdoc_grant_default_role_permissions(PDO $conn, array $permissionIds): void
{
    $rolesStmt = $conn->query("
        SELECT id, role_name
        FROM roles
        WHERE deleted_at IS NULL
          AND status = 'active'
    ");
    $roles = $rolesStmt ? $rolesStmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $publisherRoleIds = [SYSTEM_ADMIN_ROLE, CCS_ADMIN_ROLE];
    $viewerRoleIds = [DEALER_USER_ROLE, DEALER_ENGINEER_USER_ROLE, ELGI_ENGINEER_USER_ROLE, SALES_COORDINATOR_USER_ROLE, MANAGEMENT_USER_ROLE];

    foreach ($roles as $role) {
        $roleName = strtolower(trim((string) ($role['role_name'] ?? '')));
        $roleId = (int) $role['id'];
        if ($roleId <= 0) {
            continue;
        }

        $slugs = [];
        if (in_array($roleId, $viewerRoleIds, true) || strpos($roleName, 'dealer') !== false) {
            $slugs = ['view'];
        }
        if (
            in_array($roleId, $publisherRoleIds, true)
            || strpos($roleName, 'system admin') !== false
            || strpos($roleName, 'ccs admin') !== false
        ) {
            $slugs = ['view', 'add', 'edit', 'delete'];
        }

        foreach ($slugs as $slug) {
            if (!isset($permissionIds[$slug])) {
                continue;
            }
            cdoc_ensure_role_permission($conn, $roleId, (int) $permissionIds[$slug]);
        }
    }
}

function cdoc_ensure_role_permission(PDO $conn, int $roleId, int $permissionId): void
{
    $stmt = $conn->prepare('
        SELECT id
        FROM role_permissions
        WHERE role_id = :role_id
          AND permission_id = :permission_id
        LIMIT 1
    ');
    $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
    $stmt->bindValue(':permission_id', $permissionId, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->fetchColumn()) {
        return;
    }

    $insert = $conn->prepare('
        INSERT INTO role_permissions (role_id, permission_id, created_by, created_at)
        VALUES (:role_id, :permission_id, :created_by, CURRENT_TIMESTAMP)
    ');
    $insert->bindValue(':role_id', $roleId, PDO::PARAM_INT);
    $insert->bindValue(':permission_id', $permissionId, PDO::PARAM_INT);
    $insert->bindValue(':created_by', 'system');
    $insert->execute();
}

function cdoc_action_permissions(PDO $conn): array
{
    return [
        'view' => rbac_user_can($conn, CDOC_MODULE_SLUG, 'view'),
        'add' => rbac_user_can($conn, CDOC_MODULE_SLUG, 'add'),
        'edit' => rbac_user_can($conn, CDOC_MODULE_SLUG, 'edit'),
        'delete' => rbac_user_can($conn, CDOC_MODULE_SLUG, 'delete'),
    ];
}

function cdoc_user_can_manage(array $permissions): bool
{
    return !empty($permissions['add']) || !empty($permissions['edit']) || !empty($permissions['delete']);
}

function cdoc_is_dealer_visible(array $row): bool
{
    return strtolower((string) ($row['visibility'] ?? '')) === CDOC_VISIBILITY_PUBLIC
        && strtolower((string) ($row['approval_status'] ?? '')) === CDOC_APPROVAL_APPROVED
        && strtolower((string) ($row['status'] ?? '')) === CDOC_STATUS_ACTIVE
        && empty($row['deleted_at']);
}

function cdoc_user_can_access_document(array $row, array $permissions): bool
{
    if (empty($permissions['view'])) {
        return false;
    }

    if (cdoc_user_can_manage($permissions)) {
        return empty($row['deleted_at']);
    }

    return cdoc_is_dealer_visible($row);
}

function cdoc_encoded_id(int $id): string
{
    return rawurlencode(base64_encode((string) $id));
}

function cdoc_decoded_id(?string $encoded): int
{
    $decoded = base64_decode((string) $encoded, true);
    if ($decoded === false) {
        return 0;
    }

    return (int) $decoded;
}

function cdoc_next_document_number(PDO $conn): string
{
    $year = date('Y');
    $stmt = $conn->prepare("
        SELECT COALESCE(MAX(SPLIT_PART(document_number, '-', 3)::int), 0)
        FROM cdoc_documents
        WHERE document_number LIKE :prefix
    ");
    $prefix = 'CDOC-' . $year . '-%';
    $stmt->bindValue(':prefix', $prefix);
    $stmt->execute();
    $seq = ((int) $stmt->fetchColumn()) + 1;

    return sprintf('CDOC-%s-%04d', $year, $seq);
}

function cdoc_from_post(array $post, ?array $existing = null): array
{
    $existing = $existing ?? [];

    return [
        'title' => trim((string) ($post['title'] ?? '')),
        'product_group' => trim((string) ($post['product_group'] ?? '')),
        'product_name' => trim((string) ($post['product_name'] ?? '')),
        'document_type' => trim((string) ($post['document_type'] ?? '')),
        'version_number' => trim((string) ($post['version_number'] ?? ($existing['version_number'] ?? ''))),
        'description' => trim((string) ($post['description'] ?? '')),
        'visibility' => trim((string) ($existing['visibility'] ?? CDOC_VISIBILITY_PUBLIC)),
        'approval_status' => trim((string) ($existing['approval_status'] ?? CDOC_APPROVAL_APPROVED)),
        'status' => trim((string) ($existing['status'] ?? CDOC_STATUS_ACTIVE)),
    ];
}

function cdoc_validate(array $data, bool $fileRequired, ?array $fileField, ?PDO $conn = null): ?string
{
    if ($data['title'] === '') {
        return 'Document Title is required.';
    }
    if (strlen($data['title']) > 200) {
        return 'Document Title cannot exceed 200 characters.';
    }
    if ($data['product_name'] === '') {
        return 'Product Name is required.';
    }
    if (strlen($data['product_name']) > 200) {
        return 'Product Name cannot exceed 200 characters.';
    }
    if ($conn !== null) {
        $productCode = cdoc_product_code_from_stored($data['product_name']);
        if (cdoc_product_by_code($conn, $productCode) === null) {
            return 'Please select a valid product.';
        }
        if ($data['product_group'] !== '') {
            $groups = cdoc_product_groups($conn);
            if (!isset($groups[$data['product_group']])) {
                return 'Please select a valid Product Group.';
            }
        }
    }
    if ($data['document_type'] !== '' && !array_key_exists($data['document_type'], cdoc_document_type_options())) {
        return 'Please select a valid Document Type.';
    }
    if ($data['visibility'] === '' || !array_key_exists($data['visibility'], cdoc_visibility_options())) {
        return 'Please select a valid Visibility.';
    }
    if ($data['approval_status'] === '' || !array_key_exists($data['approval_status'], cdoc_approval_options())) {
        return 'Please select a valid Approval Status.';
    }
    if ($data['status'] === '' || !array_key_exists($data['status'], cdoc_status_options())) {
        return 'Please select a valid Status.';
    }
    if (strlen($data['product_name']) > 200) {
        return 'Product Name cannot exceed 200 characters.';
    }
    if (strlen($data['version_number']) > 30) {
        return 'Version cannot exceed 30 characters.';
    }

    $hasFile = $fileField !== null
        && isset($fileField['error'])
        && (int) $fileField['error'] !== UPLOAD_ERR_NO_FILE;

    if ($fileRequired && !$hasFile) {
        return 'Please attach the approved document file.';
    }

    if ($hasFile) {
        return cdoc_validate_upload($fileField);
    }

    return null;
}

function cdoc_validate_upload(array $file): ?string
{
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return 'Document upload failed. Please try again.';
    }

    $name = (string) ($file['name'] ?? '');
    $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($extension, cdoc_allowed_extensions(), true)) {
        return 'Invalid file type. Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG.';
    }

    if ((int) ($file['size'] ?? 0) > CDOC_MAX_FILE_SIZE) {
        return 'File must be 10 MB or smaller.';
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return 'Invalid uploaded file.';
    }

    $mime = cdoc_detect_mime($tmpName, $extension);
    if (!in_array($mime, cdoc_allowed_mime_types(), true)) {
        return 'File type is not allowed.';
    }

    return null;
}

function cdoc_detect_mime(string $path, string $extension): string
{
    $mime = '';
    if (function_exists('mime_content_type')) {
        $detected = @mime_content_type($path);
        if (is_string($detected) && $detected !== '') {
            $mime = strtolower($detected);
        }
    }

    if ($mime === '' || $mime === 'application/octet-stream') {
        $map = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        ];
        $mime = $map[$extension] ?? 'application/octet-stream';
    }

    return $mime;
}

function cdoc_store_upload(array $file): array
{
    $dir = cdoc_ensure_upload_dir();
    $extension = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $storedName = 'cdoc_' . str_replace('.', '', uniqid('', true)) . '.' . $extension;
    $targetPath = $dir . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Unable to save the document file.');
    }

    return [
        'original_filename' => basename((string) $file['name']),
        'stored_filename' => $storedName,
        'file_mime' => cdoc_detect_mime($targetPath, $extension),
        'file_size' => (int) filesize($targetPath),
        'file_extension' => $extension,
    ];
}

function cdoc_delete_stored_file(?string $storedFilename): void
{
    $name = basename((string) $storedFilename);
    if ($name === '' || $name === '.' || $name === '..') {
        return;
    }

    $path = cdoc_upload_dir() . DIRECTORY_SEPARATOR . $name;
    if (is_file($path)) {
        unlink($path);
    }
}

function cdoc_file_path(array $row): ?string
{
    $name = basename((string) ($row['stored_filename'] ?? ''));
    if ($name === '') {
        return null;
    }

    $path = cdoc_upload_dir() . DIRECTORY_SEPARATOR . $name;
    return is_file($path) ? $path : null;
}

function cdoc_format_file_size($bytes): string
{
    $size = (int) $bytes;
    if ($size <= 0) {
        return '-';
    }
    if ($size < 1024) {
        return $size . ' B';
    }
    if ($size < 1048576) {
        return number_format($size / 1024, 1) . ' KB';
    }

    return number_format($size / 1048576, 1) . ' MB';
}

function cdoc_format_date(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $ts = strtotime($value);
    return $ts ? date('d M Y', $ts) : '-';
}

function cdoc_visibility_badge(string $visibility): string
{
    $label = cdoc_visibility_options()[$visibility] ?? $visibility;
    $class = $visibility === CDOC_VISIBILITY_PUBLIC ? 'bg-success' : 'bg-secondary';

    return '<span class="badge ' . $class . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

function cdoc_approval_badge(string $status): string
{
    $label = cdoc_approval_options()[$status] ?? $status;
    $class = $status === CDOC_APPROVAL_APPROVED ? 'bg-primary' : 'bg-warning text-dark';

    return '<span class="badge ' . $class . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

function cdoc_status_badge(string $status): string
{
    $label = cdoc_status_options()[$status] ?? $status;
    $class = $status === CDOC_STATUS_ACTIVE ? 'bg-success' : 'bg-secondary';

    return '<span class="badge ' . $class . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

function cdoc_insert_record(PDO $conn, array $data, array $fileMeta, int $createdBy, string $createdByName): int
{
    $stmt = $conn->prepare("
        INSERT INTO cdoc_documents (
            document_number, title, product_group, product_name, document_type,
            version_number, description, visibility, approval_status, status,
            original_filename, stored_filename, file_mime, file_size, file_extension,
            published_at, created_by, created_by_name
        ) VALUES (
            :document_number, :title, :product_group, :product_name, :document_type,
            :version_number, :description, :visibility, :approval_status, :status,
            :original_filename, :stored_filename, :file_mime, :file_size, :file_extension,
            CURRENT_TIMESTAMP, :created_by, :created_by_name
        )
        RETURNING id
    ");
    cdoc_bind_document_fields($stmt, $data);
    $stmt->bindValue(':original_filename', $fileMeta['original_filename']);
    $stmt->bindValue(':stored_filename', $fileMeta['stored_filename']);
    $stmt->bindValue(':file_mime', $fileMeta['file_mime']);
    $stmt->bindValue(':file_size', (int) $fileMeta['file_size'], PDO::PARAM_INT);
    $stmt->bindValue(':file_extension', $fileMeta['file_extension']);
    $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    $stmt->bindValue(':created_by_name', $createdByName);

    $maxAttempts = 5;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $stmt->bindValue(':document_number', cdoc_next_document_number($conn));

        try {
            $stmt->execute();
            break;
        } catch (PDOException $e) {
            $isDuplicateNumber = $e->getCode() === '23505'
                && stripos($e->getMessage(), 'cdoc_documents_document_number') !== false;

            if (!$isDuplicateNumber || $attempt >= $maxAttempts) {
                throw $e;
            }
        }
    }

    return (int) $stmt->fetchColumn();
}

function cdoc_update_record(PDO $conn, int $id, array $data, ?array $fileMeta, string $updatedBy): bool
{
    $sql = '
        UPDATE cdoc_documents SET
            title = :title,
            product_group = :product_group,
            product_name = :product_name,
            document_type = :document_type,
            version_number = :version_number,
            description = :description,
            visibility = :visibility,
            approval_status = :approval_status,
            status = :status,
            updated_by = :updated_by,
            updated_at = CURRENT_TIMESTAMP
    ';

    if ($fileMeta !== null) {
        $sql .= ',
            original_filename = :original_filename,
            stored_filename = :stored_filename,
            file_mime = :file_mime,
            file_size = :file_size,
            file_extension = :file_extension
        ';
    }

    $sql .= ' WHERE id = :id AND deleted_at IS NULL';

    $stmt = $conn->prepare($sql);
    cdoc_bind_document_fields($stmt, $data);
    $stmt->bindValue(':updated_by', $updatedBy);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);

    if ($fileMeta !== null) {
        $stmt->bindValue(':original_filename', $fileMeta['original_filename']);
        $stmt->bindValue(':stored_filename', $fileMeta['stored_filename']);
        $stmt->bindValue(':file_mime', $fileMeta['file_mime']);
        $stmt->bindValue(':file_size', (int) $fileMeta['file_size'], PDO::PARAM_INT);
        $stmt->bindValue(':file_extension', $fileMeta['file_extension']);
    }

    return $stmt->execute();
}

function cdoc_bind_document_fields(PDOStatement $stmt, array $data): void
{
    $stmt->bindValue(':title', $data['title']);
    $stmt->bindValue(':product_group', $data['product_group'] !== '' ? $data['product_group'] : null);
    $stmt->bindValue(':product_name', $data['product_name'] !== '' ? $data['product_name'] : null);
    $stmt->bindValue(':document_type', $data['document_type'] !== '' ? $data['document_type'] : null);
    $stmt->bindValue(':version_number', $data['version_number'] !== '' ? $data['version_number'] : null);
    $stmt->bindValue(':description', $data['description'] !== '' ? $data['description'] : null);
    $stmt->bindValue(':visibility', $data['visibility']);
    $stmt->bindValue(':approval_status', $data['approval_status']);
    $stmt->bindValue(':status', $data['status']);
}

function cdoc_list(PDO $conn, bool $manageAll): array
{
    $sql = 'SELECT * FROM cdoc_documents WHERE deleted_at IS NULL';
    if (!$manageAll) {
        $sql .= " AND visibility = 'public' AND approval_status = 'approved' AND status = 'active'";
    }
    $sql .= ' ORDER BY created_at DESC';

    $stmt = $conn->query($sql);

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function cdoc_find_by_id(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare('SELECT * FROM cdoc_documents WHERE id = :id AND deleted_at IS NULL');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

function cdoc_soft_delete(PDO $conn, int $id): bool
{
    $stmt = $conn->prepare('
        UPDATE cdoc_documents
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->rowCount() > 0;
}

function cdoc_display_value($value): string
{
    $trimmed = trim((string) $value);

    return $trimmed !== '' ? $trimmed : '-';
}

function cdoc_is_previewable(array $row): bool
{
    $ext = strtolower((string) ($row['file_extension'] ?? ''));

    return in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true);
}
