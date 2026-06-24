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

if (!function_exists('current_assignee_name')) {
    function current_assignee_name(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $displayName = trim((string) ($_SESSION['display_name'] ?? ''));

        return $displayName !== '' ? $displayName : current_username();
    }
}
