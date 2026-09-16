<?php
/**
 * Handles the OIDC callback: validates the response, authenticates the user,
 * creates a secure session, and redirects to the dashboard.
 */
class SsoCallbackHandler
{
    private SsoConfig $config;
    private SsoClient $client;
    private SsoSessionManager $session;
    private SsoUserAuthenticator $authenticator;

    public function __construct(
        SsoConfig $config,
        SsoClient $client,
        SsoSessionManager $session,
        SsoUserAuthenticator $authenticator
    ) {
        $this->config = $config;
        $this->client = $client;
        $this->session = $session;
        $this->authenticator = $authenticator;
    }

    /**
     * Process GET parameters from the Identity Provider redirect.
     *
     * @param array<string, mixed> $query Typically $_GET
     * @throws SsoException
     */
    public function handle(array $query): void
    {
        $this->config->assertReadyForLogin();
        $this->session->ensurePhpSession();

        if (!empty($_SESSION['usr_name'])) {
            $this->redirect($this->config->getDashboardPath());
        }

        $providerError = trim((string) ($query['error'] ?? ''));
        if ($providerError !== '') {
            $description = trim((string) ($query['error_description'] ?? $providerError));
            throw new SsoException(
                'SSO provider returned an error: ' . $description,
                'provider_error'
            );
        }

        $code = trim((string) ($query['code'] ?? ''));
        $state = trim((string) ($query['state'] ?? ''));

        if ($code === '' || $state === '') {
            throw new SsoException(
                'Invalid SSO callback. Missing authorization code or state.',
                'callback_incomplete'
            );
        }

        $handshake = $this->session->consumeHandshake($state);
        $tokenResponse = $this->client->exchangeAuthorizationCode($code, $handshake['code_verifier']);

        $claims = $this->resolveClaims($tokenResponse, $handshake['nonce']);
        $email = $this->client->extractEmail($claims);

        $user = $this->authenticator->authenticateByEmail($email);

        $idToken = isset($tokenResponse['id_token']) ? (string) $tokenResponse['id_token'] : null;
        $this->session->markAuthenticatedViaSso(
            (string) ($user['email'] ?? $email),
            $idToken
        );

        $this->redirect($this->config->getDashboardPath());
    }

    /**
     * Prefer validated ID token claims; fall back to UserInfo when needed.
     *
     * @param array<string, mixed> $tokenResponse
     * @return array<string, mixed>
     * @throws SsoException
     */
    private function resolveClaims(array $tokenResponse, string $expectedNonce): array
    {
        $claims = [];

        $idToken = trim((string) ($tokenResponse['id_token'] ?? ''));
        if ($idToken !== '') {
            $claims = $this->client->validateIdToken($idToken, $expectedNonce);
        }

        $emailClaim = $this->config->getEmailClaim();
        $hasEmail = !empty($claims[$emailClaim]) || !empty($claims['email']);

        if (!$hasEmail) {
            $accessToken = trim((string) ($tokenResponse['access_token'] ?? ''));
            if ($accessToken === '') {
                throw new SsoException(
                    'SSO response did not include identity claims or an access token.',
                    'claims_missing'
                );
            }

            $userInfo = $this->client->fetchUserInfo($accessToken);
            $claims = array_merge($claims, $userInfo);
        }

        if ($claims === []) {
            throw new SsoException('Unable to resolve SSO identity claims.', 'claims_empty');
        }

        return $claims;
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}