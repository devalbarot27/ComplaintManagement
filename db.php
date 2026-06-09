<?php

$host = "localhost";
$port = "5432";
$dbname = "complaint_management";
$user = "postgres";
$password = "123456789";

$conn = pg_connect(
    "host=$host port=$port dbname=$dbname user=$user password=$password"
);

if (!$conn) {
    die("Database connection failed.");
}