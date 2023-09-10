<?php

namespace Lomkit\Rest\Documentation\Schemas;

class Server extends Schema
{
    /**
     * A URL to the target host. This URL supports Server Variables and MAY be relative,
     * to indicate that the host location is relative to the location where the OpenAPI document is being served.
     * Variable substitutions will be made when a variable is named in {brackets}.
     *
     * @var string
     */
    protected string $url;

    /**
     * An optional string describing the host designated by the URL. CommonMark syntax MAY be used for rich text representation.
     *
     * @var string
     */
    protected string $description;

    /**
     * A map between a variable name and its value. The value is used for substitution in the server's URL template.
     *
     * @var array
     */
    protected array $variables = [];

    public function withUrl(string $url): Server
    {
        $this->url = $url;

        return $this;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function withDescription(string $description): Server
    {
        $this->description = $description;

        return $this;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function withVariable(string $key, ServerVariable $variable): Server
    {
        $this->variables[$key] = $variable;

        return $this;
    }

    public function variables(): array
    {
        return $this->variables;
    }

    public function jsonSerialize(): mixed
    {
        return array_merge(
            isset($this->url) ? ['url' => $this->url()] : [],
            isset($this->description) ? ['description' => $this->description()] : [],
            !empty($this->variables) ? ['variables' => collect($this->variables())->map->jsonSerialize()->toArray()] : [],
        );
    }

    public function generate(): Server
    {
        return $this;
    }
}
