<?php

/**
 * Encode a JSON payload with XSS-safe string escaping.
 */
function api_json_encode(array $payload): string
{
    array_walk_recursive($payload, static function (&$val): void {
        if (is_string($val)) {
            $val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
        }
    });

    $json = json_encode(
        $payload,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );

    return is_string($json) ? $json : '{}';
}

/**
 * Echo a JSON payload with XSS-safe string escaping.
 */
function api_json_echo(array $payload): void
{
    echo api_json_encode($payload);
}
