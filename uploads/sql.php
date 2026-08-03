<?php
include '../pdo_obconn.php';

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
        WHERE table_name = 'plexecom_customer_units'
        ORDER BY ordinal_position
    ");
    
    $stmt->execute();
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get sample data
    $stmt = $obconn->prepare("
        SELECT *
        FROM plexecom_customer_units
where refno = 'E/UNITS/2607318797'
order by order_date DESC
        LIMIT 10
    ");

    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Merge output
    echo "<pre>";
    print_r([
        //"structure" => $structure,
        "data" => $data
    ]);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
die();


$sql = "SELECT COUNT(*) AS total_records FROM elgi_item_master";
$stmt = $obconn->prepare($sql);

if (!$stmt->execute()) {
    print_r($stmt->errorInfo());
    die();
}

$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Total Records: " . $result['total_records'];
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