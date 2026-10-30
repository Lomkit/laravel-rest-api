<?php

namespace Lomkit\Rest\Rules\Mutate;

use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Fields\WriteRules;
use Lomkit\Rest\Rules\RestRule;

final class MutatePivot extends RestRule
{
    public function __construct(private readonly PivotRelation $relation) {}

    /** @return array<string, list<mixed>> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $write = MutationOperation::tryFromEntry($value)?->pivotWriteOperation();
        $rules = [$attribute.'.pivot' => ['array', ...self::prohibitions($attribute)]];

        if ($write === null || ! is_array($value) || ! is_array($value['pivot'] ?? [])) {
            return $rules;
        }

        $fields = $this->relation->getPivotFields();
        $rules[$attribute.'.pivot'][] = new WritableAttributes($fields, $write);

        return [...$rules, ...WriteRules::of($fields, $write)->under($attribute.'.pivot.')];
    }

    /** @return list<string> */
    private static function prohibitions(string $attribute): array
    {
        $prohibited = array_filter(
            MutationOperation::cases(),
            fn (MutationOperation $operation): bool => $operation->pivotWriteOperation() === null,
        );

        return array_values(array_map(
            fn (MutationOperation $operation): string => 'prohibited_if:'.$attribute.'.operation,'.$operation->value,
            $prohibited,
        ));
    }
}
