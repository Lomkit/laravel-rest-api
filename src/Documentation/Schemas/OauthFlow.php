<?php

namespace Lomkit\Rest\Documentation\Schemas;

class OauthFlow extends Schema
{
    /**
     * The authorization URL to be used for this flow. This MUST be in the form of a URL. The OAuth2 standard requires the use of TLS.
     */
    protected string $authorizationUrl;

    /**
     * The token URL to be used for this flow. This MUST be in the form of a URL. The OAuth2 standard requires the use of TLS.
     */
    protected string $tokenUrl;

    /**
     * The URL to be used for obtaining refresh tokens. This MUST be in the form of a URL. The OAuth2 standard requires the use of TLS.
     */
    protected string $refreshUrl;

    /**
     * The available scopes for the OAuth2 security scheme. A map between the scope name and a short description for it. The map MAY be empty.
     */
    protected array $scopes;

    /**
     * Set the authorization URL for this OAuth2 flow.
     */
    public function withAuthorizationUrl(string $authorizationUrl): OauthFlow
    {
        $this->authorizationUrl = $authorizationUrl;

        return $this;
    }

    /**
     * Get the authorization URL for this OAuth2 flow.
     */
    public function authorizationUrl(): string
    {
        return $this->authorizationUrl;
    }

    /**
     * Set the token URL for this OAuth2 flow.
     */
    public function withTokenUrl(string $tokenUrl): OauthFlow
    {
        $this->tokenUrl = $tokenUrl;

        return $this;
    }

    /**
     * Get the token URL for this OAuth2 flow.
     */
    public function tokenUrl(): string
    {
        return $this->tokenUrl;
    }

    /**
     * Set the refresh URL for this OAuth2 flow.
     */
    public function withRefreshUrl(string $refreshUrl): OauthFlow
    {
        $this->refreshUrl = $refreshUrl;

        return $this;
    }

    /**
     * Get the refresh URL for this OAuth2 flow.
     */
    public function refreshUrl(): string
    {
        return $this->refreshUrl;
    }

    /**
     * Set the available scopes for this OAuth2 flow.
     */
    public function withScopes(array $scopes): OauthFlow
    {
        $this->scopes = $scopes;

        return $this;
    }

    /**
     * Get the available scopes for this OAuth2 flow.
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /**
     * Generate and return the OauthFlow object.
     *
     * @return OauthFlow
     */
    public function generate(): Schema
    {
        return $this;
    }

    /**
     * Serialize the OauthFlow object to JSON format.
     */
    public function jsonSerialize(): mixed
    {
        return array_merge(
            isset($this->scopes) ? ['scopes' => $this->scopes()] : [],
            isset($this->tokenUrl) ? ['tokenUrl' => $this->tokenUrl()] : [],
            isset($this->refreshUrl) ? ['refreshUrl' => $this->refreshUrl()] : [],
            isset($this->authorizationUrl) ? ['authorizationUrl' => $this->authorizationUrl()] : [],
        );
    }
}
