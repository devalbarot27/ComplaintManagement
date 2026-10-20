<?php

require_once __DIR__ . '/admin_access_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';
require_once __DIR__ . '/user_helpers.php';

/**
 * VAYU Engineers and Management filter their associated dealers.
 * System Admin filters every dealer.
 */
function ar_statement_user_can_filter_dealers(?PDO $conn = null): bool
{
    return ar_statement_user_sees_assigned_dealers($conn)
        || ar_statement_user_can_view_all_dealers();
}

/**
 * System Admin can filter every dealer.
 * Management users see only dealers assigned to them.
 */
function ar_statement_user_can_view_all_dealers(): bool
{
    return is_system_admin();
}

/**
 * VAYU Engineers and Management see dealers linked as their L1 or L2 approver.
 */
function ar_statement_user_sees_assigned_dealers(?PDO $conn = null): bool
{
    return is_management_user() || ar_statement_user_is_vayu_engineer($conn);
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

    foreach ([$obconn, $obconn] as $conn) {
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
    if ($cuno === '' || !ar_statement_user_can_filter_dealers($obconn)) {
        return false;
    }

    foreach (ar_statement_dealer_options_like_installed_base($obconn) as $option) {
        if (strcasecmp((string) $option['id'], $cuno) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * @return array<int, array{id: string, text: string, name: string}>
 */
/**
 * Same dealer list as Installed Base Capture Dealer Name:
 * a dealer user sees only themselves; every other role sees active dealer users.
 *
 * @return array<int, array{id: string, text: string, name: string}>
 */
function ar_statement_dealer_options_like_installed_base(PDO $obconn, string $search = ''): array
{
    require_once __DIR__ . '/complaint_assignment_helpers.php';

    $search = trim($search);
    $assignees = complaint_assign_options_for_current_creator($obconn)['assignees'] ?? [];
    $results = [];
    $seen = [];

    foreach ($assignees as $assignee) {
        $userId = (int) ($assignee['id'] ?? 0);
        $code = ar_statement_user_dealer_code($obconn, $userId);
        if ($code === '' || isset($seen[strtolower($code)])) {
            continue;
        }

        $name = complaint_assignee_option_value($assignee);
        $text = $name !== '' ? $name : $code;
        if ($search !== '') {
            $haystack = strtolower($text . ' ' . $code);
            if (strpos($haystack, strtolower($search)) === false) {
                continue;
            }
        }

        $seen[strtolower($code)] = true;
        $results[] = [
            'id' => $code,
            'text' => $text,
            'name' => $text,
        ];
    }

    usort($results, static function (array $left, array $right): int {
        return strcasecmp($left['text'], $right['text']);
    });

    return $results;
}

function ar_statement_user_dealer_code(PDO $conn, int $userId): string
{
    if ($userId <= 0) {
        return '';
    }

    $stmt = $conn->prepare('
        SELECT
            TRIM(COALESCE(customer_code, \'\')) AS customer_code,
            TRIM(COALESCE(customer_number, \'\')) AS customer_number
        FROM user_master
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    foreach (['customer_code', 'customer_number'] as $column) {
        $code = trim((string) ($row[$column] ?? ''));
        if ($code !== '') {
            return $code;
        }
    }

    return '';
}

function ar_statement_search_dealers(?PDO $dpconn, PDO $obconn, string $search, int $limit = 50): array
{
    unset($dpconn, $limit);

    if (!ar_statement_user_can_filter_dealers($obconn)) {
        return [];
    }

    return ar_statement_dealer_options_like_installed_base($obconn, $search);
}

function ar_statement_resolve_cuno(PDO $obconn): string
{
    
    $sessionCuno = trim((string) (
        $_SESSION['customer_number_vayu']
        ?? $_SESSION['cuno']
        ?? $_GET['dealer']
        ?? ''
    ));

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

    if (ar_statement_user_sees_assigned_dealers($obconn)) {
        $assigned = ar_statement_assigned_dealer_codes($obconn);
        if (count($assigned) === 1 && ar_statement_is_allowed_cuno($obconn, $assigned[0])) {
            return $assigned[0];
        }
    }

    return '';
}