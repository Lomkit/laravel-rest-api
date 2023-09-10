<?php

namespace Lomkit\Rest\Documentation\Schemas;

class License extends Schema
{
    /**
     * The license name used for the API.
     *
     * @var string
     */
    protected string $name;

    /**
     * An SPDX license expression for the API. The identifier field is mutually exclusive of the url field.
     *
     * @var string
     */
    protected string $identifier;

    /**
     * A URL to the license used for the API. This MUST be in the form of a URL. The url field is mutually exclusive of the identifier field.
     *
     * @var string
     */
    protected string|null $url;

    public function withName(string $name): License
    {
        $this->name = $name;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function withIdentifier(string $identifier): License
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function withUrl(string|null $url): License
    {
        $this->url = $url;

        return $this;
    }

    public function url(): string|null
    {
        return $this->url;
    }

    public function jsonSerialize(): mixed
    {
        return array_merge(
            [
                'name'       => $this->name(),
                'identifier' => $this->identifier(),
            ],
            !is_null($this->url()) ? ['url' => $this->url()] : []
        );
    }

    public function generate(): License
    {
        return $this
            ->withUrl(config('rest.documentation.info.license.url'))
            ->withName(config('rest.documentation.info.license.name'))
            ->withIdentifier(config('rest.documentation.info.license.identifier'));
    }
}
