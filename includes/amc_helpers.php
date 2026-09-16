<?php

/**
 * AMC (Annual Maintenance Contract) Registration.
 * Ported/adapted from the legacy call-center AMC registration flow
 * (index.php + getdata.php) into the dealer portal's schema/conventions.
 */

require_once __DIR__ . '/current_username_helpers.php';
require_once __DIR__ . '/rbac_access_helpers.php';
require_once __DIR__ . '/after_market_access_helpers.php';
require_once __DIR__ . '/installed_base_helpers.php';
require_once __DIR__ . '/warranty_claims_helpers.php';

const AMC_OBLIGATION_OPTIONS = [
    'win' => 'Within Warranty',
    'wcon' => 'Within Contract',
    'wout' => 'Out Of Warranty',
];

const AMC_TYPE_OPTIONS = [
    'S' => 'Standard',
    'C' => 'Comprehensive',
];

const AMC_ENVIRONMENT_OPTIONS = ['Indoor', 'Outdoor', 'Semi-Outdoor'];

const AMC_MODE_OF_CALL_OPTIONS = [
    'T' => 'Phone',
    'E' => 'E-Mail',
    'P' => 'Person',
    'F' => 'Fax',
    'L' => 'Letter',
];

const AMC_CUSTOMER_GROUP_OPTIONS = ['Government', 'Industrial', 'Individual', 'OEM'];

const AMC_BUSINESS_LINE_OPTIONS = ['Air Compressor', 'Air Dryer', 'Generator', 'Other'];

const AMC_PRODUCT_GROUP_OPTIONS = ['Air Compressor', 'Air Dryer', 'Generator', 'Spares'];

const AMC_STATUS_ACTIVE = 'Active';
const AMC_STATUS_EXPIRED = 'Expired';
const AMC_STATUS_CANCELLED = 'Cancelled';

const AMC_VISIT_PENDING = 'Pending';
const AMC_VISIT_COMPLETED = 'Completed';

