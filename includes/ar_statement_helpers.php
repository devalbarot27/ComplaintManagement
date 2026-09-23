<?php

require_once __DIR__ . '/admin_access_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';
require_once __DIR__ . '/user_helpers.php';

/**
 * VAYU Engineers (ELGi Engineer) and Business Head / Manager / System Admin
 * can filter AR statements by dealer name.
 */
function ar_statement_user_can_filter_dealers(?PDO $conn = null): bool
{
    return ar_statement_user_is_vayu_engineer($conn)
        || ar_statement_user_can_view_all_dealers();
}

/**
 * Business Head / Manager (Management) and System Admin see every dealer.
 */
function ar_statement_user_can_view_all_dealers(): bool
{
    return is_management_user() || is_system_admin();
}

/**
 * VAYU Engineers are stored as ELGi Engineer (role 3).
 */
function ar_statement_user_is_vayu_engineer(?PDO $conn = null): bool
{
    if (is_elgi_engineer_user()) {
        return true;
    }

    if ($conn === null) {
        return false;
    }

    $roleName = strtolower(trim(user_role_label($conn, current_user_role())));

    return in_array($roleName, ['elgi engineer', 'vayu engineer', 'vayu engineers'], true);
}

/**
 * Distinct dealer codes assigned to the current user as L1 or L2 approver,
 * plus the engineer's own mapped customer code.
 *
 * @return array<int, string>
 */
function ar_statement_assigned_dealer_codes(PDO $conn): array
{
    $userId = (int) (current_user_id($conn) ?? 0);
    $username = current_username();
    if ($userId <= 0 && $username !== '') {
        $idStmt = $conn->prepare('
            SELECT id
            FROM user_master
            WHERE deleted_at IS NULL
              AND LOWER(TRIM(username)) = LOWER(TRIM(:username))
            LIMIT 1
        ');
        $idStmt->bindValue(':username', $username);
        $idStmt->execute();
        $userId = (int) $idStmt->fetchColumn();
    }

    $codes = [];

    $ownStmt = $conn->prepare("
        SELECT
            TRIM(COALESCE(customer_code, '')) AS customer_code,
            TRIM(COALESCE(customer_number, '')) AS customer_number
        FROM user_master
        WHERE deleted_at IS NULL
          AND (
                id = :uid
                OR LOWER(TRIM(username)) = LOWER(TRIM(:username))
          )
        LIMIT 1
    ");
    $ownStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $ownStmt->bindValue(':username', $username);
    $ownStmt->execute();
    $own = $ownStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    foreach (['customer_code', 'customer_number'] as $column) {
        $code = trim((string) ($own[$column] ?? ''));
        if ($code !== '') {
            $codes[] = $code;
            break;
        }
    }

    if ($userId > 0) {
        user_ensure_schema($conn);
        $stmt = $conn->prepare("
            SELECT DISTINCT TRIM(
                CASE
                    WHEN TRIM(COALESCE(customer_code, '')) <> '' THEN customer_code
                    ELSE customer_number
                END
            ) AS cuno
            FROM user_master
            WHERE deleted_at IS NULL
              AND (level_1_approver_id = :uid OR level_2_approver_id = :uid)
              AND (
                    TRIM(COALESCE(customer_code, '')) <> ''
                    OR TRIM(COALESCE(customer_number, '')) <> ''
              )
            ORDER BY 1
        ");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code = trim((string) ($row['cuno'] ?? ''));
            if ($code !== '') {
                $codes[] = $code;
            }
        }
    }

    return array_values(array_unique($codes));
}

function ar_statement_dealer_label(string $cuno, string $cuname = ''): string
{
    $cuno = trim($cuno);
    $cuname = trim($cuname);
    if ($cuno === '') {
        return '';
    }
    if ($cuname !== '') {
        return $cuname . ' - [' . $cuno . ']';
    }

    return $cuno;
}

/**
 * @return array{code: string, name: string, text: string}|null
 */
function ar_statement_dealer_from_row(array $row, string $codeFallback = ''): ?array
{
    $code = trim((string) ($row['cuno'] ?? $codeFallback));
    if ($code === '') {
        return null;
    }

    $name = trim((string) ($row['cuname'] ?? ''));

    return [
        'code' => $code,
        'name' => $name !== '' ? $name : $code,
        'text' => ar_statement_dealer_label($code, $name),
    ];
}

/**
 * @return array{code: string, name: string, text: string}|null
 */
function ar_statement_dealer_get(?PDO $dpconn, PDO $obconn, string $cuno): ?array
{
    $cuno = trim($cuno);
    if ($cuno === '') {
        return null;
    }

    $sql = '
        SELECT TRIM(cuno) AS cuno, TRIM(cuname) AS cuname
        FROM customer_master
        WHERE TRIM(cuno) = :cuno
        LIMIT 1
    ';

    foreach ([$dpconn, $obconn] as $conn) {
        if (!$conn instanceof PDO) {
            continue;
        }
        try {
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':cuno', $cuno);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return ar_statement_dealer_from_row($row, $cuno);
            }
        } catch (Throwable $e) {
            // customer_master may exist on only one connection.
        }
    }

    return [
        'code' => $cuno,
        'name' => $cuno,
        'text' => $cuno,
    ];
}

