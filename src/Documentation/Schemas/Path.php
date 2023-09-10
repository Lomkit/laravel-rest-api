<?php

namespace Lomkit\Rest\Documentation\Schemas;

use Lomkit\Rest\Http\Controllers\Controller;

class Path extends Schema
{
    /**
     * An optional, string summary, intended to apply to all operations in this path.
     *
     * @var string
     */
    protected string $summary;

    /**
     * An optional, string description, intended to apply to all operations in this path. CommonMark syntax MAY be used for rich text representation.
     *
     * @var string
     */
    protected string $description;

    /**
     * A definition of a GET operation on this path.
     *
     * @var Operation
     */
    protected Operation $get;

    /**
     * A definition of a PUT operation on this path.
     *
     * @var Operation
     */
    protected Operation $put;

    /**
     * A definition of a POST operation on this path.
     *
     * @var Operation
     */
    protected Operation $post;

    /**
     * A definition of a DELETE operation on this path.
     *
     * @var Operation
     */
    protected Operation $delete;

    /**
     * A definition of a OPTIONS operation on this path.
     *
     * @var Operation
     */
    protected Operation $options;

    /**
     * A definition of a HEAD operation on this path.
     *
     * @var Operation
     */
    protected Operation $head;

    /**
     * A definition of a PATCH operation on this path.
     *
     * @var Operation
     */
    protected Operation $patch;

    /**
     * A definition of a TRACE operation on this path.
     *
     * @var Operation
     */
    protected Operation $trace;

    /**
     * A list of parameters that are applicable for all the operations described under this path.
     *
     * @var array
     */
    protected array $parameters = [];

    /**
     * Set the summary for this path.
     *
     * @param string $summary
     *
     * @return Path
     */
    public function withSummary(string $summary): Path
    {
        $this->summary = $summary;

        return $this;
    }

    /**
     * Get the summary for this path.
     *
     * @return string
     */
    public function summary(): string
    {
        return $this->summary;
    }

    /**
     * Set the description for this path.
     *
     * @param string $description
     *
     * @return Path
     */
    public function withDescription(string $description): Path
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get the description for this path.
     *
     * @return string
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Set the GET operation for this path.
     *
     * @param Operation $get The GET operation to set.
     *
     * @return Path
     */
    public function withGet(Operation $get): Path
    {
        $this->get = $get;

        return $this;
    }

    /**
     * Get the GET operation for this path.
     *
     * @return Operation
     */
    public function get(): Operation
    {
        return $this->get;
    }

    /**
     * Set the PUT operation for this path.
     *
     * @param Operation $put The PUT operation to set.
     *
     * @return Path
     */
    public function withPut(Operation $put): Path
    {
        $this->put = $put;

        return $this;
    }

    /**
     * Get the PUT operation for this path.
     *
     * @return Operation
     */
    public function put(): Operation
    {
        return $this->put;
    }

    /**
     * Set the POST operation for this path.
     *
     * @param Operation $post The POST operation to set.
     *
     * @return Path
     */
    public function withPost(Operation $post): Path
    {
        $this->post = $post;

        return $this;
    }

    /**
     * Get the POST operation for this path.
     *
     * @return Operation
     */
    public function post(): Operation
    {
        return $this->post;
    }

    /**
     * Set the DELETE operation for this path.
     *
     * @param Operation $delete The DELETE operation to set.
     *
     * @return Path
     */
    public function withDelete(Operation $delete): Path
    {
        $this->delete = $delete;

        return $this;
    }

    /**
     * Get the DELETE operation for this path.
     *
     * @return Operation
     */
    public function delete(): Operation
    {
        return $this->delete;
    }

    /**
     * Set the OPTIONS operation for this path.
     *
     * @param Operation $options The OPTIONS operation to set.
     *
     * @return Path
     */
    public function withOptions(Operation $options): Path
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get the OPTIONS operation for this path.
     *
     * @return Operation
     */
    public function options(): Operation
    {
        return $this->options;
    }

    /**
     * Set the HEAD operation for this path.
     *
     * @param Operation $head The HEAD operation to set.
     *
     * @return Path
     */
    public function withHead(Operation $head): Path
    {
        $this->head = $head;

        return $this;
    }

    /**
     * Get the HEAD operation for this path.
     *
     * @return Operation
     */
    public function head(): Operation
    {
        return $this->head;
    }

    /**
     * Set the PATCH operation for this path.
     *
     * @param Operation $patch The PATCH operation to set.
     *
     * @return Path
     */
    public function withPatch(Operation $patch): Path
    {
        $this->patch = $patch;

        return $this;
    }

