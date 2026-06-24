<!doctype html>
<?php
session_start();

include 'pdo_obconn.php';
include 'includes/login_helpers.php';

$error_message = '';
$success_message = '';
$username_value = '';

if (!empty($_SESSION['success_message'])) {
    $success_message = (string) $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

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
    $password = (string) ($_POST['password'] ?? '');
    $rememberMe = isset($_POST['remember_me']);

    if ($username_value === '' || $password === '') {
        $error_message = 'Invalid username or password';
    } else {
        try {
            $user = login_fetch_user_auth($obconn, $username_value);

            if ($user && login_verify_password($user, $password)) {
                login_update_last_login_at($obconn, $username_value);
                login_start_session($user, $rememberMe);
                header('Location: index.php');
                exit;
            }

            $error_message = 'Invalid username or password';
        } catch (PDOException $e) {
            $error_message = 'Invalid username or password';
        }
    }
}
?>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dealer Portal Login</title>

    <!-- GOOGLE FONT -->

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- BOOTSTRAP -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- ICONS -->

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="css/auth_pages.css" rel="stylesheet" />

    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: #f1f5f9;
        height: 100vh;
        overflow: hidden;
    }

    .login-page {
        width: 100%;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    /* CARD */

    .login-card {
        width: 100%;
        max-width: 980px;
        min-height: 620px;
        background: #fff;
        border-radius: 22px;
        overflow: hidden;
        display: flex;
        box-shadow: 0 10px 35px rgba(15, 23, 42, 0.08);
    }

    /* LEFT */

    .left-panel {
        width: 45%;
        padding: 50px;
        color: #fff;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
    }

    .left-panel h2 {
        font-size: 34px;
        font-weight: 700;
        margin-bottom: 14px;
        line-height: 1.3;
    }

    .left-panel p {
        font-size: 15px;
        line-height: 1.8;
        opacity: .9;
        margin-bottom: 30px;
    }

    .signup-btn {
        width: max-content;
        height: 46px;
        padding: 0 26px;
        border: 1px solid rgba(255, 255, 255, 0.35);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        backdrop-filter: blur(8px);
    }

    .signup-btn:hover {
        background: #fff;
        color: #1565d8;
    }

    /* RIGHT */

    .right-panel {
        width: 55%;
        padding: 50px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .login-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px;
    }

    .login-title {
        font-size: 28px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 6px;
    }

    .login-subtitle {
        font-size: 14px;
        color: #64748b;
    }

    /* SOCIAL */

    .social-icons {
        display: flex;
        gap: 10px;
    }

    .social-btn {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        border: 1px solid #dbe2ea;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: #334155;
        font-size: 16px;
        transition: .2s;
    }

    .social-btn:hover {
        background: #1565d8;
        color: #fff;
        border-color: #1565d8;
    }

    /* FORM */

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        font-size: 14px;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 10px;
    }

    .custom-input {
        width: 100%;
        height: 50px;
        border: 1px solid #dbe2ea;
        border-radius: 14px;
        padding: 0 16px;
        font-size: 14px;
        outline: none;
        transition: .2s;
    }

    .custom-input:focus {
        border-color: #1565d8;
        box-shadow: 0 0 0 4px rgba(21, 101, 216, 0.08);
    }

    .password-input-wrapper {
        position: relative;
    }

    .custom-input--password {
        padding-right: 48px;
    }

    .password-toggle-btn {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        border: none;
        background: transparent;
        color: #64748b;
        font-size: 18px;
        line-height: 1;
        padding: 0;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .password-toggle-btn:hover {
        color: #1565d8;
    }

    /* OPTIONS */

    .form-options {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
    }

    .remember {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: #475569;
    }

    .forgot-link {
        font-size: 14px;
        color: #1565d8;
        text-decoration: none;
        font-weight: 500;
    }

    /* BUTTON */

    .validation-msg {
        color: #dc3545;
        font-size: 13px;
        margin-top: 6px;
    }

    .custom-input.is-invalid {
        border-color: #dc3545;
    }

    .login-alert {
        border-radius: 12px;
        font-size: 14px;
        margin-bottom: 20px;
    }

    /* MOBILE */

    @media(max-width:992px) {

        body {
            overflow: auto;
        }

        .login-page {
            height: auto;
            min-height: 100vh;
            padding: 20px 14px;
        }

        .login-card {
            flex-direction: column;
            min-height: auto;
        }

        .left-panel {
            width: 100%;
            padding: 40px 30px;
            text-align: center;
            align-items: center;
        }

        .right-panel {
            width: 100%;
            padding: 40px 24px;
        }

        .login-top {
            flex-direction: column;
            align-items: flex-start;
            gap: 18px;
        }

    }

    @media(max-width:576px) {

        .left-panel h2 {
            font-size: 28px;
        }

        .login-title {
            font-size: 24px;
        }

        .form-options {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }

    }
    </style>

</head>

<body>

    <div class="login-page">

        <div class="login-card">

            <!-- LEFT -->

            <div class="left-panel">

                <?php include 'includes/auth_brand_logo.php'; ?>

                <h2>
                    Welcome to Dealer Portal
                </h2>

                <p>
                    Manage orders, dispatch, AR statements and dealer operations.
                </p>

            </div>

            <!-- RIGHT -->

            <div class="right-panel">

                <!-- TOP -->

                <div class="login-top">

                    <div>

                        <div class="login-title">
                            Sign In
                        </div>

                        <div class="login-subtitle">
                            Login to continue to your dashboard
                        </div>

                    </div>

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

                <!-- FORM -->

                <form method="post" action="login.php" id="loginForm" novalidate>

                    <!-- USERNAME -->

                    <div class="form-group">

                        <label class="form-label" for="usr_name">
                            Username
                        </label>

                        <input
                            type="text"
                            class="custom-input"
                            id="usr_name"
                            name="usr_name"
                            placeholder="Enter username"
                            value="<?php echo htmlspecialchars($username_value ?? ''); ?>"
                            autocomplete="username"
                        >
                        <div class="validation-msg" data-field="usr_name"></div>

                    </div>

                    <!-- PASSWORD -->

                    <div class="form-group">

                        <label class="form-label" for="password">
                            Password
                        </label>

                        <div class="password-input-wrapper">
                            <input
                                type="password"
                                class="custom-input custom-input--password"
                                id="password"
                                name="password"
                                placeholder="Enter password"
                                autocomplete="current-password"
                            >
                            <button
                                type="button"
                                class="password-toggle-btn"
                                id="passwordToggle"
                                aria-label="Show password"
                            >
                                <i class="bi bi-eye" id="passwordToggleIcon"></i>
                            </button>
                        </div>
                        <div class="validation-msg" data-field="password"></div>

                    </div>

                    <div class="form-options">

                        <label class="remember">
                            <input type="checkbox" name="remember_me" value="1" <?php echo !empty($_POST['remember_me']) ? 'checked' : ''; ?>>
                            Remember me
                        </label>

                        <a href="forgot-password.php" class="forgot-link" style="color: #1d2735;">
                            Forgot Password?
                        </a>

                    </div>

                    <!-- BUTTON -->

                    <button type="submit" class="login-btn">
                        Login
                    </button>

                </form>

                <div class="form-options">
                    <a href="login-otp.php" class="forgot-link mt-3" style="color: #1d2735;">
                        Login with OTP
                    </a>
                </div>

            </div>

        </div>

    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/validate.js/0.13.1/validate.min.js"></script>
    <script src="js/login_validation.js"></script>

</body>

</html>