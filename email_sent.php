<?php

ini_set("SMTP", "localhost");
ini_set("smtp_port", "25");

$sent = mail(
    "deval.barot27@gmail.com",
    "Local Test",
    "Hello from XAMPP",
    "From: no-reply@localhost.com"
);

echo $sent ? "Sent" : "Failed";
die();
