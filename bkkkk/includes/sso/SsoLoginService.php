<?php
/**
 * Starts the OIDC Authorization Code (+ PKCE) login redirect.
 */
class SsoLoginService
{
    private SsoConfig $config;
    private SsoClient $client;
    private SsoSessionManager $session;

    public function __construct(
        SsoConfig $config,
        SsoClient $client,
        SsoSessionManager $session
    ) {
        $this->config = $config;
        $this->client = $client;
        $this->session = $session;
    }

    /**
     * Validate configuration, create handshake state, and redirect to the IdP.
     *
     * @throws SsoException
     */
    public function redirectToIdentityProvider(): void
    {
        $this->config->assertReadyForLogin();

        // Already authenticated in the app — send to dashboard
        $this->session->ensurePhpSession();
        if (!empty($_SESSION['usr_name'])) {
            $this->redirect($this->config->getDashboardPath());
        }

        $handshake = $this->session->beginHandshake();
        $authorizeUrl = $this->client->buildAuthorizeUrl($handshake);

        $this->redirect($authorizeUrl);
    }

    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}