<?php

namespace Lomkit\Rest\Documentation\Schemas;

use Lomkit\Rest\Http\Controllers\Controller;

class RequestBody extends Schema
{
    /**
     * A brief description of the request body. This could contain examples of use. CommonMark syntax MAY be used for rich text representation
     * @var string
     */
    protected string $description;

    /**
     * The content of the request body. The key is a media type or media type range and the value describes it.
     * For requests that match multiple keys, only the most specific key is applicable. e.g. text/plain overrides text/*
     * @var array
     */
    protected array $content = [];

    /**
     * Determines if the request body is required in the request. Defaults to false.
     * @var bool
     */
    protected bool $required;

    public function withDescription(string $description): RequestBody
    {
        $this->description = $description;
        return $this;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function withContent(array $content): RequestBody
    {
        $this->content = array_merge($this->content, $content);
        return $this;
    }

    public function content(): array
    {
        return $this->content;
    }

    public function withRequired(bool $required = true): RequestBody
    {
        $this->required = $required;
        return $this;
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function generate(): Schema
    {
        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return array_merge(
            ['content' => collect($this->content())->map->jsonSerialize()->toArray()],
            isset($this->required) ? ['required' => $this->required()] : [],
            isset($this->description) ? ['description' => $this->description()] : [],
        );
    }

    public function generateSearch(Controller $controller): RequestBody
    {
        return $this
            ->withContent(
                [
                    'application/json' => (new MediaType)
                        ->withExample(
                            (new Example)
                                ->withValue(
                                    [
                                        'scopes' => [
                                            ['name' => 'withTrashed', 'parameters' => [true]]
                                        ],
                                        'filters' => [
                                            ['field' => 'id', 'operator' => '>', 'value' => 1, 'type' => 'or'],
                                            ['nested' => [
                                                ['field' => 'user.id', 'operator' => '<', 'value' => 2],
                                                ['field' => 'id', 'operator' => '>', 'value' => 100, 'type' => 'or'],
                                            ]]
                                        ],
                                        'sorts' => [
                                            ['field' => 'user_id', 'direction' => 'desc'],
                                            ['field' => 'id', 'direction' => 'asc']
                                        ],
                                        'selects' => [
                                            ['field' => 'id']
                                        ],
                                        'includes' => [
                                            [
                                                'relation' => 'posts',
                                                'filters' => [
                                                    ['field' => 'id', 'operator' => 'in', 'value' => [1, 3]]
                                                ],
                                                'limit' => 2
                                            ]
                                        ],
                                        'aggregates' => [
                                            [
                                                'relation' => 'stars',
                                                'type' => 'max',
                                                'field' => 'rate',
                                                'filters' => [
                                                    [
                                                        'name' => 'type',
                                                        'value' => 'odd'
                                                    ]
                                                ]
                                            ]
                                        ],
                                        'page' => 2,
                                        'limit' => 10
                                    ]
                                )
                                ->generate()
                        )
                        ->generate()
                ]
            )
            ->generate();
    }

    public function generateMutate(Controller $controller): RequestBody
    {
        return $this
            ->withContent(
                [
                    'application/json' => (new MediaType)
                        ->withExample(
                            (new Example)
                                ->withValue(
                                    [
                                        'mutate' => [
                                            [
                                                'operation' => 'create',
                                                'attributes' => ['email' => 'me@email.com'],
                                                'relations' => [
                                                    'star' => [
                                                        'operation' => 'create',
                                                        'attributes' => ['number' => 2]
                                                    ]
                                                ]
                                            ],
                                            [
                                                'operation' => 'update',
                                                'key' => 1,
                                                'attributes' => ['email' => 'me@email.com'],
                                                'relations' => [
                                                    'star' => [
                                                        'operation' => 'detach',
                                                        'key' => 1
                                                    ]
                                                ]
                                            ]]
                                    ]
                                )
                                ->generate()
                        )
                        ->generate()
                ]
            )
            ->generate();
    }
}