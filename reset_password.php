<!doctype html>
<?php
session_start();

include 'pdo_obconn.php';
include 'includes/password_reset_helpers.php';

$error_message = '';
$success_message = '';
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenValid = false;

if (!empty($_SESSION['usr_name'])) {
    header('Location: index.php');
    exit;
}

if ($token !== '') {
    $tokenValid = password_reset_find_valid_token($obconn, $token) !== null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $result = password_reset_process_reset(
        $obconn,
        $token,
        (string) ($_POST['new_password'] ?? ''),
        (string) ($_POST['confirm_password'] ?? '')
    );

    if ($result['success']) {
        $_SESSION['success_message'] = $result['message'];
        header('Location: login.php');
        exit;
    }

    $error_message = $result['error'] ?? 'Failed to reset password.';
    $tokenValid = password_reset_find_valid_token($obconn, $token) !== null;
} elseif ($token === '') {
    $error_message = 'Invalid or expired reset link. Please request a new one.';
} elseif (!$tokenValid) {
    $error_message = 'Invalid or expired reset link. Please request a new one.';
}
?>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dealer Portal - Reset Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: #f1f5f9; min-height: 100vh; }
    .login-page { width: 100%; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .login-card { width: 100%; max-width: 980px; min-height: 620px; background: #fff; border-radius: 22px; overflow: hidden; display: flex; box-shadow: 0 10px 35px rgba(15, 23, 42, 0.08); }
    .left-panel { width: 45%; background: #1d2735; padding: 50px; color: #fff; display: flex; flex-direction: column; justify-content: center; }
    .brand-logo { width: 70px; height: 70px; border-radius: 18px; background: rgba(255, 255, 255, 0.15); display: flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 24px; }
    .left-panel h2 { font-size: 34px; font-weight: 700; margin-bottom: 14px; line-height: 1.3; }
    .left-panel p { font-size: 15px; line-height: 1.8; opacity: .9; }
    .right-panel { width: 55%; padding: 50px; display: flex; flex-direction: column; justify-content: center; }
    .login-title { font-size: 28px; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
    .login-subtitle { font-size: 14px; color: #64748b; margin-bottom: 24px; }
    .form-group { margin-bottom: 20px; }
    .form-label { font-size: 14px; font-weight: 600; color: #0f172a; margin-bottom: 10px; display: block; }
    .custom-input { width: 100%; height: 50px; border: 1px solid #dbe2ea; border-radius: 14px; padding: 0 16px; font-size: 14px; outline: none; }
    .custom-input:focus { border-color: #1565d8; box-shadow: 0 0 0 4px rgba(21, 101, 216, 0.08); }
    .custom-input.is-invalid { border-color: #dc3545; }
    .password-input-wrapper { position: relative; }
    .custom-input--password { padding-right: 48px; }
    .password-toggle-btn { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); border: none; background: transparent; color: #64748b; font-size: 18px; cursor: pointer; }
    .password-toggle-btn:hover { color: #1565d8; }
    .validation-msg { color: #dc3545; font-size: 13px; margin-top: 6px; }
    .password-hint { color: #64748b; font-size: 12px; margin-top: 6px; line-height: 1.5; }
    .login-btn { width: 100%; height: 52px; border: none; border-radius: 14px; background: #1d2735; color: #fff; font-size: 15px; font-weight: 600; }
    .login-btn:hover { background: #0f4db3; }
    .login-btn:disabled { opacity: 0.7; cursor: not-allowed; }
    .login-alert { border-radius: 12px; font-size: 14px; margin-bottom: 20px; }
    .form-options { margin-top: 20px; }
    .forgot-link { font-size: 14px; color: #1565d8; text-decoration: none; font-weight: 500; }
    @media(max-width:992px) { .login-card { flex-direction: column; min-height: auto; } .left-panel, .right-panel { width: 100%; } }
    </style>
</head>

<body>
    <div class="login-page">
        <div class="login-card">
            <div class="left-panel">
                <div class="brand-logo"><i class="bi bi-grid"></i></div>
                <h2>Welcome to Dealer Portal</h2>
                <p>Manage orders, dispatch, AR statements and dealer operations.</p>
            </div>

            <div class="right-panel">
                <div class="login-title">Reset Password</div>
                <div class="login-subtitle">Choose a new password for your account.</div>

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

                <?php if ($tokenValid && empty($success_message)) { ?>
                <form method="post" action="reset_password.php?token=<?php echo urlencode($token); ?>" id="resetPasswordForm" novalidate>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="reset_password" value="1">

                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password</label>
                        <div class="password-input-wrapper">
                            <input
                                type="password"
                                class="custom-input custom-input--password"
                                id="new_password"
                                name="new_password"
                                placeholder="Enter new password"
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle-btn" data-toggle-password="new_password" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="password-hint">
                            Minimum 8 characters with at least one digit, uppercase letter, lowercase letter, and special character.
                            Cannot reuse your last 3 passwords.
                        </div>
                        <div class="validation-msg" data-field="new_password"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <div class="password-input-wrapper">
                            <input
                                type="password"
                                class="custom-input custom-input--password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Confirm new password"
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle-btn" data-toggle-password="confirm_password" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="validation-msg" data-field="confirm_password"></div>
                    </div>

                    <button type="submit" class="login-btn">Reset Password</button>
                </form>
                <?php } ?>

                <div class="form-options">
                    <a href="forgot-password.php" class="forgot-link">Request New Reset Link</a>
                    <span class="mx-2">|</span>
                    <a href="login.php" class="forgot-link">Back to Login</a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($tokenValid && empty($success_message)) { ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/validate.js/0.13.1/validate.min.js"></script>
    <script src="js/password_history_check.js"></script>
    <script src="js/reset_password_validation.js"></script>
    <?php } ?>
</body>
</html>
