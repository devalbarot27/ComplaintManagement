<?php
session_start();

include('pdo_obconn.php');
class orderClass
{

    private $userId;
    private $obconn;
    private $dpconn;

    public function __construct($obconn, $dpconn)
    {

        $this->obconn = $obconn;
        $this->dpconn = $dpconn;
        $this->userId = $_SESSION['usr_name'];
    }

    public function getCartCount()
    {
        $cnt = $this->obconn->prepare("SELECT * FROM tbl_vayu_item_master WHERE status=0 AND created_by=:createdBy");
        $cnt->bindParam(':createdBy', $this->userId, PDO::PARAM_STR);
        $cnt->execute();
        return $cnt->rowCount();
    }


    public function searchItems()
    {
        $search = $_POST['search'] ?? '';

        $getItem = $this->obconn->prepare("SELECT tplcode, tpldesc FROM product_master WHERE (tplcode ILIKE :search OR tpldesc ILIKE :search) ORDER BY tplcode LIMIT 20");
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
        $item = $_POST['item'] ?? '';

        $stmt = $this->obconn->prepare("
        SELECT cos
        FROM product_master
        WHERE tplcode = :item
        LIMIT 1
    ");

        $stmt->bindParam(':item', $item, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode([
                'status' => true,
                'price'  => (float)$row['cos']
            ]);
        } else {
            echo json_encode([
                'status' => false,
                'price'  => 0
            ]);
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
            $stmt->bindValue(':createdBy', $this->userId);
            $stmt->bindValue(':item_code', $item_code);
            $stmt->execute();
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                $totalAmount = $qty * $price;
                $getDesc = $this->obconn->prepare("SELECT tpldesc FROM product_master WHERE tplcode=:tplcode");
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
                $insert->bindValue(':created_by', $this->userId);
                return $insert->execute() ? 1 : 0;
            } else {
                $updatedQty = $item['qty'] + $qty;
                $totalAmount = $updatedQty * $price;
                $update = $this->obconn->prepare("UPDATE tbl_vayu_cartitems SET qty = :qty,price = :price,total_amount = :totalAmount WHERE created_by = :createdBy AND status = 0 AND item_code = :item_code");
                $update->bindValue(':qty', $updatedQty);
                $update->bindValue(':price', $price);
                $update->bindValue(':totalAmount', $totalAmount);
                $update->bindValue(':createdBy', $this->userId);
                $update->bindValue(':item_code', $item_code);
                return $update->execute() ? 1 : 0;
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return 0;
        }
    }

    public function getCartItems()
    {
        $chkItem = $this->obconn->prepare("SELECT * FROM tbl_vayu_cartitems WHERE created_by = :createdBy AND status = 0");
        $chkItem->bindParam(':createdBy', $this->userId, PDO::PARAM_STR);
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

    public function submitCart()
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
            $frtamount = $_POST['freightAmount'] ?? null;

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

            // -----------------------------------------------------------------
            // CART ITEMS
            // -----------------------------------------------------------------

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

            // -----------------------------------------------------------------
            // CONSTANTS
            // -----------------------------------------------------------------

            $cmp      = "401";
            $cuno     = "CU1A03751";
            $aoseries = "201";
            $state    = "TN";
            $sid      = session_id();

            // -----------------------------------------------------------------
            // CUSTOMER DATA (LOAD ONCE)
            // -----------------------------------------------------------------

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

            // -----------------------------------------------------------------
            // DPST DATA (LOAD ONCE)
            // -----------------------------------------------------------------

            $dpstStmt = $this->obconn->prepare("
            SELECT product_group
            FROM dpst_master
            WHERE dpst_code = :dpst
        ");

            $dpstStmt->execute([
                ':dpst' => $dpst
            ]);

            $div = $dpstStmt->fetchColumn();

            // -----------------------------------------------------------------
            // PREPARED STATEMENTS
            // -----------------------------------------------------------------

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
            FROM product_master
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

                // $tplcode = $item['item_code']; // removed hardcoded S011154
                $tplcode = "000329820";

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
                    ':emp_code'          => $this->userId,
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
                    ':frtamount'         => null,
                    ':cmp'               => $cmp,
                    ':adrcode'           => $adrcode,
                    ':refno'             => $refno,
                    ':hsn'               => "80:11:545",
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
            ]);
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
            ]);
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
                $where = "AND (ordno ILIKE :search)";
                $params[':search'] = "%{$search}%";
            }


            $totalQry = $this->dpconn->prepare("SELECT COUNT(*) FROM user_area_dpst_mapping u JOIN pendingordersnew p ON p.dpst = ANY(u.dpst) AND p.area = u.area WHERE u.usr_name = :uname AND trim(p.dpst)=any(u.dpst) AND u.valid='Y'");
            $totalQry->bindParam(':uname', $this->userId, PDO::PARAM_STR);
            $totalQry->execute();
            $totalRecords = $totalQry->fetchColumn();
            $countSql = "SELECT COUNT(*) FROM user_area_dpst_mapping u JOIN pendingordersnew p ON p.dpst = ANY(u.dpst) AND p.area = u.area WHERE u.usr_name = :uname AND trim(p.dpst)=any(u.dpst) AND u.valid='Y' {$where}";
            $countStmt = $this->dpconn->prepare($countSql);
            $countStmt->bindParam(':uname', $this->userId, PDO::PARAM_STR);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $filteredRecords = $countStmt->fetchColumn();

            $sql = "SELECT p.area, p.indentno,p.cuno,p.cuname,p.ordno,p.orddt,p.dpst,p.qty,p.unitvalue,p.itemcode,p.itemdesc,p.pono,p.otcode,p.delydt,delydt-current_date AS diff,p.cuno,p.company FROM pendingordersnew p,user_area_dpst_mapping u WHERE u.usr_name=:uname AND trim(p.dpst)=any(u.dpst) AND trim(p.area)=u.area AND u.valid='Y' {$where} ORDER BY orddt DESC LIMIT :length OFFSET :start";

            $stmt = $this->dpconn->prepare($sql);

            $stmt->bindParam(':uname', $this->userId, PDO::PARAM_STR);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(':length', $length, PDO::PARAM_INT);
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);

            $stmt->execute();

            $data = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                $data[] = [
                    'cuno'            => $row['cuno'],
                    'pono'            => $row['pono'],
                    'aono'             => $row['ordno'],
                    'aodate'             => $row['orddt'],
                    'delydt'         => date('d-m-Y', strtotime($row['delydt'])),
                    'lines' => '<a href="order_data.php?order=' . urlencode($row['ordno']) .
                        '&cuno=' . urlencode($row['cuno']) . '" target="_blank" style="text-decoration:none;">View</a>'
                ];
            }
            return json_encode([
                'draw'            => $draw,
                'recordsTotal'    => (int)$totalRecords,
                'recordsFiltered' => (int)$filteredRecords,
                'data'            => $data
            ]);
        } catch (Exception $e) {

            error_log($e->getMessage());

            return json_encode([
                'draw' => 0,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage() . $e->getLine()
            ]);
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


            $totalQry = $this->dpconn->prepare("SELECT COUNT(*) FROM ( SELECT DISTINCT m.areaname, m.indentno, m.indentdate, m.cuname, m.ordno, m.purno, m.ord_date, m.dpst, m.cuno FROM maintdealer m JOIN user_area_dpst_mapping u ON m.dpst = ANY(u.dpst) AND m.area = u.area WHERE u.usr_name = :uname AND u.valid = 'Y') x ");
            $totalQry->bindParam(':uname', $this->userId, PDO::PARAM_STR);
            $totalQry->execute();
            $totalRecords = $totalQry->fetchColumn();

            $countStmt = $this->dpconn->prepare("SELECT COUNT(*) FROM (SELECT DISTINCT m.areaname, m.indentno,m.indentdate, m.cuname, m.ordno, m.purno, m.ord_date, m.dpst, m.cuno FROM maintdealer m JOIN user_area_dpst_mapping u ON m.dpst = ANY(u.dpst) AND m.area = u.area WHERE u.usr_name = :uname AND u.valid = 'Y' {$where}) x");
            $countStmt->bindParam(':uname', $this->userId, PDO::PARAM_STR);
            $totalQry->execute();
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $filteredRecords = $countStmt->fetchColumn();


            $sql = "SELECT distinct m.areaname,m.indentno,m.indentdate,m.cuname,m.ordno,m.purno,m.ord_date,m.dpst, m.cuno FROM maintdealer m JOIN user_area_dpst_mapping u ON m.dpst = ANY(u.dpst) AND m.area = u.area WHERE u.usr_name = :uname AND u.valid = 'Y' {$where} ORDER BY ord_date DESC LIMIT :length OFFSET :start";

            $stmt = $this->dpconn->prepare($sql);

            $stmt->bindParam(':uname', $this->userId, PDO::PARAM_STR);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(':length', $length, PDO::PARAM_INT);
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);

            $stmt->execute();

            $data = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                $data[] = [
                    'cuno'      => $row['cuno'],
                    'dpst'      => $row['dpst'],
                    'purno'     => $row['purno'],
                    'ordno'     => $row['ordno'],
                    'ord_date'  => date('d-m-Y', strtotime($row['ord_date'])),
                    'lines' => '<a href="order_data.php?order=' . urlencode($row['ordno']) .
                        '&cuno=' . urlencode($row['cuno']) . '" target="_blank" style="text-decoration:none;">View</a>'
                ];
            }
            return json_encode([
                'draw'            => $draw,
                'recordsTotal'    => (int)$totalRecords,
                'recordsFiltered' => (int)$filteredRecords,
                'data'            => $data
            ]);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return json_encode([
                'draw' => 0,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage() . $e->getLine()
            ]);
        }
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

            if (!empty($search)) {
                $where = "AND (order_no ILIKE :search OR item_code ILIKE :search OR item_description ILIKE :search)";
                $params[':search'] = "%{$search}%";
            }

            $totalQry = $this->obconn->prepare("SELECT COUNT(*) FROM tbl_vayu_orders_header WHERE created_by = :createdBy");
            $totalQry->bindParam(':createdBy', $this->userId, PDO::PARAM_STR);
            $totalQry->execute();
            $totalRecords = $totalQry->fetchColumn();
            $countSql = "SELECT COUNT(*) FROM tbl_vayu_orders_header WHERE created_by = :createdBy {$where}";
            $countStmt = $this->obconn->prepare($countSql);
            $countStmt->bindParam(':createdBy', $this->userId, PDO::PARAM_STR);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $filteredRecords = $countStmt->fetchColumn();
            $sql = "SELECT a.order_no, a.ln_acknowledge, a.created_at, b.item_code, b.item_description, b.quantity, b.price, b.total_amount,d.order_category,a.dealer_address,c.delivery_term,e.payment_term,a.transporter FROM tbl_vayu_orders_header as a JOIN  tbl_vayu_orders_line as b on a.order_no=b.order_no JOIN tbl_vayu_delivery_term as c on a.delivery_term=c.delivery_code::varchar JOIN tbl_vayu_order_category as d on a.order_category = d.id::varchar JOIN tbl_vayu_payment_term as e on a.payment_term=e.payment_code::varchar  WHERE created_by = :createdBy {$where} ORDER BY created_at DESC LIMIT :length OFFSET :start";

            $stmt = $this->obconn->prepare($sql);

            $stmt->bindParam(':createdBy', $this->userId, PDO::PARAM_STR);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(':length', $length, PDO::PARAM_INT);
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);

            $stmt->execute();

            $data = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                $data[] = [
                    'order_no'         => $row['order_no'],
                    'ln_reference'     => $row['ln_reference'],
                    'order_category'   => $row['order_category'],
                    'dealer_address'   => $row['dealer_address'],
                    'delivery_term'    => $row['delivery_term'],
                    'payment_term'     => $row['payment_term'],
                    'transporter'      => $row['transporter'],
                    'date'             => date('d-m-Y', strtotime($row['created_at'])),
                    'lines' => '<button style="background:transparent;border:none;" onclick="openLineItems(\'' . $row['order_no'] . '\')"><i class="fa fa-eye"></i></button>'
                ];
            }
            return json_encode([
                'draw'            => $draw,
                'recordsTotal'    => (int)$totalRecords,
                'recordsFiltered' => (int)$filteredRecords,
                'data'            => $data
            ]);
        } catch (Exception $e) {

            error_log($e->getMessage());

            return json_encode([
                'draw' => 0,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage() . $e->getLine()
            ]);
        }
    }

    public function customer_master()
    {
        $this->userId = "CU1A02024";
        $search = trim($_POST['search'] ?? '');

        $sql = "SELECT adr_code, cuname
            FROM customer_address
            WHERE cuno = :cuno
            AND length(adr_code) = 9";

        if (!empty($search)) {
            $sql .= " AND (
                    LOWER(cuname) LIKE LOWER(:search)
                    OR adr_code LIKE :search
                 )";
        }

        $sql .= " ORDER BY cuname LIMIT 50";

        $stmt = $this->obconn->prepare($sql);
        $stmt->bindParam(':cuno', $this->userId, PDO::PARAM_STR);

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

        echo json_encode($result);
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

    public function getDespatchDetails()
    {
        try {

            $draw   = $_POST['draw'] ?? 0;
            $start  = $_POST['start'] ?? 0;
            $length = $_POST['length'] ?? 10;
            $search = $_POST['search']['value'] ?? '';

            $cuno = "CU1A03751";

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
            ]);
        } catch (Exception $e) {

            echo json_encode([
                'draw' => 0,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getLrDetails()
    {
        try {

            $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
            $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
            $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;

            $search = $_POST['search']['value'] ?? '';

            $cuno = "CU1A03751";

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
            ]);
        } catch (Exception $e) {

            echo json_encode([
                'draw' => 0,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage()
            ]);
        }
    }
}
