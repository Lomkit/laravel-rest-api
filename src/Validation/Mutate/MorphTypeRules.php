<?php

namespace Lomkit\Rest\Validation\Mutate;

use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Fields\WriteRules;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Rules\OneOf;

final readonly class MorphTypeRules
{
    /** @param array<string, OneOf> $columns */
    private function __construct(private array $columns) {}

    public static function of(Resource $resource, WriteOperation $operation): self
    {
        $writable = $resource->getFields()->writableOn($operation);
        $columns = [];

        foreach ($resource->getRelations() as $relation) {
            if (! $relation instanceof PolymorphicRelation) {
                continue;
            }

            $column = $relation->columns()->typeColumn;

            if ($writable->has($column)) {
                $columns[$column] = new OneOf($relation->targets()->types());
            }
        }

        return new self($columns);
    }

    /**
     * @param  array<array-key, mixed>  $rules
     * @return array<array-key, mixed>
     */
    public function addTo(array $rules): array
    {
        foreach ($this->columns as $column => $rule) {
            $rules[$column] = [...WriteRules::listOf($rules[$column] ?? []), $rule];
        }

        return $rules;
    }
}
