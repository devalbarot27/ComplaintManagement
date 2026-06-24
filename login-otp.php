<!doctype html>
<?php
session_start();

include 'pdo_obconn.php';
include 'includes/login_helpers.php';

$error_message = '';
$success_message = '';
$username_value = '';

if (!empty($_SESSION['usr_name'])) {
    header('Location: index.php');
    exit;
}

if (login_attempt_remember($obconn)) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_value = trim($_POST['usr_name'] ?? '');

    if ($username_value === '') {
        $error_message = 'Username is required.';
    } else {
        try {
            $result = login_issue_otp($obconn, $username_value);

            if ($result['success']) {
                header('Location: verify-otp.php');
                exit;
            }

            $error_message = $result['error'] ?? 'Failed to send OTP. Please try again.';
        } catch (PDOException $e) {
            $error_message = 'Failed to send OTP. Please try again.';
        }
    }
}
?>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dealer Portal Login - OTP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="css/auth_pages.css" rel="stylesheet" />
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: #f1f5f9; height: 100vh; overflow: hidden; }
    .login-page { width: 100%; height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .login-card { width: 100%; max-width: 980px; min-height: 620px; background: #fff; border-radius: 22px; overflow: hidden; display: flex; box-shadow: 0 10px 35px rgba(15, 23, 42, 0.08); }
    .left-panel { width: 45%; padding: 50px; color: #fff; display: flex; flex-direction: column; justify-content: center; }
    .left-panel h2 { font-size: 34px; font-weight: 700; margin-bottom: 14px; line-height: 1.3; }
    .left-panel p { font-size: 15px; line-height: 1.8; opacity: .9; margin-bottom: 30px; }
    .right-panel { width: 55%; padding: 50px; display: flex; flex-direction: column; justify-content: center; }
    .login-title { font-size: 28px; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
    .login-subtitle { font-size: 14px; color: #64748b; margin-bottom: 24px; }
    .form-group { margin-bottom: 20px; }
    .form-label { font-size: 14px; font-weight: 600; color: #0f172a; margin-bottom: 10px; display: block; }
    .custom-input { width: 100%; height: 50px; border: 1px solid #dbe2ea; border-radius: 14px; padding: 0 16px; font-size: 14px; outline: none; transition: .2s; }
    .custom-input:focus { border-color: #1565d8; box-shadow: 0 0 0 4px rgba(21, 101, 216, 0.08); }
    .custom-input.is-invalid { border-color: #dc3545; }
    .validation-msg { color: #dc3545; font-size: 13px; margin-top: 6px; }
    .login-alert { border-radius: 12px; font-size: 14px; margin-bottom: 20px; }
    .form-options { margin-top: 20px; }
    .forgot-link { font-size: 14px; color: #1565d8; text-decoration: none; font-weight: 500; }
    @media(max-width:992px) { body { overflow: auto; } .login-page { height: auto; min-height: 100vh; } .login-card { flex-direction: column; min-height: auto; } .left-panel, .right-panel { width: 100%; } }
    </style>
</head>

<body>
    <div class="login-page">
        <div class="login-card">
            <div class="left-panel">
                <?php include 'includes/auth_brand_logo.php'; ?>
                <h2>Welcome to Dealer Portal</h2>
                <p>Manage orders, dispatch, AR statements and dealer operations.</p>
            </div>

            <div class="right-panel">
                <div class="login-title">Login with OTP</div>
                <div class="login-subtitle">Enter your username to receive a one-time password on your registered email.</div>

                <?php if (!empty($error_message)) { ?>
                <div class="alert alert-danger login-alert" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php } ?>

                <form method="post" action="login-otp.php" id="loginOtpForm" novalidate>
                    <div class="form-group">
                        <label class="form-label" for="usr_name">Username</label>
                        <input
                            type="text"
                            class="custom-input"
                            id="usr_name"
                            name="usr_name"
                            placeholder="Enter username"
                            value="<?php echo htmlspecialchars($username_value); ?>"
                            autocomplete="username"
                        >
                        <div class="validation-msg" data-field="usr_name"></div>
                    </div>

                    <button type="submit" class="login-btn">Send OTP</button>
                </form>

                <div class="form-options">
                    <a href="login.php" class="forgot-link text-dark">Login with Username &amp; Password</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/validate.js/0.13.1/validate.min.js"></script>
    <script src="js/login_otp_validation.js"></script>
</body>
</html>
