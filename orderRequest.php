
<?php
session_start();

include('pdo_obconn.php');
include('orderClass.php');

$ordInstance = new orderClass($obconn, $dpconn);


switch ($_POST['action']) {
     case 'addItem':
          echo $ordInstance->addItemCart();
          break;

     case 'searchItems':
          echo $ordInstance->searchItems();
          break;
     case 'itemSync':
          echo $ordInstance->itemSync();
          break;
     case 'getCartItems':
          echo $ordInstance->getCartItems();
          break;
     case 'deleteItem':
          echo $ordInstance->deleteItem();
          break;
     case 'updatePrice':
          echo $ordInstance->updatePrice();
          break;
     case 'submitCart':
          echo $ordInstance->submitCart();
          break;
     case 'getOrderAcknowledgeList':
          echo $ordInstance->getOrderAcknowledgeList();
          break;
     case 'customer_master':
          echo $ordInstance->customer_master();
          break;
     case 'getAcknowledgeLine':
          echo $ordInstance->getAcknowledgeLine();
          break;
     case 'getRecentOrderLine':
          echo $ordInstance->getRecentOrderLine();
          break;
     case 'getRecentOrders':
          echo $ordInstance->getRecentOrders();
          break;
     case 'getPendingOrderList':
          echo $ordInstance->getPendingOrderList();
          break;
     case 'getPendingOrderListNew':
               echo $ordInstance->getPendingOrderListNew();
               break;
     case 'getPendingOrderPriceBreakup':
          echo $ordInstance->getPendingOrderPriceBreakup();
          break;
     case 'getPrice':
          echo $ordInstance->getPrice();
          break;
     case 'getCartPriceBreakup':
          echo $ordInstance->getCartPriceBreakup();
          break;
     case 'getCartOrderPriceBreakup':
          echo $ordInstance->getCartOrderPriceBreakup();
          break;
     case 'getDespatchDetails':
          echo $ordInstance->getDespatchDetails();
          break;
     case 'getLrDetails':
          echo $ordInstance->getLrDetails();
          break;
     case 'submitCartApi':
          echo $ordInstance->submitCartApi();
          break;
     case 'search_dealer':
          echo $ordInstance->search_dealer();
          break;
     default:
          echo json_encode([
               'status' => false,
               'message' => 'Invalid action'
          ]);
          break;
}

?>