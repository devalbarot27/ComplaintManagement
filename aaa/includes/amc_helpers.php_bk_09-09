<?php

/**
 * AMC (Annual Maintenance Contract) Registration.
 * Ported/adapted from the legacy call-center AMC registration flow
 * (index.php + getdata.php) into the dealer portal's schema/conventions.
 */

require_once __DIR__ . '/current_username_helpers.php';
require_once __DIR__ . '/rbac_access_helpers.php';

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
        ADD COLUMN IF NOT EXISTS state_name VARCHAR(100) NULL
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
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
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

function amc_from_post(array $post): array
{
    return [
        'product_group' => trim((string) ($post['product_group'] ?? '')),
        'product_model' => trim((string) ($post['product_model'] ?? '')),
        'fab_number' => trim((string) ($post['fab_number'] ?? '')),
        'obligation' => trim((string) ($post['obligation'] ?? '')),
        'customer_name' => trim((string) ($post['customer_name'] ?? '')),
        'contact_person' => trim((string) ($post['contact_person'] ?? '')),
        'telephone_number' => trim((string) ($post['telephone_number'] ?? '')),
        'email_id' => trim((string) ($post['email_id'] ?? '')),
        'address_line1' => trim((string) ($post['address_line1'] ?? '')),
        'address_line2' => trim((string) ($post['address_line2'] ?? '')),
        'city_name' => trim((string) ($post['city'] ?? '')),
        'district_name' => trim((string) ($post['district'] ?? '')),
        'state_name' => trim((string) ($post['state'] ?? '')),
        'post_code' => trim((string) ($post['post_code'] ?? '')),
        'customer_group' => trim((string) ($post['customer_group'] ?? '')),
        'business_line' => trim((string) ($post['business_line'] ?? '')),
        'environment' => trim((string) ($post['environment'] ?? '')),
        'amc_type' => trim((string) ($post['amc_type'] ?? '')),
        'amc_type_remarks' => trim((string) ($post['amc_type_remarks'] ?? '')),
        'mode_of_call' => trim((string) ($post['mode_of_call'] ?? '')),
        'amc_start_date' => trim((string) ($post['amc_start_date'] ?? '')),
        'amc_end_date' => trim((string) ($post['amc_end_date'] ?? '')),
        'visit_start_date' => trim((string) ($post['visit_start_date'] ?? '')),
        'no_of_visits' => trim((string) ($post['no_of_visits'] ?? '')),
        'amc_value' => trim((string) ($post['amc_value'] ?? '')),
    ];
}

function amc_validate(array $data): ?string
{
    if ($data['customer_name'] === '') {
        return 'Customer Name is required.';
    }

    if ($data['product_group'] === '' || !in_array($data['product_group'], AMC_PRODUCT_GROUP_OPTIONS, true)) {
        return 'Please select a valid Product Group.';
    }

    if ($data['obligation'] === '' || !array_key_exists($data['obligation'], AMC_OBLIGATION_OPTIONS)) {
        return 'Please select a valid Obligation.';
    }

    if ($data['amc_type'] === '' || !array_key_exists($data['amc_type'], AMC_TYPE_OPTIONS)) {
        return 'Please select a valid AMC Type.';
    }

    if ($data['amc_type'] === 'S' && strlen($data['amc_type_remarks']) < 5) {
        return 'AMC Type Remarks cannot be blank for Standard AMC (min 5 characters).';
    }

    if ($data['telephone_number'] !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $data['telephone_number'])) {
        return 'Telephone Number is invalid.';
    }

    if ($data['email_id'] !== '' && !filter_var($data['email_id'], FILTER_VALIDATE_EMAIL)) {
        return 'Email Id must be a valid email address.';
    }

    foreach (['amc_start_date', 'amc_end_date', 'visit_start_date'] as $dateField) {
        if ($data[$dateField] === '' || !DateTime::createFromFormat('Y-m-d', $data[$dateField])) {
            return 'Please provide a valid date for all AMC dates.';
        }
    }

    if ($data['amc_end_date'] <= $data['amc_start_date']) {
        return 'AMC End Date must be after AMC Start Date.';
    }

    if ($data['visit_start_date'] < $data['amc_start_date'] || $data['visit_start_date'] > $data['amc_end_date']) {
        return 'Visit Start Date must fall within the AMC start/end dates.';
    }

    if (!is_numeric($data['no_of_visits']) || (int) $data['no_of_visits'] <= 0) {
        return 'Number of Visits must be a positive number.';
    }

    if (!is_numeric($data['amc_value']) || (float) $data['amc_value'] <= 0) {
        return 'AMC Value must be greater than 0.';
    }

    return null;
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
            contract_number, product_group, product_model, fab_number, obligation,
            customer_name, contact_person, telephone_number, email_id,
            address_line1, address_line2, city_name, post_code,
            district_name, state_name,
            customer_group, business_line, environment, amc_type, amc_type_remarks,
            mode_of_call, amc_start_date, amc_end_date, visit_start_date,
            no_of_visits, amc_value, dealer_name, status, created_by, username
        )
        VALUES
        (
            :contract_number, :product_group, :product_model, :fab_number, :obligation,
            :customer_name, :contact_person, :telephone_number, :email_id,
            :address_line1, :address_line2, :city_name, :post_code,
            :district_name, :state_name,
            :customer_group, :business_line, :environment, :amc_type, :amc_type_remarks,
            :mode_of_call, :amc_start_date, :amc_end_date, :visit_start_date,
            :no_of_visits, :amc_value, :dealer_name, :status, :created_by, :username
        )
        RETURNING id
    ");

    $stmt->bindValue(':product_group', $data['product_group']);
    $stmt->bindValue(':product_model', $data['product_model'] !== '' ? $data['product_model'] : null);
    $stmt->bindValue(':fab_number', $data['fab_number'] !== '' ? $data['fab_number'] : null);
    $stmt->bindValue(':obligation', $data['obligation']);
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
    $stmt->bindValue(':status', AMC_STATUS_ACTIVE);
    $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    $stmt->bindValue(':username', $username);

    // contract_number is generated from a MAX() lookup, which is not safe against
    // concurrent inserts — retry a few times with a fresh number on a unique-violation.
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
    $stmt = $conn->query("
        SELECT *
        FROM amc_contracts
        WHERE deleted_at IS NULL
        ORDER BY created_at DESC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function amc_find_by_id(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare('SELECT * FROM amc_contracts WHERE id = :id AND deleted_at IS NULL');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

function amc_visits_for_contract(PDO $conn, int $contractId): array
{
    $stmt = $conn->prepare('
        SELECT *
        FROM amc_visits
        WHERE amc_contract_id = :id
        ORDER BY visit_number ASC
    ');
    $stmt->bindValue(':id', $contractId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function amc_mark_visit_status(PDO $conn, int $visitId, int $contractId, string $status): bool
{
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
