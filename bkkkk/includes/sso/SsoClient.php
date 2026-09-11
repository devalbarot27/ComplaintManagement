<?php
/**
 * Low-level HTTP + JWT helpers for OIDC token exchange and claim validation.
 */
class SsoClient
{
    private SsoConfig $config;

    public function __construct(SsoConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Build the Identity Provider authorize URL.
     *
     * @param array{state: string, nonce: string, code_challenge: string} $handshake
     */
    public function buildAuthorizeUrl(array $handshake): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->config->getClientId(),
            'redirect_uri' => $this->config->getRedirectUri(),
            'scope' => $this->config->getScopes(),
            'state' => $handshake['state'],
            'nonce' => $handshake['nonce'],
            'code_challenge' => $handshake['code_challenge'],
            'code_challenge_method' => 'S256',
        ];

        $separator = strpos($this->config->getAuthorizeUrl(), '?') === false ? '?' : '&';
        return $this->config->getAuthorizeUrl() . $separator . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange an authorization code for tokens.
     *
     * @return array<string, mixed>
     * @throws SsoException
     */
    public function exchangeAuthorizationCode(string $code, string $codeVerifier): array
    {
        $postFields = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->config->getRedirectUri(),
            'client_id' => $this->config->getClientId(),
            'code_verifier' => $codeVerifier,
        ];

        $secret = $this->config->getClientSecret();
        if ($secret !== '') {
            $postFields['client_secret'] = $secret;
        }

        $response = $this->httpPostForm($this->config->getTokenUrl(), $postFields);

        if (empty($response['access_token']) && empty($response['id_token'])) {
            $description = (string) ($response['error_description'] ?? $response['error'] ?? 'Token exchange failed.');
            throw new SsoException('SSO token exchange failed: ' . $description, 'token_exchange_failed');
        }

