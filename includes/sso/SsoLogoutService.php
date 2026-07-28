<?php
/**
 * Ends the local session and optionally redirects to the Identity Provider logout.
 */
class SsoLogoutService
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
     * Destroy the application session. When the user signed in via SSO and IdP
     * logout is configured, redirect there; otherwise go to the local login page.
     */
    public function logout(): void
    {
        $this->session->ensurePhpSession();

        $wasSso = $this->session->isAuthenticatedViaSso();
        $idToken = $this->session->getIdToken();

        // Clear SSO markers before destroying the whole session
        $this->session->clearSsoMarkers();
        login_destroy_session();

        if (
            $wasSso
            && $this->config->isEnabled()
            && $this->config->shouldLogoutAtIdp()
        ) {
            $idpLogoutUrl = $this->client->buildLogoutUrl($idToken !== '' ? $idToken : null);
            if ($idpLogoutUrl !== null) {
                header('Location: ' . $idpLogoutUrl);
                exit;
            }
        }

        header('Location: ' . $this->config->getLoginPath());
        exit;
    }
}
