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

    /**
     * Set the default response.
     *
     * @param Response $default
     *
     * @return Responses
     *
     * This method allows setting the default response for undeclared responses in documentation.
     */
    public function withDefault(Response $default): Responses
    {
        $this->default = $default;

        return $this;
    }

    /**
     * Get the default response.
     *
     * @return Response
     *
     * This method retrieves the default response for undeclared responses in documentation.
     */
    public function default(): Response
    {
        return $this->default;
    }

    /**
     * Add other responses to the collection.
     *
     * @param array $others
     *
     * @return Responses
     *
     * This method allows adding other responses to the collection, typically responses for specific
     * HTTP response codes.
     */
    public function withOthers(array $others): Responses
    {
        $this->others = array_merge($this->others, $others);

        return $this;
    }

    /**
     * Get the other responses in the collection.
     *
     * @return array
     *
     * This method retrieves the other responses in the collection, typically responses for specific
     * HTTP response codes.
     */
    public function others(): array
    {
        return $this->others;
    }

    /**
     * Serialize the responses to JSON.
     *
     * @return mixed
     *
     * This method serializes the responses to a JSON format, including the default response and other responses
     * for specific HTTP response codes.
     */
    public function jsonSerialize(): mixed
    {
        return array_merge(
            ['default' => $this->default()->jsonSerialize()],
            collect($this->others())->map->jsonSerialize()->toArray()
        );
    }

    /**
     * Generate the responses.
     *
     * @return Responses
     *
     * This method returns the responses itself as no additional generation or processing is needed.
     */
    public function generate(): Responses
    {
        return $this;
    }

    /**
     * Generate detailed responses for a controller.
     *
     * @param Controller $controller
     *
     * @return Responses
     *
     * This method generates detailed responses for a controller, typically for actions that retrieve detailed
     * information about a resource.
     */
    public function generateDetail(Controller $controller): Responses
    {
        return $this
            ->withDefault(
                (new Response())
                    ->generateDetail($controller)
            )
            ->generate();
    }

    /**
     * Generate search responses for a controller.
     *
     * @param Controller $controller
     *
     * @return Responses
     *
     * This method generates search responses for a controller, typically for actions that retrieve lists
     * of resources based on search criteria.
     */
    public function generateSearch(Controller $controller): Responses
    {
        return $this
            ->withDefault(
                (new Response())
                    ->generateSearch($controller)
            )
            ->generate();
    }

    /**
     * Generate mutate responses for a controller.
     *
     * @param Controller $controller
     *
     * @return Responses
     *
     * This method generates mutate responses for a controller, typically for actions that create, update,
     * or delete resources.
     */
    public function generateMutate(Controller $controller): Responses
    {
        return $this
            ->withDefault(
                (new Response())
                    ->generateMutate($controller)
            )
            ->generate();
    }

    /**
     * Generate action responses for a controller.
     *
     * @param Controller $controller
     *
     * @return Responses
     *
     * This method generates action responses for a controller, typically for custom actions defined on a resource.
     */
    public function generateActions(Controller $controller): Responses
    {
        return $this
            ->withDefault(
                (new Response())
                    ->generateActions($controller)
            )
            ->generate();
    }

    /**
     * Generate destroy responses for a controller.
     *
     * @param Controller $controller
     *
     * @return Responses
     *
     * This method generates destroy responses for a controller, typically for actions that delete resources.
     */
    public function generateDestroy(Controller $controller): Responses
    {
        return $this
            ->withDefault(
                (new Response())
                    ->generateDestroy($controller)
            )
            ->generate();
    }

    /**
     * Generate restore responses for a controller.
     *
     * @param Controller $controller
     *
     * @return Responses
     *
     * This method generates restore responses for a controller, typically for actions that restore deleted resources.
     */
    public function generateRestore(Controller $controller): Responses
    {
        return $this
            ->withDefault(
                (new Response())
                    ->generateRestore($controller)
            )
            ->generate();
    }

    /**
     * Generate force delete responses for a controller.
     *
     * @param Controller $controller
     *
     * @return Responses
     *
     * This method generates force delete responses for a controller, typically for actions that permanently
     * delete resources.
     */
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
