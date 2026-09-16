<?php

/**
 * RSA-OAEP transport encryption for login passwords.
 * Password is encrypted in the browser with the public key, then decrypted here
 * before normal password_verify hashing checks.
 */

function login_transport_keys_dir(): string
{
    return __DIR__ . '/keys';
}

function login_transport_private_key_path(): string
{
    return login_transport_keys_dir() . '/login_transport_private.pem';
}

function login_transport_public_key_path(): string
{
    return login_transport_keys_dir() . '/login_transport_public.pem';
}

function login_transport_openssl_config_path(): ?string
{
    $candidates = [
        'C:/xampp/apache/conf/openssl.cnf',
        'C:/xampp/php/extras/ssl/openssl.cnf',
        dirname(__DIR__) . '/../apache/conf/openssl.cnf',
        dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf',
        getenv('OPENSSL_CONF') ?: '',
    ];

    foreach ($candidates as $path) {
        $path = str_replace('\\', '/', (string) $path);
        if ($path !== '' && is_file($path)) {
            return $path;
        }
    }

    return null;
}

function login_ensure_transport_keys(): void
{
    $dir = login_transport_keys_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Require all denied\nDeny from all\n");
    }

    $privatePath = login_transport_private_key_path();
    $publicPath = login_transport_public_key_path();

    if (is_file($privatePath) && is_file($publicPath)) {
        return;
    }

    $config = [
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];

    $opensslConf = login_transport_openssl_config_path();
    if ($opensslConf !== null) {
        $config['config'] = $opensslConf;
        putenv('OPENSSL_CONF=' . $opensslConf);
    }

    $key = openssl_pkey_new($config);

    if ($key === false) {
        $errors = [];
        while ($message = openssl_error_string()) {
            $errors[] = $message;
        }
        throw new RuntimeException(
            'Unable to generate login transport key pair.'
            . ($errors ? ' ' . implode(' | ', $errors) : '')
        );
    }

    $privatePem = '';
    if (!openssl_pkey_export($key, $privatePem, null, $opensslConf ? ['config' => $opensslConf] : [])) {
        throw new RuntimeException('Unable to export login transport private key.');
    }

    $details = openssl_pkey_get_details($key);
    $publicPem = is_array($details) ? (string) ($details['key'] ?? '') : '';
    if ($publicPem === '') {
        throw new RuntimeException('Unable to export login transport public key.');
    }

    if (file_put_contents($privatePath, $privatePem) === false) {
        throw new RuntimeException('Unable to store login transport private key.');
    }
    chmod($privatePath, 0600);

    if (file_put_contents($publicPath, $publicPem) === false) {
        throw new RuntimeException('Unable to store login transport public key.');
    }
    chmod($publicPath, 0644);
}

function login_transport_public_key_pem(): string
{
    login_ensure_transport_keys();
    $pem = file_get_contents(login_transport_public_key_path());
    if ($pem === false || trim($pem) === '') {
        throw new RuntimeException('Login transport public key is unavailable.');
    }

    return $pem;
}

/**
 * Decrypt a base64-encoded RSA-OAEP (SHA-1) ciphertext from the browser.
 */
function login_decrypt_transport_password(string $passwordEncryptedBase64): ?string
{
    $passwordEncryptedBase64 = trim($passwordEncryptedBase64);
    if ($passwordEncryptedBase64 === '') {
        return null;
    }

    login_ensure_transport_keys();
    $privatePem = file_get_contents(login_transport_private_key_path());
    if ($privatePem === false || $privatePem === '') {
        return null;
    }

    $privateKey = openssl_pkey_get_private($privatePem);
    if ($privateKey === false) {
        return null;
    }

    $cipherBin = base64_decode($passwordEncryptedBase64, true);
    if ($cipherBin === false || $cipherBin === '') {
        return null;
    }

    $plain = '';
    $ok = openssl_private_decrypt(
        $cipherBin,
        $plain,
        $privateKey,
        OPENSSL_PKCS1_OAEP_PADDING
    );

    if (!$ok) {
        return null;
    }

    return $plain;
}