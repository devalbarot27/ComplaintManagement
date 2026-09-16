<?php

session_start();

include('pdo_obconn.php');

$userId = $_SESSION['usr_name'];


header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Order_Acknowledgement_" . date('YmdHis') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>
    <tr style='font-weight:bold;background:#D9EAD3;'>
        <th>Order No</th>
        <th>LN Reference</th>
        <th>Date</th>
        <th>Item Code</th>
        <th>Item Description</th>
        <th>Quantity</th>
        <th>Price</th>
        <th>Total Amount</th>
    </tr>
";

$sql = " SELECT order_no, ln_acknowledge, created_at, item_code, item_description, quantity, price, total_amount FROM tbl_vayu_orders WHERE created_by = :createdBy ORDER BY created_at DESC";


$stmt = $obconn->prepare($sql);
$stmt->bindParam(':createdBy', $userId, PDO::PARAM_STR);
$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['order_no']) . "</td>";
    echo "<td>" . htmlspecialchars($row['ln_acknowledge']) . "</td>";
    echo "<td>" . date('d-m-Y', strtotime($row['created_at'])) . "</td>";
    echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
    echo "<td>" . htmlspecialchars($row['item_description']) . "</td>";
    echo "<td align='right'>" . $row['quantity'] . "</td>";
    echo "<td align='right'>" . number_format($row['price'], 2) . "</td>";
    echo "<td align='right'>" . number_format($row['total_amount'], 2) . "</td>";
    echo "</tr>";
}

echo "</table>";
exit;