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
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return $this->value();
    }

    /**
     * Generate the example.
     *
     * @return Example
     */
    public function generate(): Example
    {
        return $this;
    }

    /**
     * Set the value associated with this example.
     *
     * @param array $value
     *
     * @return Example
     */
    public function withValue(array $value): Example
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get the value associated with this example.
     *
     * @return array
     */
    public function value(): array
    {
        return $this->value;
    }
}
