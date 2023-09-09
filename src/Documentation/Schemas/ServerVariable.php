<?php

namespace Lomkit\Rest\Documentation\Schemas;

class ServerVariable extends Schema
{
    /**
     * An enumeration of string values to be used if the substitution options are from a limited set.
     * @var array
     */
    protected array $enum = [];

    /**
     * The default value to use for substitution, which SHALL be sent if an alternate value is not supplied.
     * @var string
     */
    protected string $default;

    /**
     * An optional description for the server variable. CommonMark syntax MAY be used for rich text representation.
     * @var string
     */
    protected string $description;

    /**
     * Set the enum values for the server variable.
     *
     * @param array $enum
     * @return ServerVariable
     *
     * This method allows setting an enumeration of string values to be used if the substitution
     * options for the server variable are from a limited set.
     */
    public function withEnum(array $enum): ServerVariable
    {
        $this->enum = $enum;
        return $this;
    }

    /**
     * Get the enum values of the server variable.
     *
     * @return array
     *
     * This method retrieves the enum values of the server variable, if any.
     */
    public function enum(): array
    {
        return $this->enum;
    }

    /**
     * Set the default value for the server variable.
     *
     * @param string $default
     * @return ServerVariable
     *
     * This method allows setting the default value to use for substitution if an alternate
     * value is not supplied.
     */
    public function withDefault(string $default): ServerVariable
    {
        $this->default = $default;
        return $this;
    }

    /**
     * Get the default value of the server variable.
     *
     * @return string
     *
     * This method retrieves the default value of the server variable.
     */
    public function default(): string
    {
        return $this->default;
    }

    /**
     * Set the description for the server variable.
     *
     * @param string $description
     * @return ServerVariable
     *
     * This method allows setting an optional description for the server variable. CommonMark syntax
     * MAY be used for rich text representation.
     */
    public function withDescription(string $description): ServerVariable
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the description of the server variable.
     *
     * @return string
     *
     * This method retrieves the description of the server variable, if any.
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Serialize the server variable to JSON.
     *
     * @return mixed
     *
     * This method serializes the server variable to a JSON format, including its enum values,
     * default value, and description.
     */
    public function jsonSerialize(): mixed
    {
        return [
            'enum' => $this->enum(),
            'default' => $this->default(),
            'description' => $this->description()
        ];
    }

    /**
     * Generate the server variable.
     *
     * @return ServerVariable
     *
     * This method returns the server variable itself as no additional generation or processing is needed.
     */
    public function generate(): ServerVariable
    {
        return $this;
    }
}