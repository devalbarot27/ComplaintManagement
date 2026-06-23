  <?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    include("session_check.php");
    require_once __DIR__ . '/includes/admin_access_helpers.php';
    require_once __DIR__ . '/includes/rbac_access_helpers.php';
    if (!isset($obconn)) {
        require_once __DIR__ . '/pdo_obconn.php';
    }
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
    } else if ($currentPage == "installed_base.php") {
        $pageName = "Installed Base Capture";
    } else if ($currentPage == "installed_base_details.php") {
        $pageName = "Installed Base Capture Details";
    } else if ($currentPage == "service_log.php") {
        $pageName = "Service Log Capture";
    } else if ($currentPage == "service_log_details.php") {
        $pageName = "Service Log Capture Details";
    } else if ($currentPage == "spare_parts_consumption.php") {
        $pageName = "Spare Parts Consumption";
    } else if ($currentPage == "spare_parts_consumption_details.php") {
        $pageName = "Spare Parts Consumption Details";
    } else if ($currentPage == "recent_orders.php") {
        $pageName = "Recent Orders";
    } else if ($currentPage == "despatch_details.php") {
        $pageName = "Despatch Details";
    } else if ($currentPage == "lr_details.php") {
        $pageName = "LR Details";
    } else if ($currentPage == 'index.php' || $currentPage == 'dashboard.php') {
        $pageName = "Dashboard";
    } else if ($currentPage == 'users.php') {
        $pageName = "User Management";
    } else if ($currentPage == 'user_details.php') {
        $pageName = "User Details";
    } else if ($currentPage == 'roles.php') {
        $pageName = "Role Management";
    } else if ($currentPage == 'role_details.php') {
        $pageName = "Role Details";
    } else if ($currentPage == 'modules.php') {
        $pageName = "Module Management";
    } else if ($currentPage == 'module_details.php') {
        $pageName = "Module Details";
    } else if ($currentPage == 'permissions.php') {
        $pageName = "Permission Management";
    } else if ($currentPage == 'permission_details.php') {
        $pageName = "Permission Details";
    } else if ($currentPage == 'assign_permissions.php') {
        $pageName = "Assign Permissions";
    } else if ($currentPage == 'access_denied.php') {
        $pageName = "Access Denied";
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

      <?php if (rbac_can_access_menu($obconn, 'dashboard.php')) { ?>
      <div class="menu-section">

          <div class="menu-heading">
              OVERVIEW
          </div>

          <a href="dashboard.php"
              class="menu-item <?= ($currentPage == 'index.php' || $currentPage == 'dashboard.php') ? 'active' : '' ?>">

              <i class="bi bi-grid"></i>

              Dashboard

          </a>

      </div>
      <?php } ?>

      <?php
        $showOrders = rbac_can_access_menu($obconn, 'orderbooking.php')
            || rbac_can_access_menu($obconn, 'order_acknowledgement.php')
            || rbac_can_access_menu($obconn, 'pending_order.php')
            || rbac_can_access_menu($obconn, 'recent_orders.php')
            || rbac_can_access_menu($obconn, 'despatch_details.php')
            || rbac_can_access_menu($obconn, 'lr_details.php');
      ?>
      <?php if ($showOrders) { ?>
      <div class="menu-section">

          <div class="menu-heading">
              ORDERS
          </div>

          <?php if (rbac_can_access_menu($obconn, 'orderbooking.php')) { ?>
          <a href="orderbooking.php"
              class="menu-item <?= ($currentPage == 'orderbooking.php') ? 'active' : '' ?>">

              <i class="bi bi-cart"></i>

              Order Booking

          </a>
          <?php } ?>

          <?php if (rbac_can_access_menu($obconn, 'order_acknowledgement.php')) { ?>
          <a href="order_acknowledgement.php"
              class="menu-item <?= ($currentPage == 'order_acknowledgement.php') ? 'active' : '' ?>">

              <i class="bi bi-check2-square"></i>

              Order Acknowledgement

          </a>
          <?php } ?>

          <?php if (rbac_can_access_menu($obconn, 'pending_order.php')) { ?>
          <a href="pending_order.php"
              class="menu-item <?= ($currentPage == 'pending_order.php') ? 'active' : '' ?>">

              <i class="bi bi-clock-history"></i>

              Pending Orders

          </a>
          <?php } ?>
          <?php if (rbac_can_access_menu($obconn, 'recent_orders.php')) { ?>
          <a href="recent_orders.php"
              class="menu-item <?= ($currentPage == 'recent_orders.php') ? 'active' : '' ?>">

              <i class="bi bi-arrow-down-left-square"></i>

              Recent Orders

          </a>
          <?php } ?>
          <?php if (rbac_can_access_menu($obconn, 'despatch_details.php')) { ?>
          <a href="despatch_details.php"
              class="menu-item <?= ($currentPage == 'despatch_details.php') ? 'active' : '' ?>">

              <i class="bi bi-capslock"></i>

              Despatch Details

          </a>
          <?php } ?>
          <?php if (rbac_can_access_menu($obconn, 'lr_details.php')) { ?>
          <a href="lr_details.php"
              class="menu-item <?= ($currentPage == 'lr_details.php') ? 'active' : '' ?>">

              <i class="bi bi-bus-front"></i>

              LR Details

          </a>
          <?php } ?>


      </div>
      <?php } ?>

      <?php
        $showAfterMarket = rbac_can_access_menu($obconn, 'installed_base.php')
            || rbac_can_access_menu($obconn, 'service_log.php')
            || rbac_can_access_menu($obconn, 'spare_parts_consumption.php');
      ?>
      <?php if ($showAfterMarket) { ?>
      <div class="menu-section">

          <div class="menu-heading">
              AFTER MARKET
          </div>

          <?php if (rbac_can_access_menu($obconn, 'installed_base.php')) { ?>
          <a href="installed_base.php" class="menu-item <?= ($currentPage == 'installed_base.php' || $currentPage == 'installed_base_details.php') ? 'active' : '' ?>">
              <i class="bi bi-bank"></i>
              Installed Base Capture
          </a>
          <?php } ?>
          <?php if (rbac_can_access_menu($obconn, 'service_log.php')) { ?>
          <a href="service_log.php" class="menu-item <?= ($currentPage == 'service_log.php' || $currentPage == 'service_log_details.php') ? 'active' : '' ?>">
              <i class="bi bi-clipboard-pulse"></i>
              Service Log Capture
          </a>
          <?php } ?>
          <?php if (rbac_can_access_menu($obconn, 'spare_parts_consumption.php')) { ?>
          <a href="spare_parts_consumption.php" class="menu-item <?= ($currentPage == 'spare_parts_consumption.php' || $currentPage == 'spare_parts_consumption_details.php') ? 'active' : '' ?>">
              <i class="bi bi-gear"></i>
              Spare Parts Consumption
          </a>
          <?php } ?>

      </div>
      <?php } ?>

      <?php
        $showSupport = rbac_can_access_menu($obconn, 'new_complaint.php')
            || rbac_can_access_menu($obconn, 'dse_lse_complaint_list.php');
      ?>
      <?php if ($showSupport) { ?>
      <div class="menu-section">

          <div class="menu-heading">
              SUPPORT
          </div>

          <?php if (rbac_can_access_menu($obconn, 'new_complaint.php')) { ?>
          <a href="new_complaint.php"
              class="menu-item <?= ($currentPage == 'new_complaint.php' || ($currentPage == 'complaint_details.php' && @$_GET['from'] == 'entry')) ? 'active' : '' ?>">
              <i class="bi bi-credit-card"></i>
              Complaint Entry
          </a>
          <?php } ?>
          <?php if (rbac_can_access_menu($obconn, 'dse_lse_complaint_list.php')) { ?>
          <a href="dse_lse_complaint_list.php" class="menu-item <?= ($currentPage == 'dse_lse_complaint_list.php' || ($currentPage == 'complaint_details.php' && @$_GET['from'] == 'list')) ? 'active' : '' ?>">
              <i class="bi bi-microsoft-teams"></i>
              Assigned Complaint List
          </a>
          <?php } ?>


      </div>
      <?php } ?>

      <?php if (is_system_admin()) { ?>
      <div class="menu-section">

          <div class="menu-heading">
              ADMINISTRATION
          </div>

          <a href="users.php"
              class="menu-item <?= ($currentPage == 'users.php' || $currentPage == 'user_details.php') ? 'active' : '' ?>">
              <i class="bi bi-people"></i>
              Users
          </a>

          <a href="roles.php"
              class="menu-item <?= ($currentPage == 'roles.php' || $currentPage == 'role_details.php') ? 'active' : '' ?>">
              <i class="bi bi-shield-lock"></i>
              Roles
          </a>

          <a href="modules.php"
              class="menu-item <?= ($currentPage == 'modules.php' || $currentPage == 'module_details.php') ? 'active' : '' ?>">
              <i class="bi bi-grid-3x3-gap"></i>
              Modules
          </a>

          <a href="permissions.php"
              class="menu-item <?= ($currentPage == 'permissions.php' || $currentPage == 'permission_details.php') ? 'active' : '' ?>">
              <i class="bi bi-key"></i>
              Permissions
          </a>

          <a href="assign_permissions.php"
              class="menu-item <?= ($currentPage == 'assign_permissions.php') ? 'active' : '' ?>">
              <i class="bi bi-check2-square"></i>
              Assign Permissions
          </a>

      </div>
      <?php } ?>


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
