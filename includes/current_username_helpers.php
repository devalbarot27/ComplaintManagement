<?php

if (!function_exists('current_username')) {
    function current_username(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return trim((string) ($_SESSION['usr_name'] ?? ''));
    }
}
