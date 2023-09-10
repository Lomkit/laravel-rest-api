<?php

namespace Lomkit\Rest\Documentation\Schemas;

use Lomkit\Rest\Http\Controllers\Controller;

class Response extends Schema
{
    /**
     * A description of the response. CommonMark syntax MAY be used for rich text representation.
     *
     * @var string
     */
    protected string $description;

    /**
     * Maps a header name to its definition. RFC7230 states header names are case insensitive.
     * If a response header is defined with the name "Content-Type", it SHALL be ignored.
     *
     * @var array
     */
    protected array $headers = [];

    /**
     * A map containing descriptions of potential response payloads.
     * The key is a media type or media type range and the value describes it. For responses that match multiple keys, only the most specific key is applicable. e.g. text/plain overrides text/*.
     *
     * @var array
     */
    protected array $content = [];

    /**
     * A map of operations links that can be followed from the response. The key of the map is a short name for the link, following the naming constraints of the names for Component Objects.
     *
     * @var array
     */
    protected array $links = [];

    /**
     * Set the description for the response.
     *
     * @param string $description The description of the response.
     *
     * @return Response
     */
    public function withDescription(string $description): Response
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get the description of the response.
     *
     * @return string
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Set the response headers.
     *
     * @param array $headers The response headers.
     *
     * @return Response
     */
    public function withHeaders(array $headers): Response
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Get the response headers.
     *
     * @return array
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Set the response content.
     *
     * @param array $content The response content.
     *
     * @return Response
     */
    public function withContent(array $content): Response
    {
        $this->content = array_merge($this->content, $content);

        return $this;
    }

    /**
     * Get the response content.
     *
     * @return array
     */
    public function content(): array
    {
        return $this->content;
    }

    /**
     * Set the links that can be followed from the response.
     *
     * @param array $links The response links.
     *
     * @return Response
     */
    public function withLinks(array $links): Response
    {
        $this->links = array_merge($this->links, $links);

        return $this;
    }

    /**
     * Get the response links.
     *
     * @return array
     */
    public function links(): array
    {
        return $this->links;
    }

    /**
     * Generate a JSON serializable representation of the response.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return array_merge(
            [
                'description' => $this->description(),
            ],
            !empty($this->headers) ? ['headers' => collect($this->headers())->map->jsonSerialize()->toArray()] : [],
            !empty($this->content) ? ['content' => $this->content()] : [],
            !empty($this->links) ? ['links' => $this->links()] : []
        );
    }

    /**
     * Generates a new instance of the Response schema.
     *
     * @return Response
     */
    public function generate(): Response
    {
        return $this;
    }

    /**
     * Generates a detailed response schema for a specific controller action.
     *
     * @param Controller $controller The controller associated with the response.
     *
     * @return Response
     */
    public function generateDetail(Controller $controller): Response
    {
        return $this
            ->withDescription('')
            ->withContent(
                [
                    'application/json' => (new MediaType())
                        ->generateDetail($controller),
                ]
            )
            ->generate();
    }

    /**
     * Generates a response schema for a search operation.
     *
     * @param Controller $controller The controller associated with the response.
     *
     * @return Response
     */
    public function generateSearch(Controller $controller): Response
    {
        return $this
            ->withDescription('')
            ->withContent(
                [
                    'application/json' => (new MediaType())
                        ->generateSearch($controller),
                ]
            )
            ->generate();
    }

    /**
     * Generates a response schema for a mutation operation.
     *
     * @param Controller $controller The controller associated with the response.
     *
     * @return Response
     */
    public function generateMutate(Controller $controller): Response
    {
        return $this
            ->withDescription('')
            ->withContent(
                [
                    'application/json' => (new MediaType())
                        ->generateMutate($controller),
                ]
            )
            ->generate();
    }

    /**
     * Generates a response schema for an actions operation.
     *
     * @param Controller $controller The controller associated with the response.
     *
     * @return Response
     */
    public function generateActions(Controller $controller): Response
    {
        return $this
            ->withDescription('')
            ->withContent(
                [
                    'application/json' => (new MediaType())
                        ->generateActions($controller),
                ]
            )
            ->generate();
    }

    /**
     * Generates a response schema for a destroy operation.
     *
     * @param Controller $controller The controller associated with the response.
     *
     * @return Response
     */
    public function generateDestroy(Controller $controller): Response
    {
        return $this
            ->withDescription('')
            ->withContent(
                [
                    'application/json' => (new MediaType())
                        ->generateDestroy($controller),
                ]
            )
            ->generate();
    }

    /**
     * Generates a response schema for a restore operation.
     *
     * @param Controller $controller The controller associated with the response.
     *
     * @return Response
     */
    public function generateRestore(Controller $controller): Response
    {
        return $this
            ->withDescription('')
            ->withContent(
                [
                    'application/json' => (new MediaType())
                        ->generateRestore($controller),
                ]
            )
            ->generate();
    }

    /**
     * Generates a response schema for a force delete operation.
     *
     * @param Controller $controller The controller associated with the response.
     *
     * @return Response
     */
    public function generateForceDelete(Controller $controller): Response
    {
        return $this
            ->withDescription('')
            ->withContent(
                [
                    'application/json' => (new MediaType())
                        ->generateForceDelete($controller),
                ]
            )
            ->generate();
    }
}
