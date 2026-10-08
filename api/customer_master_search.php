<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/customer_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

admin_api_require_system_admin($obconn);

header('Content-Type: application/json; charset=utf-8');

$search = trim((string) ($_GET['q'] ?? $_GET['term'] ?? $_POST['q'] ?? $_POST['term'] ?? ''));
$results = customer_master_search($obconn, $search, 50);

api_json_echo(['results' => $results]);