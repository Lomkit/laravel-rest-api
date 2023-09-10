<?php

namespace Lomkit\Rest\Documentation\Schemas;

class ServerVariable extends Schema
{
    /**
     * An enumeration of string values to be used if the substitution options are from a limited set.
     *
     * @var array
     */
    protected array $enum = [];

    /**
     * The default value to use for substitution, which SHALL be sent if an alternate value is not supplied.
     *
     * @var string
     */
    protected string $default;

    /**
     * An optional description for the server variable. CommonMark syntax MAY be used for rich text representation.
     *
     * @var string
     */
    protected string $description;

    public function withEnum(array $enum): ServerVariable
    {
        $this->enum = $enum;

        return $this;
    }

    public function enum(): array
    {
        return $this->enum;
    }

    public function withDefault(string $default): ServerVariable
    {
        $this->default = $default;

        return $this;
    }

    public function default(): string
    {
        return $this->default;
    }

    public function withDescription(string $description): ServerVariable
    {
        $this->description = $description;

        return $this;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'enum'        => $this->enum(),
            'default'     => $this->default(),
            'description' => $this->description(),
        ];
    }

    public function generate(): ServerVariable
    {
        return $this;
    }
}