/**
 * @return array<int, array{id: string, text: string, name: string}>
 */
function ar_statement_search_all_dealers(?PDO $dpconn, PDO $obconn, string $search, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    $search = trim($search);
    $sql = '
        SELECT TRIM(cuno) AS cuno, TRIM(cuname) AS cuname
        FROM customer_master
        WHERE length(TRIM(cuno)) > 0
    ';
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (
            LOWER(cuno) LIKE LOWER(:search)
            OR LOWER(COALESCE(cuname, \'\')) LIKE LOWER(:search)
        )';
        $params[':search'] = '%' . $search . '%';
    }
    $sql .= ' ORDER BY cuname, cuno LIMIT ' . (int) $limit;

    foreach ([$dpconn, $obconn] as $conn) {
        if (!$conn instanceof PDO) {
            continue;
        }
        try {
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            return ar_statement_map_dealer_results($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
            // Try the other connection.
        }
    }

    return [];
}

/**
 * @param array<int, string> $codes
 * @return array<int, array{id: string, text: string, name: string}>
 */
function ar_statement_dealers_for_codes(?PDO $dpconn, PDO $obconn, array $codes, string $search = ''): array
{
    $search = trim($search);
    $results = [];
    foreach ($codes as $code) {
        $dealer = ar_statement_dealer_get($dpconn, $obconn, $code);
        if ($dealer === null) {
            continue;
        }
        if ($search !== '') {
            $haystack = strtolower($dealer['name'] . ' ' . $dealer['code'] . ' ' . $dealer['text']);
            if (strpos($haystack, strtolower($search)) === false) {
                continue;
            }
        }
        $results[] = [
            'id' => $dealer['code'],
            'text' => $dealer['text'],
            'name' => $dealer['name'],
        ];
    }

    usort($results, static function (array $a, array $b): int {
        return strcasecmp($a['text'], $b['text']);
    });

    return $results;
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array{id: string, text: string, name: string}>
 */
function ar_statement_map_dealer_results(array $rows): array
{
    $results = [];
    foreach ($rows as $row) {
        $dealer = ar_statement_dealer_from_row($row);
        if ($dealer === null) {
            continue;
        }
        $results[] = [
            'id' => $dealer['code'],
            'text' => $dealer['text'],
            'name' => $dealer['name'],
        ];
    }

    return $results;
}

function ar_statement_is_allowed_cuno(PDO $obconn, string $cuno): bool
{
    $cuno = trim($cuno);
    if ($cuno === '') {
        return false;
    }

    if (ar_statement_user_can_view_all_dealers()) {
        return true;
    }

    if (ar_statement_user_is_vayu_engineer($obconn)) {
        foreach (ar_statement_assigned_dealer_codes($obconn) as $assigned) {
            if (strcasecmp($assigned, $cuno) === 0) {
                return true;
            }
        }
    }

    return false;
}

/**
 * @return array<int, array{id: string, text: string, name: string}>
 */
function ar_statement_search_dealers(?PDO $dpconn, PDO $obconn, string $search, int $limit = 50): array
{
    if (ar_statement_user_can_view_all_dealers()) {
        return ar_statement_search_all_dealers($dpconn, $obconn, $search, $limit);
    }

    if (ar_statement_user_is_vayu_engineer($obconn)) {
        $results = ar_statement_dealers_for_codes(
            $dpconn,
            $obconn,
            ar_statement_assigned_dealer_codes($obconn),
            $search
        );
        if ($results !== []) {
            return $results;
        }

        return ar_statement_search_all_dealers($dpconn, $obconn, $search, $limit);
    }

    return [];
}

function ar_statement_resolve_cuno(PDO $obconn): string
{
    $sessionCuno = trim((string) ($_SESSION['customer_number_vayu'] ?? ''));

    if (!ar_statement_user_can_filter_dealers($obconn)) {
        return $sessionCuno;
    }

    if (array_key_exists('dealer', $_GET)) {
        $requested = trim((string) $_GET['dealer']);
        if ($requested !== '' && ar_statement_is_allowed_cuno($obconn, $requested)) {
            return $requested;
        }

        return '';
    }

    if ($sessionCuno !== '' && ar_statement_is_allowed_cuno($obconn, $sessionCuno)) {
        return $sessionCuno;
    }

    if (ar_statement_user_is_vayu_engineer($obconn)) {
        $assigned = ar_statement_assigned_dealer_codes($obconn);
        if (count($assigned) === 1) {
            return $assigned[0];
        }
    }

    return '';
}
