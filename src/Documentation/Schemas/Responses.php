<?php

namespace Lomkit\Rest\Documentation\Schemas;

use Lomkit\Rest\Http\Controllers\Controller;

class Responses extends Schema
{
    /**
     * The documentation of responses other than the ones declared for specific HTTP response codes. Use this field to cover undeclared responses.
     *
     * @var Response
     */
    protected Response $default;

    /**
     * Other responses.
     *
     * @var object
     */
    protected array $others = [];

    public function withDefault(Response $default): Responses
    {
        $this->default = $default;

        return $this;
    }

    public function default(): Response
    {
        return $this->default;
    }

    public function withOthers(array $others): Responses
    {
        $this->others = array_merge($this->others, $others);

        return $this;
    }

    public function others(): array
    {
        return $this->others;
    }

    public function jsonSerialize(): mixed
    {
        return array_merge(
            ['default' => $this->default()->jsonSerialize()],
            collect($this->others())->map->jsonSerialize()->toArray()
        );
    }

    public function generate(): Responses
    {
        return $this;
    }

    public function generateDetail(Controller $controller): Responses
    {
        return $this
            ->withDefault(
                (new Response())
                    ->generateDetail($controller)
            )
            ->generate();
    }

    public function generateSearch(Controller $controller): Responses
    {
        return $this
            ->withDefault(
                (new Response())
                    ->generateSearch($controller)
            )
            ->generate();
    }

    public function generateMutate(Controller $controller): Responses
    {
        return $this
            ->withDefault(
                (new Response())
                    ->generateMutate($controller)
            )
            ->generate();
    }

    public function generateActions(Controller $controller): Responses
    {
        return $this
            ->withDefault(
                (new Response())
                    ->generateActions($controller)
            )
            ->generate();
    }

    public function generateDestroy(Controller $controller): Responses
    {
        return $this
            ->withDefault(
                (new Response())
                    ->generateDestroy($controller)
            )
            ->generate();
    }

    public function generateRestore(Controller $controller): Responses
    {
        return $this
            ->withDefault(
                (new Response())
                    ->generateRestore($controller)
            )
            ->generate();
    }

    public function generateForceDelete(Controller $controller): Responses
    {
        return $this
            ->withDefault(
                (new Response())
                    ->generateForceDelete($controller)
            )
            ->generate();
    }
}
