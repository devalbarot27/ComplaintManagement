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

if (!function_exists('current_user_id')) {
    function current_user_id(?PDO $conn = null): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
        if ($sessionUserId > 0) {
            return $sessionUserId;
        }

        if ($conn === null) {
            return null;
        }

        $username = current_username();
        if ($username === '') {
            return null;
        }

        $stmt = $conn->prepare('
            SELECT id
            FROM user_master
            WHERE TRIM(username) = :username
              AND deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->bindValue(':username', $username);
        $stmt->execute();
        $id = $stmt->fetchColumn();

        if ($id === false) {
            return null;
        }

        $userId = (int) $id;
        if ($userId > 0) {
            $_SESSION['user_id'] = $userId;
        }

        return $userId > 0 ? $userId : null;
    }
}
