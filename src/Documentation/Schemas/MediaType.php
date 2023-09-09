<?php

namespace Lomkit\Rest\Documentation\Schemas;

use Lomkit\Rest\Http\Controllers\Controller;

class MediaType extends Schema
{
    /**
     * The concrete schema for this media type.
     * @var SchemaConcrete
     */
    protected SchemaConcrete $schemaConcrete;

    /**
     * Examples of this media type.
     * @var Examples
     */
    protected Examples $examples;

    /**
     * An example of this media type.
     * @var Example
     */
    protected Example $example;

    /**
     * Set the concrete schema for this media type.
     *
     * @param  SchemaConcrete  $schemaConcrete
     * @return MediaType
     */
    public function withSchemaConcrete(SchemaConcrete $schemaConcrete): MediaType
    {
        $this->schemaConcrete = $schemaConcrete;
        return $this;
    }

    /**
     * Get the concrete schema for this media type.
     *
     * @return SchemaConcrete
     */
    public function schemaConcrete(): SchemaConcrete
    {
        return $this->schemaConcrete;
    }

    /**
     * Set the examples for this media type.
     *
     * @param  Examples  $examples
     * @return MediaType
     */
    public function withExamples(Examples $examples): MediaType
    {
        $this->examples = $examples;
        return $this;
    }

    /**
     * Get the examples for this media type.
     *
     * @return Examples
     */
    public function examples(): Examples
    {
        return $this->examples;
    }

    /**
     * Set an example for this media type.
     *
     * @param  Example  $example
     * @return MediaType
     */
    public function withExample(Example $example): MediaType
    {
        $this->example = $example;
        return $this;
    }

    /**
     * Get an example for this media type.
     *
     * @return Example
     */
    public function example(): Example
    {
        return $this->example;
    }

    /**
     * Generate a MediaType object.
     *
     * @return MediaType
     */
    public function generate(): MediaType
    {
        return $this;
    }

    /**
     * Serialize the object to a JSON representation.
     *
     * @return mixed
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
     *
     * @param  Controller  $controller
     * @return MediaType
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
     *
     * @param  Controller  $controller
     * @return MediaType
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
     *
     * @param  Controller  $controller
     * @return MediaType
     */
    public function generateMutate(Controller $controller): MediaType
    {
        return $this
            ->withExample(
                (new Example)
                    ->withValue(
                        ['created' => [1], 'updated' => [2,3]]
                    )
            )
            ->generate();
    }

    /**
     * Generate a MediaType object with an example for an actions action.
     *
     * @param  Controller  $controller
     * @return MediaType
     */
    public function generateActions(Controller $controller): MediaType
    {
        return $this
            ->withExample(
                (new Example)
                    ->withValue(
                        [
                            'data' => [
                                'impacted' => 2
                            ]
                        ]
                    )
            )
            ->generate();
    }

    /**
     * Generate a MediaType object with an example for a destroy action.
     *
     * @param  Controller  $controller
     * @return MediaType
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
     *
     * @param  Controller  $controller
     * @return MediaType
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
     *
     * @param  Controller  $controller
     * @return MediaType
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