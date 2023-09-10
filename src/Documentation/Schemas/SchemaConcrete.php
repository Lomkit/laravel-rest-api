<?php

namespace Lomkit\Rest\Documentation\Schemas;

class SchemaConcrete extends Schema
{
    protected string $type;

    public function jsonSerialize(): mixed
    {
        return [
            'type' => $this->type(),
        ];
    }

    public function generate(): SchemaConcrete
    {
        return $this;
    }

    public function withType(string $type): SchemaConcrete
    {
        $this->type = $type;

        return $this;
    }

    public function type(): string
    {
        return $this->type;
    }
}
