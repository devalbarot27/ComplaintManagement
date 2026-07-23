<?php

include('pdo_obconn.php');

header('Content-Type: application/json; charset=UTF-8');

$xmlString = file_get_contents("php://input");

// Log incoming XML
error_log("===== Incoming Sales Order XML =====");
error_log($xmlString);
error_log("===================================");

$xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NONET);

if ($xml === false) {
    echo json_encode([
        "status" => 500,
        "message" => "Invalid XML"
    ]);
    exit;
}

$orderNumber = (string)$xml->ordernumber;
$aoNumber = (string)$xml->elgi_aonumber;
$commitmentDate = (string)$xml->elgi_commitmentdate;
$revisedCommitmentDate = (string)$xml->elgi_revisedcommitmentdate;
$delivery = (string)$xml->elgi_delivery;

if (strpos($aoNumber, '-') !== false) {
    $aoNumber = explode('-', $aoNumber, 2)[0];
}

$orderDate = !empty($commitmentDate)
    ? date('d.m.Y', strtotime($commitmentDate))
    : '';

$erpln = 'Y';

try {

    $upd = $obconn->prepare("
        UPDATE plexecom_customer_units
        SET
            order_number = :orderNo,
            order_date = :orderDate,
            erpln = :erpln
        WHERE refno = :refno
    ");

    $upd->bindValue(':orderNo', $aoNumber);
    $upd->bindValue(':orderDate', $orderDate);
    $upd->bindValue(':erpln', $erpln);
    $upd->bindValue(':refno', $orderNumber);

    $upd->execute();

    $xmlNo = str_replace('/', '-', $orderNumber);

    $folder = __DIR__ . DIRECTORY_SEPARATOR . 'xml';

    if (!is_dir($folder)) {

        if (!mkdir($folder, 0755, true) && !is_dir($folder)) {
            throw new RuntimeException('Unable to create XML directory.');
        }
    }
    $file = $folder . DIRECTORY_SEPARATOR . $xmlNo . '.xml';


    if (file_put_contents($file, $xmlString, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write XML.');
    }


    chmod($file, 0644);

    echo json_encode([
        "status" => 200,
        "message" => "Received Successfully"
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
} catch (PDOException $e) {

    error_log("Database Error: " . $e->getMessage());

    echo json_encode([
        "status" => 500,
        "message" => $e->getMessage()
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
