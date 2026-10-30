<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Support\Arr;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Relations\Relation;

final readonly class RelationMutationType
{
    public function __construct(private Relation $relation) {}

    public function toType(): Type
    {
        $entry = new ObjectType;
        $entry->addProperty('operation', (new StringType)->enum($this->operations()));
        $entry->addProperty('key', (new MixedType)->setDescription('Integer for single operations; array of integers for bulk operations'));
        $entry->addProperty('attributes', (new ObjectType)->setDescription('Fields of the related resource'));

        if ($this->relation instanceof PivotRelation) {
            $entry->addProperty('pivot', $this->pivot($this->relation));
            $entry->addProperty('without_detaching', (new BooleanType)->setDescription('When true, existing relations are kept during sync'));
        }

        $description = 'Type: '.class_basename($this->relation);

        if (! $this->relation->hasMultipleEntries()) {
            return $entry->setDescription($description);
        }

        $entries = new ArrayType;
        $entries->setItems($entry);

        return $entries->setDescription($description);
    }

    /** @return list<string> */
    private function operations(): array
    {
        return array_map(fn (MutationOperation $operation): string => $operation->value, $this->relation->mutationOperations());
    }

    private function pivot(PivotRelation $relation): ObjectType
    {
        $grouped = [];

        foreach ($this->relation->mutationOperations() as $operation) {
            $grouped[$operation->pivotWriteOperation()->value ?? ''][] = $operation->value;
        }

        $operations = [];

        foreach ($grouped as $write => $names) {
            $operations[Arr::join($names, ', ', ' and ')] = WriteOperation::tryFrom($write);
        }

        return WritableFieldsType::of($relation->getPivotFields(), $operations)->toType();
    }
}
