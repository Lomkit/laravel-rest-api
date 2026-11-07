<?php

namespace Lomkit\Rest\Mutations;

use Lomkit\Rest\Fields\MutationOperation;

final readonly class MutationNode
{
    /**
     * @param  int|string|list<int|string>|null  $key
     * @param  array<array-key, mixed>|null  $attributes
     * @param  array<array-key, mixed>|null  $pivot
     * @param  array<string, MutationNode|list<MutationNode>>|null  $relations
     */
    public function __construct(
        public MutationOperation $operation,
        public int|string|array|null $key,
        public ?array $attributes,
        public ?array $pivot,
        public ?bool $withoutDetaching,
        public ?array $relations,
        public int $depth,
        public ?string $type = null,
        public string $path = '',
    ) {}

    public function context(): MutationContext
    {
        return new MutationContext($this->operation, is_array($this->key) ? null : $this->key, $this->depth);
    }
}
