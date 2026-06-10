  <?php

    if (session_status() === PHP_SESSION_NONE) {

        session_start();

    }

    include("session_check.php");

    $currentPage = basename($_SERVER['PHP_SELF']);

    $pageName = "";

    if ($currentPage == "orderbooking.php") {

        $pageName = "Create Order";

    } else if ($currentPage == "order_acknowledgement.php") {

        $pageName = "Order Acknowledgement List";

    } else if ($currentPage == "pending_order.php") {

        $pageName = "Pending Order List";

    } else if ($currentPage == "new_complaint.php") {

        $pageName = "Complaint Entry";

    } else if ($currentPage == "dse_lse_complaint_list.php") {

        $pageName = "Assigned Complaint List";

    }else if ($currentPage == "installed_base.php") {

        $pageName = "Installed Base Capture";

    }else if ($currentPage == "service_log.php") {

        $pageName = "Service Log Capture";

    }

    ?>
<!-- TOPBAR -->
<div class="topbar">
<div class="topbar-left">
<i class="bi bi-list toggle-btn" id="menuToggle"></i>
<h5 class="page-subtitle mb-0">
<?php echo $pageName; ?>
</h5>
</div>
<?php include('topbar.php'); ?>
</div>
 
  <div class="sidebar" id="sidebar">
<div class="mobile-close"

          id="mobileClose">
 
          <i class="bi bi-x-lg"></i>
 
      </div>
 
      <div class="brand-section">
 
          <div class="brand-wrapper">
 
              <div class="brand-logo">

                  DP
</div>
 
              <div>
 
                  <div class="brand-title">

                      Dealer Portal
</div>
 
              </div>
 
          </div>
 
      </div>
 
      <!-- MENU -->
 
      <div class="menu-section">
 
          <div class="menu-heading">

              OVERVIEW
</div>
 
          <a href="dashboard.php"

              class="menu-item <?= ($currentPage == 'dashboard.php') ? 'active' : '' ?>">
 
              <i class="bi bi-grid"></i>
 
              Dashboard
 
          </a>
 
      </div>
 
      <div class="menu-section">
 
          <div class="menu-heading">

              ORDERS
</div>
 
          <a href="orderbooking.php"

              class="menu-item <?= ($currentPage == 'orderbooking.php') ? 'active' : '' ?>">
 
              <i class="bi bi-cart"></i>
 
              Order Booking
 
          </a>
 
          <a href="order_acknowledgement.php"

              class="menu-item <?= ($currentPage == 'order_acknowledgement.php') ? 'active' : '' ?>">
 
              <i class="bi bi-check2-square"></i>
 
              Order Acknowledgement
 
          </a>
 
          <a href="pending_order.php"

              class="menu-item <?= ($currentPage == 'pending_order.php') ? 'active' : '' ?>">
 
              <i class="bi bi-clock-history"></i>
 
              Pending Orders
 
          </a>
 
      </div>
 
      <div class="menu-section">
 
          <div class="menu-heading">

              AFTER MARKET
</div>
 
          <a href="installed_base.php"

              class="menu-item <?= ($currentPage == 'installed_base.php' || $currentPage == 'installed_base_details.php') ? 'active' : '' ?>">
 
              <i class="bi bi-bank"></i>
 
              Installed Base Capture
 
          </a>
<a href="service_log.php"

              class="menu-item <?= ($currentPage == 'service_log.php' || $currentPage == 'service_log_details.php') ? 'active' : '' ?>">
 
              <i class="bi bi-clipboard-pulse"></i>
 
              Service Log Capture
 
          </a>
<a href="spare_parts_consumption.php"

              class="menu-item <?= ($currentPage == 'spare_parts_consumption.php') ? 'active' : '' ?>">
 
              <i class="bi bi-gear"></i>
 
              Spare Parts Consumption
 
          </a>
 
      </div>
 
      <!-- <div class="menu-section">
 
          <div class="menu-heading">

              FINANCE
</div>
 
          <a href="ar_statement.php"

              class="menu-item">
 
              <i class="bi bi-credit-card"></i>
 
              AR Statement
 
          </a>
 
      </div> -->
 
 
      <div class="menu-section">
 
          <div class="menu-heading">

              SUPPORT
</div>
 
          <a href="new_complaint.php"

              class="menu-item <?= ($currentPage == 'new_complaint.php' || ($currentPage == 'complaint_details.php' && @$_GET['from'] == 'entry')) ? 'active' : '' ?>">
<i class="bi bi-credit-card"></i>

              Complaint Entry
</a>
<a href="dse_lse_complaint_list.php" class="menu-item <?= ($currentPage == 'dse_lse_complaint_list.php' || ($currentPage == 'complaint_details.php' && @$_GET['from'] == 'list')) ? 'active' : '' ?>">
<i class="bi bi-microsoft-teams"></i>

              Assigned Complaint List
</a>
 
 
      </div>
 
 
  </div>
<script>

      const menuToggle = document.getElementById('menuToggle');

      const sidebar = document.getElementById('sidebar');
 
      menuToggle.addEventListener('click', function() {
 
          if (window.innerWidth <= 768) {
 
              sidebar.classList.toggle('mobile-show');
 
          } else {
 
              sidebar.classList.toggle('hide');
 
              const mainWrapper = document.getElementById('mainWrapper');

              if (mainWrapper) {

                  mainWrapper.classList.toggle('full');

              }

          }
 
      });
 
      const mobileClose = document.getElementById('mobileClose');
 
      mobileClose.addEventListener('click', function() {

          sidebar.classList.remove('mobile-show');

      });
</script>
 