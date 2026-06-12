<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/order_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$data = order_from_post($_POST);
$error = order_validate_create($data);

if ($error !== null) {
    http_response_code(422);
    echo json_encode(['error' => $error]);
    exit;
}

try {
    $order = order_create($obconn, $data, 1);
    echo json_encode([
        'success' => true,
        'message' => 'Order created successfully.',
        'order' => order_to_select2_result($order),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create order.']);
}
