<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;

final readonly class PlannedKeys
{
    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, Model>  $targets
     */
    public function __construct(
        public array $values = [],
        public array $targets = [],
    ) {}

    public function with(self $keys): self
    {
        return new self([...$this->values, ...$keys->values], [...$this->targets, ...$keys->targets]);
    }
}
