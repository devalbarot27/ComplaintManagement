<?php

$topbarUserName = trim((string) ($_SESSION['display_name'] ?? ''));

if ($topbarUserName === '') {
    $topbarUserName = trim((string) ($_SESSION['usr_name'] ?? ''));
}

$topbarUserInitial = $topbarUserName !== ''
    ? strtoupper(substr($topbarUserName, 0, 1))
    : 'U';

if (!isset($obconn)) {
    require_once __DIR__ . '/pdo_obconn.php';
}

require_once __DIR__ . '/includes/current_username_helpers.php';
require_once __DIR__ . '/includes/notification_helpers.php';

notification_ensure_schema($obconn);

$topbarNotificationUserId = current_user_id($obconn);
$topbarUnreadCount = ($topbarNotificationUserId !== null && $topbarNotificationUserId > 0)
    ? notification_unread_count($obconn, $topbarNotificationUserId)
    : 0;

?>

<div class="topbar-right">

    <div class="notification-dropdown">
        <button type="button"
            class="notification-bell-btn"
            id="notificationBellBtn"
            aria-label="Notifications"
            aria-haspopup="true"
            aria-expanded="false">
            <i class="bi bi-bell"></i>
            <span class="notification-badge<?= $topbarUnreadCount > 0 ? ' is-visible' : '' ?>"
                id="notificationBadge"
                aria-hidden="<?= $topbarUnreadCount > 0 ? 'false' : 'true' ?>">
                <?= $topbarUnreadCount > 99 ? '99+' : ($topbarUnreadCount > 0 ? (int) $topbarUnreadCount : '') ?>
            </span>
        </button>

        <div class="notification-menu" id="notificationMenu">
            <div class="notification-menu-header">
                <h6>Notifications</h6>
                <button type="button"
                    class="notification-mark-all"
                    id="notificationMarkAllBtn"
                    <?= $topbarUnreadCount > 0 ? '' : 'disabled' ?>>
                    Mark all as read
                </button>
            </div>
            <div class="notification-list" id="notificationDropdownList">
                <div class="notification-loading">Loading...</div>
            </div>
            <div class="notification-menu-footer">
                <a href="notifications.php">View All</a>
            </div>
        </div>
    </div>

    <div class="profile-dropdown">

        <div class="profile-btn" id="profileToggle">
            <i class="bi bi-person-workspace"></i>
        </div>

        <div class="profile-menu" id="profileMenu">

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

            <a href="#" class="profile-item" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                <i class="bi bi-shield-lock"></i>
                Change Password
            </a>

            <div class="profile-divider"></div>

            <a href="logout.php" class="profile-item logout">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>

        </div>

    </div>

</div>

<?php include __DIR__ . '/includes/change_password_modal.php'; ?>

<script src="js/notifications.js"></script>

<script>
    const profileToggle = document.getElementById('profileToggle');
    const profileMenu = document.getElementById('profileMenu');

    if (profileToggle && profileMenu) {
        profileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const notificationMenu = document.getElementById('notificationMenu');
            const notificationBellBtn = document.getElementById('notificationBellBtn');
            if (notificationMenu) {
                notificationMenu.classList.remove('show');
            }
            if (notificationBellBtn) {
                notificationBellBtn.classList.remove('is-open');
            }
            profileMenu.classList.toggle('show');
        });

        profileMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        document.addEventListener('click', function() {
            profileMenu.classList.remove('show');
        });
    }
</script>