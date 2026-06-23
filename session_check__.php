<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usr_name'])) {
?>
    <div style="
        max-width:700px;
        margin:50px auto;
        padding:20px;
        border:1px solid #dc3545;
        border-radius:5px;
        background:#fff5f5;
        text-align:center;
        font-family:Verdana, Arial, sans-serif;
    ">
        <h3 style="color:#dc3545; margin:0 0 10px;">
            Unauthorized Access / Session Expired
        </h3>

        <p style="margin:0; color:#333;">
            Please
            <a href="https://dp.elgi.com/index.php">
                login
            </a>
            using a valid User ID and Password.
        </p>
    </div>
<?php
    exit;
}
?>