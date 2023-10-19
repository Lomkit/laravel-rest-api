<?php

namespace Lomkit\Rest\Documentation\Schemas;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Relations\Relation;

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
     * @return RequestBody
     */
    public function generate(): RequestBody
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
        $request = app()->make(RestRequest::class);

        return $this
            ->withContent(
                [
                    'application/json' => (new MediaType())
                        ->withExample(
                            (new Example())
                                ->withValue(
                                    [
                                        'search' => array_merge(
                                            // Scopes
                                            [
                                                'scopes' =>
                                                    collect($controller::newResource()->getScopes($request))
                                                        ->map(function ($scope) {
                                                            return ['name' => $scope, 'parameters' => []];
                                                        })
                                                        ->toArray()
                                            ],
                                            // Filters
                                            [
                                                'filters' =>
                                                    collect($controller::newResource()->getFields($request))
                                                        ->map(function ($filter) {
                                                            return ['field' => $filter, 'operator' => '=', 'value' => ''];
                                                        })
                                                        ->toArray()
                                            ],
                                            // Sorts
                                            [
                                                'sorts' =>
                                                    collect($controller::newResource()->getFields($request))
                                                        ->map(function ($sort) {
                                                            return ['field' => $sort, 'direction' => 'desc'];
                                                        })
                                                        ->toArray()
                                            ],
                                            // Selects
                                            [
                                                'selects' =>
                                                    collect($controller::newResource()->getFields($request))
                                                        ->map(function ($select) {
                                                            return ['field' => $select];
                                                        })
                                                        ->toArray()
                                            ],
                                            // Includes
                                            [
                                                'includes' =>
                                                    collect($controller::newResource()->getRelations($request))
                                                        ->map(function (Relation $relation) {
                                                            return ['relation' => $relation->relation];
                                                        })
                                                        ->toArray()
                                            ],
                                            // Aggregates
                                            [
                                                'aggregates' => []
                                            ],
                                            // Instructions
                                            [
                                                'instructions' =>
                                                    collect($controller::newResource()->getInstructions($request))
                                                        ->map(function (Instruction $instruction) use ($request) {
                                                            return [
                                                                'name' => $instruction->name(),
                                                                'fields' => collect($instruction->fields($request))
                                                                    ->map(function ($field) {
                                                                        return ['field' => $field, 'value' => ''];
                                                                    })
                                                                    ->toArray()
                                                            ];
                                                        })
                                                        ->toArray()
                                            ],
                                            // Gates
                                            [
                                                'gates' => [
                                                    'create',
                                                    'update',
                                                    'delete',
                                                ]
                                            ],
                                            // Page
                                            [
                                                'page' => 1
                                            ],
                                            // Limit
                                            [
                                                'limit' => $controller::newResource()->getLimits($request)[0]
                                            ]
                                        ),
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
        $request = app()->make(RestRequest::class);

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
                                                // Attributes
                                                'attributes' =>
                                                    collect($controller::newResource()->getFields($request))
                                                        ->mapWithKeys(function ($field) {
                                                            return [$field => ''];
                                                        })
                                                        ->toArray(),
                                                // Relations
                                                'relations'  =>
                                                    collect($controller::newResource()->getRelations($request))
                                                        ->mapWithKeys(function (Relation $relation) {
                                                            return [$relation->relation => [
                                                                'operation' => 'update',
                                                                'key' => 1
                                                            ]];
                                                        })
                                                        ->toArray()
                                            ]
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
