<?php

namespace Lomkit\Rest\Documentation\Schemas;

class Path extends Schema
{

    /**
     * An optional, string summary, intended to apply to all operations in this path.
     * @var string
     */
    protected string $summary;

    /**
     * An optional, string description, intended to apply to all operations in this path. CommonMark syntax MAY be used for rich text representation.
     * @var string
     */
    protected string $description;

    /**
     * A definition of a GET operation on this path.
     * @var Operation
     */
    protected Operation $get;

    /**
     * A definition of a PUT operation on this path.
     * @var Operation
     */
    protected Operation $put;

    /**
     * A definition of a POST operation on this path.
     * @var Operation
     */
    protected Operation $post;

    /**
     * A definition of a DELETE operation on this path.
     * @var Operation
     */
    protected Operation $delete;

    /**
     * A definition of a OPTIONS operation on this path.
     * @var Operation
     */
    protected Operation $options;

    /**
     * A definition of a HEAD operation on this path.
     * @var Operation
     */
    protected Operation $head;

    /**
     * A definition of a PATCH operation on this path.
     * @var Operation
     */
    protected Operation $patch;

    /**
     * A definition of a TRACE operation on this path.
     * @var Operation
     */
    protected Operation $trace;

    /**
     * A list of parameters that are applicable for all the operations described under this path.
     * @var array
     */
    protected array $parameters = [];

    public function withSummary(string $summary): Path
    {
        $this->summary = $summary;
        return $this;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function withDescription(string $description): Path
    {
        $this->description = $description;
        return $this;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function withGet(Operation $get): Path
    {
        $this->get = $get;
        return $this;
    }

    public function get(): Operation
    {
        return $this->get;
    }

    public function withPut(Operation $put): Path
    {
        $this->put = $put;
        return $this;
    }

    public function put(): Operation
    {
        return $this->put;
    }

    public function withPost(Operation $post): Path
    {
        $this->post = $post;
        return $this;
    }

    public function post(): Operation
    {
        return $this->post;
    }

    public function withDelete(Operation $delete): Path
    {
        $this->delete = $delete;
        return $this;
    }

    public function delete(): Operation
    {
        return $this->delete;
    }

    public function withOptions(Operation $options): Path
    {
        $this->options = $options;
        return $this;
    }

    public function options(): Operation
    {
        return $this->options;
    }

    public function withHead(Operation $head): Path
    {
        $this->head = $head;
        return $this;
    }

    public function head(): Operation
    {
        return $this->head;
    }

    public function withPatch(Operation $patch): Path
    {
        $this->patch = $patch;
        return $this;
    }

    public function patch(): Operation
    {
        return $this->patch;
    }

    public function withTrace(Operation $trace): Path
    {
        $this->trace = $trace;
        return $this;
    }

    public function trace(): Operation
    {
        return $this->trace;
    }

    public function withParameters(array $parameters): Path
    {
        $this->parameters = array_merge($parameters, $this->parameters);
        return $this;
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'summary' => $this->summary(),
            'description' => $this->description(),
            'get' => $this->get()->jsonSerialize(),
            'put' => $this->put()->jsonSerialize(),
            'post' => $this->post()->jsonSerialize(),
            'delete' => $this->delete()->jsonSerialize(),
            'options' => $this->options()->jsonSerialize(),
            'head' => $this->head()->jsonSerialize(),
            'patch' => $this->patch()->jsonSerialize(),
            'trace' => $this->trace()->jsonSerialize(),
            'parameters' => $this->parameters()
        ];
    }
}