<?php

namespace Lomkit\Rest\Documentation\Schemas;

class SchemaConcrete extends Schema
{
    protected string $type;

    /**
     * Serialize the schema to JSON.
     *
     * @return mixed
     *
     * This method serializes the schema to a JSON format, including its data type.
     */
    public function jsonSerialize(): mixed
    {
        return [
            'type' => $this->type()
        ];
    }

    /**
     * Generate the schema.
     *
     * @return SchemaConcrete
     *
     * This method returns the schema itself as no additional generation or processing is needed.
     */
    public function generate(): SchemaConcrete
    {
        return $this;
    }

    /**
     * Set the data type for the schema.
     *
     * @param string $type
     * @return SchemaConcrete
     *
     * This method allows setting the data type for the schema, such as "string", "integer", etc.
     */
    public function withType(string $type): SchemaConcrete
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get the data type of the schema.
     *
     * @return string
     *
     * This method retrieves the data type of the schema.
     */
    public function type(): string
    {
        return $this->type;
    }
}