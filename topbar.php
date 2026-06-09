 

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
                          G
                      </div>

                      <div>

                          <div class="profile-name">
                              Gowtham
                          </div>

                          <div class="profile-role">
                              Admin User
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
                      class="profile-item">

                      <i class="bi bi-shield-lock"></i>

                      Change Password

                  </a>

                  <div class="profile-divider"></div>

                  <a href="index.php"
                      class="profile-item logout">

                      <i class="bi bi-box-arrow-right"></i>

                      Logout

                  </a>

              </div>

          </div>

      </div>


  <script>
      // PROFILE DROPDOWN

      const profileToggle = document.getElementById('profileToggle');

      const profileMenu = document.getElementById('profileMenu');

      profileToggle.addEventListener('click', function(e) {
          console.log("sdf");

          e.stopPropagation();

          profileMenu.classList.toggle('show');

      });

      // PREVENT CLOSE WHEN CLICK INSIDE

      profileMenu.addEventListener('click', function(e) {

          e.stopPropagation();

      });

      // OUTSIDE CLICK CLOSE

      document.addEventListener('click', function() {

          profileMenu.classList.remove('show');

      });
  </script>