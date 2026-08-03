<?php

/**
 * HTTP security headers: Content-Security-Policy + Permissions-Policy.
 */

function security_headers_already_sent(): bool
{
    return headers_sent();
}

/**
 * Browser features this dealer portal does not need.
 */
function security_permissions_policy_value(): string
{
    $directives = [
        'accelerometer=()',
        'autoplay=()',
        'camera=()',
        'display-capture=()',
        'encrypted-media=()',
        'fullscreen=(self)',
        'geolocation=()',
        'gyroscope=()',
        'magnetometer=()',
        'microphone=()',
        'midi=()',
        'payment=()',
        'picture-in-picture=()',
        'publickey-credentials-get=()',
        'screen-wake-lock=()',
        'sync-xhr=(self)',
        'usb=()',
        'web-share=()',
        'xr-spatial-tracking=()',
    ];

    return implode(', ', $directives);
}

/**
 * CSP allowing self + known CDN/font hosts used by the portal.
 * unsafe-inline is required for existing inline scripts/styles/handlers.
 */
function security_content_security_policy_value(): string
{
    $scriptSrc = implode(
        ' ',
        [
            "'self'",
            "'unsafe-inline'",
            'https://cdn.jsdelivr.net',
            'https://cdnjs.cloudflare.com',
            'https://code.jquery.com',
            'https://cdn.datatables.net',
        ]
    );

    $styleSrc = implode(
        ' ',
        [
            "'self'",
            "'unsafe-inline'",
            'https://fonts.googleapis.com',
            'https://cdn.jsdelivr.net',
            'https://cdnjs.cloudflare.com',
            'https://cdn.datatables.net',
        ]
    );

    $fontSrc = implode(
        ' ',
        [
            "'self'",
            'data:',
            'https://fonts.gstatic.com',
            'https://cdn.jsdelivr.net',
            'https://cdnjs.cloudflare.com',
        ]
    );

    $imgSrc = implode(' ', ["'self'", 'data:', 'blob:', 'https:']);
    $connectSrc = implode(' ', ["'self'"]);
    $frameSrc = implode(' ', ["'self'"]);

    $directives = [
        "default-src 'self'",
        'script-src ' . $scriptSrc,
        'style-src ' . $styleSrc,
        'font-src ' . $fontSrc,
        'img-src ' . $imgSrc,
        'connect-src ' . $connectSrc,
        'frame-src ' . $frameSrc,
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "object-src 'none'",
        "worker-src 'self' blob:",
        'upgrade-insecure-requests',
    ];

    return implode('; ', $directives);
}

/**
 * Send CSP + Permissions-Policy (and related hardening headers) once per response.
 */
function security_send_http_headers(): void
{
    static $sent = false;
    if ($sent || security_headers_already_sent()) {
        return;
    }

    header('Content-Security-Policy: ' . security_content_security_policy_value());
    header('Permissions-Policy: ' . security_permissions_policy_value());
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    $sent = true;
}