        return $response;
    }

    /**
     * Fetch userinfo claims when the ID token does not include email.
     *
     * @return array<string, mixed>
     * @throws SsoException
     */
    public function fetchUserInfo(string $accessToken): array
    {
        $url = $this->config->getUserinfoUrl();
        if ($url === '') {
            throw new SsoException('UserInfo endpoint is not configured.', 'userinfo_missing');
        }

        return $this->httpGetJson($url, [
            'Authorization: Bearer ' . $accessToken,
        ]);
    }

    /**
     * Decode and validate ID token claims (iss, aud, exp, nonce).
     * When a JWKS URL is configured, the signature is verified with OpenSSL.
     *
     * @return array<string, mixed>
     * @throws SsoException
     */
    public function validateIdToken(string $idToken, string $expectedNonce): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new SsoException('Invalid ID token format.', 'invalid_id_token');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;
        $header = $this->decodeJwtSegment($headerB64);
        $payload = $this->decodeJwtSegment($payloadB64);

        if ($header === null || $payload === null) {
            throw new SsoException('Unable to decode ID token.', 'invalid_id_token');
        }

        $jwksUrl = $this->config->getJwksUrl();
        if ($jwksUrl !== '') {
            $this->verifyJwtSignature($header, $headerB64, $payloadB64, $signatureB64, $jwksUrl);
        }

        $now = time();
        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp > 0 && $now >= $exp) {
            throw new SsoException('SSO ID token has expired.', 'id_token_expired');
        }

        $nbf = (int) ($payload['nbf'] ?? 0);
        if ($nbf > 0 && $now < ($nbf - 60)) {
            throw new SsoException('SSO ID token is not yet valid.', 'id_token_nbf');
        }

        $issuer = $this->config->getIssuer();
        if ($issuer !== '') {
            $tokenIssuer = (string) ($payload['iss'] ?? '');
            if ($tokenIssuer === '' || !hash_equals($issuer, $tokenIssuer)) {
                throw new SsoException('SSO ID token issuer mismatch.', 'issuer_mismatch');
            }
        }

        $audience = $payload['aud'] ?? null;
        $clientId = $this->config->getClientId();
        $audienceOk = false;
        if (is_string($audience)) {
            $audienceOk = hash_equals($clientId, $audience);
        } elseif (is_array($audience)) {
            foreach ($audience as $aud) {
                if (is_string($aud) && hash_equals($clientId, $aud)) {
                    $audienceOk = true;
                    break;
                }
            }
        }

        if (!$audienceOk) {
            throw new SsoException('SSO ID token audience mismatch.', 'audience_mismatch');
        }

        $nonce = (string) ($payload['nonce'] ?? '');
        if ($expectedNonce === '' || $nonce === '' || !hash_equals($expectedNonce, $nonce)) {
            throw new SsoException('SSO ID token nonce mismatch.', 'nonce_mismatch');
        }

        return $payload;
    }

    /**
     * Resolve the email address from ID token / userinfo claims.
     *
     * @param array<string, mixed> $claims
     * @throws SsoException
     */
    public function extractEmail(array $claims): string
    {
        $primary = $this->config->getEmailClaim();
        $email = trim((string) ($claims[$primary] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fallback = $this->config->getFallbackEmailClaim();
            if ($fallback !== '') {
                $email = trim((string) ($claims[$fallback] ?? ''));
            }
        }

        // Some IdPs put an email-like value in upn / unique_name
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            foreach (['email', 'upn', 'unique_name', 'preferred_username'] as $claim) {
                $candidate = trim((string) ($claims[$claim] ?? ''));
                if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                    $email = $candidate;
                    break;
                }
            }
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new SsoException(
                'SSO response did not include a valid email address.',
                'email_missing'
            );
        }

        return strtolower($email);
    }

    /**
     * Build IdP end-session URL when configured.
     */
    public function buildLogoutUrl(?string $idTokenHint = null): ?string
    {
        $logoutUrl = $this->config->getLogoutUrl();
        if ($logoutUrl === '') {
            return null;
        }

        $params = [
            'post_logout_redirect_uri' => $this->config->getPostLogoutRedirectUri(),
            'client_id' => $this->config->getClientId(),
        ];

        if ($idTokenHint !== null && $idTokenHint !== '') {
            $params['id_token_hint'] = $idTokenHint;
        }

        $separator = strpos($logoutUrl, '?') === false ? '?' : '&';
        return $logoutUrl . $separator . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>
     * @throws SsoException
     */
    private function httpPostForm(string $url, array $fields): array
    {
        return $this->requestJson('POST', $url, $fields, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ]);
    }

    /**
     * @param list<string> $headers
     * @return array<string, mixed>
     * @throws SsoException
     */
    private function httpGetJson(string $url, array $headers = []): array
    {
        $headers[] = 'Accept: application/json';
        return $this->requestJson('GET', $url, null, $headers);
    }

    /**
     * @param array<string, string>|null $fields
     * @param list<string> $headers
     * @return array<string, mixed>
     * @throws SsoException
     */
    private function requestJson(string $method, string $url, ?array $fields, array $headers): array
    {
        if (!function_exists('curl_init')) {
            throw new SsoException('cURL extension is required for SSO.', 'curl_missing');
        }

        $ch = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config->getHttpTimeout(),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ];

        if (strtoupper($method) === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($fields ?? [], '', '&');
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new SsoException('SSO HTTP request failed: ' . $error, 'http_error');
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new SsoException(
                'SSO provider returned a non-JSON response (HTTP ' . $httpCode . ').',
                'invalid_json'
            );
        }

        if ($httpCode >= 400) {
            $description = (string) ($decoded['error_description'] ?? $decoded['error'] ?? 'HTTP ' . $httpCode);
            throw new SsoException('SSO provider error: ' . $description, 'provider_http_error');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJwtSegment(string $segment): ?array
    {
        $json = $this->base64UrlDecode($segment);
        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    private function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }

    /**
     * @param array<string, mixed> $header
     * @throws SsoException
     */
    private function verifyJwtSignature(
        array $header,
        string $headerB64,
        string $payloadB64,
        string $signatureB64,
        string $jwksUrl
    ): void {
        $alg = strtoupper((string) ($header['alg'] ?? ''));
        $kid = (string) ($header['kid'] ?? '');

        if ($alg !== 'RS256') {
            throw new SsoException('Unsupported ID token signing algorithm: ' . $alg, 'unsupported_alg');
        }

        $jwks = $this->httpGetJson($jwksUrl);
        $keys = $jwks['keys'] ?? null;
        if (!is_array($keys) || $keys === []) {
            throw new SsoException('JWKS response did not contain keys.', 'jwks_invalid');
        }

        $jwk = null;
        foreach ($keys as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            if ($kid !== '' && (string) ($candidate['kid'] ?? '') === $kid) {
                $jwk = $candidate;
                break;
            }
            if ($kid === '' && strtoupper((string) ($candidate['kty'] ?? '')) === 'RSA') {
                $jwk = $candidate;
                break;
            }
        }

        if ($jwk === null) {
            throw new SsoException('Matching JWKS key was not found for the ID token.', 'jwks_key_missing');
        }

        $publicKey = $this->jwkToPem($jwk);
        $signature = $this->base64UrlDecode($signatureB64);
        if ($signature === null || $publicKey === '') {
            throw new SsoException('Unable to verify ID token signature.', 'signature_decode_failed');
        }

        $verified = openssl_verify(
            $headerB64 . '.' . $payloadB64,
            $signature,
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        if ($verified !== 1) {
            throw new SsoException('ID token signature verification failed.', 'signature_invalid');
        }
    }

    /**
     * @param array<string, mixed> $jwk
     */
    private function jwkToPem(array $jwk): string
    {
        $n = $this->base64UrlDecode((string) ($jwk['n'] ?? ''));
        $e = $this->base64UrlDecode((string) ($jwk['e'] ?? ''));
        if ($n === null || $e === null || $n === '' || $e === '') {
            return '';
        }

        // ASN.1 INTEGER values must be positive (leading 0x00 if high bit set).
        if ((ord($n[0]) & 0x80) !== 0) {
            $n = "\x00" . $n;
        }
        if ((ord($e[0]) & 0x80) !== 0) {
            $e = "\x00" . $e;
        }

        $modulus = chr(0x02) . $this->encodeLength($n) . $n;
        $exponent = chr(0x02) . $this->encodeLength($e) . $e;
        $rsaPublicKey = chr(0x30) . $this->encodeLength($modulus . $exponent) . $modulus . $exponent;

        $algorithmIdentifier = hex2bin('300d06092a864886f70d0101010500');
        if ($algorithmIdentifier === false) {
            return '';
        }

        $bitStringValue = "\x00" . $rsaPublicKey;
        $bitString = chr(0x03) . $this->encodeLength($bitStringValue) . $bitStringValue;
        $sequence = chr(0x30) . $this->encodeLength($algorithmIdentifier . $bitString)
            . $algorithmIdentifier . $bitString;

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($sequence), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    private function encodeLength(string $data): string
    {
        $length = strlen($data);
        if ($length <= 0x7F) {
            return chr($length);
        }

        $temp = ltrim(pack('N', $length), "\x00");
        return chr(0x80 | strlen($temp)) . $temp;
    }
}