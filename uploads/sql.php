<?php
include '../pdo_obconn.php';


$sql = "UPDATE user_master
SET
    level_1_approval = TRUE,
    level_2_approval = TRUE,
    updated_at = CURRENT_TIMESTAMP
WHERE id = 14
  AND deleted_at IS NULL;";

$stmt = $obconn->prepare($sql);

if ($stmt->execute()) {
    echo "Column added successfully (or already exists).";
} else {
    print_r($stmt->errorInfo());
}
die();


try {
    // 1. Get table structure
    $stmt = $obconn->prepare("
        SELECT 
            column_name,
            data_type,
            character_maximum_length,
            is_nullable,
            column_default
        FROM information_schema.columns
        WHERE table_name = 'service_claims'
        ORDER BY ordinal_position
    ");
    
    $stmt->execute();
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get sample data
    $stmt = $obconn->prepare("
        SELECT *
        FROM service_claims
        LIMIT 10
    ");

    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Merge output
    echo "<pre>";
    print_r([
        "structure" => $structure,
        "data" => $data
    ]);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
die();



$sql = "UPDATE user_master
SET
    level_1_approval = TRUE,
    level_2_approval = TRUE,
    updated_at = CURRENT_TIMESTAMP
WHERE id = 14
  AND deleted_at IS NULL;";

$stmt = $obconn->prepare($sql);

if ($stmt->execute()) {
    echo "Column added successfully (or already exists).";
} else {
    print_r($stmt->errorInfo());
}
die();

$sql = "SELECT * FROM elgi_item_master Limit 10";
$stmt = $obconn->prepare($sql);

if (!$stmt->execute()) {
    print_r($stmt->errorInfo());
    die();
}

$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($result);
die();
/*
$stmt = $obconn->prepare($sql);
$stmt->execute();

$fields = [];

for ($i = 0; $i < $stmt->columnCount(); $i++) {
    $meta = $stmt->getColumnMeta($i);
    $fields[] = $meta['name'];
echo $meta['name'].'<br>';
}




die();
*/
/*
$sql = "DELETE FROM notifications";

$stmt = $obconn->prepare($sql);

if (!$stmt->execute()) {
    echo "<pre>";
    print_r($stmt->errorInfo());
    exit;
}

echo "Deleted rows: " . $stmt->rowCount();

$sql = "DELETE FROM complaint_nudge_logs";

$stmt = $obconn->prepare($sql);

if (!$stmt->execute()) {
    echo "<pre>";
    print_r($stmt->errorInfo());
    exit;
}

echo "Deleted rowsss: " . $stmt->rowCount();
die('_');
*/

$sql = "SELECT * FROM ln_invoice_details LIMIT 10";

$stmt = $dpconn->prepare($sql);

if (!$stmt->execute()) {
    echo "<pre>";
    print_r($stmt->errorInfo());
    exit;
}

$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($result);
exit;

// Select query
$sql = "SELECT * FROM service_log_part_replacements";
$stmt = $obconn->prepare($sql);

if (!$stmt->execute()) {
    print_r($stmt->errorInfo());
    die();
}

$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($result);
die();/*
$stmt = $obconn->prepare("SELECT * FROM tbl_vayu_orders_header LIMIT 500");

if (!$stmt->execute()) {
    print_r($stmt->errorInfo());
    die();
}

$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($result);
die();
*/


/* 1. Create industry_segments */


/*
$stmt = $obconn->prepare("
ALTER TABLE complaint_closures
    ADD COLUMN IF NOT EXISTS customer_feedback VARCHAR(100);");
$stmt->execute();
*/



try {
    // 1. Get table structure
    $stmt = $dpconn->prepare("
        SELECT 
            column_name,
            data_type,
            character_maximum_length,
            is_nullable,
            column_default
        FROM information_schema.columns
        WHERE table_name = 'pendingordersnew'
        ORDER BY ordinal_position
    ");
    
    $stmt->execute();
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get sample data
    $stmt = $dpconn->prepare("
        SELECT *
        FROM pendingordersnew
        LIMIT 10
    ");

    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Merge output
    echo "<pre>";
    print_r([
        "structure" => $structure,
        "data" => $data
    ]);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
die();

?>