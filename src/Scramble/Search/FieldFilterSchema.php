<?php

namespace Lomkit\Rest\Scramble\Search;

use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Filtering\CaseInsensitivePattern;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\Filtering\Membership;
use Lomkit\Rest\Fields\Filtering\Nullity;
use Lomkit\Rest\Fields\Filtering\Pattern;
use Lomkit\Rest\Fields\Filtering\Range;
use Lomkit\Rest\Scramble\FieldSchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Types\OneOf;
use Lomkit\Rest\Validation\Limits;

final readonly class FieldFilterSchema
{
    public function __construct(
        private Limits $limits,
    ) {}

    public static function joint(): StringType
    {
        return (new StringType)->enum(['and', 'or']);
    }

    public function schema(string $path, Field $field): Type
    {
        $groups = [];

        foreach ($field->filterOperators() as $operator) {
            $groups[self::shape($operator)][] = $operator;
        }

        $branches = [];

        foreach ($groups as $shape => $operators) {
            $branches[] = $this->branch($path, $field, $shape, $operators);
        }

        return count($branches) === 1 ? $branches[0] : new OneOf($branches);
    }

    /** @param non-empty-list<FilterOperator> $operators */
    private function branch(string $path, Field $field, string $shape, array $operators): ObjectType
    {
        $value = $this->value($field, $shape);
        $properties = [
            'field' => (new StringType)->const($path),
            'operator' => (new StringType)->enum(array_map(fn (FilterOperator $operator): string => $operator->value, $operators)),
        ];

        if ($value !== null) {
            $properties['value'] = $value;
        }

        $properties['type'] = self::joint();
        $operator = in_array(FilterOperator::Equal, $operators, true) ? [] : ['operator'];

        return Schemas::closed($properties, ['field', ...$operator, ...($value === null ? [] : ['value'])]);
    }

    private function value(Field $field, string $shape): ?Type
    {
        return match ($shape) {
            'value' => FieldSchema::value($field),
            'pattern' => $this->pattern(),
            'list' => self::items(FieldSchema::value($field), 1, $this->limits->sizes->values),
            'pair' => self::items(FieldSchema::value($field), 2, 2),
            default => null,
        };
    }

    private function pattern(): StringType
    {
        $pattern = new StringType;
        $pattern->setMax($this->limits->lengths->patterns);

        return $pattern;
    }

    private static function items(Type $item, int $min, int $max): ArrayType
    {
        $items = Schemas::list($item);
        $items->setMin($min);
        $items->setMax($max);

        return $items;
    }

    private static function shape(FilterOperator $operator): string
    {
        return match (true) {
            $operator->condition() instanceof Pattern, $operator->condition() instanceof CaseInsensitivePattern => 'pattern',
            $operator->condition() instanceof Membership => 'list',
            $operator->condition() instanceof Range => 'pair',
            $operator->condition() instanceof Nullity => 'none',
            default => 'value',
        };
    }
}
