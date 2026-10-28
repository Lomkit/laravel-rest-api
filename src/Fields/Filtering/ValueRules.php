<?php

namespace Lomkit\Rest\Fields\Filtering;

final readonly class ValueRules
{
    /**
     * @param  list<mixed>  $value
     * @param  list<mixed>  $items
     */
    public function __construct(
        public array $value,
        public array $items = [],
    ) {}

    /** @return array<string, list<mixed>> */
    public function for(string $attribute): array
    {
        if ($this->items === []) {
            return [$attribute => $this->value];
        }

        return [$attribute => $this->value, $attribute.'.*' => $this->items];
    }
}
