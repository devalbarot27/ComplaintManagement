<?php

require_once __DIR__ . '/password_security_helpers.php';

function password_history_limit(): int
{
    return 3;
}

function password_history_reuse_error(): string
{
    return 'New password cannot be same as your last 3 passwords';
}

function password_history_normalize_stored_hash(?string $hash): string
{
    return trim((string) $hash);
}

function password_history_get_user_id(PDO $obconn, string $username): ?int
{
    $stmt = $obconn->prepare('
        SELECT id
        FROM user_master
        WHERE TRIM(username) = :username
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':username', trim($username));
    $stmt->execute();
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

function password_history_get_recent_hashes(PDO $obconn, string $username, int $limit = 3): array
{
    $stmt = $obconn->prepare('
        SELECT password
        FROM password_history
        WHERE TRIM(username) = :username
        ORDER BY created_at DESC, id DESC
        LIMIT :limit
    ');
    $stmt->bindValue(':username', trim($username));
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $hashes = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $hash = password_history_normalize_stored_hash($row['password'] ?? '');
        if ($hash !== '') {
            $hashes[] = $hash;
        }
    }

    return $hashes;
}

/**
 * Check whether a candidate password matches a stored hash from user_master
 * or password_history.
 */
function password_history_matches(string $plainPassword, string $storedHash): bool
{
    return user_password_verify($plainPassword, password_history_normalize_stored_hash($storedHash));
}

function password_history_is_reused(
    PDO $obconn,
    string $username,
    string $newPassword,
    string $currentPasswordHash
): bool {
    $blocked = [password_history_normalize_stored_hash($currentPasswordHash)];

    foreach (password_history_get_recent_hashes($obconn, $username, password_history_limit()) as $hash) {
        $blocked[] = $hash;
    }

    $blocked = array_values(array_unique(array_filter($blocked)));

    foreach ($blocked as $hash) {
        if (password_history_matches($newPassword, $hash)) {
            return true;
        }
    }

    return false;
}

function password_history_record(PDO $obconn, string $username, string $oldPasswordHash): void
{
    $oldHash = password_history_normalize_stored_hash($oldPasswordHash);
    if ($oldHash === '') {
        return;
    }

    $username = trim($username);
    $userId = password_history_get_user_id($obconn, $username);

    $insert = $obconn->prepare('
        INSERT INTO password_history (user_id, username, password, created_at)
        VALUES (:user_id, :username, :password, CURRENT_TIMESTAMP)
    ');
    $insert->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $insert->bindValue(':username', $username);
    $insert->bindValue(':password', $oldHash);
    $insert->execute();

    $trim = $obconn->prepare('
        DELETE FROM password_history
        WHERE id IN (
            SELECT id
            FROM password_history
            WHERE TRIM(username) = :username
            ORDER BY created_at DESC, id DESC
            OFFSET :keep_limit
        )
    ');
    $trim->bindValue(':username', $username);
    $trim->bindValue(':keep_limit', password_history_limit(), PDO::PARAM_INT);
    $trim->execute();
}