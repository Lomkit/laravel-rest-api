<?php

namespace Lomkit\Rest\Documentation\Schemas;

use Lomkit\Rest\Http\Controllers\Controller;

class RequestBody extends Schema
{
    /**
     * A brief description of the request body. This could contain examples of use. CommonMark syntax MAY be used for rich text representation.
     *
     * @var string
     */
    protected string $description;

    /**
     * The content of the request body. The key is a media type or media type range and the value describes it.
     * For requests that match multiple keys, only the most specific key is applicable. e.g. text/plain overrides text/*.
     *
     * @var array
     */
    protected array $content = [];

    /**
     * Determines if the request body is required in the request. Defaults to false.
     *
     * @var bool
     */
    protected bool $required;

    /**
     * Set a brief description for the request body.
     *
     * @param string $description The description to set.
     *
     * @return RequestBody
     */
    public function withDescription(string $description): RequestBody
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get the description of the request body.
     *
     * @return string
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Set the content of the request body.
     *
     * @param array $content The content to set.
     *
     * @return RequestBody
     */
    public function withContent(array $content): RequestBody
    {
        $this->content = array_merge($this->content, $content);

        return $this;
    }

    /**
     * Get the content of the request body.
     *
     * @return array
     */
    public function content(): array
    {
        return $this->content;
    }

    /**
     * Set whether the request body is required.
     *
     * @param bool $required The required flag to set.
     *
     * @return RequestBody
     */
    public function withRequired(bool $required = true): RequestBody
    {
        $this->required = $required;

        return $this;
    }

    /**
     * Check if the request body is required.
     *
     * @return bool
     */
    public function required(): bool
    {
        return $this->required;
    }

    /**
     * Generate and return the request body schema.
     *
     * @return Schema
     */
    public function generate(): Schema
    {
        return $this;
    }

    /**
     * Serialize the request body to JSON format for documentation.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return array_merge(
            ['content' => collect($this->content())->map->jsonSerialize()->toArray()],
            isset($this->required) ? ['required' => $this->required()] : [],
            isset($this->description) ? ['description' => $this->description()] : [],
        );
    }

    /**
     * Generate and return a request body schema for a search operation.
     *
     * @param Controller $controller The controller for which to generate the request body.
     *
     * @return RequestBody
     */
    public function generateSearch(Controller $controller): RequestBody
    {
        return $this
            ->withContent(
                [
                    'application/json' => (new MediaType())
                        ->withExample(
                            (new Example())
                                ->withValue(
                                    [
                                        'scopes' => [
                                            ['name' => 'withTrashed', 'parameters' => [true]],
                                        ],
                                        'filters' => [
                                            ['field' => 'id', 'operator' => '>', 'value' => 1, 'type' => 'or'],
                                            ['nested' => [
                                                ['field' => 'user.id', 'operator' => '<', 'value' => 2],
                                                ['field' => 'id', 'operator' => '>', 'value' => 100, 'type' => 'or'],
                                            ]],
                                        ],
                                        'sorts' => [
                                            ['field' => 'user_id', 'direction' => 'desc'],
                                            ['field' => 'id', 'direction' => 'asc'],
                                        ],
                                        'selects' => [
                                            ['field' => 'id'],
                                        ],
                                        'includes' => [
                                            [
                                                'relation' => 'posts',
                                                'filters'  => [
                                                    ['field' => 'id', 'operator' => 'in', 'value' => [1, 3]],
                                                ],
                                                'limit' => 2,
                                            ],
                                        ],
                                        'aggregates' => [
                                            [
                                                'relation' => 'stars',
                                                'type'     => 'max',
                                                'field'    => 'rate',
                                                'filters'  => [
                                                    [
                                                        'name'  => 'type',
                                                        'value' => 'odd',
                                                    ],
                                                ],
                                            ],
                                        ],
                                        'instructions' => [
                                            [
                                                'name'   => 'odd-even-id',
                                                'fields' => [
                                                    [
                                                        'name'  => 'type',
                                                        'value' => 'odd',
                                                    ],
                                                ],
                                            ],
                                        ],
                                        'gates' => [
                                            'create'
                                        ],
                                        'page'  => 2,
                                        'limit' => 10,
                                    ]
                                )
                                ->generate()
                        )
                        ->generate(),
                ]
            )
            ->generate();
    }