    /**
     * Get the PATCH operation for this path.
     *
     * @return Operation
     */
    public function patch(): Operation
    {
        return $this->patch;
    }

    /**
     * Set the TRACE operation for this path.
     *
     * @param Operation $trace The TRACE operation to set.
     *
     * @return Path
     */
    public function withTrace(Operation $trace): Path
    {
        $this->trace = $trace;

        return $this;
    }

    /**
     * Get the TRACE operation for this path.
     *
     * @return Operation
     */
    public function trace(): Operation
    {
        return $this->trace;
    }

    /**
     * Set the parameters applicable to all operations in this path.
     *
     * @param array $parameters An array of Parameter objects.
     *
     * @return Path
     */
    public function withParameters(array $parameters): Path
    {
        $this->parameters = array_merge($parameters, $this->parameters);

        return $this;
    }

    /**
     * Get the parameters applicable to all operations in this path.
     *
     * @return array
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * Serialize the Path instance to an array for JSON serialization.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return array_merge(
            isset($this->description) ? ['description' => $this->description()] : [],
            isset($this->summary) ? ['summary' => $this->summary()] : [],
            !empty($this->parameters) ? ['parameters' => collect($this->parameters())->map->jsonSerialize()] : [],
            isset($this->get) ? ['get' => $this->get()->jsonSerialize()] : [],
            isset($this->put) ? ['put' => $this->get()->jsonSerialize()] : [],
            isset($this->post) ? ['post' => $this->post()->jsonSerialize()] : [],
            isset($this->delete) ? ['delete' => $this->delete()->jsonSerialize()] : [],
            isset($this->options) ? ['options' => $this->options()->jsonSerialize()] : [],
            isset($this->head) ? ['head' => $this->head()->jsonSerialize()] : [],
            isset($this->patch) ? ['patch' => $this->patch()->jsonSerialize()] : [],
            isset($this->trace) ? ['trace' => $this->trace()->jsonSerialize()] : [],
        );
    }

    /**
     * Generate a Path schema.
     *
     * @return Path
     */
    public function generate(): Path
    {
        return $this;
    }

    /**
     * Generates a Path schema with operations for retrieving resource details and performing resource deletion.
     *
     * @param Controller $controller The controller instance used for generating documentation.
     *
     * @return Path
     */
    public function generateDetailAndDestroy(Controller $controller): Path
    {
        return $this
            ->withGet(
                (new Operation())
                    ->generateDetail($controller)
            )
            ->withDelete(
                (new Operation())
                    ->generateDestroy($controller)
            )
            ->generate();
    }

    /**
     * Generates a Path schema with an operation for searching resources.
     *
     * @param Controller $controller The controller instance used for generating documentation.
     *
     * @return Path
     */
    public function generateSearch(Controller $controller): Path
    {
        return $this
            ->withPost(
                (new Operation())
                    ->generateSearch($controller)
            )
            ->generate();
    }

    /**
     * Generates a Path schema with an operation for mutating resources.
     *
     * @param Controller $controller The controller instance used for generating documentation.
     *
     * @return Path
     */
    public function generateMutate(Controller $controller): Path
    {
        return $this
            ->withPost(
                (new Operation())
                    ->generateMutate($controller)
            )
            ->generate();
    }

    /**
     * Generates a Path schema with an operation for performing resource actions.
     *
     * @param Controller $controller The controller instance used for generating documentation.
     *
     * @return Path
     */
    public function generateActions(Controller $controller): Path
    {
        return $this
            ->withPost(
                (new Operation())
                    ->generateActions($controller)
            )
            ->withParameters(
                [
                    (new Parameter())
                        ->withName('action')
                        ->withDescription('The action uriKey')
                        ->withIn('path')
                        ->withSchema(
                            (new SchemaConcrete())
                                ->withType('string')
                                ->generate()
                        )
                        ->withRequired()
                        ->generate(),
                ]
            )
            ->generate();
    }

    /**
     * Generates a Path schema with an operation for restoring soft-deleted resources.
     *
     * @param Controller $controller The controller instance used for generating documentation.
     *
     * @return Path
     */
    public function generateRestore(Controller $controller): Path
    {
        return $this
            ->withPost(
                (new Operation())
                    ->generateRestore($controller)
            )
            ->generate();
    }

    /**
     * Generates a Path schema with an operation for performing force deletions of resources.
     *
     * @param Controller $controller The controller instance used for generating documentation.
     *
     * @return Path
     */
    public function generateForceDelete(Controller $controller): Path
    {
        return $this
            ->withDelete(
                (new Operation())
                    ->generateForceDelete($controller)
            )
            ->generate();
    }
}
