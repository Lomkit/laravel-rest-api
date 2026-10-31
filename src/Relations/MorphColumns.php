<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Relations\MorphTo as EloquentMorphTo;
use JsonSerializable;

final readonly class MorphColumns implements JsonSerializable
{
    private function __construct(public string $typeColumn, public string $keyColumn) {}

    /** @param EloquentMorphTo<*, *> $relation */
    public static function of(EloquentMorphTo $relation): self
    {
        return new self($relation->getMorphType(), $relation->getForeignKeyName());
    }

    /** @return array{type_column: string, key_column: string} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return ['type_column' => $this->typeColumn, 'key_column' => $this->keyColumn];
    }
}
