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
    ) {}

    public function context(): MutationContext
    {
        return new MutationContext($this->operation, is_array($this->key) ? null : $this->key, $this->depth);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $entry = [
            'operation' => $this->operation->value,
            'type' => $this->type,
            'key' => $this->key,
            'attributes' => $this->attributes,
            'pivot' => $this->pivot,
            'without_detaching' => $this->withoutDetaching,
            'relations' => $this->relations === null ? null : array_map(self::relationToArray(...), $this->relations),
        ];

        return array_filter($entry, fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  MutationNode|list<MutationNode>  $relation
     * @return array<array-key, mixed>
     */
    private static function relationToArray(self|array $relation): array
    {
        if ($relation instanceof self) {
            return $relation->toArray();
        }

        return array_map(fn (self $node): array => $node->toArray(), $relation);
    }
}
