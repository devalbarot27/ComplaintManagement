<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';

header('Content-Type: application/json; charset=utf-8');

$term = trim((string) ($_GET['q'] ?? $_GET['term'] ?? ''));

if ($term === '') {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $dpst = '90092';
    $stmt = $obconn->prepare("
        SELECT tplcode, tpldesc
        FROM product_master
        WHERE 
         dpst = :dpst
        and UPPER(TRIM(status)) = 'YES'
          AND UPPER(TRIM(valid)) = 'Y'
          AND (
                tplcode ILIKE :term
             OR tpldesc ILIKE :term
          )
        ORDER BY tplcode    
         LIMIT 25    
    "); 
    $stmt->bindValue(':term', '%' . $term . '%');
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo json_encode(['results' => []]);
    exit;
}

$results = [];

foreach ($rows as $row) {
    $code = trim((string) $row['tplcode']);
    $description = trim((string) $row['tpldesc']);
    $label = $code . ' - ' . $description;

    $results[] = [
        'id' => $code,
        'text' => $label,
        'tplcode' => $code,
        'tpldesc' => $description,
    ];
}

echo json_encode(['results' => $results]);
