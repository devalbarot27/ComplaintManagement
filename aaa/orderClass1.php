<?php
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('pdo_obconn.php');
require_once __DIR__ . '/includes/admin_access_helpers.php';
class orderClass
{

    private $userId;
    private $obconn;
    private $dpconn;
    private $customer_code;

    public function __construct($obconn, $dpconn)
    {

        $this->obconn = $obconn;
        $this->dpconn = $dpconn;
        $this->userId = $_SESSION['usr_name'];
        $this->customer_code = $_SESSION['customer_number_vayu'];
    }

    public function getCartCount()
    {
        $cnt = $this->obconn->prepare("SELECT * FROM tbl_vayu_item_master WHERE status=0 AND created_by=:createdBy");
        $cnt->bindParam(':createdBy', $this->customer_code, PDO::PARAM_STR);
        $cnt->execute();
        return $cnt->rowCount();
    }


    public function searchItems()
    {
        $search = htmlspecialchars($_POST['search'] ?? '', ENT_QUOTES, 'UTF-8');

        $getItem = $this->obconn->prepare("SELECT tplcode, tpldesc FROM product_master_vayu WHERE (tplcode ILIKE :search OR tpldesc ILIKE :search) ORDER BY tplcode LIMIT 20");
        $searchTerm = "%{$search}%";
        $getItem->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        $getItem->execute();
        $data = [];
        while ($row = $getItem->fetch(PDO::FETCH_ASSOC)) {
            $data[] = [
                'id' => $row['tplcode'],
                'text' => $row['tplcode'] . ' - ' . $row['tpldesc']
            ];
        }

        return json_encode($data);
    }
    public function getPrice()
    {
        $item = htmlspecialchars($_POST['item'] ?? '', ENT_QUOTES, 'UTF-8');
        $dpst = htmlspecialchars($_POST['dpst'] ?? '', ENT_QUOTES, 'UTF-8');

        $stmt = $this->obconn->prepare("
        SELECT cos
        FROM product_master_vayu
        WHERE tplcode = :item
        AND dpst = :dpst
        LIMIT 1
    ");

        $stmt->bindParam(':item', $item, PDO::PARAM_STR);
        $stmt->bindParam(':dpst', $dpst, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode([
                'status' => true,
                'price'  => htmlspecialchars((float)$row['cos'] ?? '', ENT_QUOTES, 'UTF-8'),
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } else {
            echo json_encode([
                'status' => false,
                'price'  => 0
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
        exit;
    }

    public function itemSync()
    {
        $xml = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" 
        xmlns:sal="http://www.infor.com/businessinterface/CustomerPrice">
            <soapenv:Header>
                <sal:Activation>
                    <company>1100</company>
                </sal:Activation>
            </soapenv:Header>
            <soapenv:Body>
                <sal:SimulatePrice>
                    <SimulatePriceRequest>
                        <ControlArea>
                            <processingScope>request</processingScope>
                            <SuppressNillable>false</SuppressNillable>
                        </ControlArea>
                        <DataArea>
                            <CustomerPrice>
                                <customerCode>BP0001127</customerCode>
                                <itemCode>S016701</itemCode>
                                <priceDatetime>2025-03-28</priceDatetime>
                                <UserArea/>
                            </CustomerPrice>
                        </DataArea>
                    </SimulatePriceRequest>
                </sal:SimulatePrice>
            </soapenv:Body>
        </soapenv:Envelope>';
        $beaerToken = $this->getBearerToken();
        $url = 'https://mingle-ionapi.eu1.inforcloudsuite.com/ELGI_TST/LN/c4ws/services/CustomerPrice';

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml;charset=utf-8',
                'Authorization: Bearer ' . $beaerToken,
                'Content-Length: ' . strlen($xml)
            ]
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        unset($ch);

        $xml = simplexml_load_string($response);

        $result = $xml->xpath('//CustomerPrice');

        try {

            foreach ($result as $priceInfo) {

                $itemCode = trim((string)$priceInfo->itemCode);
                $customer = trim((string)$priceInfo->customerCode);

                $chkData = $this->obconn->prepare("SELECT 1 FROM tbl_vayu_item_master WHERE item_code = :itemCode AND status = 1");
                $chkData->bindParam(':itemCode', $itemCode, PDO::PARAM_STR);
                $chkData->execute();
                if ($chkData->rowCount() == 0) {
                    $insData = $this->obconn->prepare("INSERT INTO tbl_vayu_item_master(item_code,item_description)VALUES(:itemCode,:itemDesc)");
                    $insData->bindParam(':itemCode', $itemCode, PDO::PARAM_STR);
                    $insData->bindParam(':itemDesc', $customer, PDO::PARAM_STR);
                    $insData->execute();
                } else {
                    $upTime = date('d.m.Y H:i:S');
                    $upData = $this->obconn->prepare("UPDATE tbl_vayu_item_master SET item_description = :itemDesc WHERE item_code = :itemCode");
                    $upData->bindParam(':itemCode', $itemCode, PDO::PARAM_STR);
                    $upData->bindParam(':itemDesc', $customer, PDO::PARAM_STR);
                    $upData->bindParam(':updated_at', $upTime, PDO::PARAM_STR);
                    $upData->execute();
                }
            }
            return 1;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return 0;
        }
    }


    private function getBearerToken()
    {

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://mingle-sso.eu1.inforcloudsuite.com:443/ELGI_TST/as/token.oauth2',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'token_name'    => 'ELGi_TST',
                'grant_type'    => 'password',
                'redirect_uri'  => 'https://mingle-sso.eu1.inforcloudsuite.com:443/ELGI_TST/as/token.oauth2',
                'client_id'     => 'ELGI_TST~p4V8O-Ozk_oHnB1Sm8gADwT6AaqWwCelyMy6cEwaiHI',
                'client_secret' => 'JZP5EFh1MNO1KbV4wwDLflbL-2tyJEV2cyyhC78rmffsRyvAJqM5Jt5mdkI5VkSaR3AYpj_Mq0BJ1bjs5rgcHQ',
                'username'      => 'ELGI_TST#09VzG5W5QzUuK0nA2IU0uOqrqZ0YLRdMvc4_n9RVyCzdHesvBBRx65ZoDaE6mAxkj3hYw_dTYuhPfgPG4TaUaA',
                'password'      => 'YVCL-70dqTapkmhY-V_8Clfqpse09KP8YyUST9xs3cNBQgjQXHapCKw0ZmRor-HVjsTqK6TCMfctfOwnJPKkeA',
                'scope'         => ''
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'cURL Error: ' . curl_error($ch);
        }
        $data = json_decode($response, true);
        unset($ch);
        return $data['access_token'] ?? '';
    }

    public function addItemCart()
    {
        try {
            $item_code = filter_input(INPUT_POST, 'item', FILTER_SANITIZE_SPECIAL_CHARS);
            $qty = filter_input(INPUT_POST, 'qty', FILTER_VALIDATE_FLOAT);
            $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
            if (empty($item_code) || $qty <= 0 || $price <= 0) {
                return 0;
            }
            $stmt = $this->obconn->prepare("SELECT qty FROM tbl_vayu_cartitems WHERE created_by = :createdBy AND status = 0 AND item_code = :item_code");
            $stmt->bindValue(':createdBy', $this->customer_code);
            $stmt->bindValue(':item_code', $item_code);
            $stmt->execute();
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                $totalAmount = $qty * $price;
                $getDesc = $this->obconn->prepare("SELECT tpldesc FROM product_master_vayu WHERE tplcode=:tplcode");
                $getDesc->bindParam(":tplcode", $item_code);
                $getDesc->execute();
                $fetDesc = $getDesc->fetch(PDO::FETCH_ASSOC);
                $desc = $fetDesc['tpldesc'] ?? '-';
                $insert = $this->obconn->prepare("INSERT INTO tbl_vayu_cartitems(item_code,item_name,price,qty,total_amount,created_by)VALUES(:item_code,:item_name,:price,:qty,:total_amount,:created_by)");
                $insert->bindValue(':item_code', $item_code);
                $insert->bindValue(':item_name', $desc);
                $insert->bindValue(':price', $price);
                $insert->bindValue(':qty', $qty);
                $insert->bindValue(':total_amount', $totalAmount);
                $insert->bindValue(':created_by', $this->customer_code);
                return $insert->execute() ? 1 : 0;
            } else {
                $updatedQty = $item['qty'] + $qty;
                $totalAmount = $updatedQty * $price;
                $update = $this->obconn->prepare("UPDATE tbl_vayu_cartitems SET qty = :qty,price = :price,total_amount = :totalAmount WHERE created_by = :createdBy AND status = 0 AND item_code = :item_code");
                $update->bindValue(':qty', $updatedQty);
                $update->bindValue(':price', $price);
                $update->bindValue(':totalAmount', $totalAmount);
                $update->bindValue(':createdBy', $this->customer_code);
                $update->bindValue(':item_code', $item_code);
                return $update->execute() ? 1 : 0;
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return 0;
        }
    }

    private function freightPercentage(): float
    {
        return 4;
    }



    private function computeCartLineGstAmounts(float $unitPrice, float $qty): array
    {
        // Total Price = unit � qty (excludes freight). Freight = 4% of Total Price.
        $lineBase = round($unitPrice * $qty, 2);
        $freight = round(($lineBase * $this->freightPercentage()) / 100, 2);
        $cgstRate = 9.0;
        $sgstRate = 9.0;
        $cgstAmount = round($lineBase * $cgstRate / 100, 2);
        $sgstAmount = round($lineBase * $sgstRate / 100, 2);
        $lineGst = round($cgstAmount + $sgstAmount, 2);
        $lineTotal = round($lineBase + $lineGst + $freight, 2);

        return [
            'line_base' => $lineBase,
            'freight' => $freight,
            'line_gst' => $lineGst,
            'line_total' => $lineTotal,
            'cgst_rate' => $cgstRate,
            'sgst_rate' => $sgstRate,
            'cgst_amount' => $cgstAmount,
            'sgst_amount' => $sgstAmount,
        ];
    }

    private function renderCartGstBreakdownHtml(float $cgst, float $sgst, ?float $totalGst = null): string
    {
        if ($totalGst === null) {
            $totalGst = round($cgst + $sgst, 2);
        }

        return '<div class="cart-gst-breakdown">'

            . '<span class="cart-gst-line cart-gst-line--total">' . $this->formatCartMoney($totalGst) . '</span>'
            . '</div>';
    }

    private function formatCartMoney(float $amount): string
    {
        return number_format($amount, 2);
    }

    public function getCartItems()
    {
        $freightPercentage = $this->freightPercentage();
        $chkItem = $this->obconn->prepare("SELECT * FROM tbl_vayu_cartitems WHERE created_by = :createdBy AND status = 0 ORDER BY id");
        $chkItem->bindParam(':createdBy', $this->customer_code, PDO::PARAM_STR);
        $chkItem->execute();

        if ($chkItem->rowCount() == 0) {
            return '
            <div class="cart-empty-state">
                <div class="cart-empty-state__icon"><i class="bi bi-cart3"></i></div>
                <h4 class="cart-empty-state__title">Your cart is empty</h4>
                <p class="cart-empty-state__text">Select a product above and click <strong>Add Item</strong> to start building your order.</p>
            </div>';
        }

        $html = '
    <div class="cart-table-scroll">
    <table class="cart-table" id="cartTable">
        <thead>
            <tr>
                <th class="cart-col-code">Item Code</th>
                <th class="cart-col-desc">Item Description</th>
                <th class="cart-col-money">Unit Price</th>
                <th class="cart-col-qty">Qty</th>
                <th class="cart-col-total-price">Total Price</th>
                <th class="cart-col-gst">GST (18%)<span class="cart-gst-hint">CGST (9%) + SGST (9%)</span></th>
                <th class="cart-col-freight">Freight (' . $freightPercentage . '%' . ')</th>
                <th class="cart-col-money">Total Amount</th>
                <th class="cart-col-action">Action</th>
            </tr>
        </thead>
        <tbody>';

        $totalPriceSum = 0.0;
        $totalGstSum = 0.0;
        $totalFreightSum = 0.0;
        $totalAmountSum = 0.0;
        $rows = $chkItem->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $unitPrice = filter_var($row['price'] ?? 0, FILTER_VALIDATE_FLOAT);
            $qty       = filter_var($row['qty'] ?? 0, FILTER_VALIDATE_FLOAT);
            $amounts = $this->computeCartLineGstAmounts($unitPrice, $qty);
            $totalPriceSum += $amounts['line_base'];
            $totalGstSum += $amounts['line_gst'];
            $totalFreightSum += $amounts['freight'];
            $totalAmountSum += $amounts['line_total'];
            $cartId = filter_var($row['id'] ?? 0, FILTER_VALIDATE_INT);

            $html .= '
        <tr class="cart-item-row" data-cart-id="' . $cartId . '" data-unit-price="' . htmlspecialchars((string) $unitPrice, ENT_QUOTES, 'UTF-8') . '">
            <td class="cart-col-code"><span class="cart-item-code">' . htmlspecialchars($row['item_code']) . '</span></td>
            <td class="cart-col-desc">' . htmlspecialchars($row['item_name']) . '</td>
            <td class="cart-col-money cart-money" id="idUnitPrice' . $cartId . '">' . $this->formatCartMoney($unitPrice) . '</td>
            <td class="cart-col-qty">
                <input type="text" value="' . htmlspecialchars((string) $row['qty']) . '" id="idQty' . $cartId . '" class="cart-qty-input" oninput="recalculateCartLine(' . $cartId . ')" onKeyup="updatePrice(\'' . $cartId . '\')">
            </td>
            <td class="cart-col-total-price cart-money" id="idLineTotalPrice' . $cartId . '">' . $this->formatCartMoney($amounts['line_base']) . '</td>
            <td class="cart-col-gst" id="idGst' . $cartId . '">'
                . $this->renderCartGstBreakdownHtml($amounts['cgst_amount'], $amounts['sgst_amount'], $amounts['line_gst']) .
                '</td>
            <td class="cart-col-freight cart-money" id="idFreight' . $cartId . '">' . $this->formatCartMoney($amounts['freight']) . '</td>
            <td class="cart-col-money cart-money cart-money--total" id="idTotal' . $cartId . '">' . $this->formatCartMoney($amounts['line_total']) . '</td>
            <td class="cart-col-action">
                <div class="cart-action-group">
                    <button type="button" class="cart-action-btn cart-action-btn--breakup" onclick="openPriceBreakup(' . $cartId . ')" title="Price Breakup">
                        <i class="bi bi-receipt"></i>
                    </button>
                    <button type="button" class="cart-action-btn cart-action-btn--delete removeItem" onclick="deleteCartItem(' . $cartId . ')" data-id="' . $cartId . '" title="Remove item">
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
            </td>
        </tr>';
        }

        $totalPriceSum = round($totalPriceSum, 2);
        $totalGstSum = round($totalGstSum, 2);
        $totalFreightSum = round($totalFreightSum, 2);
        $totalAmountSum = round($totalAmountSum, 2);

        $html .= '
        </tbody>
        <tfoot class="cart-table__footer">
        <tr class="cart-summary-row cart-summary-row--grand">
            <td colspan="4" class="cart-summary-label cart-summary-label--grand">Grand Total</td>
            <td class="cart-col-money cart-summary-value" id="cartTotalPrice" data-cart-total-price="' . htmlspecialchars((string) $totalPriceSum, ENT_QUOTES, 'UTF-8') . '">
                ' . $this->formatCartMoney($totalPriceSum) . '
            </td>
            <td class="cart-col-money cart-summary-value cart-summary-value--gst" id="cartTotalGst" data-cart-total-gst="' . htmlspecialchars((string) $totalGstSum, ENT_QUOTES, 'UTF-8') . '">
                ' . $this->formatCartMoney($totalGstSum) . '
            </td>
            <td class="cart-col-money cart-summary-value" id="cartTotalFreight" data-cart-total-freight="' . htmlspecialchars((string) $totalFreightSum, ENT_QUOTES, 'UTF-8') . '">
                ' . $this->formatCartMoney($totalFreightSum) . '
            </td>
            <td class="cart-col-money cart-summary-value cart-summary-value--grand" id="cartTotalAmount" data-cart-total-amount="' . htmlspecialchars((string) $totalAmountSum, ENT_QUOTES, 'UTF-8') . '">
                ' . $this->formatCartMoney($totalAmountSum) . '
            </td>
            <td class="cart-col-action">
                <button type="button" class="cart-action-btn cart-action-btn--breakup cart-action-btn--breakup-lg" onclick="openOrderPriceBreakup()" title="Order Price Breakup">
                    <i class="bi bi-receipt-cutoff"></i>
                    <span>Breakup</span>
                </button>
            </td>
        </tr>
        </tfoot>
    </table>
    </div>';

        return $html;
    }



    public function getCartItems_bk()
    {
        $chkItem = $this->obconn->prepare("SELECT * FROM tbl_vayu_cartitems WHERE created_by = :createdBy AND status = 0");
        $chkItem->bindParam(':createdBy', $this->customer_code, PDO::PARAM_STR);
        $chkItem->execute();

        if ($chkItem->rowCount() == 0) {
            return '<div class="alert alert-info">No items in cart.</div>';
        }

        $html = '
    <table class="table table-bordered" id="cartTable">
        <thead>
            <tr>
                <th>S.No</th>
                <th>Item Code</th>
                <th>Item Description</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Total Amount</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>';

        $i = 1;

        while ($row = $chkItem->fetch(PDO::FETCH_ASSOC)) {

            $total = $row['price'] * $row['qty'];

            $html .= '
        <tr>
            <td>' . $i . '</td>
            <td>' . htmlspecialchars($row['item_code']) . '</td>
            <td>' . htmlspecialchars($row['item_name']) . '</td>
            <td><input type="text" value="' . $row['qty'] . '" id="idQty' . $row['id'] . '" class="form-control" style="width:100px;" onKeyup="updatePrice(\'' . $row['id'] . '\')"></td>
            <td id="idPrice' . $row['id'] . '">' . number_format($row['price'], 2) . '</td>
            <td id="idTotal' . $row['id'] . '">' . number_format($total, 2) . '</td>
            <td>
 <button type="button"
                            class="btn btn-sm text-white"
                            onclick="openPriceBreakup(' . (int) $row['id'] . ')"
                            title="Price Breakup" style="background-color:#F44611">
                        <i class="fa fa-rupee"></i>
                    </button>
                <button type="button"
                        class="btn btn-danger btn-sm removeItem" onclick="deleteCartItem(' . $row['id'] . ')"
                        data-id="' . $row['id'] . '" style="background-color:#F44611">
                    <i class="fa fa-trash"></i>
                </button>
            </td>
        </tr>';

            $i++;
        }

        $html .= '
        </tbody>
    </table>';

        return $html;
    }

    public function deleteItem()
    {

        try {
            $id  = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $delItem = $this->obconn->prepare("DELETE FROM tbl_vayu_cartitems WHERE id=:id");
            $delItem->bindParam(":id", $id, PDO::PARAM_INT);
            $delItem->execute();
            return 1;
        } catch (PDOException $e) {
            echo $e->getMessage();
            return 0;
        }
    }

    public function updatePrice()
    {
        try {
            $id  = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $qty = filter_input(INPUT_POST, 'qty', FILTER_VALIDATE_FLOAT);

            if ($id === false || $id === null) {
                return 0;
            }

            if ($qty === false || $qty === null || $qty <= 0) {
                return 0;
            }
            $chkItem = $this->obconn->prepare("SELECT price FROM tbl_vayu_cartitems WHERE id = :id AND status = 0");
            $chkItem->bindParam(':id', $id, PDO::PARAM_STR);
            $chkItem->execute();
            $item = $chkItem->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                return 0;
            }
            $total_amount = $qty * $item['price'];;

            $updPrice = $this->obconn->prepare("UPDATE tbl_vayu_cartitems SET qty=:qty, total_amount=:total_amount WHERE id=:id");
            $updPrice->bindParam(":qty", $qty, PDO::PARAM_INT);
            $updPrice->bindParam(":total_amount", $total_amount, PDO::PARAM_INT);
            $updPrice->bindParam(":id", $id, PDO::PARAM_INT);
            $updPrice->execute();
            return json_encode(["total_amount" => $total_amount]);
        } catch (Exception $e) {
            echo $e->getMessage();
            return 0;
        }
    }

    private function parseGstTaxRate(?string $taxCode): ?float
    {
        $taxCode = trim((string) $taxCode);
        if ($taxCode === '' || !preg_match('/GSTAG(\d+)/', $taxCode, $matches)) {
            return null;
        }

        $rate = (float) $matches[1];

        return $rate > 0 && $rate <= 28 ? $rate : null;
    }

    private function formatPriceBreakupCurrency($amount): string
    {
        if ($amount === null || $amount === '' || !is_numeric($amount)) {
            return 'N/A';
        }

        return number_format((float) $amount, 2);
    }

    private function formatPriceBreakupPercent(?float $rate): string
    {
        if ($rate === null) {
            return 'N/A';
        }

        $formatted = rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');

        return $formatted . '%';
    }

    private function resolveCartPriceBreakupTaxRates(
        string $itemCode,
        string $orderCategory,
        string $country,
        string $state,
        string $company = '401'
    ): array {

        $orderCategoryId = (int) $orderCategory;

        // Override for order categories 4 & 6
        if (in_array($orderCategoryId, [4, 6], true)) {
            $gstRate = ($state === 'TN') ? 5 : 12;
        } else {
            $gstRate = 18;
        }

        return [
            'cgst_rate' => $gstRate / 2,
            'sgst_rate' => $gstRate / 2,
        ];
    }

    private function fetchPriceBreakupCustomerContext(): array
    {
        $customerStmt = $this->obconn->prepare("
            SELECT cm.country
            FROM customer_master cm
            WHERE cm.cuno = :cuno
            LIMIT 1
        ");
        $customerStmt->bindValue(':cuno', $this->customer_code, PDO::PARAM_STR);
        $customerStmt->execute();
        $customer = $customerStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'country' => trim((string) ($customer['country'] ?? 'IND')),
            'state' => 'TN',
        ];
    }

    /**
     * @return array{
     *     price_inclusive: float,
     *     price_before_gst: ?float,
     *     cgst_rate: ?float,
     *     sgst_rate: ?float,
     *     cgst_amount: ?float,
     *     sgst_amount: ?float
     * }
     */
    private function computeLinePriceBreakup(array $item, string $orderCategory, array $customerContext): array
    {
        $amounts = $this->computeCartLineGstAmounts(
            (float) ($item['price'] ?? 0),
            (float) ($item['qty'] ?? 0)
        );

        return [
            'price_inclusive' => $amounts['line_total'],
            'price_before_gst' => $amounts['line_base'],
            'freight' => $amounts['freight'],
            'line_gst' => $amounts['line_gst'],
            'cgst_rate' => $amounts['cgst_rate'],
            'sgst_rate' => $amounts['sgst_rate'],
            'cgst_amount' => $amounts['cgst_amount'],
            'sgst_amount' => $amounts['sgst_amount'],
        ];
    }

    private function formatPriceBreakupResponse(array $breakup, array $extra = []): string
    {
        return json_encode(array_merge([
            'status' => true,
            'price_before_gst' => $this->formatPriceBreakupCurrency($breakup['price_before_gst'] ?? null),
            'cgst_percent' => $this->formatPriceBreakupPercent($breakup['cgst_rate'] ?? null),
            'cgst_amount' => $this->formatPriceBreakupCurrency($breakup['cgst_amount'] ?? null),
            'sgst_percent' => $this->formatPriceBreakupPercent($breakup['sgst_rate'] ?? null),
            'sgst_amount' => $this->formatPriceBreakupCurrency($breakup['sgst_amount'] ?? null),
            'freight_amount' => $this->formatPriceBreakupCurrency($breakup['freight'] ?? null),
            'total_price' => $this->formatPriceBreakupCurrency($breakup['price_inclusive'] ?? null),
        ], $extra));
    }

    /**
     * Display-only price breakup for a cart line. Cart price is GST-inclusive;
     * base price and tax components are derived for display only (no DB writes).
     */
    public function getCartPriceBreakup()
    {
        try {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $orderCategory = trim((string) ($_POST['orderCategory'] ?? ''));

            if ($id === false || $id === null || $id <= 0) {
                return json_encode([
                    'status' => false,
                    'message' => 'Invalid cart item.',
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            }


            $cartStmt = $this->obconn->prepare("
                SELECT id, item_code, item_name, price, qty, total_amount
                FROM tbl_vayu_cartitems
                WHERE id = :id
                  AND created_by = :createdBy
                  AND status = 0
                LIMIT 1
            ");
            $cartStmt->bindValue(':id', $id, PDO::PARAM_INT);
            $cartStmt->bindValue(':createdBy', $this->customer_code, PDO::PARAM_STR);
            $cartStmt->execute();
            $item = $cartStmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                return json_encode([
                    'status' => false,
                    'message' => 'Cart item not found.',
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            }

            $customerContext = $this->fetchPriceBreakupCustomerContext();
            $breakup = $this->computeLinePriceBreakup($item, $orderCategory, $customerContext);

            return $this->formatPriceBreakupResponse($breakup, [
                'item_code' => (string) $item['item_code'],
                'item_name' => (string) $item['item_name'],
            ]);
        } catch (Exception $e) {
            error_log($e->getMessage());

            return json_encode([
                'status' => false,
                'message' => 'Unable to fetch price breakup.',
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
    }

    /**
     * Display-only consolidated price breakup for all cart items combined.
     */
    public function getCartOrderPriceBreakup()
    {
        try {
            $orderCategory = trim((string) ($_POST['orderCategory'] ?? ''));
            $customerContext = $this->fetchPriceBreakupCustomerContext();

            $cartStmt = $this->obconn->prepare("
                SELECT id, item_code, item_name, price, qty, total_amount
                FROM tbl_vayu_cartitems
                WHERE created_by = :createdBy
                  AND status = 0
                ORDER BY id
            ");
            $cartStmt->bindValue(':createdBy', $this->customer_code, PDO::PARAM_STR);
            $cartStmt->execute();
            $items = $cartStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$items) {
                return json_encode([
                    'status' => false,
                    'message' => 'No cart items found.',
                ]);
            }

            $totals = [
                'price_inclusive' => 0.0,
                'price_before_gst' => 0.0,
                'freight' => 0.0,
                'line_gst' => 0.0,
                'cgst_amount' => 0.0,
                'sgst_amount' => 0.0,
                'cgst_rate' => null,
                'sgst_rate' => null,
            ];

            foreach ($items as $item) {
                $lineBreakup = $this->computeLinePriceBreakup($item, $orderCategory, $customerContext);
                $totals['price_inclusive'] += (float) ($lineBreakup['price_inclusive'] ?? 0);
                $totals['price_before_gst'] += (float) ($lineBreakup['price_before_gst'] ?? 0);
                $totals['freight'] += (float) ($lineBreakup['freight'] ?? 0);
                $totals['line_gst'] += (float) ($lineBreakup['line_gst'] ?? 0);
                $totals['cgst_amount'] += (float) ($lineBreakup['cgst_amount'] ?? 0);
                $totals['sgst_amount'] += (float) ($lineBreakup['sgst_amount'] ?? 0);

                if ($totals['cgst_rate'] === null) {
                    $totals['cgst_rate'] = $lineBreakup['cgst_rate'] ?? null;
                    $totals['sgst_rate'] = $lineBreakup['sgst_rate'] ?? null;
                }
            }

            $totals['price_before_gst'] = round($totals['price_before_gst'], 2);
            $totals['freight'] = round($totals['freight'], 2);
            $totals['line_gst'] = round($totals['line_gst'], 2);
            $totals['cgst_amount'] = round($totals['cgst_amount'], 2);
            $totals['sgst_amount'] = round($totals['sgst_amount'], 2);
            $totals['price_inclusive'] = round($totals['price_inclusive'], 2);

            return $this->formatPriceBreakupResponse($totals, [
                'item_count' => count($items),
            ]);
        } catch (Exception $e) {
            error_log($e->getMessage());

            return json_encode([
                'status' => false,
                'message' => 'Unable to fetch order price breakup.',
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
    }
    public function submitCartApi()
    {
        date_default_timezone_set('UTC');

        $datetime = date('Y-m-d\TH:i:s\Z');

        $addrCode  = $_POST['addressCode'];

        $area      = $_POST['area'];

        $indcat    = $_POST['orderCategory'];

        $deladdr   = strtoupper($_POST['addressCode']);

        $addrType = $_POST['deliveryAddressType'];

        $trans     = $_POST['transporter'];

        // $delterms  = $_POST['deliveryTerm'];

        $paycode   = $_POST['paymentTerm'];

        $pono      = $_POST['pono'];

        $frtamount = (isset($_POST['freightAmount']) && $_POST['freightAmount'] !== '') ? $_POST['freightAmount'] : null;

        $cuno     = $this->customer_code;

        $sid      = session_id();

        $addrCode  = $_POST['addressCode'];

        $indcat1    = $_POST['orderCategory'];

        $indcat = "Normal Order";

        $otcode = 511;

        $empcode = "102464";

        $warehouse = "W_553";

        $aoseries = "501";

        $cmp      = "401";

        $state    = "TN";

        $dpst      = "90092";

        $delterms = "CIF";

        if ($addrType == 1) {

            $getAddr = $this->dpconn->prepare("SELECT * FROM customer_address WHERE adr_code=:addrCode limit 1");

            $getAddr->bindParam(":addrCode", $deladdr);

            $getAddr->execute();

            if ($getAddr->rowCount() > 0) {

                $fetAddr = $getAddr->fetch(PDO::FETCH_ASSOC);

                $cuname = $fetAddr['cuname'];

                $street1 = $fetAddr['st1'];

                $street2 = $fetAddr['st2'];

                $city = $fetAddr['city'];

                $state = isset($fetAddr['state']) ? $fetAddr['state'] : 'TN';

                $country = $fetAddr['country'];

                $pincode = trim((string)($fetAddr['pin'] ?? ''));

                if ($pincode !== '') {
                    $pincodeValue = (int) str_replace(' ', '', $pincode);
                } else {
                    $pincodeValue = 0;
                }

                $emailValue =  null; // Added new field 01-07-26

                $pincodeValue =  ($pincode) ? str_replace(' ', '', $pincode) : 000; // Added new field 01-07-26

                $districtValue = $city ?? null; // Added new field 01-07-26
            }
        } else {
            $cuname = trim($_POST['end_customer_name'] ?? '');

            $email     = trim($_POST['end_customer_email'] ?? ''); // Added new field 01-07-26

            $street1   = trim($_POST['street_1'] ?? ''); // Added new field 01-07-26

            $street2   = trim($_POST['street_2'] ?? ''); // Added new field 01-07-26

            $city      = trim($_POST['city'] ?? ''); // Added new field 01-07-26

            $state = trim($_POST['state_code'] ?? 'TN'); // Added new field 01-07-26

            $district  = trim($_POST['district'] ?? ''); // Added new field 01-07-26

            $pincode   = trim($_POST['pincode'] ?? ''); // Added new field 01-07-26

            $emailValue = ($email !== '') ? $email : null; // Added new field 01-07-26

            $pincodeValue = ($pincode !== '') ? $pincode : 000; // Added new field 01-07-26

            $districtValue = ($district !== '') ? $district : null; // Added new field 01-07-26
        }


        $bearerToken = $this->getBearerTokenLN();

        if ($bearerToken) {
            try {

                $this->obconn->beginTransaction();

                $rs = $this->obconn->prepare("select to_char(current_date,'YYMMDD') as ymd");

                $rs->execute();

                $getData = $rs->fetch(PDO::FETCH_ASSOC);

                $ymd = $getData['ymd'];

                $rs = $this->obconn->prepare("select nextval('dp_spares') as slno");

                $rs->execute();

                $getData = $rs->fetch(PDO::FETCH_ASSOC);

                $slno = $getData['slno'];

                $slno = str_pad($slno, 4, "0", STR_PAD_LEFT);

                $refno = "E/UNITS/" . $ymd . $slno;

                $cartStmt = $this->obconn->prepare("SELECT item_code,item_name,qty,price,total_amount FROM tbl_vayu_cartitems WHERE created_by = :createdBy AND status = 0");

                $cartStmt->execute([
                    ':createdBy' => $this->customer_code
                ]);

                $cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($cartItems)) {

                    throw new Exception("Cart is empty.");
                }

                $stmt = $this->obconn->prepare("SELECT max(substr(indent_number,7,9)) AS maxindno FROM 
                plexecom_customer_units15062026 WHERE areacode = :area AND indent_date >= '01.04.2022'");

                $stmt->execute([
                    ':area' => $area
                ]);

                $maxIndno = $stmt->fetchColumn();

                $letter = substr($maxIndno, 0, 1);

                $number = substr($maxIndno, 1, 2);

                $letter = $letter ?: 'A';

                $number = $number ?: 0;

                if ($number == 99) {

                    $letter = chr(ord($letter) + 1);

                    $number = 1;
                } else {

                    $number++;
                }

                $number   = str_pad($number, 2, "0", STR_PAD_LEFT);

                $newIndno = $indcat1 . 'N' . $letter . $number;

                $customerStmt = $this->obconn->prepare("SELECT cm.adr_code, cm.country,ca.custaddr FROM customer_master cm LEFT JOIN customer_address ca ON ca.adr_code = cm.adr_code AND ca.cuno = cm.cuno WHERE cm.cuno=:cuno ");

                $customerStmt->execute([
                    ':cuno' => $cuno
                ]);

                $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

                $adrcode = $customer['adr_code'];

                $country = trim($customer['country']);

                $invaddr = pg_escape_string($customer['custaddr']);

                $dpstStmt = $this->obconn->prepare("SELECT product_group FROM dpst_master WHERE dpst_code = :dpst");

                $dpstStmt->execute([':dpst' => $dpst]);

                $div = $dpstStmt->fetchColumn();

                $indent_number = $div . substr($area, 2, 2) . 'A' . $newIndno;

                $seqStmt = $this->obconn->prepare("SELECT nextval('plexecom_unique_sequence')");

                $productStmt = $this->obconn->prepare("SELECT tpldesc, excisable, warehouse, otcode, mc, vc, fc, cos,dealer_price FROM product_master_vayu WHERE tplcode = :tplcode AND dpst = :dpst");

                $hsnStmt = $this->obconn->prepare("SELECT substr(replace(hsn,':',''),1,4) AS hsn FROM elgi_item_master WHERE item_code = :tplcode");

                $xml = "";

                $xml .= "<?xml version='1.0' encoding='UTF-8'?>
                <messageRequest>
                    <documentName>Process.SalesOrder</documentName>
                    <fromLogicalId>lid://infor.ims.ho_mscrm</fromLogicalId> 
                    <toLogicalId>lid://default</toLogicalId> 
                        <messageId>lid://infor.ims.mscrm_sync_salesorder_" . $datetime . "</messageId> 
                        <document>
                        <value>			
                    <![CDATA[
                <ProcessSalesOrder	xmlns='http://schema.infor.com/InforOAGIS/2'
                    xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance'
                    xsi:schemaLocation='http://schema.infor.com/InforOAGIS/2 http://schema.infor.com/trunk/InforOAGIS/BODs/Developer/ProcessSalesOrder.xsd'
                    xmlns:xsd='http://www.w3.org/2001/XMLSchema'
                    releaseID='9.2'
                    versionID='2.5.0'>
                    <ApplicationArea>
                    <Sender>
                            <LogicalID>lid://infor.ims.mscrm</LogicalID>
                            <ComponentID>crm</ComponentID> 
                            <ConfirmationCode>OnError</ConfirmationCode>
                        </Sender><CreationDateTime>" . $datetime . "</CreationDateTime><BODID>infor-nid:infor.ln:401::" . $refno . ":?SalesOrder&amp;verb=Sync</BODID>
                    </ApplicationArea>
                    <DataArea>
                        <Process>
                            <TenantID>ELGI2_PRD</TenantID> 
                            <AccountingEntityID>401</AccountingEntityID> 
                            <LocationID>S_401</LocationID>
                            <ActionCriteria>
                                <ActionExpression actionCode='Add' />
                            </ActionCriteria>
                        </Process>
                <SalesOrder>
                <SalesOrderHeader>
                    <DocumentID agencyRole='Supplier'>
                        <ID>" . $refno . "</ID>
                    </DocumentID>
                    <AlternateDocumentID agencyRole='Customer'><ID>" . $refno . "</ID></AlternateDocumentID>
                    <DocumentDateTime>" . $datetime . "</DocumentDateTime><Status><Code>Open</Code></Status>
                    <SupplierParty>
                    <Location type='Office'>
                        <ID>" . $dpst . "</ID> 
                    </Location>
                    </SupplierParty>
                    <CustomerParty>
                        <PartyIDs><ID>" . $cuno . "</ID></PartyIDs>
                    </CustomerParty> 
                    <ShipToParty>
                            <PartyIDs>
                                <ID>" . $cuno . "</ID>
                            </PartyIDs>
                            <Location>
                            <Address type='Discrete'>
                            <AttentionOfName>" . $cuname . "</AttentionOfName>
                            <StreetName>" . $street1 . "</StreetName>
                            <BuildingName>" . $street2 . "</BuildingName>
                            <Floor></Floor>
                            <CityName>" . $city . "</CityName>
                            <CountrySubDivisionCode>" . $state . "</CountrySubDivisionCode>
                            <CountryCode>IN</CountryCode>
                            <PostalCode>" . $pincode . "</PostalCode>
                            </Address>
                            </Location>
                    </ShipToParty>
                    <TransportationTerm>
                        <IncotermsCode>" . $delterms . "</IncotermsCode>
                    </TransportationTerm>
                    <PaymentTerm>
                        <IDs><ID>" . $paycode . "</ID></IDs>
                    </PaymentTerm>
                    <RequestedShipDateTime>" . $datetime . "</RequestedShipDateTime> 
                    <UserArea>
                        <Property>
                        <NameValue name='ln.CRMID' type='StringType'>" . $refno . "</NameValue>
                        </Property>
                        <Property>
                        <NameValue name='crm.PriceOverride' type='StringType'>N</NameValue></Property>
                        <Property>
                        <NameValue name='crm.AccountType' type='StringType'>C</NameValue>
                        </Property>
                        <Property>
                        <NameValue name='crm.OrderCategory' type='StringType'>" . $indcat . "</NameValue>
                        </Property>
                        <Property>
                        <NameValue name='crm.OrderType' type='StringType'>" . $indcat . "</NameValue>
                        </Property>
                        <Property>
                        <NameValue name='crm.TODApplicable' type='StringType'>N</NameValue>
                        </Property>
                        <Property>
                        <NameValue name='crm.CustomerState' type='StringType'>" . $state . "</NameValue>
                        </Property>
                    </UserArea>
                    <SalesPersonReference>
                        <IDs><ID>102765</ID></IDs>
                    <SalesPersonRole>Internal</SalesPersonRole>
                </SalesPersonReference>
                </SalesOrderHeader>";

                $line = 10;

                foreach ($cartItems as $item) {

                    $tplcode = $item['item_code'];

                    $productStmt->execute([
                        ':tplcode' => $tplcode,
                        ':dpst'    => $dpst
                    ]);

                    $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$product) {
                        continue;
                    }

                    $seqStmt->execute();

                    $seqid = $seqStmt->fetchColumn();

                    $hsnStmt->execute([
                        ':tplcode' => $tplcode
                    ]);

                    $hsn = $hsnStmt->fetchColumn();

                    $taxColumn = ($country == 'IND' && $state == 'TN') ? 'sgst' : 'igst';

                    $taxStmt = $this->obconn->prepare("SELECT {$taxColumn} AS taxcode FROM gst_hsn WHERE replace(hsn,':','') = :hsn AND company = :company");

                    $taxStmt->execute([
                        ':hsn'     => $hsn,
                        ':company' => $cmp
                    ]);

                    $taxcode = $taxStmt->fetchColumn();

                    if (in_array($indcat, [4, 6])) {
                        $taxcode = ($state == 'TN') ? 'GSTAG05' : 'GSTAG62';
                    }

                    $xml .= "<SalesOrderLine>
                    
                    <LineNumber>" . $line . "</LineNumber>

                    <Item>
                        <ItemID><ID>" . $tplcode . "</ID></ItemID>
                    </Item>

                    <Quantity unitCode='NOS'>" . $item['qty'] . "</Quantity>

                    <UnitPrice>
                        <Amount>" . $item['total_amount'] . "</Amount>
                        <PerQuantity unitCode='NOS'>" . $item['qty'] . "</PerQuantity>
                    </UnitPrice>

                    <UserArea>
                        <Property>
                        <NameValue name='ln.HSNCode' type='StringType'>80:11</NameValue>
                        </Property>
            
                        <Property><NameValue name='ln.Motor' type='StringType'>ELGI</NameValue>
                        </Property>
                    </UserArea>

                    <CarrierParty>
                        <PartyIDs>
                            <ID>TC3</ID>
                        </PartyIDs>
                    </CarrierParty>

                    <ShipFromParty>
                        <Location type='Warehouse'>
                            <ID>" . $warehouse . "</ID>
                        </Location>
                    </ShipFromParty>
                    
                    </SalesOrderLine>";

                    $line += 10;

                    $insertStmt = $this->obconn->prepare("INSERT INTO plexecom_customer_units(usr_name,emp_code,cuno,cuname,areacode,pono,indent_category,indent_number,indent_date,transporter,delterms_code,delivery_date,invaddr,email, pincode,district,deladdr,dpst,tplcode,price,qty,salestax_code,sessionid,paycode,insby,edi_cuno,seqid,status,aoseries,otcode,warehouse,edi_delivery_date,edi_delivery_code,tpldesc,mc,vc,fc,cos,delivery_code,frtamount,company,adrcode,refno,hsn,state,country,edistatus,edi_date)VALUES(:uname,:emp_code,:cuno,:cname,:area,:pono,:indcat,:indno,current_date,:trans,:delterms,:deldate,:invaddr,:email,:pincode,:district,:deladdr,:dpst,:tplcode,:price,:qty,:taxcode,:sid,:paycode,:insby,:edi_cuno,:seqid,:status,:aoseries,:otcode,:warehouse,:edi_delivery_date,:edi_delivery_code,:tpldesc,:mcval,:vcval,:fcval,:cosval,:shipto,:frtamount,:cmp,:adrcode,:refno,:hsn,:state,:country,:edistatus,:edi_date)");

                    // $params = [
                    //     ':uname'             => $this->userId,
                    //     ':emp_code'          => "102464",
                    //     ':cuno'              => $cuno,
                    //     ':cname'             => '',
                    //     ':area'              => $area,
                    //     ':pono'              => $pono,
                    //     ':indcat'            => $indcat1,
                    //     ':indno'             => $indent_number,
                    //     ':trans'             => $trans,
                    //     ':delterms'          => $delterms,
                    //     ':deldate'           => date('d.m.Y'),
                    //     ':invaddr'           => $invaddr,
                    //     ':email'             => $emailValue,
                    //     ':pincode'           => $pincodeValue,
                    //     ':district'          => $districtValue,
                    //     ':deladdr'           => $deladdr,
                    //     ':dpst'              => $dpst,
                    //     ':tplcode'           => $tplcode,
                    //     ':price'             => $item['total_amount'],
                    //     ':qty'               => $item['qty'],
                    //     ':taxcode'           => $taxcode,
                    //     ':sid'               => $sid,
                    //     ':paycode'           => $paycode,
                    //     ':insby'             => '',
                    //     ':edi_cuno'          => $cuno,
                    //     ':seqid'             => $seqid,
                    //     ':status'            => 'A',
                    //     ':aoseries'          => $aoseries,
                    //     ':otcode'            => '511',
                    //     ':warehouse'         => $product['warehouse'],
                    //     ':edi_delivery_date' => date('d.m.Y'),
                    //     ':edi_delivery_code' => $deladdr,
                    //     ':tpldesc'           => pg_escape_string($product['tpldesc']),
                    //     ':mcval'             => ($product['mc'] === '' || $product['mc'] === null) ? null : $product['mc'],
                    //     ':vcval'             => ($product['vc'] === '' || $product['vc'] === null) ? null : $product['vc'],
                    //     ':fcval'             => ($product['fc'] === '' || $product['fc'] === null) ? null : $product['fc'],
                    //     ':cosval'            => ($product['cos'] === '' || $product['cos'] === null) ? null : $product['cos'],
                    //     ':shipto'            => $addrCode,
                    //     ':frtamount'         => $frtamount,
                    //     ':cmp'               => $cmp,
                    //     ':adrcode'           => $adrcode,
                    //     ':refno'             => $refno,
                    //     ':hsn'               => "80:11",
                    //     ':state'             => $state,
                    //     ':country'           => $country,
                    //     ':edistatus'         => 'Y',
                    //     ':edi_date'          => date('d.m.Y')
                    // ];

                    // $sql = $insertStmt->queryString;

                    // // Replace placeholders with actual values
                    // foreach ($params as $key => $value) {
                    //     if ($value === null) {
                    //         $replacement = "NULL";
                    //     } elseif (is_numeric($value)) {
                    //         $replacement = $value;
                    //     } else {
                    //         $replacement = "'" . str_replace("'", "''", $value) . "'";
                    //     }

                    //     $sql = str_replace($key, $replacement, $sql);
                    // }

                    // echo "<pre>";
                    // echo $sql;
                    // echo "</pre>";
                    // exit;

                    $success = $insertStmt->execute([
                        ':uname'             => $this->userId,

                        ':emp_code'          => $empcode,

                        ':cuno'              => $cuno,

                        ':cname'             => '',

                        ':area'              => $area,

                        ':pono'              => $pono,

                        ':indcat'            => $indcat1,

                        ':indno'             => $indent_number,

                        ':trans'             => $trans,

                        ':delterms'          => $delterms,

                        ':deldate'           => date('d.m.Y'),

                        ':invaddr'           => $invaddr,

                        ':email'             => $emailValue,

                        ':pincode'           => $pincodeValue,

                        ':district'          => $districtValue,

                        ':deladdr'           => $deladdr,

                        ':dpst'              => $dpst,

                        ':tplcode'           => $tplcode,

                        ':price'             => $item['total_amount'],

                        ':qty'               => $item['qty'],

                        ':taxcode'           => $taxcode,

                        ':sid'               => $sid,

                        ':paycode'           => $paycode,

                        ':insby'             => '',

                        ':edi_cuno'          => $cuno,

                        ':seqid'             => $seqid,

                        ':status'            => 'A',

                        ':aoseries'          => $aoseries,

                        ':otcode'            => $otcode,

                        ':warehouse'         => $product['warehouse'],

                        ':edi_delivery_date' => date('d.m.Y'),

                        ':edi_delivery_code' => $deladdr,

                        ':tpldesc'           => pg_escape_string($product['tpldesc']),

                        ':mcval'             => ($product['mc'] === '' || $product['mc'] === null) ? null : $product['mc'],

                        ':vcval'             => ($product['vc'] === '' || $product['vc'] === null) ? null : $product['vc'],

                        ':fcval'             => ($product['fc'] === '' || $product['fc'] === null) ? null : $product['fc'],

                        ':cosval'            => ($product['cos'] === '' || $product['cos'] === null) ? null : $product['cos'],

                        ':shipto'            => $addrCode,

                        ':frtamount'         => $frtamount,

                        ':cmp'               => $cmp,

                        ':adrcode'           => $adrcode,

                        ':refno'             => $refno,

                        ':hsn'               => "80:11",

                        ':state' => (!empty(trim((string)$state))) ? trim($state) : 'TN',

                        ':country'           => $country,

                        ':edistatus'         => 'Y',

                        ':edi_date'          => date('d.m.Y')

                    ]);

                    if (!$success) {

                        $this->obconn->rollBack();

                        return json_encode([
                            'status'  => 'error',
                            'message' => "Error in request"
                        ]);
                    }
                }

                $xml .= " </SalesOrder>
                </DataArea>
                </ProcessSalesOrder>]]>
                </value>
                <encoding>NONE</encoding>		
                <characterSet>UTF-8</characterSet> 
                </document>
                </messageRequest>";

                $url = "https://mingle-ionapi.eu1.inforcloudsuite.com/ELGI_TST/IONSERVICES/api/ion/messaging/service/v2/message";

                $maxRetries = 3;

                $retryDelay = 1;

                $ch = null;

                for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {

                    $ch = curl_init();

                    curl_setopt_array($ch, [
                        CURLOPT_URL            => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => $xml,
                        CURLOPT_HTTPHEADER     => [
                            "Content-Type: application/xml; charset=UTF-8",
                            "Authorization: Bearer $bearerToken"
                        ],
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_SSL_VERIFYHOST => 2,
                        CURLOPT_CONNECTTIMEOUT => 30,
                        CURLOPT_TIMEOUT        => 60,
                        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
                    ]);

                    $response = curl_exec($ch);

                    $errno    = curl_errno($ch);

                    $error    = curl_error($ch);

                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                    curl_close($ch);

                    if ($errno == 0) {
                        break;
                    }

                    error_log("Attempt {$attempt}: cURL Error {$errno} - {$error}");

                    if ($errno == CURLE_COULDNT_CONNECT && $attempt < $maxRetries) {
                        sleep($retryDelay);
                        continue;
                    }

                    throw new Exception("cURL Error {$errno}: {$error}");
                }


                $response = curl_exec($ch);

                $errno    = curl_errno($ch);

                $error    = curl_error($ch);

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                curl_close($ch);

                if ($errno != 0) {
                    return json_encode(["status" => ($data['message'] ?? 'Unknown error')]);
                }

                $data = json_decode($response, true);

                $deleteStmt = $this->obconn->prepare("DELETE FROM tbl_vayu_cartitems WHERE created_by = :createdBy
            AND status = 0");

                $deleteStmt->execute([
                    ':createdBy' => $this->customer_code
                ]);

                $this->obconn->commit();

                if ($httpCode == 201 && isset($data['status']) && $data['status'] === 'OK') {
                    return json_encode([
                        'status'   => $refno,
                    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                } else {
                    return json_encode(["status" => ($data['message'] ?? 'Unknown error')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                }
            } catch (Exception $e) {

                if ($this->obconn->inTransaction()) {

                    $this->obconn->rollBack();
                }

                error_log(
                    "[" . date('Y-m-d H:i:s') . "] " .
                        "User: {$this->userId} | " .
                        "Error: {$e->getMessage()} | " .
                        "Line: {$e->getLine()}"
                );

                return json_encode([
                    'status'  => 'error',
                    'message' => $e->getMessage() . $e->getLine()
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            }
        }
    }

    public function submitCart()
    {
        try {

            $addrCode  = $_POST['addressCode'];
            $area      = $_POST['area'];
            $indcat    = (int) ($_POST['orderCategory'] ?? 0);
            $deladdr   = strtoupper($_POST['addressCode']);
            $trans     = $_POST['transporter'];
            $delterms  = $_POST['deliveryTerm'];
            // $paycode   = $_POST['paymentTerm']
            $paycode = "301";
            $dpst      = "90092";
            $pono      = $_POST['pono'];
            $frtamount = (isset($_POST['freightAmount']) && $_POST['freightAmount'] !== '') ? $_POST['freightAmount'] : null;
            $email     = trim($_POST['end_customer_email'] ?? ''); // Added new field 01-07-26
            $street1   = trim($_POST['street_1'] ?? ''); // Added new field 01-07-26
            $street2   = trim($_POST['street_2'] ?? ''); // Added new field 01-07-26
            $city      = trim($_POST['city'] ?? ''); // Added new field 01-07-26
            $addressState = trim($_POST['state'] ?? ''); // Added new field 01-07-26
            $district  = trim($_POST['district'] ?? ''); // Added new field 01-07-26
            $pincode   = trim($_POST['pincode'] ?? ''); // Added new field 01-07-26
            $emailValue = ($email !== '') ? $email : null; // Added new field 01-07-26
            $pincodeValue = ($pincode !== '') ? $pincode : null; // Added new field 01-07-26
            $districtValue = ($district !== '') ? $district : null; // Added new field 01-07-26

            if ($indcat <= 0) {
                throw new Exception('Order category is required.');
            }

            $this->obconn->beginTransaction();

            $rs = $this->obconn->prepare("select to_char(current_date,'YYMMDD') as ymd");
            $rs->execute();
            $getData = $rs->fetch(PDO::FETCH_ASSOC);
            $ymd = $getData['ymd'];

            $rs = $this->obconn->prepare("select nextval('dp_spares') as slno");
            $rs->execute();
            $getData = $rs->fetch(PDO::FETCH_ASSOC);
            $slno = $getData['slno'];

            $slno = str_pad($slno, 4, "0", STR_PAD_LEFT);

            $refno = "E/UNITS/" . $ymd . $slno;


            $cartStmt = $this->obconn->prepare("
            SELECT item_code,
                   item_name,
                   qty,
                   price,
                   total_amount
            FROM tbl_vayu_cartitems
            WHERE created_by = :createdBy
            AND status = 0
        ");

            $cartStmt->execute([
                ':createdBy' => $this->customer_code
            ]);

            $cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($cartItems)) {
                throw new Exception("Cart is empty.");
            }


            $stmt = $this->obconn->prepare("
            SELECT max(substr(indent_number,7,9)) AS maxindno
            FROM plexecom_customer_units15062026
            WHERE areacode = :area
            AND indent_date >= '01.04.2022'
        ");

            $stmt->execute([
                ':area' => $area
            ]);

            $maxIndno = $stmt->fetchColumn();

            $letter = substr($maxIndno, 0, 1);
            $number = substr($maxIndno, 1, 2);

            $letter = $letter ?: 'A';
            $number = $number ?: 0;

            if ($number == 99) {
                $letter = chr(ord($letter) + 1);
                $number = 1;
            } else {
                $number++;
            }

            $number   = str_pad($number, 2, "0", STR_PAD_LEFT);
            $newIndno = $indcat . 'N' . $letter . $number;

            $cmp      = "401";
            $cuno     = $this->customer_code;
            $aoseries = "501";
            $state    = "TN";
            $sid      = session_id();



            $customerStmt = $this->obconn->prepare("
            SELECT
                cm.adr_code,
                cm.country,
                ca.custaddr
            FROM customer_master cm
            LEFT JOIN customer_address ca
                ON ca.adr_code = cm.adr_code
               AND ca.cuno = cm.cuno
            WHERE cm.cuno = :cuno
        ");

            $customerStmt->execute([
                ':cuno' => $cuno
            ]);

            $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

            $adrcode = $customer['adr_code'];
            $country = trim($customer['country']);
            $hasEndCustomerAddress = ( // Added new field 01-07-26
                $street1 !== '' || // Added new field 01-07-26
                $street2 !== '' || // Added new field 01-07-26
                $city !== '' || // Added new field 01-07-26
                $addressState !== '' || // Added new field 01-07-26
                $district !== '' || // Added new field 01-07-26
                $pincode !== '' // Added new field 01-07-26
            ); // Added new field 01-07-26

            if ($hasEndCustomerAddress) { // Added new field 01-07-26
                $invaddr = pg_escape_string( // Added new field 01-07-26
                    $street1 . '<br> - <br>' . // Added new field 01-07-26
                        $street2 . '<br> - <br>' . // Added new field 01-07-26
                        $city . '<br> - <br>' . // Added new field 01-07-26
                        $addressState . '<br> - <br>' . // Added new field 01-07-26
                        $district . '<br> - <br>' . // Added new field 01-07-26
                        $pincode // Added new field 01-07-26
                ); // Added new field 01-07-26
            } else { // Added new field 01-07-26
                $invaddr = pg_escape_string($customer['custaddr']); // Added new field 01-07-26
            } // Added new field 01-07-26



            $dpstStmt = $this->obconn->prepare("
            SELECT product_group
            FROM dpst_master
            WHERE dpst_code = :dpst
        ");

            $dpstStmt->execute([
                ':dpst' => $dpst
            ]);

            $div = $dpstStmt->fetchColumn();


            $seqStmt = $this->obconn->prepare("
            SELECT nextval('plexecom_unique_sequence')
        ");

            $productStmt = $this->obconn->prepare("
            SELECT
                tpldesc,
                excisable,
                warehouse,
                otcode,
                mc,
                vc,
                fc,
                cos,
                dealer_price
            FROM product_master_vayu
            WHERE tplcode = :tplcode
            AND dpst = :dpst
        ");

            $hsnStmt = $this->obconn->prepare("
            SELECT substr(replace(hsn,':',''),1,4) AS hsn
            FROM elgi_item_master
            WHERE item_code = :tplcode
        ");

            $insertStmt = $this->obconn->prepare("
            INSERT INTO plexecom_customer_units
            (
                usr_name,
                emp_code,
                cuno,
                cuname,
                areacode,
                pono,
                indent_category,
                indent_number,
                indent_date,
                transporter,
                delterms_code,
                delivery_date,
                invaddr,"
                . "email,\n" // Added new field 01-07-26
                . "pincode,\n" // Added new field 01-07-26
                . "district,\n" // Added new field 01-07-26
                . "deladdr,
                dpst,
                tplcode,
                price,
                qty,
                salestax_code,
                sessionid,
                paycode,
                insby,
                edi_cuno,
                seqid,
                status,
                aoseries,
                otcode,
                warehouse,
                edi_delivery_date,
                edi_delivery_code,
                tpldesc,
                mc,
                vc,
                fc,
                cos,
                delivery_code,
                frtamount,
                company,
                adrcode,
                refno,
                hsn,
                state,
                country,
                edistatus,
                edi_date
            )
            VALUES
            (
                :uname,
                :emp_code,
                :cuno,
                :cname,
                :area,
                :pono,
                :indcat,
                :indno,
                current_date,
                :trans,
                :delterms,
                :deldate,
                :invaddr,"
                . ":email,\n" // Added new field 01-07-26
                . ":pincode,\n" // Added new field 01-07-26
                . ":district,\n" // Added new field 01-07-26
                . ":deladdr,
                :dpst,
                :tplcode,
                :price,
                :qty,
                :taxcode,
                :sid,
                :paycode,
                :insby,
                :edi_cuno,
                :seqid,
                :status,
                :aoseries,
                :otcode,
                :warehouse,
                :edi_delivery_date,
                :edi_delivery_code,
                :tpldesc,
                :mcval,
                :vcval,
                :fcval,
                :cosval,
                :shipto,
                :frtamount,
                :cmp,
                :adrcode,
                :refno,
                :hsn,
                :state,
                :country,
                :edistatus,
                :edi_date
            )
        ");

            // -----------------------------------------------------------------
            // PROCESS CART
            // -----------------------------------------------------------------

            foreach ($cartItems as $item) {

                $tplcode = $item['item_code'];

                $productStmt->execute([
                    ':tplcode' => $tplcode,
                    ':dpst'    => $dpst
                ]);

                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    continue;
                }

                $seqStmt->execute();
                $seqid = $seqStmt->fetchColumn();

                $hsnStmt->execute([
                    ':tplcode' => $tplcode
                ]);

                $hsn = $hsnStmt->fetchColumn();

                $taxColumn = ($country == 'IND' && $state == 'TN')
                    ? 'sgst'
                    : 'igst';


                $taxStmt = $this->obconn->prepare("SELECT {$taxColumn} AS taxcode FROM gst_hsn WHERE replace(hsn,':','') = :hsn AND company = :company");

                $taxStmt->execute([
                    ':hsn'     => $hsn,
                    ':company' => $cmp
                ]);

                $taxcode = $taxStmt->fetchColumn();

                if (in_array($indcat, [4, 6])) {
                    $taxcode = ($state == 'TN')
                        ? 'GSTAG05'
                        : 'GSTAG62';
                }

                $indent_number =
                    $div .
                    substr($area, 2, 2) .
                    'A' .
                    $newIndno;

                $success = $insertStmt->execute([
                    ':uname'             => $this->userId,
                    ':emp_code'          => "102464",
                    ':cuno'              => $cuno,
                    ':cname'             => '',
                    ':area'              => $area,
                    ':pono'              => $pono,
                    ':indcat'            => $indcat,
                    ':indno'             => $indent_number,
                    ':trans'             => $trans,
                    ':delterms'          => $delterms,
                    ':deldate'           => date('d.m.Y'),
                    ':invaddr'           => $invaddr,
                    ':email'             => $emailValue, // Added new field 01-07-26
                    ':pincode'           => $pincodeValue, // Added new field 01-07-26
                    ':district'          => $districtValue, // Added new field 01-07-26
                    ':deladdr'           => $deladdr,
                    ':dpst'              => $dpst,
                    ':tplcode'           => $tplcode,
                    ':price'             => $item['total_amount'],
                    ':qty'               => $item['qty'],
                    ':taxcode'           => $taxcode,
                    ':sid'               => $sid,
                    ':paycode'           => $paycode,
                    ':insby'             => '',
                    ':edi_cuno'          => $cuno,
                    ':seqid'             => $seqid,
                    ':status'            => 'A',
                    ':aoseries'          => $aoseries,
                    ':otcode'            => '511',
                    // ':warehouse'         => $product['warehouse'],
                    ':warehouse'         => '257',
                    ':edi_delivery_date' => date('d.m.Y'),
                    ':edi_delivery_code' => $deladdr,
                    ':tpldesc'           => pg_escape_string($product['tpldesc']),
                    ':mcval'             => ($product['mc'] === '' || $product['mc'] === null) ? null : $product['mc'],
                    ':vcval'             => ($product['vc'] === '' || $product['vc'] === null) ? null : $product['vc'],
                    ':fcval'             => ($product['fc'] === '' || $product['fc'] === null) ? null : $product['fc'],
                    ':cosval'            => ($product['cos'] === '' || $product['cos'] === null) ? null : $product['cos'],
                    ':shipto'            => $addrCode,
                    ':frtamount'         => $frtamount,
                    ':cmp'               => $cmp,
                    ':adrcode'           => $adrcode,
                    ':refno'             => $refno,
                    ':hsn'               => "80:11",
                    ':state'             => $state,
                    ':country'           => $country,
                    ':edistatus'         => 'Y',
                    ':edi_date'          => date('d.m.Y')
                ]);

                if (!$success) {
                    throw new Exception("Failed to insert order item");
                }
            }

            // -----------------------------------------------------------------
            // CLEAR CART
            // -----------------------------------------------------------------

            $deleteStmt = $this->obconn->prepare("
            DELETE FROM tbl_vayu_cartitems
            WHERE created_by = :createdBy
            AND status = 0
        ");

            $deleteStmt->execute([
                ':createdBy' => $this->customer_code
            ]);

            $this->obconn->commit();

            return json_encode([
                'status'   => 'success',
                'order_no' => $refno
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (Exception $e) {

            if ($this->obconn->inTransaction()) {
                $this->obconn->rollBack();
            }

            error_log(
                "[" . date('Y-m-d H:i:s') . "] " .
                    "User: {$this->userId} | " .
                    "Error: {$e->getMessage()} | " .
                    "Line: {$e->getLine()}"
            );

            return json_encode([
                'status'  => 'error',
                'message' => $e->getMessage() . $e->getLine()
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
    }

    public function submitCart_bk()

    {

        try {

            $addrCode  = $_POST['addressCode'];

            $area      = $_POST['area'];

            $indcat    = $_POST['orderCategory'];

            $deladdr   = strtoupper($_POST['addressCode']);

            $trans     = $_POST['transporter'];

            $delterms  = $_POST['deliveryTerm'];

            $paycode   = $_POST['paymentTerm'];

            $dpst      = "90092";

            $pono      = $_POST['pono'];

            $frtamount = (isset($_POST['freightAmount']) && $_POST['freightAmount'] !== '') ? $_POST['freightAmount'] : null;

            $this->obconn->beginTransaction();

            $rs = $this->obconn->prepare("select to_char(current_date,'YYMMDD') as ymd");

            $rs->execute();

            $getData = $rs->fetch(PDO::FETCH_ASSOC);

            $ymd = $getData['ymd'];

            $rs = $this->obconn->prepare("select nextval('dp_spares') as slno");

            $rs->execute();

            $getData = $rs->fetch(PDO::FETCH_ASSOC);

            $slno = $getData['slno'];

            $slno = str_pad($slno, 4, "0", STR_PAD_LEFT);

            $refno = "E/UNITS/" . $ymd . $slno;


            $cartStmt = $this->obconn->prepare("

            SELECT item_code,

                   item_name,

                   qty,

                   price,

                   total_amount

            FROM tbl_vayu_cartitems

            WHERE created_by = :createdBy

            AND status = 0

        ");

            $cartStmt->execute([

                ':createdBy' => $this->userId

            ]);

            $cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($cartItems)) {

                throw new Exception("Cart is empty.");
            }


            $stmt = $this->obconn->prepare("

            SELECT max(substr(indent_number,7,9)) AS maxindno

            FROM plexecom_customer_units15062026

            WHERE areacode = :area

            AND indent_date >= '01.04.2022'

        ");

            $stmt->execute([

                ':area' => $area

            ]);

            $maxIndno = $stmt->fetchColumn();

            $letter = substr($maxIndno, 0, 1);

            $number = substr($maxIndno, 1, 2);

            $letter = $letter ?: 'A';

            $number = $number ?: 0;

            if ($number == 99) {

                $letter = chr(ord($letter) + 1);

                $number = 1;
            } else {

                $number++;
            }

            $number   = str_pad($number, 2, "0", STR_PAD_LEFT);

            $newIndno = $indcat . 'N' . $letter . $number;

            $cmp      = "401";

            $cuno     = "CU1A03751";

            $aoseries = "201";

            $state    = "TN";

            $sid      = session_id();



            $customerStmt = $this->obconn->prepare("

            SELECT

                cm.adr_code,

                cm.country,

                ca.custaddr

            FROM customer_master cm

            LEFT JOIN customer_address ca

                ON ca.adr_code = cm.adr_code

               AND ca.cuno = cm.cuno

            WHERE cm.cuno = :cuno

        ");

            $customerStmt->execute([

                ':cuno' => $cuno

            ]);

            $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

            $adrcode = $customer['adr_code'];

            $country = trim($customer['country']);

            $invaddr = pg_escape_string($customer['custaddr']);



            $dpstStmt = $this->obconn->prepare("

            SELECT product_group

            FROM dpst_master

            WHERE dpst_code = :dpst

        ");

            $dpstStmt->execute([

                ':dpst' => $dpst

            ]);

            $div = $dpstStmt->fetchColumn();


            $seqStmt = $this->obconn->prepare("

            SELECT nextval('plexecom_unique_sequence')

        ");

            $productStmt = $this->obconn->prepare("

            SELECT

                tpldesc,

                excisable,

                warehouse,

                otcode,

                mc,

                vc,

                fc,

                cos,

                dealer_price

            FROM product_master_vayu

            WHERE tplcode = :tplcode

            AND dpst = :dpst

        ");

            $hsnStmt = $this->obconn->prepare("

            SELECT substr(replace(hsn,':',''),1,4) AS hsn

            FROM elgi_item_master

            WHERE item_code = :tplcode

        ");

            $insertStmt = $this->obconn->prepare("

            INSERT INTO plexecom_customer_units

            (

                usr_name,

                emp_code,

                cuno,

                cuname,

                areacode,

                pono,

                indent_category,

                indent_number,

                indent_date,

                transporter,

                delterms_code,

                delivery_date,

                invaddr,

                deladdr,

                dpst,

                tplcode,

                price,

                qty,

                salestax_code,

                sessionid,

                paycode,

                insby,

                edi_cuno,

                seqid,

                status,

                aoseries,

                otcode,

                warehouse,

                edi_delivery_date,

                edi_delivery_code,

                tpldesc,

                mc,

                vc,

                fc,

                cos,

                delivery_code,

                frtamount,

                company,

                adrcode,

                refno,

                hsn,

                state,

                country,

                edistatus,

                edi_date

            )

            VALUES

            (

                :uname,

                :emp_code,

                :cuno,

                :cname,

                :area,

                :pono,

                :indcat,

                :indno,

                current_date,

                :trans,

                :delterms,

                :deldate,

                :invaddr,

                :deladdr,

                :dpst,

                :tplcode,

                :price,

                :qty,

                :taxcode,

                :sid,

                :paycode,

                :insby,

                :edi_cuno,

                :seqid,

                :status,

                :aoseries,

                :otcode,

                :warehouse,

                :edi_delivery_date,

                :edi_delivery_code,

                :tpldesc,

                :mcval,

                :vcval,

                :fcval,

                :cosval,

                :shipto,

                :frtamount,

                :cmp,

                :adrcode,

                :refno,

                :hsn,

                :state,

                :country,

                :edistatus,

                :edi_date

            )

        ");

            // -----------------------------------------------------------------

            // PROCESS CART

            // -----------------------------------------------------------------

            foreach ($cartItems as $item) {

                $tplcode = $item['item_code'];

                $productStmt->execute([

                    ':tplcode' => $tplcode,

                    ':dpst'    => $dpst

                ]);

                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {

                    continue;
                }

                $seqStmt->execute();

                $seqid = $seqStmt->fetchColumn();

                $hsnStmt->execute([

                    ':tplcode' => $tplcode

                ]);

                $hsn = $hsnStmt->fetchColumn();

                $taxColumn = ($country == 'IND' && $state == 'TN')

                    ? 'sgst'

                    : 'igst';


                $taxStmt = $this->obconn->prepare("SELECT {$taxColumn} AS taxcode FROM gst_hsn WHERE replace(hsn,':','') = :hsn AND company = :company");

                $taxStmt->execute([

                    ':hsn'     => $hsn,

                    ':company' => $cmp

                ]);

                $taxcode = $taxStmt->fetchColumn();

                if (in_array($indcat, [4, 6])) {

                    $taxcode = ($state == 'TN')

                        ? 'GSTAG05'

                        : 'GSTAG62';
                }

                $indent_number =

                    $div .

                    substr($area, 2, 2) .

                    'A' .

                    $newIndno;

                $success = $insertStmt->execute([

                    ':uname'             => $this->userId,

                    ':emp_code'          => "102464",

                    ':cuno'              => $cuno,

                    ':cname'             => '',

                    ':area'              => $area,

                    ':pono'              => $pono,

                    ':indcat'            => $indcat,

                    ':indno'             => $indent_number,

                    ':trans'             => $trans,

                    ':delterms'          => $delterms,

                    ':deldate'           => date('d.m.Y'),

                    ':invaddr'           => $invaddr,

                    ':deladdr'           => $deladdr,

                    ':dpst'              => $dpst,

                    ':tplcode'           => $tplcode,

                    ':price'             => $item['total_amount'],

                    ':qty'               => $item['qty'],

                    ':taxcode'           => $taxcode,

                    ':sid'               => $sid,

                    ':paycode'           => $paycode,

                    ':insby'             => '',

                    ':edi_cuno'          => $cuno,

                    ':seqid'             => $seqid,

                    ':status'            => 'A',

                    ':aoseries'          => $aoseries,

                    ':otcode'            => '611',

                    ':warehouse'         => $product['warehouse'],

                    ':edi_delivery_date' => date('d.m.Y'),

                    ':edi_delivery_code' => $deladdr,

                    ':tpldesc'           => pg_escape_string($product['tpldesc']),

                    ':mcval'             => $product['mc'],

                    ':vcval'             => $product['vc'],

                    ':fcval'             => $product['fc'],

                    ':cosval'            => $product['cos'],

                    ':shipto'            => $addrCode,

                    ':frtamount'         => $frtamount,

                    ':cmp'               => $cmp,

                    ':adrcode'           => $adrcode,

                    ':refno'             => $refno,

                    ':hsn'               => "80:11",

                    ':state'             => $state,

                    ':country'           => $country,

                    ':edistatus'         => 'Y',

                    ':edi_date'          => date('d.m.Y')

                ]);

                if (!$success) {

                    throw new Exception("Failed to insert order item");
                }
            }

            // -----------------------------------------------------------------

            // CLEAR CART

            // -----------------------------------------------------------------

            $deleteStmt = $this->obconn->prepare("

            DELETE FROM tbl_vayu_cartitems

            WHERE created_by = :createdBy

            AND status = 0

        ");

            $deleteStmt->execute([

                ':createdBy' => $this->userId

            ]);

            $this->obconn->commit();

            return json_encode([

                'status'   => 'success',

                'order_no' => $refno

            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (Exception $e) {

            if ($this->obconn->inTransaction()) {

                $this->obconn->rollBack();
            }

            error_log(

                "[" . date('Y-m-d H:i:s') . "] " .

                    "User: {$this->userId} | " .

                    "Error: {$e->getMessage()} | " .

                    "Line: {$e->getLine()}"

            );

            return json_encode([

                'status'  => 'error',

                'message' => $e->getMessage() . $e->getLine()

            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
    }



    private function generateOrderNo()
    {

        $date = date('Y'); // 20260604

        $stmt = $this->obconn->prepare("SELECT order_no FROM tbl_vayu_orders_header WHERE order_no LIKE :prefix ORDER BY id DESC LIMIT 1");
        $prefix = "ORD/$date/%";
        $stmt->execute([':prefix' => $prefix]);

        $lastOrder = $stmt->fetchColumn();

        if ($lastOrder) {
            $lastSeq = (int) substr($lastOrder, -6);
            $newSeq = $lastSeq + 1;
        } else {
            $newSeq = 1;
        }

        $orderNo = sprintf("ORD/%s/%06d", $date, $newSeq);

        return $orderNo;
    }

    // public function getPendingOrderList()
    // {
    //     try {

    //         $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
    //         $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
    //         $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;

    //         $search = $_POST['search']['value'] ?? '';

    //         $where = '';
    //         $params = [];

    //         if (!empty($search)) {
    //             $where = "AND (ordno ILIKE :search)";
    //             $params[':search'] = "%{$search}%";
    //         }


    //         $totalQry = $this->dpconn->prepare("SELECT COUNT(*) FROM pendingordersnew p LEFT OUTER JOIN dpst_master dm ON TRIM(p.dpst) = dm.dpst_code::text LEFT JOIN tbl_commitment tc ON p.ordno = tc.orderno AND p.posno = tc.posno WHERE p.company != 600 and p.cuno=:uname");
    //         $totalQry->bindParam(':uname', $this->customer_code, PDO::PARAM_STR);
    //         $totalQry->execute();
    //         $totalRecords = $totalQry->fetchColumn();
    //         $countSql = "SELECT COUNT(*) FROM pendingordersnew p LEFT OUTER JOIN dpst_master dm ON TRIM(p.dpst) = dm.dpst_code::text LEFT JOIN tbl_commitment tc ON p.ordno = tc.orderno AND p.posno = tc.posno WHERE p.company != 600 AND p.cuno=:uname {$where}";
    //         $countStmt = $this->dpconn->prepare($countSql);
    //         $countStmt->bindParam(':uname', $this->customer_code, PDO::PARAM_STR);
    //         foreach ($params as $key => $value) {
    //             $countStmt->bindValue($key, $value);
    //         }
    //         $countStmt->execute();
    //         $filteredRecords = $countStmt->fetchColumn();

    //         $sql = "SELECT cuno,cuname,ordno,orddt,itemcode,itemdesc,qty,unitvalue,currency,pono,dpst,dpst_desc,delydt,otcode,tbl_commitment.comm_dt FROM pendingordersnew LEFT OUTER JOIN dpst_master ON trim(dpst)=dpst_code::text LEFT JOIN tbl_commitment ON pendingordersnew.ordno=tbl_commitment.orderno AND pendingordersnew.posno=tbl_commitment.posno WHERE company!=600 AND cuno=:uname {$where} ORDER BY orddt DESC LIMIT :length OFFSET :start";

    //         $stmt = $this->dpconn->prepare($sql);

    //         $stmt->bindParam(':uname', $this->customer_code, PDO::PARAM_STR);

    //         foreach ($params as $key => $value) {
    //             $stmt->bindValue($key, $value);
    //         }

    //         $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    //         $stmt->bindValue(':start', $start, PDO::PARAM_INT);

    //         $stmt->execute();

    //         $data = [];

    //         while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

    //             $data[] = [
    //                 'cuno'            => $row['cuno'],
    //                 'pono'            => $row['pono'],
    //                 'aono'             => $row['ordno'],
    //                 'aodate'             => $row['orddt'],
    //                 'delydt'         => date('d-m-Y', strtotime($row['delydt'])),
    //                 'lines' => '<a href="order_data.php?order=' . urlencode($row['ordno']) .
    //                     '&cuno=' . urlencode($row['cuno']) . '&reference=pending_order" target="_blank" style="text-decoration:none;">View</a>'
    //             ];
    //         }
    //         return json_encode([
    //             'draw'            => $draw,
    //             'recordsTotal'    => (int)$totalRecords,
    //             'recordsFiltered' => (int)$filteredRecords,
    //             'data'            => $data
    //         ]);
    //     } catch (Exception $e) {

    //         error_log($e->getMessage());

    //         return json_encode([
    //             'draw' => 0,
    //             'recordsTotal' => 0,
    //             'recordsFiltered' => 0,
    //             'data' => [],
    //             'error' => $e->getMessage() . $e->getLine()
    //         ]);
    //     }
    // }



    // Start of New Pending Order List
    public function getPendingOrderListNew()
    {
        try {

            $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
            $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
            $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;

            $search = $_POST['search']['value'] ?? '';

            /* // user wise data
            admin_refresh_session_role($this->obconn);
            $seeAll = is_system_admin() || is_management_user();
            $cunoFilter = $seeAll ? '1=1' : 'p.cuno = :uname';
*/
            $seeAll = '';
            $cunoFilter = 'p.cuno = :uname';



            $where = '';
            $params = [];

            if (!empty($search)) {
                $where = "AND (
                    p.ordno ILIKE :search
                    OR COALESCE(p.pono, '') ILIKE :search
                    OR COALESCE(p.indentno, '') ILIKE :search
                    OR COALESCE(p.cuname, '') ILIKE :search
                )";
                $params[':search'] = "%{$search}%";
            }

            $baseWhere = "p.company != 600 AND {$cunoFilter} {$where}";

            $totalQry = $this->dpconn->prepare("
                SELECT COUNT(*) FROM (
                    SELECT p.ordno
                    FROM pendingordersnew p
                    WHERE p.company != 600 AND {$cunoFilter}
                    GROUP BY p.ordno
                ) x
            ");
            if (!$seeAll) {
                $totalQry->bindParam(':uname', $this->customer_code, PDO::PARAM_STR);
            }
            $totalQry->execute();
            $totalRecords = $totalQry->fetchColumn();

            $countSql = "
                SELECT COUNT(*) FROM (
                    SELECT p.ordno
                    FROM pendingordersnew p
                    WHERE {$baseWhere}
                    GROUP BY p.ordno
                ) x
            ";
            $countStmt = $this->dpconn->prepare($countSql);
            if (!$seeAll) {
                $countStmt->bindParam(':uname', $this->customer_code, PDO::PARAM_STR);
            }
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $filteredRecords = $countStmt->fetchColumn();

            $sql = "
                SELECT
                    p.cuno,
                    MAX(p.cuname) AS cuname,
                    p.ordno,
                    MAX(p.orddt) AS orddt,
                    MAX(COALESCE(p.pono, '')) AS pono,
                    MAX(COALESCE(p.indentno, '')) AS indentno
                FROM pendingordersnew p
                WHERE {$baseWhere}
                GROUP BY p.cuno, p.ordno
                ORDER BY MAX(p.orddt) DESC
                LIMIT :length OFFSET :start
            ";

            $stmt = $this->dpconn->prepare($sql);
            if (!$seeAll) {
                $stmt->bindParam(':uname', $this->customer_code, PDO::PARAM_STR);
            }
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':length', $length, PDO::PARAM_INT);
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $plexecomMap = $this->fetchPendingOrderPlexecomDetails(array_column($rows, 'ordno'), $seeAll);

            $data = [];

            foreach ($rows as $row) {
                $ordno = trim((string) ($row['ordno'] ?? ''));
                $cuno = trim((string) ($row['cuno'] ?? ''));
                $plexecom = $plexecomMap[$ordno] ?? [];

                $orderNumber = trim((string) ($row['indentno'] ?? ''));
                if ($orderNumber === '') {
                    $orderNumber = trim((string) ($plexecom['refno'] ?? ''));
                }
                if ($orderNumber === '') {
                    $orderNumber = '-';
                }

                $customerName = trim((string) ($row['cuname'] ?? ''));
                $customerLabel = $customerName !== ''
                    ? $customerName
                    : '-';

                $actionCell = '';
                if ($ordno !== '') {
                    $ordnoJs = htmlspecialchars($ordno, ENT_QUOTES, 'UTF-8');
                    /* $actionCell = '<div class="cart-action-group">'
                        . '<button type="button" class="cart-action-btn cart-action-btn--breakup" '
                        . 'onclick="openPendingOrderPriceBreakup(\'' . $ordnoJs . '\')" title="Price Breakup">'
                        . '<i class="bi bi-receipt"></i></button>'
                        . '<a href="order_data_new.php?order=' . urlencode($ordno)
                        . '&cuno=' . urlencode($cuno)
                        . '&reference=pending_order" target="_blank" class="btn btn-sm btn-outline-dark" title="View">'
                        . '<i class="fa fa-eye"></i></a>'
                        . '</div>';
                        */
                    $actionCell = '<div class="cart-action-group">'
                        . '<a href="order_data_new.php?order=' . urlencode($ordno)
                        . '&cuno=' . urlencode($cuno)
                        . '&reference=pending_order" target="_blank" class="btn btn-sm btn-outline-dark" title="View">'
                        . '<i class="fa fa-eye"></i></a>'
                        . '</div>';
                }

                $data[] = [
                    'order_number'     => htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8'),
                    'ao_number'        => $ordno !== '' ? htmlspecialchars($ordno, ENT_QUOTES, 'UTF-8') : '-',
                    'order_date'       => !empty($row['orddt']) ? date('d-m-Y', strtotime($row['orddt'])) : '-',
                    'customer'         => htmlspecialchars($customerLabel, ENT_QUOTES, 'UTF-8'),
                    'po_number'        => trim((string) ($row['pono'] ?? '')) !== ''
                        ? htmlspecialchars(trim((string) $row['pono']), ENT_QUOTES, 'UTF-8')
                        : '-',
                    'invoice_address'  => $this->formatPendingOrderTextAddress($plexecom['invaddr'] ?? null),
                    'payment_terms'    => $this->displayOrDash($plexecom['payment_terms'] ?? null),
                    'delivery_terms'   => $this->displayOrDash($plexecom['delivery_terms'] ?? null),
                    'delivery_address' => $this->formatPendingOrderTextAddress($plexecom['deladdr'] ?? null),
                    'action'           => $actionCell,
                ];
            }

            return json_encode([
                'draw'            => $draw,
                'recordsTotal'    => (int)$totalRecords,
                'recordsFiltered' => (int)$filteredRecords,
                'data'            => $data
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (Exception $e) {

            error_log($e->getMessage());

            return json_encode([
                'draw' => 0,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage() . $e->getLine()
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
    }

    /**
     * Display-only consolidated price breakup for a pending LN order (all lines).
     */
    public function getPendingOrderPriceBreakup()
    {
        try {
            $ordno = trim((string) ($_POST['ordno'] ?? ''));

            if ($ordno === '') {
                return json_encode([
                    'status' => false,
                    'message' => 'Invalid order number.',
                ]);
            }

            $stmt = $this->dpconn->prepare("
                SELECT itemcode, itemdesc, qty, unitvalue
                FROM pendingordersnew
                WHERE company != 600
                  AND cuno = :cuno
                  AND TRIM(ordno) = :ordno
                ORDER BY posno
            ");
            $stmt->bindValue(':cuno', $this->customer_code, PDO::PARAM_STR);
            $stmt->bindValue(':ordno', $ordno, PDO::PARAM_STR);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$items) {
                return json_encode([
                    'status' => false,
                    'message' => 'No pending order lines found.',
                ]);
            }

            $totals = [
                'price_inclusive' => 0.0,
                'price_before_gst' => 0.0,
                'freight' => 0.0,
                'line_gst' => 0.0,
                'cgst_amount' => 0.0,
                'sgst_amount' => 0.0,
                'cgst_rate' => null,
                'sgst_rate' => null,
            ];

            foreach ($items as $item) {
                $amounts = $this->computeCartLineGstAmounts(
                    (float) ($item['unitvalue'] ?? 0),
                    (float) ($item['qty'] ?? 0)
                );

                $totals['price_inclusive'] += (float) $amounts['line_total'];
                $totals['price_before_gst'] += (float) $amounts['line_base'];
                $totals['freight'] += (float) $amounts['freight'];
                $totals['line_gst'] += (float) $amounts['line_gst'];
                $totals['cgst_amount'] += (float) $amounts['cgst_amount'];
                $totals['sgst_amount'] += (float) $amounts['sgst_amount'];

                if ($totals['cgst_rate'] === null) {
                    $totals['cgst_rate'] = $amounts['cgst_rate'] ?? null;
                    $totals['sgst_rate'] = $amounts['sgst_rate'] ?? null;
                }
            }

            $totals['price_before_gst'] = round($totals['price_before_gst'], 2);
            $totals['freight'] = round($totals['freight'], 2);
            $totals['line_gst'] = round($totals['line_gst'], 2);
            $totals['cgst_amount'] = round($totals['cgst_amount'], 2);
            $totals['sgst_amount'] = round($totals['sgst_amount'], 2);
            $totals['price_inclusive'] = round($totals['price_inclusive'], 2);

            return $this->formatPriceBreakupResponse($totals, [
                'item_count' => count($items),
                'ordno' => $ordno,
            ]);
        } catch (Exception $e) {
            error_log($e->getMessage());

            return json_encode([
                'status' => false,
                'message' => 'Unable to fetch pending order price breakup.',
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
    }

    private function displayOrDash($value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : '-';
    }


    /**
     * Fetch invoice/delivery address and payment/delivery terms from plexecom_customer_units
     */
    private function fetchPendingOrderPlexecomDetails(array $orderNumbers): array
    {
        $orderNumbers = array_values(array_filter(array_unique(array_map('trim', $orderNumbers))));

        if ($orderNumbers === []) {
            return [];
        }

        $placeholders = [];
        $params = [':cuno' => $this->customer_code];

        foreach ($orderNumbers as $index => $orderNumber) {
            $paramKey = ':ordno' . $index;
            $placeholders[] = $paramKey;
            $params[$paramKey] = $orderNumber;
        }

        $sql = "
            SELECT DISTINCT ON (TRIM(a.order_number))
                TRIM(a.order_number) AS order_number,
                TRIM(COALESCE(a.refno, '')) AS refno,
                a.invaddr,
                a.deladdr,
                COALESCE(NULLIF(TRIM(e.pay_desc), ''), NULLIF(TRIM(a.paycode), '')) AS payment_terms,
                COALESCE(NULLIF(TRIM(c.delivery_term), ''), NULLIF(TRIM(a.delterms_code), '')) AS delivery_terms
            FROM plexecom_customer_units a
            LEFT JOIN tbl_vayu_delivery_term c
                ON a.delterms_code = c.delivery_code::varchar
            LEFT JOIN spp_payterm_master e
                ON a.paycode = e.pay_code::varchar
            WHERE a.cuno = :cuno
              AND TRIM(a.order_number) IN (" . implode(', ', $placeholders) . ")
            ORDER BY TRIM(a.order_number), a.indent_date DESC, a.oid DESC
        ";

        $stmt = $this->obconn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[trim((string) $row['order_number'])] = $row;
        }

        return $map;
    }

    private function formatPendingOrderTextAddress($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        $lines = [];
        foreach (preg_split('/\R+/', $value) as $line) {
            $line = trim($line);
            if ($line !== '' && !in_array($line, $lines, true)) {
                $lines[] = $line;
            }
        }

        if ($lines === []) {
            return '-';
        }

        $fullText = implode(', ', $lines);
        $textLen = function_exists('mb_strlen') ? mb_strlen($fullText) : strlen($fullText);
        if ($textLen > 80) {
            $display = (function_exists('mb_substr') ? mb_substr($fullText, 0, 77) : substr($fullText, 0, 77)) . '...';
        } else {
            $display = $fullText;
        }

        return '<span title="' . htmlspecialchars($fullText, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($display, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }
    // End of New Pending Order List







    public function getPendingOrderList()
    {
        try {

            $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
            $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
            $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;

            $search = $_POST['search']['value'] ?? '';

            $where = '';
            $params = [];

            if (!empty($search)) {
                $where = "AND (
                    p.ordno ILIKE :search
                    OR COALESCE(p.pono, '') ILIKE :search
                    OR COALESCE(p.indentno, '') ILIKE :search
                )";
                $params[':search'] = "%{$search}%";
            }


            $totalQry = $this->dpconn->prepare("SELECT COUNT(*) FROM pendingordersnew p LEFT OUTER JOIN dpst_master dm ON TRIM(p.dpst) = dm.dpst_code::text LEFT JOIN tbl_commitment tc ON p.ordno = tc.orderno AND p.posno = tc.posno WHERE p.company != 600 and p.cuno=:uname");
            $totalQry->bindParam(':uname', $this->customer_code, PDO::PARAM_STR);
            $totalQry->execute();
            $totalRecords = $totalQry->fetchColumn();
            $countSql = "SELECT COUNT(*) FROM pendingordersnew p LEFT OUTER JOIN dpst_master dm ON TRIM(p.dpst) = dm.dpst_code::text LEFT JOIN tbl_commitment tc ON p.ordno = tc.orderno AND p.posno = tc.posno WHERE p.company != 600 AND p.cuno=:uname {$where}";
            $countStmt = $this->dpconn->prepare($countSql);
            $countStmt->bindParam(':uname', $this->customer_code, PDO::PARAM_STR);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $filteredRecords = $countStmt->fetchColumn();

            $sql = "SELECT p.cuno,p.cuname,p.ordno,p.orddt,p.itemcode,p.itemdesc,p.qty,p.unitvalue,p.currency,p.pono,p.dpst,dm.dpst_desc,p.delydt,p.otcode,p.indentno,tc.comm_dt FROM pendingordersnew p LEFT OUTER JOIN dpst_master dm ON trim(p.dpst)=dm.dpst_code::text LEFT JOIN tbl_commitment tc ON p.ordno=tc.orderno AND p.posno=tc.posno WHERE p.company!=600 AND p.cuno=:uname {$where} ORDER BY p.orddt DESC LIMIT :length OFFSET :start";

            $stmt = $this->dpconn->prepare($sql);

            $stmt->bindParam(':uname', $this->customer_code, PDO::PARAM_STR);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(':length', $length, PDO::PARAM_INT);
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);

            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $refMap = $this->fetchPendingOrderRefMap(array_column($rows, 'ordno'));
            $statusCache = [];

            $data = [];

            foreach ($rows as $row) {
                $ordno = trim((string) ($row['ordno'] ?? ''));
                $cuno = trim((string) ($row['cuno'] ?? ''));
                $refNo = $refMap[$ordno] ?? trim((string) ($row['indentno'] ?? ''));

                if ($refNo === '') {
                    $refNo = '-';
                }

                if (!isset($statusCache[$ordno])) {
                    $statusCache[$ordno] = $this->resolveRecentOrderStatus($ordno, $cuno);
                }

                $getRef = $this->obconn->prepare("SELECT refno FROM plexecom_customer_units WHERE pono =:pono ORDER BY oid desc limit 1");
                $getRef->bindParam(":pono", $row['pono']);
                $getRef->execute();
                if ($getRef->rowCount() > 0) {
                    $getRefNo = $getRef->fetch(PDO::FETCH_ASSOC);
                    $refNo = $getRefNo['refno'];
                } else {
                    $refNo = '-';
                }

                $refNoHtml = htmlspecialchars($refNo, ENT_QUOTES, 'UTF-8');
                $actionCell = $ordno !== ''
                    ? '<a href="order_data.php?order=' . urlencode($ordno)
                    . '&cuno=' . urlencode($cuno)
                    . '&reference=pending_order" target="_blank" class="btn btn-sm btn-outline-dark" title="View">'
                    . '<i class="fa fa-eye"></i></a>'
                    : '';

                $data[] = [
                    'ref_no'        => $refNoHtml,
                    'po_number'     => trim((string) ($row['pono'] ?? '')) !== '' ? trim((string) $row['pono']) : '-',
                    'ao_number'     => $ordno !== '' ? $ordno : '-',
                    'delivery_date' => !empty($row['delydt']) ? date('d-m-Y', strtotime($row['delydt'])) : '-',
                    'order_status'  => $statusCache[$ordno],
                    'action'        => $actionCell,
                ];
            }
            return json_encode([
                'draw'            => $draw,
                'recordsTotal'    => (int)$totalRecords,
                'recordsFiltered' => (int)$filteredRecords,
                'data'            => $data
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (Exception $e) {

            error_log($e->getMessage());

            return json_encode([
                'draw' => 0,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage() . $e->getLine()
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
    }

    public function getOrderAcknowledgeList()
    {
        try {

            $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
            $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
            $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;

            $search = $_POST['search']['value'] ?? '';

            $where = '';
            $params = [];

            if (!empty($search)) {
                $where = "AND (ordno ILIKE :search)";
                $params[':search'] = "%{$search}%";
            }


            $totalQry = $this->dpconn->prepare("SELECT COUNT(*) FROM ( SELECT DISTINCT m.cuno,m.ordno,m.ord_date,m.purno,m.dpst,d.dpst_desc FROM maintdealer m  LEFT OUTER JOIN dpst_master d ON trim(m.dpst)=d.dpst_code::text WHERE  company!=600 AND cuno = :uname) x ");
            $totalQry->bindParam(':uname', $this->customer_code, PDO::PARAM_STR);
            $totalQry->execute();
            $totalRecords = $totalQry->fetchColumn();

            $countStmt = $this->dpconn->prepare("SELECT COUNT(*) FROM (SELECT DISTINCT m.cuno,m.ordno,m.ord_date,m.purno,m.dpst,d.dpst_desc FROM maintdealer m  LEFT OUTER JOIN dpst_master d ON trim(m.dpst)=d.dpst_code::text WHERE  company!=600 AND cuno = :uname {$where}) x");
            $countStmt->bindParam(':uname', $this->customer_code, PDO::PARAM_STR);
            $totalQry->execute();
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $filteredRecords = $countStmt->fetchColumn();

            $sql = "SELECT DISTINCT m.cuno,m.ordno,m.ord_date,m.purno,m.dpst,d.dpst_desc FROM maintdealer m  LEFT OUTER JOIN dpst_master d ON trim(m.dpst)=d.dpst_code::text WHERE  company!=600 AND cuno = :uname order by m.ord_date DESC LIMIT :length OFFSET :start";

            $stmt = $this->dpconn->prepare($sql);

            $stmt->bindParam(':uname', $this->customer_code, PDO::PARAM_STR);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(':length', $length, PDO::PARAM_INT);
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);

            $stmt->execute();

            $data = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $refMap = $this->fetchPendingOrderRefMap(array_column($row, 'ordno'));
                $data[] = [
                    'ref_no'    => $refMap,
                    'po_number' => $row['purno'],
                    'cuno'      => $row['cuno'],
                    'ao_number'      => $row['ordno'],
                    'purno'     => $row['purno'],
                    'ao_date'     => $row['ord_date'],
                    'ord_date'  => date('d-m-Y', strtotime($row['ord_date'])),
                    'lines' => '<a class="btn btn-sm btn-outline-dark" href="order_data.php?order=' . urlencode($row['ordno']) .
                        '&cuno=' . urlencode($row['cuno']) . '&reference=order_acknowledgement" target="_blank" style="text-decoration:none;"><i class="fa fa-eye"></i></a>'
                ];
            }
            return json_encode([
                'draw'            => $draw,
                'recordsTotal'    => (int)$totalRecords,
                'recordsFiltered' => (int)$filteredRecords,
                'data'            => $data
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return json_encode([
                'draw' => 0,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage() . $e->getLine()
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
    }

    private function fetchPendingOrderRefMap(array $orderNumbers): array
    {
        $orderNumbers = array_values(array_filter(array_unique(array_map('trim', $orderNumbers))));

        if ($orderNumbers === []) {
            return [];
        }

        $placeholders = [];
        $params = [':cuno' => $this->customer_code];

        foreach ($orderNumbers as $index => $orderNumber) {
            $paramKey = ':ordno' . $index;
            $placeholders[] = $paramKey;
            $params[$paramKey] = $orderNumber;
        }

        $sql = "
            SELECT DISTINCT ON (TRIM(a.order_number))
                TRIM(a.order_number) AS order_number,
                TRIM(a.refno) AS refno
            FROM plexecom_customer_units a
            WHERE a.cuno = :cuno
              AND TRIM(a.order_number) IN (" . implode(', ', $placeholders) . ")
            ORDER BY TRIM(a.order_number), a.indent_date DESC
        ";

        $stmt = $this->obconn->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        $refMap = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $refMap[trim((string) $row['order_number'])] = trim((string) $row['refno']);
        }

        return $refMap;
    }

    public function getRecentOrders()
    {
        try {
            $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
            $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
            $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
            $search = $_POST['search']['value'] ?? '';
            $where = '';
            $params = [];

            // Managers (Management role) see all recent orders; others stay user-scoped.
            // Refresh role from DB so AJAX requests are not stuck with a missing/stale session role.
            admin_refresh_session_role($this->obconn);
            $seeAll = is_system_admin() || is_management_user();
            $showAddedBy = is_system_admin() || is_management_user() || is_ccs_admin_user();

            if (!empty($search)) {
                $where = "AND (
                    a.refno ILIKE :search
                    OR COALESCE(a.pono, '') ILIKE :search
                    OR a.tplcode ILIKE :search
                    OR a.tpldesc ILIKE :search
                    OR COALESCE(d.order_category, '') ILIKE :search
                ";
                if ($showAddedBy) {
                    $where .= "
                    OR COALESCE(um_by_emp.name, '') ILIKE :search
                    OR COALESCE(um_by_usr.name, '') ILIKE :search
                    OR COALESCE(a.usr_name, '') ILIKE :search
                    OR COALESCE(a.emp_code::text, '') ILIKE :search
                    ";
                }
                $where .= ")";
                $params[':search'] = "%{$search}%";
            }

            $userWhere = $seeAll ? '1=1' : 'a.cuno = :createdBy';
            $totalUserWhere = $seeAll ? '1=1' : 'cuno = :createdBy';

            $addedByJoin = '';
            $addedBySelect = '';
            if ($showAddedBy) {
                // Map emp_code ? user_master.username for dealer name; fallback to usr_name.
                $addedByJoin = "
                LEFT JOIN user_master AS um_by_emp
                    ON um_by_emp.deleted_at IS NULL
                   AND TRIM(COALESCE(a.emp_code::text, '')) <> ''
                   AND TRIM(um_by_emp.username) = TRIM(a.emp_code::text)
                LEFT JOIN user_master AS um_by_usr
                    ON um_by_usr.deleted_at IS NULL
                   AND TRIM(COALESCE(a.usr_name, '')) <> ''
                   AND TRIM(um_by_usr.username) = TRIM(a.usr_name)
               ";
                $addedBySelect = ",
                    COALESCE(
                        NULLIF(TRIM(um_by_emp.name), ''),
                        NULLIF(TRIM(um_by_usr.name), ''),
                        NULLIF(TRIM(a.usr_name), ''),
                        '-'
                    ) AS added_by_name";
            }

            $joinSql = "
                FROM plexecom_customer_units AS a
                LEFT JOIN tbl_vayu_delivery_term AS c
                    ON a.delterms_code = c.delivery_code::varchar
                LEFT JOIN tbl_vayu_order_category AS d
                    ON a.indent_category::varchar = d.id::varchar
                LEFT JOIN spp_payterm_master AS e
                    ON a.paycode = e.pay_code::varchar
                LEFT JOIN transporter_master AS f
                    ON a.transporter = f.trans_code
                {$addedByJoin}
                WHERE {$userWhere}
                {$where}
            ";
            $totalQry = $this->obconn->prepare("
                SELECT COUNT(DISTINCT refno)
                FROM plexecom_customer_units
                WHERE {$totalUserWhere}
            ");
            if (!$seeAll) {
                $totalQry->bindParam(':createdBy', $this->customer_code, PDO::PARAM_STR);
            }
            $totalQry->execute();
            $totalRecords = (int) $totalQry->fetchColumn();
            $countSql = "SELECT COUNT(*) FROM (SELECT DISTINCT a.refno {$joinSql}) recent_orders";
            $countStmt = $this->obconn->prepare($countSql);
            if (!$seeAll) {
                $countStmt->bindParam(':createdBy', $this->customer_code, PDO::PARAM_STR);
            }
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $filteredRecords = (int) $countStmt->fetchColumn();
            $sql = "
                SELECT DISTINCT ON (a.refno)
                    a.refno,
                    a.cuno,
                    a.order_number,
                    a.pono,
                    a.indent_date,
                    a.emp_code,
                    a.usr_name,
                    d.order_category,
                    c.delivery_term,
                    e.pay_desc,
                    f.trans_name AS transporter
                    {$addedBySelect}
                {$joinSql}
                ORDER BY a.refno DESC, a.indent_date DESC
                LIMIT :length OFFSET :start
            ";
            $stmt = $this->obconn->prepare($sql);
            if (!$seeAll) {
                $stmt->bindParam(':createdBy', $this->customer_code, PDO::PARAM_STR);
            }
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':length', $length, PDO::PARAM_INT);
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);
            $stmt->execute();
            $data = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $refno = (string) ($row['refno'] ?? '');
                $orderNumber = trim((string) ($row['order_number'] ?? ''));
                $orderCuno = trim((string) ($row['cuno'] ?? $this->customer_code));
                $orderStatus = $this->resolveRecentOrderStatus($orderNumber, $orderCuno);
                $rowData = [
                    'ref_no'           => htmlspecialchars($refno ??  '-', ENT_QUOTES, 'UTF-8'),
                    'order_no'         => htmlspecialchars($row['order_number'] ?? '-', ENT_QUOTES, 'UTF-8'),
                    'category'         => htmlspecialchars($row['order_category'] ?? '-', ENT_QUOTES, 'UTF-8'),
                    'order_category'   => htmlspecialchars($row['order_category'] ?? '-', ENT_QUOTES, 'UTF-8'),
                    'delivery_term'    => htmlspecialchars($row['delivery_term'] ?? '-', ENT_QUOTES, 'UTF-8'),
                    'po_number'        => htmlspecialchars($row['pono'] ?? '-', ENT_QUOTES, 'UTF-8'),
                    'payment_term'     => htmlspecialchars($row['pay_desc'] ?? '100% Advance', ENT_QUOTES, 'UTF-8'),
                    'transporter'      => htmlspecialchars($row['transporter'] ?? '-', ENT_QUOTES, 'UTF-8'),
                    'order_status'     => $orderStatus,
                    'date'             => !empty($row['indent_date'])
                        ? date('d-m-Y', strtotime($row['indent_date']))
                        : '-',
                    /*
                        <button type="button" class="btn btn-sm btn-outline-dark" onclick="openLineItems(\''
                        . htmlspecialchars($refno, ENT_QUOTES, 'UTF-8')
                        . '\')"><i class="fa fa-eye"></i></button>
                        */
                    'lines'            => ' <a href="recent_order_details.php?refno='
                        . urlencode($refno)
                        . '" class="btn btn-sm btn-outline-dark" title="View">'
                        . '<i class="fa fa-eye"></i></a>',
                ];

                if ($showAddedBy) {
                    $addedBy = trim((string) ($row['added_by_name'] ?? ''));
                    $rowData['added_by'] = htmlspecialchars(
                        $addedBy !== '' ? $addedBy : '-',
                        ENT_QUOTES,
                        'UTF-8'
                    );
                }

                $data[] = $rowData;
            }
            return json_encode([
                'draw'            => $draw,
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $data,
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return json_encode([
                'draw' => 0,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage() . $e->getLine(),
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
    }

    public function getRecentOrders_bk_14_07()

    {

        try {

            $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;

            $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;

            $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;

            $search = $_POST['search']['value'] ?? '';

            $where = '';

            $params = [];

            if (!empty($search)) {

                $where = "AND (

                    a.refno ILIKE :search

                    OR COALESCE(a.pono, '') ILIKE :search

                    OR a.tplcode ILIKE :search

                    OR a.tpldesc ILIKE :search

                    OR COALESCE(d.order_category, '') ILIKE :search

                )";

                $params[':search'] = "%{$search}%";
            }

            $joinSql = "

                FROM plexecom_customer_units AS a

                LEFT JOIN tbl_vayu_delivery_term AS c

                    ON a.delterms_code = c.delivery_code::varchar

                LEFT JOIN tbl_vayu_order_category AS d

                    ON a.indent_category::varchar = d.id::varchar

                LEFT JOIN spp_payterm_master AS e

                    ON a.paycode = e.pay_code::varchar

                LEFT JOIN transporter_master AS f

                    ON a.transporter = f.trans_code

                WHERE a.cuno = :createdBy

                {$where}

            ";

            $totalQry = $this->obconn->prepare("

                SELECT COUNT(DISTINCT refno)

                FROM plexecom_customer_units

                WHERE cuno = :createdBy

            ");

            $totalQry->bindParam(':createdBy', $this->customer_code, PDO::PARAM_STR);

            $totalQry->execute();

            $totalRecords = (int) $totalQry->fetchColumn();

            $countSql = "SELECT COUNT(*) FROM (SELECT DISTINCT a.refno {$joinSql}) recent_orders";

            $countStmt = $this->obconn->prepare($countSql);

            $countStmt->bindParam(':createdBy', $this->customer_code, PDO::PARAM_STR);

            foreach ($params as $key => $value) {

                $countStmt->bindValue($key, $value);
            }

            $countStmt->execute();

            $filteredRecords = (int) $countStmt->fetchColumn();

            $sql = "

                SELECT DISTINCT ON (a.refno)

                    a.refno,

                    a.order_number,

                    a.pono,

                    a.indent_date,

                    d.order_category,

                    c.delivery_term,

                    e.pay_desc,

                    f.trans_name AS transporter

                {$joinSql}

                ORDER BY a.refno DESC, a.indent_date DESC

                LIMIT :length OFFSET :start

            ";

            $stmt = $this->obconn->prepare($sql);

            $stmt->bindParam(':createdBy', $this->customer_code, PDO::PARAM_STR);

            foreach ($params as $key => $value) {

                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(':length', $length, PDO::PARAM_INT);

            $stmt->bindValue(':start', $start, PDO::PARAM_INT);

            $stmt->execute();

            $data = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                $refno = (string) ($row['refno'] ?? '');

                $orderNumber = trim((string) ($row['order_number'] ?? ''));

                $orderStatus = $this->resolveRecentOrderStatus($orderNumber, $this->customer_code);

                $data[] = [

                    'ref_no'           => $refno,

                    'order_no'         => $row['order_number'],

                    'category'         => $row['order_category'] ?? '-',

                    'order_category'   => $row['order_category'] ?? '-',

                    'delivery_term'    => $row['delivery_term'] ?? '-',

                    'po_number'        => $row['pono'] ?? '-',

                    'payment_term'     => $row['pay_desc'] ?? '-',

                    'transporter'      => $row['transporter'] ?? '-',

                    'order_status'     => $orderStatus,

                    'date'             => !empty($row['indent_date'])

                        ? date('d-m-Y', strtotime($row['indent_date']))

                        : '-',

                    'lines'            => '<button type="button" class="btn btn-sm btn-outline-dark" onclick="openLineItems(\''

                        . htmlspecialchars($refno, ENT_QUOTES, 'UTF-8')

                        . '\')"><i class="fa fa-eye"></i></button>',

                ];
            }

            return json_encode([

                'draw'            => $draw,

                'recordsTotal'    => $totalRecords,

                'recordsFiltered' => $filteredRecords,

                'data'            => $data,

            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (Exception $e) {

            error_log($e->getMessage());

            return json_encode([

                'draw' => 0,

                'recordsTotal' => 0,

                'recordsFiltered' => 0,

                'data' => [],

                'error' => $e->getMessage() . $e->getLine(),

            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
    }

    private function resolveRecentOrderStatus(string $orderNumber, string $cuno): string

    {

        $orderNumber = trim($orderNumber);

        $cuno = trim($cuno);

        if ($orderNumber === '' || $cuno === '') {

            return '<span class="status-badge border border-dark">Pending</span>';
        }

        $despatchStmt = $this->dpconn->prepare("

            SELECT 1

            FROM despatch

            WHERE cuno = :cuno

              AND TRIM(ordno) = :ordno

              AND cmp != 600

            LIMIT 1

        ");

        $despatchStmt->bindValue(':cuno', $cuno);

        $despatchStmt->bindValue(':ordno', $orderNumber);

        $despatchStmt->execute();

        if ($despatchStmt->fetchColumn()) {

            return '<span class="status-badge border border-dark">Despatched</span>';
        }

        $ackStmt = $this->obconn->prepare("

            SELECT 1

            FROM plexecom_customer_units

            WHERE cuno = :cuno

              AND TRIM(order_number) = :ordno

              AND company != 600

            LIMIT 1

        ");

        $ackStmt->bindValue(':cuno', $cuno);

        $ackStmt->bindValue(':ordno', $orderNumber);

        $ackStmt->execute();

        if ($ackStmt->fetchColumn()) {
            return '<span class="status-badge border border-dark">AO</span>';
        }

        return '<span class="status-badge border border-dark">Pending</span>';
    }

    public function getRecentOrders_bk()
    {


        try {

            $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;

            $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;

            $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;

            $search = $_POST['search']['value'] ?? '';

            $where = '';

            $params = [];

            if (!empty($search)) {

                $where = "AND (refno ILIKE :search OR tplcode ILIKE :search OR tpldesc ILIKE :search)";

                $params[':search'] = "%{$search}%";
            }

            $totalQry = $this->obconn->prepare("SELECT DISTINCT(select COUNT(*) FROM plexecom_customer_units WHERE cuno = :createdBy)");

            $totalQry->bindParam(':createdBy', $this->customer_code, PDO::PARAM_STR);

            $totalQry->execute();

            $totalRecords = $totalQry->fetchColumn();

            $countSql = "SELECT DISTINCT(SELECT COUNT(*) FROM plexecom_customer_units WHERE cuno = :createdBy {$where})";

            $countStmt = $this->obconn->prepare($countSql);

            $countStmt->bindParam(':createdBy', $this->customer_code, PDO::PARAM_STR);

            foreach ($params as $key => $value) {

                $countStmt->bindValue($key, $value);
            }

            $countStmt->execute();

            $filteredRecords = $countStmt->fetchColumn();

            $sql = "SELECT distinct a.refno, a.order_number, a.indent_date, a.tplcode, a.tpldesc, a.qty, a.price, d.order_category,a.deladdr,c.delivery_term,e.pay_desc,f.trans_name as transporter FROM plexecom_customer_units as a LEFT JOIN tbl_vayu_delivery_term as c on a.delterms_code=c.delivery_code::varchar LEFT JOIN tbl_vayu_order_category as d on a.indent_category::varchar = d.id::varchar LEFT JOIN spp_payterm_master as e on a.paycode=e.pay_code::varchar LEFT JOIN transporter_master as f on a.transporter = f.trans_code  WHERE cuno = :createdBy {$where} ORDER BY refno DESC LIMIT :length OFFSET :start";

            $stmt = $this->obconn->prepare($sql);

            $stmt->bindParam(':createdBy', $this->customer_code, PDO::PARAM_STR);

            foreach ($params as $key => $value) {

                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(':length', $length, PDO::PARAM_INT);

            $stmt->bindValue(':start', $start, PDO::PARAM_INT);

            $stmt->execute();

            $data = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                $data[] = [

                    'order_no'         => $row['refno'],

                    'order_category'   => $row['order_category'],

                    'dealer_address'   => $row['deladdr'],

                    'delivery_term'    => $row['delivery_term'],

                    'payment_term'     => $row['pay_desc'],

                    'transporter'      => $row['transporter'],

                    'date'             => date('d-m-Y', strtotime($row['indent_date'])),

                    'lines' => '<button style="background:transparent;border:none;" onclick="openLineItems(\'' . $row['refno'] . '\')"><i class="fa fa-eye"></i></button>'

                ];
            }

            return json_encode([

                'draw'            => $draw,

                'recordsTotal'    => (int)$totalRecords,

                'recordsFiltered' => (int)$filteredRecords,

                'data'            => $data

            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (Exception $e) {

            error_log($e->getMessage());

            return json_encode([

                'draw' => 0,

                'recordsTotal' => 0,

                'recordsFiltered' => 0,

                'data' => [],

                'error' => $e->getMessage() . $e->getLine()

            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
    }


    public function customer_master()
    {
        $search = trim($_POST['search'] ?? '');

        $dealer = trim($_POST['dealer'] ?? '');

        $sql = "SELECT adr_code, cuname
            FROM customer_address
            WHERE length(adr_code) = 9";
        if ($dealer) {
            $sql .= " AND cuno = :dealer";
        } else {
            $sql .= " AND cuno = :cuno";
        }

        if (!empty($search)) {
            $sql .= " AND (
                    LOWER(cuname) LIKE LOWER(:search)
                    OR adr_code LIKE :search
                 )";
        }

        $sql .= " ORDER BY cuname LIMIT 50";

        $stmt = $this->obconn->prepare($sql);


        if ($dealer) {
            $stmt->bindParam(':dealer', $dealer, PDO::PARAM_STR);
        } else {
            $stmt->bindParam(':cuno', $this->customer_code, PDO::PARAM_STR);
        }

        if (!empty($search)) {
            $searchLike = "%{$search}%";
            $stmt->bindParam(':search', $searchLike, PDO::PARAM_STR);
        }

        $stmt->execute();

        $result = [];

        while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            $result[] = [
                'id'   => $row->adr_code,
                'text' => $row->cuname . ' - [' . $row->adr_code . ']'
            ];
        }

        echo json_encode($result, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    public function getAcknowledgeLine()
    {
        $orderNo = $_POST['orderNo'];
        $tableLine = "";
        try {
            $chkLine = $this->obconn->prepare("SELECT * FROM tbl_vayu_orders_line WHERE order_no=:orderNo");
            $chkLine->bindParam(":orderNo", $orderNo, PDO::PARAM_STR);
            $chkLine->execute();
            if ($chkLine->rowCount() > 0) {
                $tableLine .= '
        <div class="table-responsive">
            <table id="orderTableLine" class="table table-hover align-middle w-100">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Item Code</th>
                        <th>Item Description</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
                <tbody>';

                $i = 1;

                while ($row = $chkLine->fetch(PDO::FETCH_ASSOC)) {

                    $tableLine .= '
                <tr>
                    <td>' . $i++ . '</td>
                    <td>' . $row['item_code'] . '</td>
                    <td>' . $row['item_description'] . '</td>
                    <td>' . $row['quantity'] . '</td>
                    <td>' . number_format($row['price'], 2) . '</td>
                    <td>' . number_format($row['total_amount'], 2) . '</td>
                </tr>';
                }

                $tableLine .= '
                </tbody>
            </table>
        </div>';
            } else {
                $tableLine = '<div class="alert alert-warning">No line items found.</div>';
            }

            return $tableLine;
        } catch (Exception $e) {
        }
    }


    /**
     * Fetch Recent Order header, line items, and totals by refno.
     *
     * @return array{
     *   success: bool,
     *   error?: string,
     *   header?: array<string, mixed>,
     *   lines?: array<int, array<string, mixed>>,
     *   totals?: array<string, float>
     * }
     */
    public function getRecentOrderDetails(?string $refno = null): array
    {
        $refno = trim((string) ($refno ?? ''));
        if ($refno === '') {
            return [
                'success' => false,
                'error' => 'Order reference is required.',
            ];
        }

        try {
            admin_refresh_session_role($this->obconn);
            $seeAll = is_system_admin() || is_management_user();
            $userWhere = $seeAll ? '1=1' : 'a.cuno = :cuno';

            $headerSql = "
                SELECT
                    a.refno,
                    a.cuno,
                    a.cuname,
                    a.order_number,
                    a.pono,
                    a.indent_date,
                    a.order_date,
                    a.delivery_date,
                    a.currency,
                    a.invaddr,
                    a.deladdr,
                    a.delivery_code,
                    a.areacode,
                    a.email,
                    a.pincode,
                    a.district,
                    a.state,
                    a.country,
                    a.frtamount,
                    a.remarks,
                    d.order_category,
                    c.delivery_term,
                    e.pay_desc,
                    f.trans_name AS transporter,
                    g.area_desc
                FROM plexecom_customer_units AS a
                LEFT JOIN tbl_vayu_delivery_term AS c
                    ON a.delterms_code = c.delivery_code::varchar
                LEFT JOIN tbl_vayu_order_category AS d
                    ON a.indent_category::varchar = d.id::varchar
                LEFT JOIN spp_payterm_master AS e
                    ON a.paycode = e.pay_code::varchar
                LEFT JOIN transporter_master AS f
                    ON a.transporter = f.trans_code
                LEFT JOIN area AS g
                    ON a.areacode = g.area_code
                WHERE a.refno = :refno
                  AND {$userWhere}
                ORDER BY a.oid ASC
                LIMIT 1
            ";

            $headerStmt = $this->obconn->prepare($headerSql);
            $headerStmt->bindValue(':refno', $refno);
            if (!$seeAll) {
                $headerStmt->bindValue(':cuno', $this->customer_code);
            }
            $headerStmt->execute();
            $headerRow = $headerStmt->fetch(PDO::FETCH_ASSOC);

            if (!$headerRow) {
                return [
                    'success' => false,
                    'error' => 'Recent order not found or you do not have access to view it.',
                ];
            }

            $linesSql = "
                SELECT
                    a.oid,
                    a.posno,
                    a.tplcode,
                    a.tpldesc,
                    a.uom,
                    a.qty,
                    a.price,
                    a.salestax_code,
                    a.salestpc_value,
                    a.servicetpc_value,
                    a.hsn,
                    a.frtamount
                FROM plexecom_customer_units AS a
                WHERE a.refno = :refno
                  AND {$userWhere}
                ORDER BY a.posno ASC NULLS LAST, a.oid ASC
            ";

            $linesStmt = $this->obconn->prepare($linesSql);
            $linesStmt->bindValue(':refno', $refno);
            if (!$seeAll) {
                $linesStmt->bindValue(':cuno', $this->customer_code);
            }
            $linesStmt->execute();

            $lines = [];
            $subtotal = 0.0;
            $taxTotal = 0.0;
            $freightTotal = 0.0;

            while ($row = $linesStmt->fetch(PDO::FETCH_ASSOC)) {
                $qty = (float) ($row['qty'] ?? 0);
                $price = (float) ($row['price'] ?? 0);
                $lineBase = round($qty * $price, 2);
                $salesTax = (float) ($row['salestpc_value'] ?? 0);
                $serviceTax = (float) ($row['servicetpc_value'] ?? 0);
                $lineTax = round($salesTax + $serviceTax, 2);
                $lineFreight = (float) ($row['frtamount'] ?? 0);

                $subtotal += $lineBase;
                $taxTotal += $lineTax;
                $freightTotal += $lineFreight;

                $lines[] = [
                    'posno' => trim((string) ($row['posno'] ?? '')) !== ''
                        ? trim((string) $row['posno'])
                        : (string) (count($lines) + 1),
                    'item_code' => trim((string) ($row['tplcode'] ?? '')),
                    'item_desc' => trim((string) ($row['tpldesc'] ?? '')),
                    'uom' => trim((string) ($row['uom'] ?? '')) !== ''
                        ? trim((string) $row['uom'])
                        : '-',
                    'qty' => $qty,
                    'price' => $price,
                    'line_total' => $lineBase,
                    'tax_code' => trim((string) ($row['salestax_code'] ?? '')),
                    'tax_amount' => $lineTax,
                    'hsn' => trim((string) ($row['hsn'] ?? '')),
                ];
            }

            if ($freightTotal <= 0) {
                $freightTotal = (float) ($headerRow['frtamount'] ?? 0);
            }

            $orderNumber = trim((string) ($headerRow['order_number'] ?? ''));
            $cuno = trim((string) ($headerRow['cuno'] ?? $this->customer_code));
            $orderDateRaw = $headerRow['order_date'] ?? $headerRow['indent_date'] ?? null;
            $orderDate = !empty($orderDateRaw) ? date('d-m-Y', strtotime((string) $orderDateRaw)) : '-';

            $header = [
                'refno' => trim((string) ($headerRow['refno'] ?? $refno)),
                'order_number' => $orderNumber !== '' ? $orderNumber : '-',
                'cuno' => $cuno,
                'cuname' => trim((string) ($headerRow['cuname'] ?? '')),
                'po_number' => trim((string) ($headerRow['pono'] ?? '')) !== ''
                    ? trim((string) $headerRow['pono'])
                    : '-',
                'order_date' => $orderDate,
                'currency' => trim((string) ($headerRow['currency'] ?? '')) !== ''
                    ? trim((string) $headerRow['currency'])
                    : 'INR',
                'category' => trim((string) ($headerRow['order_category'] ?? '')) !== ''
                    ? trim((string) $headerRow['order_category'])
                    : '-',
                'delivery_term' => trim((string) ($headerRow['delivery_term'] ?? '')) !== ''
                    ? trim((string) $headerRow['delivery_term'])
                    : '-',
                'payment_term' => trim((string) ($headerRow['pay_desc'] ?? '')) !== ''
                    ? trim((string) $headerRow['pay_desc'])
                    : '100% Advance',
                'transporter' => trim((string) ($headerRow['transporter'] ?? '')) !== ''
                    ? trim((string) $headerRow['transporter'])
                    : '-',
                'area' => trim((string) ($headerRow['area_desc'] ?? '')) !== ''
                    ? trim((string) $headerRow['area_desc'])
                    : (trim((string) ($headerRow['areacode'] ?? '')) !== '' ? trim((string) $headerRow['areacode']) : '-'),
                'delivery_date' => !empty($headerRow['delivery_date'])
                    ? date('d-m-Y', strtotime((string) $headerRow['delivery_date']))
                    : '-',
                'email' => trim((string) ($headerRow['email'] ?? '')),
                'invoice_address' => $this->formatRecentOrderAddressText($headerRow['invaddr'] ?? ''),
                'delivery_address' => $this->formatRecentOrderAddressText($headerRow['deladdr'] ?? ''),
                'delivery_address_type' => (trim((string) ($headerRow['delivery_code'] ?? '')) !== '') ? 'dealer' : 'end_customer',
                'delivery_code' => trim((string) ($headerRow['delivery_code'] ?? '')),
                'end_customer_name' => trim((string) ($headerRow['cuname'] ?? '')),
                'end_customer_email' => trim((string) ($headerRow['email'] ?? '')),
                'end_customer_pincode' => trim((string) ($headerRow['pincode'] ?? '')),
                'end_customer_district' => trim((string) ($headerRow['district'] ?? '')),
                'end_customer_state' => trim((string) ($headerRow['state'] ?? '')),
                'order_status' => $this->resolveRecentOrderStatusLabel($orderNumber, $cuno),
                'remarks' => trim((string) ($headerRow['remarks'] ?? '')),
            ];

            // Parse end customer street/city from invaddr when delivery_address_type is end_customer
            if ($header['delivery_address_type'] === 'end_customer') {
                $rawInv = trim((string) ($headerRow['invaddr'] ?? ''));
                if ($rawInv !== '') {
                    $normalized = preg_replace('/<br\s*\/?>\s*-\s*<br\s*\/?>/i', '||', $rawInv);
                    $normalized = preg_replace('/<br\s*\/?>/i', "\n", (string) $normalized);
                    $normalized = html_entity_decode(strip_tags((string) $normalized), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $addrParts = array_map(static fn($part) => trim((string) $part, " \t\n\r\0\x0B-"), explode('||', (string) $normalized));
                    $header['end_customer_street1'] = $addrParts[0] ?? '';
                    $header['end_customer_street2'] = $addrParts[1] ?? '';
                    $header['end_customer_city'] = $addrParts[2] ?? '';
                }
            }

            // Resolve dealer delivery address from customer_address table
            if ($header['delivery_address_type'] === 'dealer' && $header['delivery_code'] !== '') {
                try {
                    $addrStmt = $this->dpconn->prepare(
                        "SELECT cuname, st1, st2, city, pin, state, country FROM customer_address WHERE adr_code = :code LIMIT 1"
                    );
                    $addrStmt->execute([':code' => $header['delivery_code']]);
                    $addrRow = $addrStmt->fetch(PDO::FETCH_ASSOC);
                    if ($addrRow) {
                        $parts = array_filter([
                            trim((string) ($addrRow['cuname'] ?? '')),
                            trim((string) ($addrRow['st1'] ?? '')),
                            trim((string) ($addrRow['st2'] ?? '')),
                            trim((string) ($addrRow['city'] ?? '')),
                            trim((string) ($addrRow['state'] ?? '')),
                            trim((string) ($addrRow['pin'] ?? '')),
                            trim((string) ($addrRow['country'] ?? '')),
                        ], fn($v) => $v !== '' && $v !== '-');
                        $header['dealer_delivery_address'] = implode(', ', $parts);
                    }
                } catch (Throwable $e) {
                    // Silently ignore; dealer_delivery_address stays empty
                }
            }

            return [
                'success' => true,
                'header' => $header,
                'lines' => $lines,
                'totals' => [
                    'subtotal' => round($subtotal, 2),
                    'tax' => round($taxTotal, 2),
                    'freight' => round($freightTotal, 2),
                    'grand_total' => round($subtotal + $taxTotal + $freightTotal, 2),
                ],
            ];
        } catch (Exception $e) {
            error_log('getRecentOrderDetails: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unable to load recent order details.',
            ];
        }
    }

    private function formatRecentOrderAddressText($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        // Convert stored HTML line breaks to plain text lines.
        $value = preg_replace('/<br\s*\/?>/i', "\n", $value);
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = [];
        foreach (preg_split('/\R+/', $value) as $line) {
            $line = trim($line);
            $line = trim($line, "- \t");
            if ($line === '' || $line === '-') {
                continue;
            }
            if (!in_array($line, $lines, true)) {
                $lines[] = $line;
            }
        }

        return $lines === [] ? '-' : implode("\n", $lines);
    }

    private function resolveRecentOrderStatusLabel(string $orderNumber, string $cuno): string
    {
        $orderNumber = trim($orderNumber);
        $cuno = trim($cuno);

        if ($orderNumber === '' || $cuno === '') {
            return 'Pending';
        }

        $despatchStmt = $this->dpconn->prepare("
            SELECT 1
            FROM despatch
            WHERE cuno = :cuno
              AND TRIM(ordno) = :ordno
              AND cmp != 600
            LIMIT 1
        ");
        $despatchStmt->bindValue(':cuno', $cuno);
        $despatchStmt->bindValue(':ordno', $orderNumber);
        $despatchStmt->execute();

        if ($despatchStmt->fetchColumn()) {
            return 'Despatched';
        }

        $ackStmt = $this->obconn->prepare("
            SELECT 1
            FROM plexecom_customer_units
            WHERE cuno = :cuno
              AND TRIM(order_number) = :ordno
              AND company != 600
            LIMIT 1
        ");
        $ackStmt->bindValue(':cuno', $cuno);
        $ackStmt->bindValue(':ordno', $orderNumber);
        $ackStmt->execute();

        if ($ackStmt->fetchColumn()) {
            return 'AO';
        }

        return 'Pending';
    }









    public function getRecentOrderLine()
    {
        $orderNo = $_POST['orderNo'];
        $tableLine = "";
        try {
            $chkLine = $this->obconn->prepare("SELECT * FROM plexecom_customer_units WHERE refno=:orderNo");
            $chkLine->bindParam(":orderNo", $orderNo, PDO::PARAM_STR);
            $chkLine->execute();
            if ($chkLine->rowCount() > 0) {
                $tableLine .= '
        <div class="table-responsive">
            <table id="orderTableLine" class="table table-hover align-middle w-100">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Item Code</th>
                        <th>Item Description</th>
                        <th>Quantity</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>';

                $i = 1;

                while ($row = $chkLine->fetch(PDO::FETCH_ASSOC)) {

                    $tableLine .= '
                <tr>
                    <td>' . $i++ . '</td>
                    <td>' . $row['tplcode'] . '</td>
                    <td>' . $row['tpldesc'] . '</td>
                    <td>' . $row['qty'] . '</td>
                    <td>' . number_format($row['price'], 2) . '</td>
                </tr>';
                }

                $tableLine .= '
                </tbody>
            </table>
        </div>';
            } else {
                $tableLine = '<div class="alert alert-warning">No line items found.</div>';
            }

            return $tableLine;
        } catch (Exception $e) {
        }
    }

    public function getDespatchDetails()
    {
        try {

            $draw   = $_POST['draw'] ?? 0;
            $start  = $_POST['start'] ?? 0;
            $length = $_POST['length'] ?? 10;
            $search = $_POST['search']['value'] ?? '';

            $cuno = $this->customer_code;

            $where = " WHERE a.cmp != 600 AND a.dpst NOT IN ('SLS500','SLS01','SO0600','SAL01') AND a.cuno = :cuno ";

            $params = [
                ':cuno' => $cuno
            ];

            if (!empty($search)) {
                $where .= " AND (CAST(a.invno AS TEXT) ILIKE :search
    OR CAST(a.ordno AS TEXT) ILIKE :search
    OR a.cuname ILIKE :search
    OR a.dpst ILIKE :search
    OR d.dpst_desc ILIKE :search)";
                $params[':search'] = "%{$search}%";
            }

            $totalSql = "SELECT COUNT(*) FROM (SELECT DISTINCT ON(a.invdate,a.cmp,a.ordno,a.invref,a.invno) a.invno FROM despatch a LEFT JOIN lr_details b ON a.invref = b.invref AND a.invno = b.invno AND a.cmp = b.company LEFT JOIN dpst_master d ON d.dpst_code::text = a.dpst WHERE a.cmp != 600 AND a.dpst NOT IN ('SLS500','SLS01','SO0600','SAL01') AND a.cuno = :cuno) x";

            $totalStmt = $this->dpconn->prepare($totalSql);
            $totalStmt->bindValue(':cuno', $cuno);
            $totalStmt->execute();
            $totalRecords = (int)$totalStmt->fetchColumn();


            $countSql = "SELECT COUNT(*) FROM ( SELECT DISTINCT ON(a.invdate,a.cmp,a.ordno,a.invref,a.invno) a.invno FROM despatch a LEFT JOIN lr_details b ON a.invref = b.invref AND a.invno = b.invno AND a.cmp = b.company LEFT JOIN dpst_master d ON d.dpst_code::text = a.dpst {$where}) x";
            $countStmt = $this->dpconn->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $filteredRecords = (int)$countStmt->fetchColumn();
            $sql = "SELECT DISTINCT ON(a.invdate,a.cmp,a.ordno,a.invref,a.invno)a.invno,a.invdate,a.comm_dt,a.invref,a.dpst,d.dpst_desc,a.ordno,a.ord_date,a.posno,a.cmp,b.tname,b.lrno,b.lrdate,b.cases,b.bundles,b.boxes,b.carton_box,b.spl_cases,b.weight,b.w_unit,b.dly_code,a.cuno,a.cuname FROM despatch a LEFT JOIN lr_details b ON a.invref = b.invref AND a.invno = b.invno AND a.cmp = b.company LEFT JOIN dpst_master d
            ON d.dpst_code::text = a.dpst {$where} ORDER BY a.invdate DESC, a.ordno,
            a.invref, a.invno LIMIT :length OFFSET :start";
            $stmt = $this->dpconn->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
            $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
            $stmt->execute();
            $data = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                $data[] = [
                    'cuno'       => $row['cuno'],
                    'dpst'       => $row['dpst'],
                    'ordno'      => $row['ordno'],
                    'invno'      => $row['invno'],
                    'invdt'    => date('d-m-Y', strtotime($row['invdate'])),
                    'transporter' => $row['tname'],
                    'lrno'       => $row['lrno'],
                    'lrdate'     => !empty($row['lrdate'])
                        ? date('d-m-Y', strtotime($row['lrdate']))
                        : '',
                    'packing' => 'Cases:' . $row['cases'] . ' ' . 'Boxes:' . $row['boxes'] . '' . 'Bundles:' . $row['bundles'] . 'Cartoons:' . $row['carton_box'] . ' ' . 'Special Cases:' . $row['spl_cases'],
                    'weight'     => $row['weight'],
                    'action'     => '<a href="invoice.php?invno=' .
                        urlencode($row['invno']) .
                        '&invref=' .
                        urlencode($row['invref']) .
                        '">View</a>'
                ];
            }

            echo json_encode([
                'draw'            => (int)$draw,
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $data
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (Exception $e) {

            echo json_encode([
                'draw' => 0,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage()
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
    }

    public function getLrDetails()
    {
        try {

            $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
            $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
            $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;

            $search = $_POST['search']['value'] ?? '';

            $cuno = $this->customer_code;

            $where = '';

            if (!empty($search)) {
                $where = "
            AND (
                l.dpst ILIKE :search
                OR COALESCE(d.dpst_desc,'') ILIKE :search
                OR l.ordno::text ILIKE :search
                OR l.invno::text ILIKE :search
                OR COALESCE(l.lrno,'') ILIKE :search
                OR COALESCE(l.tname,'') ILIKE :search
            )
        ";
            }

            /* Total Records */

            $totalStmt = $this->dpconn->prepare("
        SELECT COUNT(*)
        FROM (
            SELECT DISTINCT
                ordno,
                invno,
                invdate,
                invref
            FROM lrdetails
            WHERE cuno = :cuno
              AND divcode <> '6'
        ) x
    ");

            $totalStmt->bindValue(':cuno', $cuno);
            $totalStmt->execute();

            $totalRecords = (int)$totalStmt->fetchColumn();

            /* Filtered Records */

            $countStmt = $this->dpconn->prepare("
        SELECT COUNT(*)
        FROM (
            SELECT DISTINCT
                l.ordno,
                l.invno,
                l.invdate,
                l.invref
            FROM lrdetails l
            LEFT JOIN dpst_master d
                ON TRIM(l.dpst) = TRIM(d.dpst_code::text)
            WHERE l.cuno = :cuno
              AND l.divcode <> '6'
              $where
        ) x
    ");

            $countStmt->bindValue(':cuno', $cuno);

            if (!empty($search)) {
                $countStmt->bindValue(':search', "%{$search}%");
            }

            $countStmt->execute();

            $filteredRecords = (int)$countStmt->fetchColumn();

            /* Main Data Query */

            $sql = "
        SELECT DISTINCT
            l.ordno AS aono,
            l.invno,
            l.invdate,
            l.lrno,
            l.lrdate,
            l.tname,
            l.dpst,
            d.dpst_desc,
            l.invref
        FROM lrdetails l
        LEFT JOIN dpst_master d
            ON TRIM(l.dpst) = TRIM(d.dpst_code::text)
        WHERE l.cuno = :cuno
          AND l.divcode <> '6'
          $where
        ORDER BY l.invdate DESC
        LIMIT :length OFFSET :start
    ";

            $stmt = $this->dpconn->prepare($sql);

            $stmt->bindValue(':cuno', $cuno);

            if (!empty($search)) {
                $stmt->bindValue(':search', "%{$search}%");
            }

            $stmt->bindValue(':length', $length, PDO::PARAM_INT);
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);

            $stmt->execute();

            $data = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                $data[] = [
                    'dpst'      => $row['dpst'],
                    'dpst_desc' => $row['dpst_desc'],

                    'ordno' => '<a href="lrview.php?ordno=' .
                        urlencode($row['aono']) .
                        '" target="_blank">' .
                        $row['aono'] .
                        '</a>',

                    'invoice' => '<a href="despatchview1.php?invref=' .
                        urlencode($row['invref']) .
                        '&invno=' .
                        urlencode($row['invno']) .
                        '" target="_blank">' .
                        $row['invref'] . '-' . $row['invno'] .
                        '</a>',

                    'invdate' => !empty($row['invdate'])
                        ? date('d-m-Y', strtotime($row['invdate']))
                        : '',

                    'tname' => $row['tname'],
                    'lrno'  => $row['lrno'],

                    'lrdate' => !empty($row['lrdate'])
                        ? date('d-m-Y', strtotime($row['lrdate']))
                        : ''
                ];
            }

            echo json_encode([
                'draw'            => $draw,
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data'            => $data
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (Exception $e) {

            echo json_encode([
                'draw' => 0,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage()
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
    }

    private function getBearerTokenLN()
    {

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://mingle-sso.eu1.inforcloudsuite.com:443/ELGI_TST/as/token.oauth2',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'token_name'    => 'ELGi_TST',
                'grant_type'    => 'password',
                'redirect_uri'  => 'https://mingle-sso.eu1.inforcloudsuite.com:443/ELGI_TST/as/token.oauth2',
                'client_id'     => 'ELGI_TST~GjQTy8se0SnpL_BcZIuhHd5P4aoiNWYNLjk3H8U-tNs',
                'client_secret' => 'N-OBXSqM6KER0LE5dVfCtAabW_Cci2zPBR1JhiPgELnoDXTYkkbL_YfMBYX5uogOXdZVcVG-8Vd9l7iLn9z1Eg',
                'username'      => 'ELGI_TST#oIdzzt-8I84jlKl-ZNUNqnMoBT3k9f0sZ2CoW2TSQBcNoo3BZTzgxYwEi2y8p-EBhNRqQXTHyZVmBYkS3ED2bg',
                'password'      => 'T1hQoXbhq5nmfn7FIG8BBzpvA3by6k9FkL0XvhLFmEkXn-EUxD_oSbQRQFJT9cyxkUeNM2fb08fFe3mt80n0Ew',
                'scope'         => ''
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'cURL Error: ' . curl_error($ch);
        }
        $data = json_decode($response, true);
        unset($ch);
        return isset($data['access_token']) && is_string($data['access_token'])
            ? $data['access_token']
            : '';
    }

    function search_dealer()
    {
        $search = htmlspecialchars(trim($_POST['search'] ?? ''),ENT_QUOTES, 'UTF-8');

        $sql = "SELECT cuno, cuname
        FROM customer_master
        WHERE 1=1";

        if (!empty($search)) {
            $sql .= " AND (
                LOWER(cuname) LIKE LOWER(:search)
                OR cuno LIKE :search
             )";
        }

        $sql .= " ORDER BY cuname LIMIT 50";

        $stmt = $this->dpconn->prepare($sql);

        if (!empty($search)) {
            $searchLike = "%{$search}%";
            $stmt->bindValue(':search', $searchLike, PDO::PARAM_STR);
        }

        $stmt->execute();

        $result = [];

        while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            $result[] = [
                'id'   => $row->cuno,
                'text' => $row->cuname . ' - [' . $row->cuno . ']'
            ];
        }

        return json_encode($result,JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
