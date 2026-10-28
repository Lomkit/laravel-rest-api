<?php

namespace Lomkit\Rest\Documentation\Schemas;

use Lomkit\Rest\Http\Controllers\Controller;

class MediaType extends Schema
{
    /**
     * The concrete schema for this media type.
     */
    protected SchemaConcrete $schemaConcrete;

    /**
     * Examples of this media type.
     */
    protected Examples $examples;

    /**
     * An example of this media type.
     */
    protected Example $example;

    /**
     * Set the concrete schema for this media type.
     */
    public function withSchemaConcrete(SchemaConcrete $schemaConcrete): MediaType
    {
        $this->schemaConcrete = $schemaConcrete;

        return $this;
    }

    /**
     * Get the concrete schema for this media type.
     */
    public function schemaConcrete(): SchemaConcrete
    {
        return $this->schemaConcrete;
    }

    /**
     * Set the examples for this media type.
     */
    public function withExamples(Examples $examples): MediaType
    {
        $this->examples = $examples;

        return $this;
    }

    /**
     * Get the examples for this media type.
     */
    public function examples(): Examples
    {
        return $this->examples;
    }

    /**
     * Set an example for this media type.
     */
    public function withExample(Example $example): MediaType
    {
        $this->example = $example;

        return $this;
    }

    /**
     * Get an example for this media type.
     */
    public function example(): Example
    {
        return $this->example;
    }

    /**
     * Generate a MediaType object.
     */
    public function generate(): MediaType
    {
        return $this;
    }

    /**
     * Serialize the object to a JSON representation.
     */
    public function jsonSerialize(): mixed
    {
        return array_merge(
            isset($this->schema) ? ['schema' => $this->schema()->jsonSerialize()] : [],
            isset($this->examples) ? ['examples' => $this->examples()->jsonSerialize()] : [],
            isset($this->example) ? ['example' => $this->example()->jsonSerialize()] : []
        );
    }

    /**
     * Generate a MediaType object with an example for a detail action.
     */
    public function generateDetail(Controller $controller): MediaType
    {
        return $this
            ->withExample(
                (new Example)
                    ->withValue(
                        ['data' => $controller::newResource()->jsonSerialize()]
                    )
            )
            ->generate();
    }

    /**
     * Generate a MediaType object with an example for a search action.
     */
    public function generateSearch(Controller $controller): MediaType
    {
        return $this
            ->withExample(
                (new Example)
                    ->withValue(
                        $controller::newResource()::newResponse()
                            ->resource($controller::newResource())
                            ->responsable(
                                $controller::newResource()::newModel()::factory()->makeOne()
                                    ->withoutRelations()
                            )
                            ->toResponse(request())
                    )
            )
            ->generate();
    }

    /**
     * Generate a MediaType object with an example for a mutate action.
     */
    public function generateMutate(Controller $controller): MediaType
    {
        return $this
            ->withExample(
                (new Example)
                    ->withValue(
                        ['created' => [1], 'updated' => [2, 3]]
                    )
            )
            ->generate();
    }

    /**
     * Generate a MediaType object with an example for an actions action.
     */
    public function generateActions(Controller $controller): MediaType
    {
        return $this
            ->withExample(
                (new Example)
                    ->withValue(
                        [
                            'data' => [
                                'impacted' => 2,
                            ],
                        ]
                    )
            )
            ->generate();
    }

    /**
     * Generate a MediaType object with an example for a destroy action.
     */
    public function generateDestroy(Controller $controller): MediaType
    {
        return $this
            ->withExample(
                (new Example)
                    ->withValue(
                        $controller::newResource()::newResponse()
                            ->resource($controller::newResource())
                            ->responsable(
                                $controller::newResource()::newModel()::factory()->makeOne()
                                    ->withoutRelations()
                            )
                            ->toResponse(request())
                    )
            )
            ->generate();
    }

    /**
     * Generate a MediaType object with an example for a restore action.
     */
    public function generateRestore(Controller $controller): MediaType
    {
        return $this
            ->withExample(
                (new Example)
                    ->withValue(
                        $controller::newResource()::newResponse()
                            ->resource($controller::newResource())
                            ->responsable(
                                $controller::newResource()::newModel()::factory()->makeOne()
                                    ->withoutRelations()
                            )
                            ->toResponse(request())
                    )
            )
            ->generate();
    }

    /**
     * Generate a MediaType object with an example for a force delete action.
     */
    public function generateForceDelete(Controller $controller): MediaType
    {
        return $this
            ->withExample(
                (new Example)
                    ->withValue(
                        $controller::newResource()::newResponse()
                            ->resource($controller::newResource())
                            ->responsable(
                                $controller::newResource()::newModel()::factory()->makeOne()
                                    ->withoutRelations()
                            )
                            ->toResponse(request())
                    )
            )
            ->generate();
    }
}
