<?php

namespace Lomkit\Rest\Scramble\Components;

use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Totals\SqlTotalsAccess;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Search\Totals\MeasureType;

/** @internal */
final readonly class TotalsResultSchema
{
    public function __construct(
        private ComponentRegistry $components,
        private RecordSchema $records,
    ) {}

    public function reference(Resource $resource): ?Reference
    {
        [$groups, $measures] = self::fields($resource);

        if ($groups === [] && $measures === []) {
            return null;
        }

        $name = $this->records->name($resource);

        return $this->components->schema($name.'Totals', $resource::class, fn (): Type => Schemas::required([
            'exact' => (new BooleanType)->setDescription('True when every figure counts every matched row; always true, since a search that cannot count exactly answers 422'),
            'all' => $this->all($measures),
            'groups' => $groups === [] ? self::none() : Schemas::list($this->components->schema($name.'TotalsGroup', $resource::class, fn (): Type => $this->group($groups)), 'One entry per group asked for, in the order of the request'),
        ], [], 'Present when the search asks for totals. The buckets of a group plus its other count add up to all.count, which equals total.'));
    }

    /** @return array{array<string, Field>, array<string, Field>} */
    private static function fields(Resource $resource): array
    {
        $fields = $resource->definition()->declaredFields();
        $sql = SqlTotalsAccess::documented($resource);
        [$groups, $measures] = $sql ? [$fields->groupable()->keyed(), $fields->measurable()->keyed()] : [[], []];
        $engine = $resource->context()->text($resource)->capabilities?->engine;

        if ($engine === null) {
            return [$groups, $measures];
        }

        foreach ($engine->totals->groups ?? [] as $name) {
            $groups[$name] ??= $engine->filters[$name]->field;
        }

        foreach (array_keys($engine->totals->measures ?? []) as $name) {
            $measures[$name] ??= $engine->filters[$name]->field;
        }

        return [$groups, $measures];
    }

    /** @param array<string, Field> $measures */
    private function all(array $measures): ObjectType
    {
        $properties = ['count' => new IntegerType];

        foreach ($measures as $name => $field) {
            foreach (MeasureType::cases() as $type) {
                $number = $type !== MeasureType::Avg && $field instanceof Integer ? new IntegerType : new NumberType;
                $properties["{$type->value}_{$name}"] = $type === MeasureType::Sum ? $number : $number->nullable(true);
            }
        }

        return Schemas::required($properties, array_values(array_diff(array_keys($properties), ['count'])), 'count, then each measure asked for under <type>_<field>');
    }

    /** @param array<string, Field> $groups */
    private function group(array $groups): ObjectType
    {
        $values = [];

        foreach ($groups as $field) {
            $schema = $field->toSchema();
            $values[json_encode($schema->toArray(), JSON_THROW_ON_ERROR)] = $schema;
        }

        $value = new AnyOf;
        $value->setItems(array_values($values));
        $bucket = Schemas::required([
            'value' => count($values) === 1 ? array_values($values)[0] : $value,
            'count' => new IntegerType,
        ]);

        return Schemas::required([
            'field' => (new StringType)->enum(array_keys($groups)),
            'buckets' => Schemas::list($bucket, 'By count, descending, then by value'),
            'other' => Schemas::required(['count' => new IntegerType], [], 'The matched rows outside the buckets: other values and rows without a value'),
            'truncated' => (new BooleanType)->setDescription('True when at least one other value exists outside the buckets'),
        ]);
    }

    private static function none(): ArrayType
    {
        $list = new ArrayType;
        $list->setMax(0);

        return $list;
    }
}
