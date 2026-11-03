<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Relations\Relation;

final readonly class PivotRenderer
{
    private function __construct(private ?Relation $relation) {}

    public static function of(?Relation $relation): self
    {
        return new self($relation);
    }

    /** @return array<string, mixed> */
    public function render(Pivot $pivot): array
    {
        $fields = $this->relation instanceof PivotRelation ? $this->relation->getPivotFields() : FieldSet::of([]);

        return array_intersect_key($fields->fromStorage($pivot->toArray()), array_flip($fields->selectable()->names()));
    }
}
