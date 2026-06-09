<?php
if (!isset($active_menu)) {
    $current_page = basename($_SERVER['PHP_SELF']);
    if ($current_page === 'dse_lse_complaint_list.php') {
        $active_menu = 'complaint_list';
    } elseif ($current_page === 'complaint_details.php') {
        $from = $_GET['from'] ?? 'entry';
        $active_menu = ($from === 'list') ? 'complaint_list' : 'complaint_entry';
    } elseif ($current_page === 'new_complaint') {
        $active_menu = 'complaint_entry';
    } else {
        $active_menu = '';
    }
}
?>
 <div class="sidebar" id="sidebar">
     <div class="mobile-close" id="mobileClose">

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

         <a href="new_complaint.php" class="menu-item<?php echo ($active_menu === 'home') ? ' active' : ''; ?>">

             <i class="bi bi-grid"></i>

             Home

         </a>

     </div>



     <div class="menu-section">
         <div class="menu-heading">
             SUPPORT
         </div>
         <a href="new_complaint.php" class="menu-item<?php echo ($active_menu === 'complaint_entry') ? ' active' : ''; ?>">
             <i class="bi bi-credit-card"></i>
             Complaint Entry
         </a>
     
         <a href="dse_lse_complaint_list.php" class="menu-item<?php echo ($active_menu === 'complaint_list') ? ' active' : ''; ?>">
             <i class="bi bi-credit-card"></i>
             Assigned Complaint List
         </a>
     </div>




 </div>
 <script>
const menuToggle = document.getElementById('menuToggle');
menuToggle.addEventListener('click', function() {

    if (window.innerWidth <= 768) {

        sidebar.classList.toggle('mobile-show');

    } else {

        sidebar.classList.toggle('hide');

        mainWrapper.classList.toggle('full');

    }

});
 </script>