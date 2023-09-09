<?php

namespace Lomkit\Rest\Documentation\Schemas;

class OauthFlows extends Schema
{
    /**
     * Configuration for the OAuth Implicit flow
     * @var OauthFlow
     */
    protected OauthFlow $implicit;

    /**
     * Configuration for the OAuth Resource Owner Password flow
     * @var OauthFlow
     */
    protected OauthFlow $password;

    /**
     * Configuration for the OAuth Client Credentials flow. Previously called application in OpenAPI 2.0.
     * @var OauthFlow
     */
    protected OauthFlow $clientCredentials;

    /**
     * Configuration for the OAuth Authorization Code flow. Previously called accessCode in OpenAPI 2.0.
     * @var OauthFlow
     */
    protected OauthFlow $authorizationCode;

    /**
     * Set the configuration for the OAuth Implicit flow.
     *
     * @param  OauthFlow  $implicit
     * @return OauthFlows
     */
    public function withImplicit(OauthFlow $implicit): OauthFlows
    {
        $this->implicit = $implicit;
        return $this;
    }

    /**
     * Get the configuration for the OAuth Implicit flow.
     *
     * @return OauthFlow
     */
    public function implicit(): OauthFlow
    {
        return $this->implicit;
    }

    /**
     * Set the configuration for the OAuth Resource Owner Password flow.
     *
     * @param  OauthFlow  $password
     * @return OauthFlows
     */
    public function withPassword(OauthFlow $password): OauthFlows
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Get the configuration for the OAuth Resource Owner Password flow.
     *
     * @return OauthFlow
     */
    public function password(): OauthFlow
    {
        return $this->password;
    }

    /**
     * Set the configuration for the OAuth Client Credentials flow.
     *
     * @param  OauthFlow  $clientCredentials
     * @return OauthFlows
     */
    public function withClientCredentials(OauthFlow $clientCredentials): OauthFlows
    {
        $this->clientCredentials = $clientCredentials;
        return $this;
    }

    /**
     * Get the configuration for the OAuth Client Credentials flow.
     *
     * @return OauthFlow
     */
    public function clientCredentials(): OauthFlow
    {
        return $this->clientCredentials;
    }

    /**
     * Set the configuration for the OAuth Authorization Code flow.
     *
     * @param  OauthFlow  $authorizationCode
     * @return OauthFlows
     */
    public function withAuthorizationCode(OauthFlow $authorizationCode): OauthFlows
    {
        $this->authorizationCode = $authorizationCode;
        return $this;
    }

    /**
     * Get the configuration for the OAuth Authorization Code flow.
     *
     * @return OauthFlow
     */
    public function authorizationCode(): OauthFlow
    {
        return $this->authorizationCode;
    }

    /**
     * Generate and return the OauthFlows object.
     *
     * @return OauthFlows
     */
    public function generate(): OauthFlows
    {
        return $this;
    }

    /**
     * Serialize the OauthFlows object to JSON format.
     *
     * @return mixed
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