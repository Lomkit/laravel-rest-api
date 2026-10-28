<?php

namespace Lomkit\Rest\Documentation\Schemas;

class OauthFlows extends Schema
{
    /**
     * Configuration for the OAuth Implicit flow.
     */
    protected OauthFlow $implicit;

    /**
     * Configuration for the OAuth Resource Owner Password flow.
     */
    protected OauthFlow $password;

    /**
     * Configuration for the OAuth Client Credentials flow. Previously called application in OpenAPI 2.0.
     */
    protected OauthFlow $clientCredentials;

    /**
     * Configuration for the OAuth Authorization Code flow. Previously called accessCode in OpenAPI 2.0.
     */
    protected OauthFlow $authorizationCode;

    /**
     * Set the configuration for the OAuth Implicit flow.
     */
    public function withImplicit(OauthFlow $implicit): OauthFlows
    {
        $this->implicit = $implicit;

        return $this;
    }

    /**
     * Get the configuration for the OAuth Implicit flow.
     */
    public function implicit(): OauthFlow
    {
        return $this->implicit;
    }

    /**
     * Set the configuration for the OAuth Resource Owner Password flow.
     */
    public function withPassword(OauthFlow $password): OauthFlows
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Get the configuration for the OAuth Resource Owner Password flow.
     */
    public function password(): OauthFlow
    {
        return $this->password;
    }

    /**
     * Set the configuration for the OAuth Client Credentials flow.
     */
    public function withClientCredentials(OauthFlow $clientCredentials): OauthFlows
    {
        $this->clientCredentials = $clientCredentials;

        return $this;
    }

    /**
     * Get the configuration for the OAuth Client Credentials flow.
     */
    public function clientCredentials(): OauthFlow
    {
        return $this->clientCredentials;
    }

    /**
     * Set the configuration for the OAuth Authorization Code flow.
     */
    public function withAuthorizationCode(OauthFlow $authorizationCode): OauthFlows
    {
        $this->authorizationCode = $authorizationCode;

        return $this;
    }

    /**
     * Get the configuration for the OAuth Authorization Code flow.
     */
    public function authorizationCode(): OauthFlow
    {
        return $this->authorizationCode;
    }

    /**
     * Generate and return the OauthFlows object.
     */
    public function generate(): OauthFlows
    {
        return $this;
    }

    /**
     * Serialize the OauthFlows object to JSON format.
     */
    public function jsonSerialize(): mixed
    {
        return array_merge(
            isset($this->implicit) ? ['implicit' => $this->implicit()->jsonSerialize()] : [],
            isset($this->password) ? ['password' => $this->password()->jsonSerialize()] : [],
            isset($this->authorizationCode) ? ['authorizationCode' => $this->authorizationCode()->jsonSerialize()] : [],
            isset($this->clientCredentials) ? ['clientCredentials' => $this->clientCredentials()->jsonSerialize()] : [],
        );
    }
}
