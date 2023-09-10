<?php

namespace Lomkit\Rest\Documentation\Schemas;

class OauthFlow extends Schema
{
    /**
     * The authorization URL to be used for this flow. This MUST be in the form of a URL. The OAuth2 standard requires the use of TLS.
     *
     * @var string
     */
    protected string $authorizationUrl;

    /**
     * The token URL to be used for this flow. This MUST be in the form of a URL. The OAuth2 standard requires the use of TLS.
     *
     * @var string
     */
    protected string $tokenUrl;

    /**
     * The URL to be used for obtaining refresh tokens. This MUST be in the form of a URL. The OAuth2 standard requires the use of TLS.
     *
     * @var string
     */
    protected string $refreshUrl;

    /**
     * The available scopes for the OAuth2 security scheme. A map between the scope name and a short description for it. The map MAY be empty.
     *
     * @var array
     */
    protected array $scopes;

    public function withAuthorizationUrl(string $authorizationUrl): OauthFlow
    {
        $this->authorizationUrl = $authorizationUrl;

        return $this;
    }

    public function authorizationUrl(): string
    {
        return $this->authorizationUrl;
    }

    public function withTokenUrl(string $tokenUrl): OauthFlow
    {
        $this->tokenUrl = $tokenUrl;

        return $this;
    }

    public function tokenUrl(): string
    {
        return $this->tokenUrl;
    }

    public function withRefreshUrl(string $refreshUrl): OauthFlow
    {
        $this->refreshUrl = $refreshUrl;

        return $this;
    }

    public function refreshUrl(): string
    {
        return $this->refreshUrl;
    }

    public function withScopes(array $scopes): OauthFlow
    {
        $this->scopes = $scopes;

        return $this;
    }

    public function scopes(): array
    {
        return $this->scopes;
    }

    public function generate(): Schema
    {
        return $this;
    }

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
