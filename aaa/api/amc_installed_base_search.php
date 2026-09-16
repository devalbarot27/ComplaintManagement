<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/amc_helpers.php';

rbac_require_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

$term = trim((string) ($_GET['q'] ?? $_GET['term'] ?? ''));

echo json_encode([
    'results' => amc_search_installed_base($obconn, $term),
]);