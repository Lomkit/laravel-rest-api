<?php

namespace Lomkit\Rest\Scramble\Search;

use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\RelevanceClause;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Components\RecordSchema;
use Lomkit\Rest\Scramble\Schemas;
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
        $filter = $this->components->schema($name.'EngineFilter', $resource::class, function () use ($resource, $engine, $name): Type {
            $schema = new FieldFilterSchema($resource->context()->limits());
            $branches = [];

            foreach ($engine->filters as $attribute => $declared) {
                $branches[] = [$attribute, $name.'EngineFilter'.FilterPaths::component($attribute), fn (): Type => $schema->schema($attribute, $declared->field, $declared->operators, ['and'])];
            }

            return $this->components->discriminated('field', $resource::class, $branches)
                ->setDescription('A condition on an attribute of the search index: the resource serves its lists through its search engine, with or without a text. Without `operator`, the condition is `=`. Filters combine with `and`.');
        });

        $list = Schemas::list($filter);
        $list->setMax($resource->context()->limits()->sizes->filters);

        return $list;
    }

    public function sort(Resource $resource, EngineGrammar $engine): Type
    {
        return $this->components->schema($this->records->name($resource).'EngineSort', $resource::class, fn (): Type => Schemas::closed([
            'field' => (new StringType)->enum([...$engine->sorts, RelevanceClause::FIELD]),
            'direction' => (new StringType)->enum(array_column(SortDirection::cases(), 'value')),
        ], ['field'], 'An attribute of the search index: the resource serves its lists through its search engine. `@relevance` is accepted with a text only, alone and `desc`.'));
    }
}
