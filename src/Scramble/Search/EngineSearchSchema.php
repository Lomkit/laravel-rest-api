<?php

namespace Lomkit\Rest\Scramble\Search;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\RelevanceClause;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Components\RecordSchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Types\OneOf;
use Lomkit\Rest\Text\Engine\EngineGrammar;

/** @internal */
final readonly class EngineSearchSchema
{
    public function __construct(
        private ComponentRegistry $components,
        private RecordSchema $records,
    ) {}

    public static function lists(Resource $resource): ?EngineGrammar
    {
        $engine = $resource->context()->text($resource)->capabilities?->engine;

        return $engine?->servesLists() === true ? $engine : null;
    }

    public function filters(Resource $resource, EngineGrammar $engine): ?ArrayType
    {
        if ($engine->filters === []) {
            return null;
        }

        $name = $this->records->name($resource);
        $conditions = $this->conditions($resource, $engine, $name);
        $filter = ! $engine->groups ? $conditions : $this->components->schema($name.'EngineFilter', $resource::class, fn (): Type => (new OneOf([
            $conditions,
            $this->components->schema($name.'EngineFilterGroup', $resource::class, fn (): Type => $this->group($resource, $engine)),
        ]))->setDescription('A condition on an attribute of the search index, or a group of filters. `type` joins it to the previous filter: `and`, the default, or `or`.'));

        $list = Schemas::list($filter);
        $list->setMax($resource->context()->limits()->sizes->filters);

        return $list;
    }

    private function conditions(Resource $resource, EngineGrammar $engine, string $name): Reference
    {
        $joints = $engine->groups ? ['and', 'or'] : ['and'];
        $component = $engine->groups ? $name.'EngineFieldFilter' : $name.'EngineFilter';

        return $this->components->schema($component, $resource::class, function () use ($resource, $engine, $name, $joints): Type {
            $schema = new FieldFilterSchema($resource->context()->limits());
            $branches = [];

            foreach ($engine->filters as $attribute => $declared) {
                $branches[] = [$attribute, $name.'EngineFilter'.FilterPaths::component($attribute), fn (): Type => $schema->schema($attribute, $declared->field, $declared->operators, $joints)];
            }

            return $this->components->discriminated('field', $resource::class, $branches)
                ->setDescription('A condition on an attribute of the search index, for a search on the search engine: with source engine, with or without a text. Without `operator`, the condition is `=`.'.($engine->groups ? '' : ' Filters combine with `and`.'));
        });
    }

    private function group(Resource $resource, EngineGrammar $engine): ObjectType
    {
        $depth = $resource->context()->limits()->depths->filters;

        return Schemas::closed(
            ['nested' => $this->filters($resource, $engine) ?? new ArrayType, 'type' => FieldFilterSchema::joint()],
            ['nested'],
            "Filters that count as one condition. Groups nest at most {$depth} deep.",
        );
    }

    public function sort(Resource $resource, EngineGrammar $engine): Type
    {
        return $this->components->schema($this->records->name($resource).'EngineSort', $resource::class, fn (): Type => Schemas::closed([
            'field' => (new StringType)->enum([...$engine->sorts, RelevanceClause::FIELD]),
            'direction' => (new StringType)->enum(array_column(SortDirection::cases(), 'value')),
        ], ['field'], 'An attribute of the search index, for a search on the search engine: with source engine, with or without a text. `@relevance` is accepted with a text only, alone and `desc`.'));
    }
}