    /**
     * Generate and return a request body schema for a mutate operation.
     *
     * @param Controller $controller The controller for which to generate the request body.
     *
     * @return RequestBody
     */
    public function generateMutate(Controller $controller): RequestBody
    {
        return $this
            ->withContent(
                [
                    'application/json' => (new MediaType())
                        ->withExample(
                            (new Example())
                                ->withValue(
                                    [
                                        'mutate' => [
                                            [
                                                'operation'  => 'create',
                                                'attributes' => ['email' => 'me@email.com'],
                                                'relations'  => [
                                                    'star' => [
                                                        'operation'  => 'create',
                                                        'attributes' => ['number' => 2],
                                                    ],
                                                ],
                                            ],
                                            [
                                                'operation'  => 'update',
                                                'key'        => 1,
                                                'attributes' => ['email' => 'me@email.com'],
                                                'relations'  => [
                                                    'star' => [
                                                        'operation' => 'detach',
                                                        'key'       => 1,
                                                    ],
                                                ],
                                            ]],
                                    ]
                                )
                                ->generate()
                        )
                        ->generate(),
                ]
            )
            ->generate();
    }

    /**
     * Generate and return a request body schema for an "Actions" operation.
     *
     * @param Controller $controller The controller for which to generate the request body.
     *
     * @return RequestBody
     */
    public function generateActions(Controller $controller): RequestBody
    {
        return $this
            ->withContent(
                [
                    'application/json' => (new MediaType())
                        ->withExample(
                            (new Example())
                                ->withValue(
                                    [
                                        'search' => [
                                            'filters' => [
                                                ['field' => 'has_received_welcome_notification', 'value' => false],
                                            ],
                                        ],
                                        'fields' => [
                                            ['name' => 'expires_at', 'value' => '2023-04-29'],
                                        ],

                                    ]
                                )
                                ->generate()
                        )
                        ->generate(),
                ]
            )
            ->generate();
    }

    /**
     * Generate and return a request body schema for a "Destroy" operation.
     *
     * @param Controller $controller The controller for which to generate the request body.
     *
     * @return RequestBody
     */
    public function generateDestroy(Controller $controller): RequestBody
    {
        return $this
            ->withContent(
                [
                    'application/json' => (new MediaType())
                        ->withExample(
                            (new Example())
                                ->withValue(
                                    [
                                        'resources' => [1, 5, 6],
                                    ]
                                )
                                ->generate()
                        )
                        ->generate(),
                ]
            )
            ->generate();
    }

    /**
     * Generate and return a request body schema for a "Restore" operation.
     *
     * @param Controller $controller The controller for which to generate the request body.
     *
     * @return RequestBody
     */
    public function generateRestore(Controller $controller): RequestBody
    {
        return $this
            ->withContent(
                [
                    'application/json' => (new MediaType())
                        ->withExample(
                            (new Example())
                                ->withValue(
                                    [
                                        'resources' => [1, 5, 6],
                                    ]
                                )
                                ->generate()
                        )
                        ->generate(),
                ]
            )
            ->generate();
    }

    /**
     * Generate and return a request body schema for a "Force Delete" operation.
     *
     * @param Controller $controller The controller for which to generate the request body.
     *
     * @return RequestBody
     */
    public function generateForceDelete(Controller $controller): RequestBody
    {
        return $this
            ->withContent(
                [
                    'application/json' => (new MediaType())
                        ->withExample(
                            (new Example())
                                ->withValue(
                                    [
                                        'resources' => [1, 5, 6],
                                    ]
                                )
                                ->generate()
                        )
                        ->generate(),
                ]
            )
            ->generate();
    }
}
