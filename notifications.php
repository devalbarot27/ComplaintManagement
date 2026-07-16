<?php
session_start();
include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/current_username_helpers.php';
require_once 'includes/notification_helpers.php';

notification_ensure_schema($obconn);

$userId = current_user_id($obconn);
if ($userId === null || $userId <= 0) {
    header('Location: login.php');
    exit;
}

$unreadCount = notification_unread_count($obconn, $userId);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <?php include 'header_css.php'; ?>
    <link href="css/notifications.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="main-wrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <div class="notifications-page-card">
                <div class="notifications-page-toolbar">
                    <h5>All Notifications</h5>
                    <button type="button"
                        class="notification-mark-all"
                        id="notificationsPageMarkAllBtn"
                        <?= $unreadCount > 0 ? '' : 'disabled' ?>>
                        Mark all as read
                    </button>
                </div>

                <div class="notifications-page-list" id="notificationsPageList">
                    <div class="notification-loading">Loading...</div>
                </div>

                <div class="notifications-load-more-wrap">
                    <button type="button"
                        class="notifications-load-more-btn"
                        id="notificationsLoadMoreBtn"
                        style="display:none;">
                        Load More
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <?php include 'script_js.php'; ?>
</body>

</html>