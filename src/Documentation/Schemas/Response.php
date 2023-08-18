<?php

namespace Lomkit\Rest\Documentation\Schemas;

class Response extends Schema
{
    /**
     * A description of the response. CommonMark syntax MAY be used for rich text representation.
     * @var string
     */
    protected string $description;

    /**
     * Maps a header name to its definition. RFC7230 states header names are case insensitive.
     * If a response header is defined with the name "Content-Type", it SHALL be ignored.
     * @var array
     */
    protected array $headers = [];

    /**
     * A map containing descriptions of potential response payloads.
     * The key is a media type or media type range and the value describes it. For responses that match multiple keys, only the most specific key is applicable. e.g. text/plain overrides text/*
     * @var array
     */
    protected array $content = [];

    /**
     * A map of operations links that can be followed from the response. The key of the map is a short name for the link, following the naming constraints of the names for Component Objects.
     * @var array
     */
    protected array $links = [];

    public function withDescription(string $description): Response
    {
        $this->description = $description;
        return $this;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function withHeaders(array $headers): Response
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function withContent(array $content): Response
    {
        $this->content = array_merge($this->content, $content);
        return $this;
    }

    public function content(): array
    {
        return $this->content;
    }

    public function withLinks(array $links): Response
    {
        $this->links = array_merge($this->links, $links);
        return $this;
    }

    public function links(): array
    {
        return $this->links;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'description' => $this->description(),
            'headers' => $this->headers(),
            'content' => $this->content(),
            'links' => $this->links()
        ];
    }
}