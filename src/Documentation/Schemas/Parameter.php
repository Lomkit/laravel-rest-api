<?php

namespace Lomkit\Rest\Documentation\Schemas;

class Parameter extends Schema
{
    /**
     * The name of the parameter.
     *
     * @var string
     */
    protected string $name;

    /**
     * The location of the parameter. Possible values are "query", "header", "path" or "cookie".
     *
     * @var string
     */
    protected string $in;

    /**
     * A brief description of the parameter. This could contain examples of use.
     * CommonMark syntax MAY be used for rich text representation.
     *
     * @var string
     */
    protected string $description;

    /**
     * Determines whether this parameter is mandatory. If the parameter location is "path", this property is REQUIRED and its value MUST be true.
     * Otherwise, the property MAY be included and its default value is false.
     *
     * @var bool
     */
    protected bool $required;

    /**
     * Specifies that a parameter is deprecated and SHOULD be transitioned out of usage. Default value is false.
     *
     * @var bool
     */
    protected bool $deprecated;

    /**
     * Schema for the parameter.
     *
     * @var SchemaConcrete
     */
    protected SchemaConcrete $schema;

    /**
     * Set the name of the parameter.
     *
     * @param string $name
     *
     * @return Parameter
     */
    public function withName(string $name): Parameter
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the name of the parameter.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Set the location of the parameter.
     *
     * @param string $in
     *
     * @return Parameter
     */
    public function withIn(string $in): Parameter
    {
        $this->in = $in;

        return $this;
    }

    /**
     * Get the location of the parameter.
     *
     * @return string
     */
    public function in(): string
    {
        return $this->in;
    }

    /**
     * Set the description of the parameter.
     *
     * @param string $description
     *
     * @return Parameter
     */
    public function withDescription(string $description): Parameter
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get the description of the parameter.
     *
     * @return string
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Set whether the parameter is required.
     *
     * @param bool $required
     *
     * @return Parameter
     */
    public function withRequired(bool $required = true): Parameter
    {
        $this->required = $required;

        return $this;
    }

    /**
     * Check if the parameter is required.
     *
     * @return bool
     */
    public function required(): bool
    {
        return $this->required;
    }

    /**
     * Set whether the parameter is deprecated.
     *
     * @param bool $deprecated
     *
     * @return Parameter
     */
    public function withDeprecated(bool $deprecated): Parameter
    {
        $this->deprecated = $deprecated;

        return $this;
    }

    /**
     * Check if the parameter is deprecated.
     *
     * @return bool
     */
    public function deprecated(): bool
    {
        return $this->deprecated;
    }

    /**
     * Serialize the Parameter instance to an array for JSON serialization.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return array_merge(
            isset($this->name) ? ['name' => $this->name()] : [],
            isset($this->in) ? ['in' => $this->in()] : [],
            isset($this->description) ? ['description' => $this->description()] : [],
            isset($this->required) ? ['required' => $this->required()] : [],
            isset($this->deprecated) ? ['deprecated' => $this->deprecated()] : [],
            isset($this->schema) ? ['schema' => $this->schema()] : [],
        );
    }

    /**
     * Generate a Parameter schema.
     *
     * @return Schema
     */
    public function generate(): Schema
    {
        return $this;
    }

    /**
     * Set the schema for the parameter.
     *
     * @param SchemaConcrete $schema
     *
     * @return Parameter
     */
    public function withSchema(SchemaConcrete $schema): Parameter
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * Get the schema for the parameter.
     *
     * @return SchemaConcrete
     */
    public function schema(): SchemaConcrete
    {
        return $this->schema;
    }
}
