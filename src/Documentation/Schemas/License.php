<?php

namespace Lomkit\Rest\Documentation\Schemas;

class License extends Schema
{
    /**
     * The license name used for the API.
     */
    protected string $name;

    /**
     * An SPDX license expression for the API. The identifier field is mutually exclusive of the url field.
     */
    protected string $identifier;

    /**
     * A URL to the license used for the API. This MUST be in the form of a URL. The url field is mutually exclusive of the identifier field.
     */
    protected ?string $url;

    /**
     * Set the name of the license used for the API.
     */
    public function withName(string $name): License
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the license name used for the API.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Set the SPDX license expression for the API.
     */
    public function withIdentifier(string $identifier): License
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * Get the SPDX license expression for the API.
     */
    public function identifier(): string
    {
        return $this->identifier;
    }

    /**
     * Set the URL to the license used for the API.
     */
    public function withUrl(?string $url): License
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get the URL to the license used for the API.
     */
    public function url(): ?string
    {
        return $this->url;
    }

    /**
     * Serialize the object to a JSON representation.
     */
    public function jsonSerialize(): mixed
    {
        return array_merge(
            [
                'name' => $this->name(),
                'identifier' => $this->identifier(),
            ],
            ! is_null($this->url()) ? ['url' => $this->url()] : []
        );
    }

    /**
     * Generate a License object with default values.
     */
    public function generate(): License
    {
        return $this
            ->withUrl(config('rest.documentation.info.license.url'))
            ->withName(config('rest.documentation.info.license.name'))
            ->withIdentifier(config('rest.documentation.info.license.identifier'));
    }
}
