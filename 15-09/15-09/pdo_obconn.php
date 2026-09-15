<?php
$hostDp = 'localhost';
$dbDp = 'dealerportal';
$userDp = 'postgres';
$passwordDp = '';

try {
    $dsnDp = "pgsql:host=$hostDp;port=5432;dbname=$dbDp";
    $dpconn = new PDO($dsnDp, $userDp, $passwordDp, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

$hostOb = 'localhost';
$dbOb = 'orderbooking';
$userOb = 'postgres';
$passwordOb = '';
try {
    $dsnOb = "pgsql:host=$hostOb;port=5432;dbname=$dbOb";
    $obconn = new PDO($dsnOb, $userOb, $passwordOb, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}


// $hostOb = '172.16.33.132';
// $dbOb = 'dealerportal';
// $userOb = 'postgres';
// $passwordOb = '';
// try {
//     $dsnOb = "pgsql:host=$hostOb;port=5432;dbname=$dbOb";
//     $dpconn1 = new PDO($dsnOb, $userOb, $passwordOb, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
// } catch (PDOException $e) {
//     echo "Connection failed: " . $e->getMessage();
// }



$hostOb = '172.16.33.130';
$dbOb = 'crm';
$userOb = 'postgres';
$passwordOb = '';
try {
    $dsnOb = "pgsql:host=$hostOb;port=5432;dbname=$dbOb";
    $ccsconn = new PDO($dsnOb, $userOb, $passwordOb, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
