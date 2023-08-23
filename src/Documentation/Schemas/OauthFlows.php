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

    public function withImplicit(OauthFlow $implicit): OauthFlows
    {
        $this->implicit = $implicit;
        return $this;
    }

    public function implicit(): OauthFlow
    {
        return $this->implicit;
    }

    public function withPassword(OauthFlow $password): OauthFlows
    {
        $this->password = $password;
        return $this;
    }

    public function password(): OauthFlow
    {
        return $this->password;
    }

    public function withClientCredentials(OauthFlow $clientCredentials): OauthFlows
    {
        $this->clientCredentials = $clientCredentials;
        return $this;
    }

    public function clientCredentials(): OauthFlow
    {
        return $this->clientCredentials;
    }

    public function withAuthorizationCode(OauthFlow $authorizationCode): OauthFlows
    {
        $this->authorizationCode = $authorizationCode;
        return $this;
    }

    public function authorizationCode(): OauthFlow
    {
        return $this->authorizationCode;
    }

    public function generate(): OauthFlows
    {
        return $this;
    }

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