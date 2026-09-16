<?php

/**
 * Copy user_master.customer_number into customer_code when customer_code is empty.
 *
 * CLI:
 *   php sync_user_customer_code.php
 */

require_once __DIR__ . '/pdo_obconn.php';
require_once __DIR__ . '/includes/user_helpers.php';

$isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server';

if (!$isCli) {
    require_once __DIR__ . '/includes/login_helpers.php';
    login_start_php_session();
    require_once __DIR__ . '/includes/admin_access_helpers.php';

    if (empty($_SESSION['usr_name'])) {
        header('Location: login.php');
        exit;
    }

    require_system_admin($obconn);
}

if (!isset($obconn) || !($obconn instanceof PDO)) {
    throw new RuntimeException('Database connection unavailable.');
}

user_ensure_schema($obconn);

$selectSql = '
    SELECT
        id,
        username,
        name,
        TRIM(customer_number) AS customer_number,
        TRIM(COALESCE(customer_code, \'\')) AS customer_code
    FROM user_master
    WHERE deleted_at IS NULL
      AND TRIM(COALESCE(customer_number, \'\')) <> \'\'
      AND TRIM(COALESCE(customer_code, \'\')) = \'\'
    ORDER BY id
';

$pending = $obconn->query($selectSql)->fetchAll(PDO::FETCH_ASSOC);

$updateSql = '
    UPDATE user_master
    SET customer_code = LEFT(TRIM(customer_number), 20),
        updated_at = CURRENT_TIMESTAMP
    WHERE deleted_at IS NULL
      AND TRIM(COALESCE(customer_number, \'\')) <> \'\'
      AND TRIM(COALESCE(customer_code, \'\')) = \'\'
';

$updated = (int) $obconn->exec($updateSql);

$summary = [
    'ok' => true,
    'matched' => count($pending),
    'updated' => $updated,
    'rows' => $pending,
];

if ($isCli) {
    echo 'Copied customer_number into empty customer_code.' . PHP_EOL;
    echo 'Matched: ' . $summary['matched'] . PHP_EOL;
    echo 'Updated: ' . $summary['updated'] . PHP_EOL;
    foreach ($pending as $row) {
        echo sprintf(
            '  id=%s username=%s customer_number=%s' . PHP_EOL,
            (string) ($row['id'] ?? ''),
            (string) ($row['username'] ?? ''),
            (string) ($row['customer_number'] ?? '')
        );
    }
    exit(0);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sync user customer_code</title>
</head>
<body>
    <h1>Sync user customer_code</h1>
    <p>Copied <code>customer_number</code> into empty <code>customer_code</code>.</p>
    <p>Matched: <?php echo (int) $summary['matched']; ?></p>
    <p>Updated: <?php echo (int) $summary['updated']; ?></p>
    <pre><?php echo htmlspecialchars(print_r($pending, true), ENT_QUOTES, 'UTF-8'); ?></pre>
</body>
</html>
