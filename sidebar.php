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
    } else if ($currentPage == "recent_orders.php" || $currentPage == "recent_order_details.php") {
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
    } else if ($currentPage == 'user_edit.php') {
        $pageName = "Edit User";
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
    } else if ($currentPage == 'industry_segments.php') {
        $pageName = "Industry Segment";
    } else if ($currentPage == 'industry_segment_details.php') {
        $pageName = "Industry Segment Details";
    } else if ($currentPage == 'warranty_chargeable.php') {
        $pageName = "Warranty / Chargeable";
    } else if ($currentPage == 'warranty_chargeable_details.php') {
        $pageName = "Warranty / Chargeable Details";
    } else if ($currentPage == 'part_replaced.php') {
        $pageName = "Part Replaced";
    } else if ($currentPage == 'part_replaced_details.php') {
        $pageName = "Part Replaced Details";
    } else if ($currentPage == 'customer_feedback.php') {
        $pageName = "Customer Feedback";
    } else if ($currentPage == 'customer_feedback_details.php') {
        $pageName = "Customer Feedback Details";
    } else if ($currentPage == 'reasons.php') {
        $pageName = "Reason";
    } else if ($currentPage == 'reason_details.php') {
        $pageName = "Reason Details";
    } else if ($currentPage == 'access_denied.php') {
        $pageName = "Access Denied";
    } else if ($currentPage == 'notifications.php') {
        $pageName = "Notifications";
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

      <div class="mobile-close" id="mobileClose">
          <i class="bi bi-x-lg"></i>
      </div>

      <div class="sidebar-brand">
          <a href="dashboard.php" class="sidebar-brand__link" aria-label="Go to Dashboard">
              <div class="sidebar-brand__logo-panel">
                  <?php
                    $brandLogoClass = 'sidebar-brand__logo-box';
                    $brandImageClass = 'sidebar-brand__logo';
                    include __DIR__ . '/includes/auth_brand_logo.php';
                    unset($brandLogoClass, $brandImageClass);
                    ?>
              </div>
              <div class="sidebar-brand__copy">
                  <span class="sidebar-brand__title">Dealer Portal</span>
                  <span class="sidebar-brand__subtitle">Service &amp; Operations</span>
              </div>
          </a>
      </div>

      <div class="sidebar-nav">

          <!-- OVERVIEW -->
          <?php if (rbac_can_access_menu($obconn, 'dashboard.php')) { ?>
              <div class="menu-section">
                  <div class="menu-heading">OVERVIEW</div>

                  <a href="dashboard.php"
                      class="menu-item <?= ($currentPage == 'index.php' || $currentPage == 'dashboard.php') ? 'active' : '' ?>">
                      <i class="bi bi-grid"></i>
                      Dashboard
                  </a>
              </div>
          <?php } ?>
          <!-- END OVERVIEW -->

          <?php
            $canOrderBooking = rbac_can_access_menu($obconn, 'orderbooking.php');
            $canOrderAcknowledgement = rbac_can_access_menu($obconn, 'order_acknowledgement.php');
            $canPendingOrders = rbac_can_access_menu($obconn, 'pending_order.php');
            $canRecentOrders = rbac_can_access_menu($obconn, 'recent_orders.php');
            $canDespatchDetails = rbac_can_access_menu($obconn, 'despatch_details.php');
            $canLrDetails = rbac_can_access_menu($obconn, 'lr_details.php');
            $showOrders = $canOrderBooking
                || $canOrderAcknowledgement
                || $canPendingOrders
                || $canRecentOrders
                || $canDespatchDetails
                || $canLrDetails;
            ?>
          <?php if ($showOrders) { ?>
              <div class="menu-section">
                  <div class="menu-heading">ORDERS</div>

                  <?php if ($canOrderBooking) { ?>
                      <a href="orderbooking.php"
                          class="menu-item <?= ($currentPage == 'orderbooking.php') ? 'active' : '' ?>">
                          <i class="bi bi-cart"></i>
                          Order Booking
                      </a>
                  <?php } ?>

                  <?php if ($canOrderAcknowledgement) { ?>
                      <a href="order_acknowledgement.php"
                          class="menu-item <?= ($currentPage == 'order_acknowledgement.php' || ($currentPage == 'order_data.php' && @$_GET['reference'] == 'order_acknowledgement')) ? 'active' : '' ?>">
                          <i class="bi bi-check2-square"></i>
                          Order Acknowledgement
                      </a>
                  <?php } ?>

                  <?php if ($canPendingOrders) { ?>
                      <a href="pending_order.php"
                          class="menu-item <?= ($currentPage == 'pending_order.php' || ($currentPage == 'order_data.php' && @$_GET['reference'] == 'pending_order')) ? 'active' : '' ?>">
                          <i class="bi bi-clock-history"></i>
                          Pending Orders
                      </a>
                  <?php } ?>

                  <?php if ($canRecentOrders) { ?>
                      <a href="recent_orders.php"
                          class="menu-item <?= ($currentPage == 'recent_orders.php' || $currentPage == 'recent_order_details.php') ? 'active' : '' ?>">
                          <i class="bi bi-arrow-down-left-square"></i>
                          Recent Orders
                      </a>
                  <?php } ?>

                  <?php if ($canDespatchDetails) { ?>
                      <a href="despatch_details.php"
                          class="menu-item <?= ($currentPage == 'despatch_details.php') ? 'active' : '' ?>">
                          <i class="bi bi-capslock"></i>
                          Despatch Details
                      </a>
                  <?php } ?>

                  <?php if ($canLrDetails) { ?>
                      <a href="lr_details.php"
                          class="menu-item <?= ($currentPage == 'lr_details.php') ? 'active' : '' ?>">
                          <i class="bi bi-bus-front"></i>
                          LR Details
                      </a>
                  <?php } ?>
              </div>
          <?php } ?>

          <?php
            // AFTER MARKET ALWAYS SHOW
            $canInstalledBase = rbac_can_access_menu($obconn, 'installed_base.php');
            $canServiceLog = rbac_can_access_menu($obconn, 'service_log.php');
            $canSparePartsConsumption = rbac_can_access_menu($obconn, 'spare_parts_consumption.php');
            $showAfterMarket = $canInstalledBase
                || $canServiceLog
                || $canSparePartsConsumption;
            ?>
          <?php if ($canInstalledBase || $canServiceLog || $canSparePartsConsumption) { ?>
              <div class="menu-section">
                  <div class="menu-heading">AFTER MARKET</div>

                  <?php if ($canInstalledBase) { ?>
                      <a href="installed_base.php"
                          class="menu-item <?= ($currentPage == 'installed_base.php' || $currentPage == 'installed_base_details.php') ? 'active' : '' ?>">
                          <i class="bi bi-bank"></i>
                          Installed Base Capture
                      </a>
                  <?php } ?>

                  <?php if ($canServiceLog) { ?>
                      <a href="service_log.php"
                          class="menu-item <?= ($currentPage == 'service_log.php' || $currentPage == 'service_log_details.php') ? 'active' : '' ?>">
                          <i class="bi bi-clipboard-pulse"></i>
                          Service Log Capture
                      </a>
                  <?php } ?>

                  <?php if ($canSparePartsConsumption) { ?>
                      <a href="spare_parts_consumption.php"
                          class="menu-item <?= ($currentPage == 'spare_parts_consumption.php' || $currentPage == 'spare_parts_consumption_details.php') ? 'active' : '' ?>">
                          <i class="bi bi-gear"></i>
                          Spare Parts Consumption
                      </a>
                  <?php } ?>
              </div>
          <?php } ?>

          <?php
            $canComplaintEntry = rbac_can_access_menu($obconn, 'new_complaint.php');
            $canAssignedComplaintList = rbac_can_access_menu($obconn, 'dse_lse_complaint_list.php');
            $showSupport = $canComplaintEntry || $canAssignedComplaintList;
            ?>
          <?php if ($canComplaintEntry || $canAssignedComplaintList) { ?>
              <div class="menu-section">
                  <div class="menu-heading">SUPPORT</div>

                  <?php if ($canComplaintEntry) { ?>
                      <a href="new_complaint.php"
                          class="menu-item <?= ($currentPage == 'new_complaint.php' || ($currentPage == 'complaint_details.php' && @$_GET['from'] == 'entry')) ? 'active' : '' ?>">
                          <i class="bi bi-credit-card"></i>
                          Complaint Entry
                      </a>
                  <?php } ?>

                  <?php if ($canAssignedComplaintList) { ?>
                      <a href="dse_lse_complaint_list.php"
                          class="menu-item <?= ($currentPage == 'dse_lse_complaint_list.php' || ($currentPage == 'complaint_details.php' && @$_GET['from'] == 'list')) ? 'active' : '' ?>">
                          <i class="bi bi-microsoft-teams"></i>
                          Assigned Complaint List
                      </a>
                  <?php } ?>
              </div>
          <?php } ?>

          <?php if (is_system_admin()) { ?>
              <div class="menu-section">
                  <div class="menu-heading">ADMINISTRATION</div>

                  <a href="users.php"
                      class="menu-item <?= ($currentPage == 'users.php' || $currentPage == 'user_details.php' || $currentPage == 'user_edit.php') ? 'active' : '' ?>">
                      <i class="bi bi-people"></i>
                      Users
                  </a>

                  <a href="roles.php"
                      class="menu-item <?= ($currentPage == 'roles.php' || $currentPage == 'role_details.php') ? 'active' : '' ?>">
                      <i class="bi bi-shield-lock"></i>
                      Roles
                  </a>

                  <?php /* 
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
        */ ?>

                  <a href="assign_permissions.php"
                      class="menu-item <?= ($currentPage == 'assign_permissions.php') ? 'active' : '' ?>">
                      <i class="bi bi-check2-square"></i>
                      Assign Permissions
                  </a>
              </div>

              <div class="menu-section">

                  <div class="menu-heading">
                      SYSTEM CONFIGURATION
                  </div>

                  <a href="complaint_categories.php"
                      class="menu-item <?= in_array($currentPage, ['complaint_categories.php', 'complaint_category_details.php'], true) ? 'active' : '' ?>">
                      <i class="bi bi-tags"></i>
                      Complaint Category
                  </a>

                  <a href="industry_segments.php"
                      class="menu-item <?= in_array($currentPage, ['industry_segments.php', 'industry_segment_details.php'], true) ? 'active' : '' ?>">
                      <i class="bi bi-building"></i>
                      Industry Segment
                  </a>

                  <a href="warranty_chargeable.php"
                      class="menu-item <?= in_array($currentPage, ['warranty_chargeable.php', 'warranty_chargeable_details.php'], true) ? 'active' : '' ?>">
                      <i class="bi bi-shield-check"></i>
                      Warranty / Chargeable
                  </a>

                  <a href="part_replaced.php"
                      class="menu-item <?= in_array($currentPage, ['part_replaced.php', 'part_replaced_details.php'], true) ? 'active' : '' ?>">
                      <i class="bi bi-tools"></i>
                      Part Replaced
                  </a>

                  <a href="customer_feedback.php"
                      class="menu-item <?= in_array($currentPage, ['customer_feedback.php', 'customer_feedback_details.php'], true) ? 'active' : '' ?>">
                      <i class="bi bi-chat-left-text"></i>
                      Customer Feedback
                  </a>

                  <a href="reasons.php"
                      class="menu-item <?= in_array($currentPage, ['reasons.php', 'reason_details.php'], true) ? 'active' : '' ?>">
                      <i class="bi bi-list-check"></i>
                      Reason
                  </a>

              </div>
          <?php } ?>

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
  <?php if (!empty($_SESSION['usr_name'])): ?>
      <script src="js/session_tab_tracker.js"></script>
  <?php endif; ?>