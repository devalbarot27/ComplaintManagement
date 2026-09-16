<?php
require_once __DIR__ . '/pdo_obconn.php';
require_once __DIR__ . '/includes/pending_order_invoice_helpers.php';

$ordno = trim($_GET['ordno'] ?? '501003367');
$invoice = pending_invoice_fetch($dpconn, $ordno);

if ($invoice === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Order not found or invoice data is unavailable.';
    exit;
}

pending_invoice_generate_pdf($invoice);