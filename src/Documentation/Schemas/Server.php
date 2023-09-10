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

    /**
     * Set the URL for the server.
     *
     * @param string $url
     *
     * @return Server
     *
     * This method allows setting the URL for the server. The URL can support Server Variables
     * and may be relative to indicate that the host location is relative to the location where
     * the OpenAPI document is being served.
     */
    public function withUrl(string $url): Server
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get the URL of the server.
     *
     * @return string
     *
     * This method retrieves the URL of the server.
     */
    public function url(): string
    {
        return $this->url;
    }

    /**
     * Set a description for the server.
     *
     * @param string $description
     *
     * @return Server
     *
     * This method allows setting an optional description for the host designated by the URL.
     * CommonMark syntax may be used for rich text representation.
     */
    public function withDescription(string $description): Server
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get the description of the server.
     *
     * @return string
     *
     * This method retrieves the description of the server, if any.
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Add a variable to the server.
     *
     * @param string         $key
     * @param ServerVariable $variable
     *
     * @return Server
     *
     * This method allows adding a variable to the server. The variable consists of a name (key) and
     * a ServerVariable object containing additional details about the variable.
     */
    public function withVariable(string $key, ServerVariable $variable): Server
    {
        $this->variables[$key] = $variable;

        return $this;
    }

    /**
     * Get the variables associated with the server.
     *
     * @return array
     *
     * This method retrieves the variables associated with the server, if any.
     */
    public function variables(): array
    {
        return $this->variables;
    }

    /**
     * Serialize the server to JSON.
     *
     * @return mixed
     *
     * This method serializes the server to a JSON format, including its URL, description, and variables.
     */
    public function jsonSerialize(): mixed
    {
        return array_merge(
            isset($this->url) ? ['url' => $this->url()] : [],
            isset($this->description) ? ['description' => $this->description()] : [],
            !empty($this->variables) ? ['variables' => collect($this->variables())->map->jsonSerialize()->toArray()] : [],
        );
    }

    /**
     * Generate the server.
     *
     * @return Server
     *
     * This method returns the server itself as no additional generation or processing is needed.
     */
    public function generate(): Server
    {
        return $this;
    }
}
