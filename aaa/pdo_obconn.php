<?php
$hostDp = 'localhost';
$dbDp = 'dealerportal';
$userDp = 'postgres';
$passwordDp = '123456789';
try {
 $dsnDp = "pgsql:host=$hostDp;port=5432;dbname=$dbDp";
 $dpconn = new PDO($dsnDp, $userDp, $passwordDp, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
 echo "Connection failed: " . $e->getMessage();
}



$hostOb = 'localhost';
$dbOb = 'orderbooking';
$dbOb = 'complaint_management';
$userOb = 'postgres';
$passwordOb = '123456789';
try {
 $dsnOb = "pgsql:host=$hostOb;port=5432;dbname=$dbOb";
 $obconn = new PDO($dsnOb, $userOb, $passwordOb, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
 echo "Connection failed: " . $e->getMessage();
}