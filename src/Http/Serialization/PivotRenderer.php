<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Relations\Relation;
use stdClass;

final readonly class PivotRenderer
{
    private function __construct(private ?Relation $relation) {}

    public static function of(?Relation $relation): self
    {
        return new self($relation);
    }

    public function render(Pivot $pivot): stdClass
    {
        $fields = $this->relation instanceof PivotRelation ? $this->relation->getPivotFields() : FieldSet::of([]);

        return (object) array_intersect_key($fields->fromStorage($pivot->toArray()), array_flip($fields->selectable()->names()));
    }
}
