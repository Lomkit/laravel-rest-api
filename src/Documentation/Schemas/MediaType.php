<?php

namespace Lomkit\Rest\Documentation\Schemas;

use Lomkit\Rest\Http\Controllers\Controller;

class MediaType extends Schema
{
    protected SchemaConcrete $schemaConcrete;

    protected Examples $examples;
    protected Example $example;

    public function withSchemaConcrete(SchemaConcrete $schemaConcrete): MediaType
    {
        $this->schemaConcrete = $schemaConcrete;
        return $this;
    }

    public function schemaConcrete(): SchemaConcrete
    {
        return $this->schemaConcrete;
    }

    public function withExamples(Examples $examples): MediaType
    {
        $this->examples = $examples;
        return $this;
    }

    public function examples(): Examples
    {
        return $this->examples;
    }

    public function withExample(Example $example): MediaType
    {
        $this->example = $example;
        return $this;
    }

    public function example(): Example
    {
        return $this->example;
    }

    public function generate(): MediaType
    {
        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return array_merge(
            isset($this->schema) ? ['schema' => $this->schema()->jsonSerialize()] : [],
            isset($this->examples) ? ['examples' => $this->examples()->jsonSerialize()] : [],
            isset($this->example) ? ['example' => $this->example()->jsonSerialize()] : []
        );
    }

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