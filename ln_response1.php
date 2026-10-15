<?php

include('pdo_obconn.php');

header('Content-Type: application/json; charset=UTF-8');

$xmlString = '<?xml version="1.0" encoding="UTF-8"?>
<SyncElgiSalesOrderAckXML
    xmlns="http://schema.infor.com/InforOAGIS/2">
    <ApplicationArea>
        <Sender>
            <LogicalID>lid://infor.ln.ho_ln_india_app_401_ecnew_sep2025</LogicalID>
            <ComponentID>erp</ComponentID>
            <ConfirmationCode>OnError</ConfirmationCode>
        </Sender>
        <CreationDateTime>2026-10-14T09:28:17Z</CreationDateTime>
        <BODID>infor-nid:ELGI_PRD:401:D_Y0001:YUU000084:?SalesOrder&amp;verb=Sync</BODID>
    </ApplicationArea>
    <DataArea>
        <Sync>
            <TenantID>ELGI_PRD</TenantID>
            <AccountingEntityID>401</AccountingEntityID>
            <LocationID>D_Y0001</LocationID>
            <ActionCriteria>
                <ActionExpression actionCode="Add"/>
            </ActionCriteria>
        </Sync>
        <ElgiSalesOrderAckXML>
            <ordernumber>E/UNITS/2610136977</ordernumber>
            <elgi_aonumber>YUU000084-401</elgi_aonumber>
            <elgi_commitmentdate>2026-10-15T09:28:00Z</elgi_commitmentdate>
            <elgi_revisedcommitmentdate>2026-10-15T09:28:00Z</elgi_revisedcommitmentdate>
            <elgi_delivery>ADD139057</elgi_delivery>
        </ElgiSalesOrderAckXML>
    </DataArea>
</SyncElgiSalesOrderAckXML>';

error_log("===== Incoming Sales Order XML =====");
error_log($xmlString);
error_log("===================================");

libxml_use_internal_errors(true);

$xml = simplexml_load_string(
    $xmlString,
    'SimpleXMLElement',
    LIBXML_NONET
);

if ($xml === false) {
    error_log("Invalid XML received");

    http_response_code(400);
    echo json_encode([
        "status" => 400,
        "message" => "Invalid XML"
    ]);
    exit;
}

try {
    // Read the root namespace
    $namespaces = $xml->getNamespaces(true);
    $namespace = $namespaces[''] ?? '';

    // Access DataArea and its child elements
    $root = $xml->children($namespace);
    $dataArea = $root->DataArea;
    $data = $dataArea->children($namespace);
    $orderData = $data->ElgiSalesOrderAckXML;

    // Read order acknowledgement fields
    $orderNumber = trim((string)$orderData->ordernumber);
    $aoNumber = trim((string)$orderData->elgi_aonumber);
    $commitmentDate = trim(
        (string)$orderData->elgi_commitmentdate
    );
    $revisedCommitmentDate = trim(
        (string)$orderData->elgi_revisedcommitmentdate
    );
    $delivery = trim((string)$orderData->elgi_delivery);

    // Validate mandatory fields
    if ($orderNumber === '' || $aoNumber === '') {
        throw new RuntimeException(
            'Order number or AO number is missing from XML.'
        );
    }

    // Remove suffix after hyphen
    if (strpos($aoNumber, '-') !== false) {
        $aoNumber = explode('-', $aoNumber, 2)[0];
    }

    $orderDate = $commitmentDate !== ''
        ? date('d.m.Y', strtotime($commitmentDate))
        : '';

    $erpln = 'Y';

    // Update database
    $upd = $obconn->prepare("
        UPDATE plexecom_customer_units
        SET
            order_number = :orderNo,
            erpln = :erpln
        WHERE refno = :refno
    ");

    $upd->bindValue(':orderNo', $aoNumber);
    $upd->bindValue(':erpln', $erpln);
    $upd->bindValue(':refno', $orderNumber);

    $upd->execute();

    error_log(
        "Updated rows: " . $upd->rowCount()
    );

    // Save original XML
    $xmlNo = str_replace('/', '-', $orderNumber);
    $folder = __DIR__ . DIRECTORY_SEPARATOR . 'xml';

    if (!is_dir($folder)) {
        throw new RuntimeException(
            'XML directory does not exist: ' . $folder
        );
    }

    if (!is_writable($folder)) {
        throw new RuntimeException(
            'PHP cannot write to the XML directory.'
        );
    }

    $file = $folder . DIRECTORY_SEPARATOR . $xmlNo . '.xml';

    if (file_put_contents($file, $xmlString, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write XML file.');
    }

    chmod($file, 0644);

    echo json_encode([
        "status" => 200,
        "message" => "Received Successfully"
    ]);

} catch (Throwable $e) {
    error_log("LN Response Error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "status" => 500,
        "message" => "Failed to process sales order acknowledgement."
    ]);
}
