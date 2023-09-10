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

    public function jsonSerialize(): mixed
    {
        return $this->value();
    }

    public function generate(): Example
    {
        return $this;
    }

    public function withValue(array $value): Example
    {
        $this->value = $value;

        return $this;
    }

    public function value(): array
    {
        return $this->value;
    }
}
