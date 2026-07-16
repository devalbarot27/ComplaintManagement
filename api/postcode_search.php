<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';

header('Content-Type: application/json; charset=utf-8');

$term = trim((string) ($_GET['q'] ?? $_GET['term'] ?? ''));

if ($term === '' || !preg_match('/^\d{1,6}$/', $term)) {
    echo json_encode(['results' => []]);
    exit;
}

$stmt = $obconn->prepare("
    SELECT postcode, city, district, state,state_code
    FROM postcodes
    WHERE postcode LIKE :term
    ORDER BY postcode, city
    LIMIT 25
");

$stmt->bindValue(':term', $term . '%');
$stmt->execute();

$result_arr = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

    $postcode = trim($row['postcode']);
    $city     = trim($row['city']);
    $district = trim($row['district']);
    $state    = trim($row['state']);
    $state_code    = trim($row['state_code']);

    $result_arr[] = [
        'id' => $postcode,
        'text' => $postcode . ' - ' . $city . ', ' . $state,
        'city' => $city,
        'district' => $district,
        'state' => $state,
        'state_code' => $state_code,
    ];

}
echo json_encode(['results' => $result_arr]);
exit;