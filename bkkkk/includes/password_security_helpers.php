<?php

/**
 * Secure password hashing and verification helpers.
 *
 * Passwords are stored with password_hash($password, PASSWORD_DEFAULT).
 * Login and password-change flows verify credentials with password_verify().
 *
 * Passwords are never encrypted or stored in plain text.
 */

/**
 * Create a one-way password hash for storage in user_master.password.
 */
function user_password_hash(string $plainPassword): string
{
    return password_hash($plainPassword, PASSWORD_DEFAULT);
}

/**
 * Verify a plain-text password against a stored PASSWORD_DEFAULT hash.
 */
function user_password_verify(string $plainPassword, string $storedHash): bool
{
    $storedHash = trim($storedHash);

    if ($storedHash === '') {
        return false;
    }

    return password_verify($plainPassword, $storedHash);
}

/**
 * Determine whether a stored hash should be re-hashed with PASSWORD_DEFAULT.
 */
function user_password_needs_rehash(string $storedHash): bool
{
    $storedHash = trim($storedHash);

    if ($storedHash === '') {
        return true;
    }

    return password_needs_rehash($storedHash, PASSWORD_DEFAULT);
}

/**
 * Persist a newly hashed password for the given username.
 */
function user_password_upgrade(PDO $conn, string $username, string $plainPassword): bool
{
    $username = trim($username);
    if ($username === '' || $plainPassword === '') {
        return false;
    }

    $stmt = $conn->prepare('
        UPDATE user_master
        SET password = :password,
            updated_at = CURRENT_TIMESTAMP
        WHERE TRIM(username) = :username
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':password', user_password_hash($plainPassword));
    $stmt->bindValue(':username', $username);
    $stmt->execute();

    return $stmt->rowCount() > 0;
}