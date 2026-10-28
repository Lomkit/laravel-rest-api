<?php

namespace Lomkit\Rest\Documentation\Schemas;

class Example extends Schema
{
    /**
     * Value.
     *
     * @var string
     */
    protected array $value;

    /**
     * Serialize the example as an array.
     */
    public function jsonSerialize(): mixed
    {
        return $this->value();
    }

    /**
     * Generate the example.
     */
    public function generate(): Example
    {
        return $this;
    }

    /**
     * Set the value associated with this example.
     */
    public function withValue(array $value): Example
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get the value associated with this example.
     */
    public function value(): array
    {
        return $this->value;
    }
}
