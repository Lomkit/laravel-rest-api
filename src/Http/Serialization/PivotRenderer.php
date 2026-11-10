<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Relations\Relation;
use stdClass;

final readonly class PivotRenderer
{
    /** @param list<string> $names */
    private function __construct(
        private FieldSet $fields,
        private array $names,
        private FieldCasts $casts,
    ) {}

    public static function of(?Relation $relation): self
    {
        $fields = $relation instanceof PivotRelation ? $relation->getPivotFields() : FieldSet::of([]);
        $names = $fields->selectable()->names();

        return new self($fields, $names, FieldCasts::of($fields, $names));
    }

    public function render(Pivot $pivot): stdClass
    {
        $values = $this->casts
            ->missingOn($pivot)
            ->appliedTo(clone $pivot)
            ->toArray();

        return (object) array_intersect_key($this->fields->render($values), array_flip($this->names));
    }
}
