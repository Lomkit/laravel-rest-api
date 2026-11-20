<?php

namespace Lomkit\Rest\Scramble\Search;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Totals\SqlTotalsAccess;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Components\RecordSchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Types\NonEmptyObject;
use Lomkit\Rest\Scramble\Types\Nothing;
use Lomkit\Rest\Search\Totals\MeasureType;
use Lomkit\Rest\Text\Engine\EngineGrammar;

/** @internal */
final readonly class TotalsSchema
{
    public function __construct(
        private ComponentRegistry $components,
        private RecordSchema $records,
    ) {}

    public function database(Resource $resource): ?Reference
    {
        if (! SqlTotalsAccess::documented($resource)) {
            return null;
        }

        $fields = $resource->definition()->declaredFields();

        return $this->request($resource, 'SearchTotals', $fields->groupable()->names(), $fields->measurable()->names(), 'The totals of every row that the search matches, returned under totals at the root of the page: count always, the measures and the groups asked for. Send measures, groups or both.');
    }

    public function engine(Resource $resource, EngineGrammar $engine): ?Reference
    {
        $totals = $engine->totals;

        if ($totals === null) {
            return null;
        }

        $types = array_values(array_unique(array_map(static fn (MeasureType $type): string => $type->value, array_merge([], ...array_values($totals->measures)))));

        return $this->request($resource, 'EngineSearchTotals', $totals->groups, array_keys($totals->measures), 'The totals of every row that the search engine matches, returned under totals at the root of the page: count always, the measures and the groups asked for. Send measures, groups or both.', $types);
    }

    /**
     * @param  list<string>  $groups
     * @param  list<string>  $measures
     * @param  list<string>|null  $types
     */
    public function request(Resource $resource, string $suffix, array $groups, array $measures, string $description, ?array $types = null): ?Reference
    {
        if ($groups === [] && $measures === []) {
            return null;
        }

        return $this->components->schema($this->records->name($resource).$suffix, $resource::class, function () use ($resource, $groups, $measures, $description, $types): Type {
            $totals = new NonEmptyObject;

            foreach (array_filter(['measures' => $this->measures($resource, $measures, $types ?? MeasureType::values()), 'groups' => $this->groups($resource, $groups)]) as $name => $list) {
                $totals->addProperty($name, $list);
            }

            $totals->setDescription($description);
            $totals->additionalProperties(new Nothing);

            return $totals;
        });
    }

    /**
     * @param  list<string>  $measures
     * @param  list<string>  $types
     */
    private function measures(Resource $resource, array $measures, array $types): ?ArrayType
    {
        if ($measures === []) {
            return null;
        }

        $measure = Schemas::closed([
            'type' => (new StringType)->enum($types),
            'field' => (new StringType)->enum($measures),
        ], ['type', 'field'], 'A measure over every matched row, returned in totals.all under <type>_<field>. sum ignores null values and is 0 without rows; avg, min and max ignore null values and are null without rows.');

        return self::capped($measure, $resource->context()->limits()->sizes->measures, 'Distinct measures');
    }

    /** @param list<string> $groups */
    private function groups(Resource $resource, array $groups): ?ArrayType
    {
        if ($groups === []) {
            return null;
        }

        $max = $resource->context()->limits()->sizes->groups;
        $limit = new IntegerType;
        $limit->setMin(1);
        $limit->setMax($max);
        $limit->setDescription("The most values returned, {$max} by default");

        $group = Schemas::closed([
            'field' => (new StringType)->enum($groups),
            'limit' => $limit,
        ], ['field'], 'Counts the matched rows per value of one field, independently of the other groups. The values come by count, descending, then by value; the rows outside them, other values and rows without a value, are counted in other.');

        return self::capped($group, $resource->context()->limits()->sizes->totalsGroups, 'Groups on distinct fields');
    }

    private static function capped(ObjectType $items, int $max, string $description): ArrayType
    {
        $list = Schemas::list($items, $description);
        $list->setMax($max);

        return $list;
    }
}
