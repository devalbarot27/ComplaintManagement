<?php

$topbarUserName = trim((string) ($_SESSION['display_name'] ?? ''));

if ($topbarUserName === '') {

    $topbarUserName = trim((string) ($_SESSION['usr_name'] ?? ''));

}

$topbarUserInitial = $topbarUserName !== ''

    ? strtoupper(substr($topbarUserName, 0, 1))

    : 'U';

?>





      <?php if (isset($_SESSION['success_message'])) { ?>
      <div class="alert alert-success alert-dismissible fade show mb-0 mx-3 mt-3" role="alert">
          <?php echo htmlspecialchars((string) $_SESSION['success_message']); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php unset($_SESSION['success_message']); } ?>

      <div class="topbar-right">



          <!-- <i class="bi bi-bell text-secondary"></i> -->



          <div class="profile-dropdown">



              <div class="profile-btn" id="profileToggle">



                  <i class="bi bi-person-workspace"></i>



              </div>



              <!-- DROPDOWN -->



              <div class="profile-menu"

                  id="profileMenu">



                  <div class="profile-info">



                      <div class="profile-avatar">

                          <?php echo htmlspecialchars($topbarUserInitial); ?>

                      </div>



                      <div>



                          <div class="profile-name">

                              <?php echo htmlspecialchars($topbarUserName !== '' ? $topbarUserName : 'User'); ?>

                          </div>



                          <div class="profile-role">

                              Logged In

                          </div>



                      </div>



                  </div>



                  <div class="profile-divider"></div>



                  <a href="#"

                      class="profile-item">



                      <i class="bi bi-person-circle"></i>



                      My Profile



                  </a>



                  <a href="#"

                      class="profile-item">



                      <i class="bi bi-gear"></i>



                      Settings



                  </a>



                  <a href="#"

                      class="profile-item"

                      data-bs-toggle="modal"

                      data-bs-target="#changePasswordModal">



                      <i class="bi bi-shield-lock"></i>



                      Change Password



                  </a>



                  <div class="profile-divider"></div>



                  <a href="logout.php"

                      class="profile-item logout">



                      <i class="bi bi-box-arrow-right"></i>



                      Logout



                  </a>



              </div>



          </div>



      </div>



<?php include __DIR__ . '/includes/change_password_modal.php'; ?>



  <script>

      const profileToggle = document.getElementById('profileToggle');

      const profileMenu = document.getElementById('profileMenu');



      if (profileToggle && profileMenu) {

          profileToggle.addEventListener('click', function (e) {

              e.stopPropagation();

              profileMenu.classList.toggle('show');

          });



          profileMenu.addEventListener('click', function (e) {

              e.stopPropagation();

          });



          document.addEventListener('click', function () {

              profileMenu.classList.remove('show');

          });

      }

  </script>