function amc_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $tableExists = static function (PDO $conn, string $table): bool {
        $stmt = $conn->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_name = :table
            LIMIT 1
        ");
        $stmt->bindValue(':table', $table);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    };

    if (!$tableExists($conn, 'amc_contracts')) {
        $conn->exec("
            CREATE TABLE amc_contracts (
                id SERIAL PRIMARY KEY,
                contract_number VARCHAR(50) NOT NULL UNIQUE,
                product_group VARCHAR(100) NULL,
                product_model VARCHAR(100) NULL,
                fab_number VARCHAR(100) NULL,
                obligation VARCHAR(20) NULL,
                customer_name VARCHAR(150) NOT NULL,
                contact_person VARCHAR(150) NULL,
                telephone_number VARCHAR(50) NULL,
                email_id VARCHAR(150) NULL,
                address_line1 VARCHAR(255) NULL,
                address_line2 VARCHAR(255) NULL,
                city_name VARCHAR(100) NULL,
                post_code VARCHAR(20) NULL,
                customer_group VARCHAR(50) NULL,
                business_line VARCHAR(100) NULL,
                environment VARCHAR(50) NULL,
                amc_type VARCHAR(20) NULL,
                amc_type_remarks VARCHAR(500) NULL,
                mode_of_call VARCHAR(20) NULL,
                amc_start_date DATE NOT NULL,
                amc_end_date DATE NOT NULL,
                visit_start_date DATE NOT NULL,
                no_of_visits INTEGER NOT NULL,
                amc_value NUMERIC(12,2) NOT NULL,
                dealer_name VARCHAR(150) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'Active',
                created_by INTEGER NULL,
                username VARCHAR(150) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL
            )
        ");
    }

    $conn->exec("
        ALTER TABLE amc_contracts
        ADD COLUMN IF NOT EXISTS district_name VARCHAR(100) NULL,
        ADD COLUMN IF NOT EXISTS state_name VARCHAR(100) NULL,
        ADD COLUMN IF NOT EXISTS installed_base_id INTEGER NULL,
        ADD COLUMN IF NOT EXISTS customer_id INTEGER NULL,
        ADD COLUMN IF NOT EXISTS warranty_status VARCHAR(50) NULL
    ");

    if (!$tableExists($conn, 'amc_visits')) {
        $conn->exec("
            CREATE TABLE amc_visits (
                id SERIAL PRIMARY KEY,
                amc_contract_id INTEGER NOT NULL REFERENCES amc_contracts(id),
                visit_number INTEGER NOT NULL,
                visit_date DATE NOT NULL,
                visit_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
                completed_date DATE NULL,
                remarks VARCHAR(500) NULL,
                service_log_id INTEGER NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    $conn->exec("
        ALTER TABLE amc_visits
        ADD COLUMN IF NOT EXISTS service_log_id INTEGER NULL
    ");

    $conn->exec("
        ALTER TABLE service_logs
        ADD COLUMN IF NOT EXISTS covered_under_amc_visit VARCHAR(10) NULL,
        ADD COLUMN IF NOT EXISTS amc_contract_id INTEGER NULL,
        ADD COLUMN IF NOT EXISTS amc_visit_id INTEGER NULL
    ");

    try {
        $conn->exec("
            CREATE UNIQUE INDEX IF NOT EXISTS amc_visits_service_log_id_uidx
            ON amc_visits (service_log_id)
            WHERE service_log_id IS NOT NULL
        ");
    } catch (PDOException $e) {
        // Duplicate links may already exist; uniqueness is still enforced in PHP.
    }

    $ensured = true;
}

function amc_action_permissions(PDO $conn): array
{
    return [
        'add' => rbac_user_can($conn, 'amc', 'add'),
        'edit' => rbac_user_can($conn, 'amc', 'edit'),
        'delete' => rbac_user_can($conn, 'amc', 'delete'),
    ];
}

/**
 * Added By is visible to System Admin, CCS Admin, and Management only.
 */
function amc_can_view_added_by(?PDO $conn = null): bool
{
    if ($conn !== null && (!isset($_SESSION['role']) || current_user_role() <= 0)) {
        admin_ensure_session_role($conn);
    }

    return is_system_admin() || is_ccs_admin_user() || is_management_user();
}

/**
 * System Admin, CCS Admin, and Management see all AMC records.
 */
function amc_sees_all_records(?PDO $conn = null): bool
{
    return amc_can_view_added_by($conn);
}

/**
 * @return array{where: string, params: array<string, mixed>, see_all: bool}
 */
function amc_list_scope(PDO $conn): array
{
    if (!isset($_SESSION['role'])) {
        admin_refresh_session_role($conn);
    }

    if (amc_sees_all_records($conn)) {
        return [
            'where' => 'ac.deleted_at IS NULL',
            'params' => [],
            'see_all' => true,
        ];
    }

    $currentUserId = (string) (int) (current_user_id($conn) ?? 0);

    return [
        'where' => 'ac.deleted_at IS NULL
            AND (
                TRIM(COALESCE(ac.created_by::text, \'\')) = :amc_scope_user_id
                OR LOWER(TRIM(COALESCE(NULLIF(TRIM(ac.username), \'\'), NULLIF(TRIM(ac.created_by::text), \'\'), \'\'))) = LOWER(TRIM(:amc_scope_username))
            )',
        'params' => [
            ':amc_scope_user_id' => $currentUserId,
            ':amc_scope_username' => current_username(),
        ],
        'see_all' => false,
    ];
}

/**
 * Installed Base search for AMC registration uses the same role split:
 * System Admin / CCS Admin / Management see all machines; others see only their own.
 *
 * @return array{where: string, params: array<string, mixed>}
 */
function amc_installed_base_list_scope(PDO $conn): array
{
    if (!isset($_SESSION['role'])) {
        admin_refresh_session_role($conn);
    }

    if (amc_sees_all_records($conn)) {
        return [
            'where' => 'deleted_at IS NULL',
            'params' => [],
        ];
    }

    return [
        'where' => 'deleted_at IS NULL AND username = :username',
        'params' => [
            ':username' => current_username(),
        ],
    ];
}

function amc_user_can_access_installed_base(PDO $conn, int $installedBaseId): bool
{
    if ($installedBaseId <= 0) {
        return false;
    }

    $scope = amc_installed_base_list_scope($conn);
    $stmt = $conn->prepare("
        SELECT id
        FROM installed_base
        WHERE id = :id
          AND {$scope['where']}
        LIMIT 1
    ");
    foreach ($scope['params'] as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':id', $installedBaseId, PDO::PARAM_INT);
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function amc_user_can_access_record(PDO $conn, ?array $record): bool
{
    if ($record === null) {
        return false;
    }

    if (amc_sees_all_records($conn)) {
        return true;
    }

    $createdBy = trim((string) ($record['created_by'] ?? ''));
    $username = trim((string) ($record['username'] ?? ''));
    $currentUsername = current_username();
    if ($username !== '' && strcasecmp($username, $currentUsername) === 0) {
        return true;
    }
    if ($createdBy !== '' && strcasecmp($createdBy, $currentUsername) === 0) {
        return true;
    }

    $currentId = (string) (int) (current_user_id($conn) ?? 0);

    return $currentId !== '0' && $createdBy === $currentId;
}

function amc_added_by_select_sql(string $alias = 'ac', string $userAlias = 'um_added'): string
{
    return "COALESCE(
        NULLIF(TRIM({$userAlias}.name), ''),
        NULLIF(TRIM({$userAlias}.username), ''),
        NULLIF(TRIM({$alias}.username), ''),
        '-'
    ) AS added_by_name";
}

function amc_added_by_join_sql(string $alias = 'ac', string $userAlias = 'um_added'): string
{
    // created_by may be INTEGER (user id) or VARCHAR (id or username) depending on the database.
    return "LEFT JOIN user_master {$userAlias}
        ON {$userAlias}.deleted_at IS NULL
       AND (
            (
                NULLIF(TRIM(COALESCE({$alias}.username, '')), '') IS NOT NULL
                AND LOWER(TRIM({$userAlias}.username)) = LOWER(TRIM({$alias}.username))
            )
            OR (
                NULLIF(TRIM(COALESCE({$alias}.username, '')), '') IS NULL
                AND (
                    {$userAlias}.id::text = NULLIF(TRIM({$alias}.created_by::text), '')
                    OR LOWER(TRIM({$userAlias}.username)) = LOWER(TRIM({$alias}.created_by::text))
                )
            )
       )";
}

function amc_added_by_label(array $row): string
{
    $name = trim((string) ($row['added_by_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    $username = trim((string) ($row['username'] ?? ''));

    return $username !== '' ? $username : '-';
}

function amc_from_post(array $post): array
{
    return [
        'installed_base_id' => trim((string) ($post['installed_base_id'] ?? '')),
        'product_group' => '',
        'product_model' => '',
        'fab_number' => '',
        'obligation' => '',
        'warranty_status' => '',
        'customer_id' => '',
        'customer_name' => '',
        'contact_person' => '',
        'telephone_number' => '',
        'email_id' => '',
        'address_line1' => '',
        'address_line2' => '',
        'city_name' => '',
        'district_name' => '',
        'state_name' => '',
        'post_code' => '',
        'customer_group' => '',
        'business_line' => '',
        'environment' => '',
        'amc_type' => trim((string) ($post['amc_type'] ?? '')),
        'amc_type_remarks' => '',
        'mode_of_call' => '',
        'amc_start_date' => trim((string) ($post['amc_start_date'] ?? '')),
        'amc_end_date' => trim((string) ($post['amc_end_date'] ?? '')),
        'visit_start_date' => trim((string) ($post['visit_start_date'] ?? '')),
        'no_of_visits' => trim((string) ($post['no_of_visits'] ?? '')),
        'amc_value' => trim((string) ($post['amc_value'] ?? '')),
    ];
}

function amc_obligation_from_warranty_status(string $status): string
{
    if ($status === INSTALLED_BASE_WARRANTY_OUT) {
        return 'wout';
    }
    if ($status === INSTALLED_BASE_WARRANTY_STANDARD || $status === INSTALLED_BASE_WARRANTY_UPTIME) {
        return 'win';
    }

    return '';
}

function amc_installed_base_option_from_row(array $row): array
{
    $installedBaseId = (int) ($row['id'] ?? 0);
    $fabNumber = trim((string) ($row['fab_number'] ?? ''));
    $customerName = trim((string) ($row['customer_name'] ?? ''));
    $machineModel = installed_base_machine_model_label($row);
    $warranty = installed_base_warranty_status($row['commissioning_date'] ?? null);
    $warrantyStatus = trim((string) ($warranty['status'] ?? 'Unknown'));
    $label = '#' . $installedBaseId . ' - ' . ($fabNumber !== '' ? $fabNumber : '-') . ' - ' . ($customerName !== '' ? $customerName : '-');

    return [
        'id' => $installedBaseId,
        'text' => $label,
        'installed_base_id' => $installedBaseId,
        'fab_number' => $fabNumber,
        'product_model' => $machineModel !== '-' ? $machineModel : '',
        'machine_model' => $machineModel !== '-' ? $machineModel : '',
        'warranty_status' => $warrantyStatus,
        'warranty_badge_class' => (string) ($warranty['badge_class'] ?? 'warranty-status--unknown'),
        'customer_id' => (int) ($row['customer_id'] ?? 0),
        'customer_name' => $customerName,
        'contact_person' => $customerName,
        'telephone_number' => trim((string) ($row['mobile'] ?? '')),
        'email_id' => trim((string) ($row['email'] ?? '')),
        'address_line1' => trim((string) ($row['street_1'] ?? '')),
        'address_line2' => trim((string) ($row['street_2'] ?? '')),
        'city_name' => trim((string) ($row['city'] ?? '')),
        'district_name' => trim((string) ($row['district'] ?? '')),
        'state_name' => trim((string) ($row['state'] ?? '')),
        'post_code' => trim((string) ($row['pincode'] ?? '')),
        'dealer_name' => trim((string) (($row['customer_dealer_name'] ?? '') !== '' ? $row['customer_dealer_name'] : ($row['dealer_name'] ?? ''))),
    ];
}

function amc_installed_base_select_sql(): string
{
    return '
        SELECT
            ib.id,
            ib.fab_number,
            ib.machine_model,
            ib.machine_model_code,
            ib.commissioning_date,
            ib.dealer_name,
            ib.customer_id,
            cm.customer_name,
            cm.email,
            cm.mobile,
            cm.street_1,
            cm.street_2,
            cm.pincode,
            cm.city,
            cm.district,
            cm.state,
            cm.dealer_name AS customer_dealer_name
        FROM installed_base ib
        ' . installed_base_customer_join_sql('ib', 'cm') . '
    ';
}

function amc_installed_base_snapshot(PDO $conn, int $installedBaseId): ?array
{
    installed_base_ensure_schema($conn);

    if ($installedBaseId <= 0 || !amc_user_can_access_installed_base($conn, $installedBaseId)) {
        return null;
    }

    $stmt = $conn->prepare(amc_installed_base_select_sql() . '
        WHERE ib.id = :id
          AND ib.deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $installedBaseId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? amc_attach_coverage_to_options($conn, [amc_installed_base_option_from_row($row)])[0] : null;
}

function amc_search_installed_base(PDO $conn, string $term): array
{
    installed_base_ensure_schema($conn);

    $scope = amc_installed_base_list_scope($conn);
    $scopeWhere = after_market_scope_where_for_alias($scope['where'], 'ib');

    $sql = amc_installed_base_select_sql() . " WHERE {$scopeWhere}";
    if ($term !== '') {
        $sql .= '
          AND (
                ib.fab_number ILIKE :term
             OR cm.customer_name ILIKE :term
             OR ib.machine_model ILIKE :term
             OR ib.machine_model_code ILIKE :term
             OR CAST(ib.id AS TEXT) ILIKE :term
          )
        ';
    }
    $sql .= '
        ORDER BY ib.id DESC
        LIMIT 25
    ';

    $stmt = $conn->prepare($sql);
    foreach ($scope['params'] as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if ($term !== '') {
        $stmt->bindValue(':term', '%' . $term . '%');
    }
    $stmt->execute();

    $results = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = amc_installed_base_option_from_row($row);
    }

    return amc_attach_coverage_to_options($conn, $results);
}

function amc_merge_installed_base_snapshot(array $data, array $snapshot): array
{
    $data['installed_base_id'] = (string) ((int) ($snapshot['installed_base_id'] ?? 0));
    $data['fab_number'] = trim((string) ($snapshot['fab_number'] ?? ''));
    $data['product_model'] = trim((string) ($snapshot['product_model'] ?? ''));
    $data['warranty_status'] = trim((string) ($snapshot['warranty_status'] ?? ''));
    $data['obligation'] = amc_obligation_from_warranty_status($data['warranty_status']);
    $data['customer_id'] = (string) ((int) ($snapshot['customer_id'] ?? 0));
    $data['customer_name'] = trim((string) ($snapshot['customer_name'] ?? ''));
    $data['contact_person'] = trim((string) ($snapshot['contact_person'] ?? ''));
    $data['telephone_number'] = trim((string) ($snapshot['telephone_number'] ?? ''));
    $data['email_id'] = trim((string) ($snapshot['email_id'] ?? ''));
    $data['address_line1'] = trim((string) ($snapshot['address_line1'] ?? ''));
    $data['address_line2'] = trim((string) ($snapshot['address_line2'] ?? ''));
    $data['city_name'] = trim((string) ($snapshot['city_name'] ?? ''));
    $data['district_name'] = trim((string) ($snapshot['district_name'] ?? ''));
    $data['state_name'] = trim((string) ($snapshot['state_name'] ?? ''));
    $data['post_code'] = trim((string) ($snapshot['post_code'] ?? ''));

    return $data;
}

function amc_validate(array $data): ?string
{
    if ($data['installed_base_id'] === '' || (int) $data['installed_base_id'] <= 0) {
        return 'Please search for and select an Installed Base machine.';
    }

    if ($data['amc_type'] === '' || !array_key_exists($data['amc_type'], AMC_TYPE_OPTIONS)) {
        return 'Please select a valid AMC Type.';
    }

    foreach (['amc_start_date', 'amc_end_date', 'visit_start_date'] as $dateField) {
        if ($data[$dateField] === '' || !DateTime::createFromFormat('Y-m-d', $data[$dateField])) {
            return 'Please provide a valid date for all AMC dates.';
        }
    }

    if ($data['amc_end_date'] <= $data['amc_start_date']) {
        return 'AMC End Date must be later than the AMC Start Date.';
    }

    if ($data['visit_start_date'] < $data['amc_start_date'] || $data['visit_start_date'] > $data['amc_end_date']) {
        return 'Visit Start Date must fall within the AMC start/end dates.';
    }

    if (!preg_match('/^[1-9]\d*$/', $data['no_of_visits'])) {
        return 'Number of Visits must be a positive whole number.';
    }

    if (!is_numeric($data['amc_value']) || (float) $data['amc_value'] <= 0) {
        return 'AMC Value must be greater than 0.';
    }

    return null;
}

/**
 * @return array{success: bool, message: string, id?: int, data: array, snapshot: ?array}
 */
function amc_create_from_post(PDO $conn, array $post, int $createdBy, string $username, string $dealerName): array
{
    $data = amc_from_post($post);
    $snapshot = null;
    $installedBaseId = (int) ($data['installed_base_id'] ?? 0);
    if ($installedBaseId > 0) {
        $snapshot = amc_installed_base_snapshot($conn, $installedBaseId);
    }

    $validationError = amc_validate($data);
    if ($validationError === null) {
        if ($snapshot === null) {
            $validationError = 'Please search for and select a valid Installed Base machine.';
        } elseif (trim((string) ($snapshot['fab_number'] ?? '')) === '') {
            $validationError = 'The selected Installed Base record does not have a FAB number.';
        } elseif (trim((string) ($snapshot['customer_name'] ?? '')) === '') {
            $validationError = 'Customer details are not available for the selected Installed Base record.';
        } else {
            $data = amc_merge_installed_base_snapshot($data, $snapshot);
        }
    }

    if ($validationError !== null) {
        return [
            'success' => false,
            'message' => $validationError,
            'data' => $data,
            'snapshot' => $snapshot,
        ];
    }

    if ($createdBy <= 0) {
        return [
            'success' => false,
            'message' => 'Unable to resolve logged-in user.',
            'data' => $data,
            'snapshot' => $snapshot,
        ];
    }

    try {
        $id = amc_insert_record($conn, $data, $createdBy, $username, $dealerName);

        return [
            'success' => true,
            'message' => 'AMC contract registered successfully.',
            'id' => $id,
            'data' => $data,
            'snapshot' => $snapshot,
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Failed to save AMC contract. Please try again.',
            'data' => $data,
            'snapshot' => $snapshot,
        ];
    }
}

/**
 * Spread visit dates evenly between visit_start_date and amc_end_date,
 * pushing any date that lands on a Sunday to the next day (clamped to end date).
 * Simplified port of the legacy getdata.php next-visit-date logic.
 */
function amc_generate_visit_schedule(string $visitStartDate, string $amcEndDate, int $noOfVisits): array
{
    $start = new DateTime($visitStartDate);
    $end = new DateTime($amcEndDate);
    $totalDays = (int) $start->diff($end)->days;
    $interval = $noOfVisits > 1 ? (int) round($totalDays / $noOfVisits) : 0;

    $dates = [];
    for ($visit = 1; $visit <= $noOfVisits; $visit++) {
        $date = clone $start;
        if ($visit > 1) {
            $date->modify('+' . ($interval * ($visit - 1)) . ' days');
        }
        if ($date > $end) {
            $date = clone $end;
        }
        if ((int) $date->format('w') === 0 && $date < $end) {
            $date->modify('+1 day');
        }
        $dates[] = $date->format('Y-m-d');
    }

    return $dates;
}

function amc_next_contract_number(PDO $conn): string
{
    $year = date('Y');
    $stmt = $conn->prepare("
        SELECT COALESCE(MAX(SPLIT_PART(contract_number, '-', 3)::int), 0)
        FROM amc_contracts
        WHERE contract_number LIKE :prefix
    ");
    $prefix = "AMC-$year-%";
    $stmt->bindValue(':prefix', $prefix);
    $stmt->execute();
    $seq = ((int) $stmt->fetchColumn()) + 1;

    return sprintf('AMC-%s-%04d', $year, $seq);
}

function amc_insert_record(PDO $conn, array $data, int $createdBy, string $username, string $dealerName): int
{
    $stmt = $conn->prepare("
        INSERT INTO amc_contracts
        (
            contract_number, installed_base_id, customer_id, product_group, product_model, fab_number,
            obligation, warranty_status,
            customer_name, contact_person, telephone_number, email_id,
            address_line1, address_line2, city_name, post_code,
            district_name, state_name,
            customer_group, business_line, environment, amc_type, amc_type_remarks,
            mode_of_call, amc_start_date, amc_end_date, visit_start_date,
            no_of_visits, amc_value, dealer_name, status, created_by, username
        )
        VALUES
        (
            :contract_number, :installed_base_id, :customer_id, :product_group, :product_model, :fab_number,
            :obligation, :warranty_status,
            :customer_name, :contact_person, :telephone_number, :email_id,
            :address_line1, :address_line2, :city_name, :post_code,
            :district_name, :state_name,
            :customer_group, :business_line, :environment, :amc_type, :amc_type_remarks,
            :mode_of_call, :amc_start_date, :amc_end_date, :visit_start_date,
            :no_of_visits, :amc_value, :dealer_name, :status, :created_by, :username
        )
        RETURNING id
    ");

    $installedBaseId = (int) ($data['installed_base_id'] ?? 0);
    $customerId = (int) ($data['customer_id'] ?? 0);
    $stmt->bindValue(':installed_base_id', $installedBaseId > 0 ? $installedBaseId : null, $installedBaseId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindValue(':customer_id', $customerId > 0 ? $customerId : null, $customerId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindValue(':product_group', $data['product_group'] !== '' ? $data['product_group'] : null);
    $stmt->bindValue(':product_model', $data['product_model'] !== '' ? $data['product_model'] : null);
    $stmt->bindValue(':fab_number', $data['fab_number'] !== '' ? $data['fab_number'] : null);
    $stmt->bindValue(':obligation', $data['obligation'] !== '' ? $data['obligation'] : null);
    $stmt->bindValue(':warranty_status', $data['warranty_status'] !== '' ? $data['warranty_status'] : null);
    $stmt->bindValue(':customer_name', $data['customer_name']);
    $stmt->bindValue(':contact_person', $data['contact_person'] !== '' ? $data['contact_person'] : null);
    $stmt->bindValue(':telephone_number', $data['telephone_number'] !== '' ? $data['telephone_number'] : null);
    $stmt->bindValue(':email_id', $data['email_id'] !== '' ? $data['email_id'] : null);
    $stmt->bindValue(':address_line1', $data['address_line1'] !== '' ? $data['address_line1'] : null);
    $stmt->bindValue(':address_line2', $data['address_line2'] !== '' ? $data['address_line2'] : null);
    $stmt->bindValue(':city_name', $data['city_name'] !== '' ? $data['city_name'] : null);
    $stmt->bindValue(':post_code', $data['post_code'] !== '' ? $data['post_code'] : null);
    $stmt->bindValue(':district_name', $data['district_name'] !== '' ? $data['district_name'] : null);
    $stmt->bindValue(':state_name', $data['state_name'] !== '' ? $data['state_name'] : null);
    $stmt->bindValue(':customer_group', $data['customer_group'] !== '' ? $data['customer_group'] : null);
    $stmt->bindValue(':business_line', $data['business_line'] !== '' ? $data['business_line'] : null);
    $stmt->bindValue(':environment', $data['environment'] !== '' ? $data['environment'] : null);
    $stmt->bindValue(':amc_type', $data['amc_type']);
    $stmt->bindValue(':amc_type_remarks', $data['amc_type_remarks'] !== '' ? $data['amc_type_remarks'] : null);
    $stmt->bindValue(':mode_of_call', $data['mode_of_call'] !== '' ? $data['mode_of_call'] : null);
    $stmt->bindValue(':amc_start_date', $data['amc_start_date']);
    $stmt->bindValue(':amc_end_date', $data['amc_end_date']);
    $stmt->bindValue(':visit_start_date', $data['visit_start_date']);
    $stmt->bindValue(':no_of_visits', (int) $data['no_of_visits'], PDO::PARAM_INT);
    $stmt->bindValue(':amc_value', (float) $data['amc_value']);
    $stmt->bindValue(':dealer_name', $dealerName !== '' ? $dealerName : null);
    $stmt->bindValue(':status', amc_status_from_dates($data['amc_start_date'], $data['amc_end_date']));
    $stmt->bindValue(':created_by', (string) $createdBy);
    $stmt->bindValue(':username', $username);

    // contract_number is generated from a MAX() lookup, which is not safe against
    // concurrent inserts � retry a few times with a fresh number on a unique-violation.
    $maxAttempts = 5;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $stmt->bindValue(':contract_number', amc_next_contract_number($conn));

        try {
            $stmt->execute();
            break;
        } catch (PDOException $e) {
            $isDuplicateContractNumber = $e->getCode() === '23505'
                && stripos($e->getMessage(), 'amc_contracts_contract_number_key') !== false;

            if (!$isDuplicateContractNumber || $attempt >= $maxAttempts) {
                throw $e;
            }
        }
    }

    $newId = (int) $stmt->fetchColumn();

    $visitDates = amc_generate_visit_schedule($data['visit_start_date'], $data['amc_end_date'], (int) $data['no_of_visits']);
    $visitStmt = $conn->prepare("
        INSERT INTO amc_visits (amc_contract_id, visit_number, visit_date, visit_status)
        VALUES (:amc_contract_id, :visit_number, :visit_date, :visit_status)
    ");
    foreach ($visitDates as $index => $visitDate) {
        $visitStmt->bindValue(':amc_contract_id', $newId, PDO::PARAM_INT);
        $visitStmt->bindValue(':visit_number', $index + 1, PDO::PARAM_INT);
        $visitStmt->bindValue(':visit_date', $visitDate);
        $visitStmt->bindValue(':visit_status', AMC_VISIT_PENDING);
        $visitStmt->execute();
    }

    return $newId;
}

function amc_list(PDO $conn): array
{
    $scope = amc_list_scope($conn);
    $stmt = $conn->prepare('
        SELECT
            ac.*,
            ' . amc_added_by_select_sql() . '
        FROM amc_contracts ac
        ' . amc_added_by_join_sql() . '
        WHERE ' . $scope['where'] . '
        ORDER BY ac.created_at DESC
    ');
    foreach ($scope['params'] as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function amc_find_by_id(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare('
        SELECT
            ac.*,
            ' . amc_added_by_select_sql() . '
        FROM amc_contracts ac
        ' . amc_added_by_join_sql() . '
        WHERE ac.id = :id
          AND ac.deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

function amc_visits_for_contract(PDO $conn, int $contractId): array
{
    $stmt = $conn->prepare('
        SELECT
            av.*,
            sl.serial_number AS service_log_serial_number,
            sl.visit_date AS service_log_visit_date,
            sl.engineer_name AS service_log_engineer_name,
            sl.deleted_at AS service_log_deleted_at
        FROM amc_visits av
        LEFT JOIN service_logs sl ON sl.id = av.service_log_id
        WHERE av.amc_contract_id = :id
        ORDER BY av.visit_number ASC
    ');
    $stmt->bindValue(':id', $contractId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function amc_find_visit(PDO $conn, int $visitId, int $contractId): ?array
{
    $stmt = $conn->prepare('
        SELECT *
        FROM amc_visits
        WHERE id = :visit_id
          AND amc_contract_id = :contract_id
        LIMIT 1
    ');
    $stmt->bindValue(':visit_id', $visitId, PDO::PARAM_INT);
    $stmt->bindValue(':contract_id', $contractId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

function amc_service_log_option_label(array $row): string
{
    $parts = ['#' . (int) ($row['id'] ?? 0)];
    $serial = trim((string) ($row['serial_number'] ?? ''));
    if ($serial !== '') {
        $parts[] = $serial;
    }
    $visitDate = amc_normalize_date($row['visit_date'] ?? '');
    if ($visitDate !== '') {
        $parts[] = 'Visit ' . $visitDate;
    }
    $engineer = trim((string) ($row['engineer_name'] ?? ''));
    if ($engineer !== '') {
        $parts[] = $engineer;
    }

    return implode(' · ', $parts);
}

/**
 * Submitted (non-draft) service logs for this AMC machine that are not already
 * linked to another visit.
 *
 * @return array<int, array<string, mixed>>
 */
function amc_service_logs_available_for_visit(PDO $conn, array $contract, int $excludeVisitId = 0): array
{
    $installedBaseId = (int) ($contract['installed_base_id'] ?? 0);
    $fabNumber = trim((string) ($contract['fab_number'] ?? ''));
    if ($installedBaseId <= 0 && $fabNumber === '') {
        return [];
    }

    $sql = '
        SELECT sl.id, sl.serial_number, sl.visit_date, sl.engineer_name, sl.fab_number
        FROM service_logs sl
        WHERE sl.deleted_at IS NULL
          AND COALESCE(sl.is_draft, 0) = 0
    ';
    $params = [];

    if ($installedBaseId > 0 && $fabNumber !== '') {
        $sql .= ' AND (sl.installed_base_id = :installed_base_id OR LOWER(TRIM(COALESCE(sl.fab_number, \'\'))) = LOWER(TRIM(:fab_number)))';
        $params[':installed_base_id'] = $installedBaseId;
        $params[':fab_number'] = $fabNumber;
    } elseif ($installedBaseId > 0) {
        $sql .= ' AND sl.installed_base_id = :installed_base_id';
        $params[':installed_base_id'] = $installedBaseId;
    } else {
        $sql .= ' AND LOWER(TRIM(COALESCE(sl.fab_number, \'\'))) = LOWER(TRIM(:fab_number))';
        $params[':fab_number'] = $fabNumber;
    }

    $sql .= '
          AND NOT EXISTS (
              SELECT 1
              FROM amc_visits av
              WHERE av.service_log_id = sl.id
                AND av.id <> :exclude_visit_id
          )
    ';
    $params[':exclude_visit_id'] = $excludeVisitId;

    if (amc_service_logs_has_column($conn, 'amc_visit_id')) {
        $sql .= ' AND (sl.amc_visit_id IS NULL OR sl.amc_visit_id = :exclude_visit_id_amc)';
        $params[':exclude_visit_id_amc'] = $excludeVisitId;
    }

    $sql .= ' ORDER BY sl.id DESC';

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function amc_service_logs_has_column(PDO $conn, string $column): bool
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'service_logs'
          AND column_name = :column_name
        LIMIT 1
    ");
    $stmt->bindValue(':column_name', $column);
    $stmt->execute();
    $cache[$column] = (bool) $stmt->fetchColumn();

    return $cache[$column];
}

function amc_sync_service_log_visit_link(PDO $conn, int $serviceLogId, int $visitId, int $contractId): void
{
    if ($serviceLogId <= 0) {
        return;
    }

    $sets = [];
    $params = [
        ':id' => $serviceLogId,
    ];

    if (amc_service_logs_has_column($conn, 'amc_visit_id')) {
        $sets[] = 'amc_visit_id = :amc_visit_id';
        $params[':amc_visit_id'] = $visitId;
    }
    if (amc_service_logs_has_column($conn, 'amc_contract_id')) {
        $sets[] = 'amc_contract_id = :amc_contract_id';
        $params[':amc_contract_id'] = $contractId;
    }
    if (amc_service_logs_has_column($conn, 'covered_under_amc_visit')) {
        $sets[] = 'covered_under_amc_visit = :covered_under_amc_visit';
        $params[':covered_under_amc_visit'] = 'Yes';
    }

    if ($sets === []) {
        return;
    }

    $stmt = $conn->prepare('
        UPDATE service_logs
        SET ' . implode(', ', $sets) . '
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
}

function amc_clear_service_log_visit_link(PDO $conn, int $serviceLogId, int $visitId): void
{
    if ($serviceLogId <= 0) {
        return;
    }

    $sets = [];
    $params = [
        ':id' => $serviceLogId,
    ];
    $where = 'id = :id AND deleted_at IS NULL';

    if (amc_service_logs_has_column($conn, 'amc_visit_id')) {
        $sets[] = 'amc_visit_id = NULL';
        $where .= ' AND (amc_visit_id IS NULL OR amc_visit_id = :visit_id)';
        $params[':visit_id'] = $visitId;
    }
    if (amc_service_logs_has_column($conn, 'amc_contract_id')) {
        $sets[] = 'amc_contract_id = NULL';
    }
    if (amc_service_logs_has_column($conn, 'covered_under_amc_visit')) {
        $sets[] = 'covered_under_amc_visit = NULL';
    }

    if ($sets === []) {
        return;
    }

    $stmt = $conn->prepare('
        UPDATE service_logs
        SET ' . implode(', ', $sets) . '
        WHERE ' . $where . '
    ');
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();
}

/**
 * @return true|string True on success, or an error message.
 */
function amc_complete_visit_with_service_log(PDO $conn, int $visitId, int $contractId, int $serviceLogId, array $contract): string|bool
{
    if ($visitId <= 0) {
        return 'Invalid visit.';
    }
    if ($serviceLogId <= 0) {
        return 'Please select a Service Log to mark this visit as completed.';
    }

    $visit = amc_find_visit($conn, $visitId, $contractId);
    if (!$visit) {
        return 'Visit not found.';
    }
    if (($visit['visit_status'] ?? '') === AMC_VISIT_COMPLETED) {
        return 'This visit is already completed.';
    }

    $available = amc_service_logs_available_for_visit($conn, $contract, $visitId);
    $allowedIds = array_map(static fn (array $row): int => (int) $row['id'], $available);
    if (!in_array($serviceLogId, $allowedIds, true)) {
        return 'Selected Service Log is not available for this visit.';
    }

    $startedTransaction = false;
    if (!$conn->inTransaction()) {
        $conn->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $stmt = $conn->prepare('
            UPDATE amc_visits
            SET visit_status = :status,
                completed_date = CURRENT_DATE,
                service_log_id = :service_log_id
            WHERE id = :visit_id
              AND amc_contract_id = :contract_id
              AND visit_status <> :completed_status
        ');
        $stmt->bindValue(':status', AMC_VISIT_COMPLETED);
        $stmt->bindValue(':completed_status', AMC_VISIT_COMPLETED);
        $stmt->bindValue(':service_log_id', $serviceLogId, PDO::PARAM_INT);
        $stmt->bindValue(':visit_id', $visitId, PDO::PARAM_INT);
        $stmt->bindValue(':contract_id', $contractId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Failed to update visit status.');
        }

        amc_sync_service_log_visit_link($conn, $serviceLogId, $visitId, $contractId);

        if ($startedTransaction) {
            $conn->commit();
        }

        return true;
    } catch (Throwable $e) {
        if ($startedTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }

        return 'Failed to update visit status.';
    }
}

function amc_reopen_visit(PDO $conn, int $visitId, int $contractId): bool
{
    $visit = amc_find_visit($conn, $visitId, $contractId);
    if (!$visit) {
        return false;
    }

    $linkedServiceLogId = (int) ($visit['service_log_id'] ?? 0);

    $startedTransaction = false;
    if (!$conn->inTransaction()) {
        $conn->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $stmt = $conn->prepare('
            UPDATE amc_visits
            SET visit_status = :status,
                completed_date = NULL,
                service_log_id = NULL
            WHERE id = :visit_id
              AND amc_contract_id = :contract_id
        ');
        $stmt->bindValue(':status', AMC_VISIT_PENDING);
        $stmt->bindValue(':visit_id', $visitId, PDO::PARAM_INT);
        $stmt->bindValue(':contract_id', $contractId, PDO::PARAM_INT);
        $stmt->execute();

        if ($linkedServiceLogId > 0) {
            amc_clear_service_log_visit_link($conn, $linkedServiceLogId, $visitId);
        }

        if ($startedTransaction) {
            $conn->commit();
        }

        return true;
    } catch (Throwable $e) {
        if ($startedTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }

        return false;
    }
}

function amc_mark_visit_status(PDO $conn, int $visitId, int $contractId, string $status): bool
{
    if ($status === AMC_VISIT_PENDING) {
        return amc_reopen_visit($conn, $visitId, $contractId);
    }

    $stmt = $conn->prepare('
        UPDATE amc_visits
        SET visit_status = :status,
            completed_date = CASE WHEN :status2 = \'Completed\' THEN CURRENT_DATE ELSE NULL END
        WHERE id = :visit_id
          AND amc_contract_id = :contract_id
    ');
    $stmt->bindValue(':status', $status);
    $stmt->bindValue(':status2', $status);
    $stmt->bindValue(':visit_id', $visitId, PDO::PARAM_INT);
    $stmt->bindValue(':contract_id', $contractId, PDO::PARAM_INT);

    return $stmt->execute();
}

function amc_warranty_badge_html(?string $status): string
{
    $status = trim((string) $status);
    if ($status === '') {
        return '-';
    }

    return installed_base_warranty_status_badge_html([
        'status' => $status,
        'badge_class' => installed_base_warranty_status_badge_class($status),
    ]);
}

function amc_status_badge_class(string $status): string
{
    switch ($status) {
        case AMC_STATUS_ACTIVE:
            return 'bg-success';
        case AMC_STATUS_EXPIRED:
            return 'bg-secondary';
        case AMC_STATUS_CANCELLED:
            return 'bg-danger';
        default:
            return 'bg-light text-dark';
    }
}

function amc_visit_status_badge_class(string $status): string
{
    return $status === AMC_VISIT_COMPLETED ? 'bg-success' : 'bg-warning text-dark';
}

function amc_normalize_date(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    return substr($value, 0, 10);
}

function amc_status_from_dates(string $startDate, string $endDate): string
{
    $today = date('Y-m-d');
    if (amc_normalize_date($endDate) < $today) {
        return AMC_STATUS_EXPIRED;
    }

    return AMC_STATUS_ACTIVE;
}

function amc_contract_is_cancelled(array $row): bool
{
    return trim((string) ($row['status'] ?? '')) === AMC_STATUS_CANCELLED;
}

function amc_contract_is_active(array $row, ?string $today = null): bool
{
    if (amc_contract_is_cancelled($row)) {
        return false;
    }

    $today = $today ?? date('Y-m-d');
    $start = amc_normalize_date($row['amc_start_date'] ?? '');
    $end = amc_normalize_date($row['amc_end_date'] ?? '');

    return $start !== '' && $end !== '' && $start <= $today && $end >= $today;
}

function amc_display_status(array $row): string
{
    if (amc_contract_is_cancelled($row)) {
        return AMC_STATUS_CANCELLED;
    }

    return amc_contract_is_active($row) ? AMC_STATUS_ACTIVE : AMC_STATUS_EXPIRED;
}

function amc_coverage_none(): array
{
    return [
        'under_amc' => false,
        'end_date' => '',
        'end_date_label' => '-',
        'contract_id' => 0,
        'contract_number' => '',
        'installed_base_id' => 0,
        'fab_number' => '',
    ];
}

function amc_coverage_from_contract(array $row): array
{
    if (!amc_contract_is_active($row)) {
        return amc_coverage_none();
    }

    $end = amc_normalize_date($row['amc_end_date'] ?? '');

    return [
        'under_amc' => true,
        'end_date' => $end,
        'end_date_label' => installed_base_format_date($end),
        'contract_id' => (int) ($row['id'] ?? 0),
        'contract_number' => trim((string) ($row['contract_number'] ?? '')),
        'installed_base_id' => (int) ($row['installed_base_id'] ?? 0),
        'fab_number' => trim((string) ($row['fab_number'] ?? '')),
    ];
}

function amc_coverage_lookup(PDO $conn, array $installedBaseIds = [], array $fabNumbers = []): array
{
    amc_ensure_schema($conn);

    $ids = [];
    foreach ($installedBaseIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    $fabs = [];
    foreach ($fabNumbers as $fab) {
        $fab = strtolower(trim((string) $fab));
        if ($fab !== '') {
            $fabs[$fab] = $fab;
        }
    }

    if ($fabs !== []) {
        $placeholders = [];
        $params = [];
        $index = 0;
        foreach ($fabs as $fab) {
            $key = ':xfab' . $index++;
            $placeholders[] = $key;
            $params[$key] = $fab;
        }
        $stmt = $conn->prepare('
            SELECT id, fab_number
            FROM installed_base
            WHERE deleted_at IS NULL
              AND LOWER(TRIM(fab_number)) IN (' . implode(', ', $placeholders) . ')
        ');
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ids[(int) $row['id']] = (int) $row['id'];
            $fabKey = strtolower(trim((string) ($row['fab_number'] ?? '')));
            if ($fabKey !== '') {
                $fabs[$fabKey] = $fabKey;
            }
        }
    }

    if ($ids === [] && $fabs === []) {
        return ['by_id' => [], 'by_fab' => []];
    }

    $clauses = [];
    $params = [];
    if ($ids !== []) {
        $placeholders = [];
        $index = 0;
        foreach ($ids as $id) {
            $key = ':ib' . $index++;
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        $clauses[] = 'installed_base_id IN (' . implode(', ', $placeholders) . ')';
    }
    if ($fabs !== []) {
        $placeholders = [];
        $index = 0;
        foreach ($fabs as $fab) {
            $key = ':fab' . $index++;
            $placeholders[] = $key;
            $params[$key] = $fab;
        }
        $clauses[] = 'LOWER(TRIM(fab_number)) IN (' . implode(', ', $placeholders) . ')';
    }

    $stmt = $conn->prepare('
        SELECT *
        FROM amc_contracts
        WHERE deleted_at IS NULL
          AND (' . implode(' OR ', $clauses) . ')
        ORDER BY amc_end_date DESC, id DESC
    ');
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    $byId = [];
    $byFab = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!amc_contract_is_active($row)) {
            continue;
        }
        $coverage = amc_coverage_from_contract($row);
        $ibId = (int) ($row['installed_base_id'] ?? 0);
        $fabKey = strtolower(trim((string) ($row['fab_number'] ?? '')));
        if ($ibId > 0 && !isset($byId[$ibId])) {
            $byId[$ibId] = $coverage;
        }
        if ($fabKey !== '' && !isset($byFab[$fabKey])) {
            $byFab[$fabKey] = $coverage;
        }
    }

    return ['by_id' => $byId, 'by_fab' => $byFab];
}

function amc_coverage_resolve(array $lookup, int $installedBaseId = 0, string $fabNumber = ''): array
{
    if ($installedBaseId > 0 && isset($lookup['by_id'][$installedBaseId])) {
        return $lookup['by_id'][$installedBaseId];
    }

    $fabKey = strtolower(trim($fabNumber));
    if ($fabKey !== '' && isset($lookup['by_fab'][$fabKey])) {
        return $lookup['by_fab'][$fabKey];
    }

    return amc_coverage_none();
}

/**
 * @return array{by_id: array<int, true>, by_fab: array<string, true>}
 */
function amc_existing_contract_lookup(PDO $conn, array $installedBaseIds = [], array $fabNumbers = []): array
{
    amc_ensure_schema($conn);

    $ids = [];
    foreach ($installedBaseIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    $fabs = [];
    foreach ($fabNumbers as $fab) {
        $fab = strtolower(trim((string) $fab));
        if ($fab !== '') {
            $fabs[$fab] = $fab;
        }
    }

    if ($ids === [] && $fabs === []) {
        return ['by_id' => [], 'by_fab' => []];
    }

    $clauses = [];
    $params = [];
    if ($ids !== []) {
        $placeholders = [];
        $index = 0;
        foreach ($ids as $id) {
            $key = ':ib' . $index++;
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        $clauses[] = 'installed_base_id IN (' . implode(', ', $placeholders) . ')';
    }
    if ($fabs !== []) {
        $placeholders = [];
        $index = 0;
        foreach ($fabs as $fab) {
            $key = ':fab' . $index++;
            $placeholders[] = $key;
            $params[$key] = $fab;
        }
        $clauses[] = 'LOWER(TRIM(fab_number)) IN (' . implode(', ', $placeholders) . ')';
    }

    $stmt = $conn->prepare('
        SELECT installed_base_id, fab_number
        FROM amc_contracts
        WHERE deleted_at IS NULL
          AND (' . implode(' OR ', $clauses) . ')
    ');
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    $byId = [];
    $byFab = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ibId = (int) ($row['installed_base_id'] ?? 0);
        $fabKey = strtolower(trim((string) ($row['fab_number'] ?? '')));
        if ($ibId > 0) {
            $byId[$ibId] = true;
        }
        if ($fabKey !== '') {
            $byFab[$fabKey] = true;
        }
    }

    return ['by_id' => $byId, 'by_fab' => $byFab];
}

function amc_has_existing_contract(array $lookup, int $installedBaseId = 0, string $fabNumber = ''): bool
{
    if ($installedBaseId > 0 && !empty($lookup['by_id'][$installedBaseId])) {
        return true;
    }

    $fabKey = strtolower(trim($fabNumber));

    return $fabKey !== '' && !empty($lookup['by_fab'][$fabKey]);
}

/**
 * @return array{
 *     warranty_status: string,
 *     warranty_end_date_heading: string,
 *     warranty_end_date_label: string,
 *     under_amc: string,
 *     amc_end_date_label: string
 * }
 */
function installed_base_warranty_amc_form_payload_from_values(?string $commissioningDate, array $coverage): array
{
    $details = installed_base_warranty_details($commissioningDate);
    $endLabel = trim((string) ($details['end_date_label'] ?? ''));
    if ($endLabel === '-') {
        $endLabel = '';
    }

    $underAmc = !empty($coverage['under_amc']);
    $amcEndLabel = trim((string) ($coverage['end_date_label'] ?? ''));
    if ($amcEndLabel === '-') {
        $amcEndLabel = '';
    }

    return [
        'warranty_status' => (string) ($details['status'] ?? 'Unknown'),
        'warranty_end_date_heading' => (string) ($details['end_date_heading'] ?? 'Warranty Ended On'),
        'warranty_end_date_label' => $endLabel,
        'under_amc' => $underAmc ? 'Yes' : 'No',
        'amc_end_date_label' => $underAmc ? $amcEndLabel : '',
    ];
}

function installed_base_warranty_amc_form_payload(
    PDO $conn,
    int $installedBaseId = 0,
    string $fabNumber = '',
    ?string $commissioningDate = null
): array {
    if ($commissioningDate === null || trim($commissioningDate) === '') {
        $commissioningDate = installed_base_commissioning_date_for_machine($conn, $installedBaseId, $fabNumber);
    }

    return installed_base_warranty_amc_form_payload_from_values(
        $commissioningDate,
        amc_coverage_for_machine($conn, $installedBaseId, $fabNumber)
    );
}

function amc_coverage_for_machine(PDO $conn, int $installedBaseId = 0, string $fabNumber = ''): array
{
    return amc_coverage_resolve(
        amc_coverage_lookup($conn, [$installedBaseId], [$fabNumber]),
        $installedBaseId,
        $fabNumber
    );
}

function amc_attach_coverage_to_options(PDO $conn, array $options): array
{
    $ids = [];
    $fabs = [];
    foreach ($options as $option) {
        $ids[] = (int) ($option['installed_base_id'] ?? $option['id'] ?? 0);
        $fabs[] = (string) ($option['fab_number'] ?? '');
    }

    $lookup = amc_coverage_lookup($conn, $ids, $fabs);
    foreach ($options as $index => $option) {
        $coverage = amc_coverage_resolve(
            $lookup,
            (int) ($option['installed_base_id'] ?? $option['id'] ?? 0),
            (string) ($option['fab_number'] ?? '')
        );
        $options[$index]['under_amc'] = !empty($coverage['under_amc']) ? 'Yes' : 'No';
        $options[$index]['amc_end_date'] = !empty($coverage['under_amc']) ? (string) $coverage['end_date'] : '';
        $options[$index]['amc_end_date_label'] = !empty($coverage['under_amc']) ? (string) $coverage['end_date_label'] : '';
    }

    return $options;
}

function amc_coverage_meta_html(array $coverage, $commissioningDate = null): string
{
    $yes = !empty($coverage['under_amc']);
    $html = '<div class="amc-coverage-meta">';
    $html .= installed_base_warranty_meta_html($commissioningDate !== null ? (string) $commissioningDate : null);
    $html .= '<div>Under AMC: <strong>' . ($yes ? 'Yes' : 'No') . '</strong></div>';
    if ($yes) {
        $html .= '<div>AMC end date: '
            . htmlspecialchars((string) $coverage['end_date_label'], ENT_QUOTES, 'UTF-8')
            . '</div>';
    }
    $html .= '</div>';

    return $html;
}

function amc_with_coverage_html(string $primaryHtml, array $coverage, $commissioningDate = null): string
{
    return $primaryHtml . amc_coverage_meta_html($coverage, $commissioningDate);
}

function amc_list_for_installed_base(PDO $conn, int $installedBaseId, string $fabNumber = ''): array
{
    amc_ensure_schema($conn);

    $clauses = [];
    $params = [];
    if ($installedBaseId > 0) {
        $clauses[] = 'installed_base_id = :installed_base_id';
        $params[':installed_base_id'] = $installedBaseId;
    }
    $fabNumber = trim($fabNumber);
    if ($fabNumber !== '') {
        $clauses[] = 'LOWER(TRIM(fab_number)) = LOWER(TRIM(:fab_number))';
        $params[':fab_number'] = $fabNumber;
    }
    if ($clauses === []) {
        return [];
    }

    $stmt = $conn->prepare('
        SELECT *
        FROM amc_contracts
        WHERE deleted_at IS NULL
          AND (' . implode(' OR ', $clauses) . ')
        ORDER BY amc_end_date DESC, id DESC
    ');
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function amc_find_visit_by_id(PDO $conn, int $visitId): ?array
{
    if ($visitId <= 0) {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT *
        FROM amc_visits
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->bindValue(':id', $visitId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

function amc_find_visit_linked_to_service_log(PDO $conn, int $serviceLogId): ?array
{
    if ($serviceLogId <= 0) {
        return null;
    }

    amc_ensure_schema($conn);

    $stmt = $conn->prepare('
        SELECT *
        FROM amc_visits
        WHERE service_log_id = :service_log_id
        ORDER BY id DESC
        LIMIT 1
    ');
    $stmt->bindValue(':service_log_id', $serviceLogId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

/**
 * AMC contract/visit to display on Service Log details.
 *
 * @return array{contract: ?array, visit: ?array}
 */
function amc_context_for_service_log(PDO $conn, array $serviceLog, ?array $installedBaseRecord = null): array
{
    amc_ensure_schema($conn);

    $serviceLogId = (int) ($serviceLog['id'] ?? 0);
    $visit = amc_find_visit_linked_to_service_log($conn, $serviceLogId);

    if (!$visit && amc_service_logs_has_column($conn, 'amc_visit_id')) {
        $visit = amc_find_visit_by_id($conn, (int) ($serviceLog['amc_visit_id'] ?? 0));
    }

    $contractId = (int) ($visit['amc_contract_id'] ?? 0);
    if ($contractId <= 0 && amc_service_logs_has_column($conn, 'amc_contract_id')) {
        $contractId = (int) ($serviceLog['amc_contract_id'] ?? 0);
    }

    $installedBaseId = (int) ($installedBaseRecord['id'] ?? ($serviceLog['installed_base_id'] ?? 0));
    $fabNumber = trim((string) ($installedBaseRecord['fab_number'] ?? ($serviceLog['fab_number'] ?? '')));

    if ($contractId <= 0) {
        $coverage = amc_coverage_for_machine($conn, $installedBaseId, $fabNumber);
        $contractId = (int) ($coverage['contract_id'] ?? 0);
    }

    if ($contractId <= 0) {
        $contracts = amc_list_for_installed_base($conn, $installedBaseId, $fabNumber);
        $contractId = (int) ($contracts[0]['id'] ?? 0);
    }

    $contract = $contractId > 0 ? amc_find_by_id($conn, $contractId) : null;

    return [
        'contract' => $contract,
        'visit' => $visit,
    ];
}