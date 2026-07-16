<!doctype html>
<?php
session_start();

include 'pdo_obconn.php';
include 'includes/login_helpers.php';

$error_message = '';
$success_message = '';
$maskedEmail = '';

if (!empty($_SESSION['usr_name'])) {
    header('Location: index.php');
    exit;
}

if (login_attempt_remember($obconn)) {
    header('Location: index.php');
    exit;
}

$otpUser = login_user_from_otp_session($obconn);
if ($otpUser === null) {
    header('Location: login-otp.php');
    exit;
}

$maskedEmail = login_mask_email((string) ($otpUser['email'] ?? ''));
$resendSecondsRemaining = login_otp_resend_seconds_remaining();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend_otp'])) {
        try {
            $result = login_issue_otp($obconn, (string) $otpUser['usr_name'], true);
            if ($result['success']) {
                $success_message = 'OTP has been sent to your registered email address.';
                $otpUser = login_user_from_otp_session($obconn) ?? $otpUser;
                $maskedEmail = login_mask_email((string) ($otpUser['email'] ?? ''));
                $resendSecondsRemaining = login_otp_resend_seconds_remaining();
            } else {
                $error_message = $result['error'] ?? 'Failed to resend OTP. Please try again.';
                $resendSecondsRemaining = login_otp_resend_seconds_remaining();
            }
        } catch (PDOException $e) {
            $error_message = 'Failed to resend OTP. Please try again.';
        }
    } else {
        $enteredOtp = trim((string) ($_POST['otp'] ?? ''));
        $rememberMe = isset($_POST['remember_me']);

        if (!login_verify_otp($enteredOtp)) {
            $error_message = 'Invalid or expired OTP. Please try again.';
        } else {
            login_update_last_login_at($obconn, (string) $otpUser['usr_name']);
            login_start_session($otpUser, $rememberMe);
            login_clear_otp_session();
            header('Location: index.php');
            exit;
        }
    }
}
?>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dealer Portal - Verify OTP</title>
    <?php include 'includes/favicon.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="css/auth_pages.css" rel="stylesheet" />
    <style>
    body { font-family: 'Inter', sans-serif; background: #f1f5f9; height: 100vh; overflow: hidden; }
    .login-page { width: 100%; height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .login-card { width: 100%; max-width: 980px; min-height: 620px; background: #fff; border-radius: 22px; overflow: hidden; display: flex; box-shadow: 0 10px 35px rgba(15, 23, 42, 0.08); }
    .left-panel { width: 45%; padding: 50px; color: #fff; display: flex; flex-direction: column; justify-content: center; }
    .left-panel h2 { font-size: 34px; font-weight: 700; margin-bottom: 14px; }
    .left-panel p { font-size: 15px; line-height: 1.8; opacity: .9; margin-bottom: 30px; }
    .right-panel { width: 55%; padding: 50px; display: flex; flex-direction: column; justify-content: center; }
    .login-title { font-size: 28px; font-weight: 700; color: #0f172a; margin-bottom: 8px; }
    .login-subtitle { font-size: 14px; color: #64748b; margin-bottom: 24px; }
    .otp-inputs { display: flex; gap: 12px; justify-content: center; margin-bottom: 12px; }
    .otp-inputs input { width: 50px; height: 60px; text-align: center; font-size: 24px; border: 1px solid #dbe2ea; border-radius: 12px; outline: none; }
    .otp-inputs input:focus { border-color: #1565d8; box-shadow: 0 0 0 3px rgba(21, 101, 216, 0.1); }
    .login-btn { margin-top: 12px; }
    .login-alert { border-radius: 12px; font-size: 14px; margin-bottom: 20px; }
    .validation-msg { color: #dc3545; font-size: 13px; text-align: center; margin-bottom: 12px; min-height: 18px; }
    .remember { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #475569; margin: 16px 0; }
    .form-options { display: flex; align-items: center; justify-content: space-between; margin-top: 16px; }
    .forgot-link { font-size: 14px; color: #1565d8; text-decoration: none; font-weight: 500; background: none; border: none; padding: 0; cursor: pointer; }
    .forgot-link:disabled { color: #94a3b8; cursor: not-allowed; }
    .resend-otp-text { font-size: 14px; color: #64748b; }
    @media(max-width:992px) { body { overflow: auto; } .login-card { flex-direction: column; min-height: auto; } .left-panel, .right-panel { width: 100%; } }
    </style>
</head>

<body>
    <div class="login-page">
        <div class="login-card">
            <div class="left-panel">
                <?php include 'includes/auth_brand_logo.php'; ?>
                <h2>Welcome to Dealer Portal</h2>
		<p>Manage orders, dispatch and dealer operations.</p>
            </div>

            <div class="right-panel">
                <div class="login-title">Verify OTP</div>
                <div class="login-subtitle">
                    Enter the 6-digit OTP sent to <?php echo htmlspecialchars($maskedEmail !== '' ? $maskedEmail : 'your registered email'); ?>.
                </div>

                <?php if (!empty($success_message)) { ?>
                <div class="alert alert-success login-alert" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
                <?php } ?>

                <?php if (!empty($error_message)) { ?>
                <div class="alert alert-danger login-alert" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php } ?>

                <form method="post" action="verify-otp.php" id="otpForm" novalidate>
                    <div class="otp-inputs">
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code">
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]*">
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]*">
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]*">
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]*">
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]*">
                    </div>
                    <div class="validation-msg" id="otpValidationMsg"></div>
                    <input type="hidden" name="otp" id="otp">

                   
                    <button type="submit" class="login-btn">Verify</button>
                </form>

                <div class="form-options">
                    <form method="post" action="verify-otp.php" id="resendOtpForm">
                        <input type="hidden" name="resend_otp" value="1">
                        <button type="submit" class="forgot-link" id="resendOtpBtn" disabled>
                            Resend OTP (<span id="resendOtpCounter"><?php echo (int) $resendSecondsRemaining; ?></span>s)
                        </button>
                    </form>
                    <a href="login-otp.php" class="forgot-link">Back to Login</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.otpResendSecondsRemaining = <?php echo (int) $resendSecondsRemaining; ?>;
    </script>
    <script src="js/verify_otp_validation.js"></script>
</body>
</html>