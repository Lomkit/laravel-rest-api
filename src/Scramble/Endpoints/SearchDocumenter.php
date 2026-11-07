<?php

namespace Lomkit\Rest\Scramble\Endpoints;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Scramble\DocumentationContext;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\TextSearchType;

final readonly class SearchDocumenter implements EndpointDocumenter
{
    #[\Override]
    public function document(Operation $operation, DocumentationContext $context): void
    {
        $operation->summary("Search {$context->name}");

        $fields = $context->resource->definition()->fields();
        $text = TextSearchType::of($context->resource);

        $search = Schemas::object([
            'filters' => Schemas::list(Schemas::object([
                'field' => (new StringType)->enum($fields->filterable()->names()),
                'operator' => (new StringType)->enum(array_column(FilterOperator::cases(), 'value')),
                'value' => (new MixedType)->setDescription('Accepts string, integer, boolean, or array'),
                'type' => (new StringType)->enum(['and', 'or']),
                'nested' => new ArrayType,
            ])),
            'sorts' => Schemas::list(Schemas::object([
                'field' => (new StringType)->enum($text->sortNames($fields->sortable()->names())),
                'direction' => (new StringType)->enum(array_column(SortDirection::cases(), 'value')),
            ])),
            'selects' => Schemas::list(Schemas::object(['field' => (new StringType)->enum($fields->selectable()->names())])),
            'includes' => Schemas::list(self::include($context->resource->definition()->relations())),
            'scopes' => Schemas::list(Schemas::object(['name' => new StringType, 'parameters' => new ArrayType])),
        ]);

        $text->addTo($search);

        $aggregate = Schemas::object([
            'relation' => new StringType,
            'type' => (new StringType)->enum(array_column(AggregateFunction::cases(), 'value')),
            'field' => new StringType,
            'filters' => new ArrayType,
        ]);
        $instruction = Schemas::object(['name' => new StringType, 'fields' => new ArrayType]);

        $search->addProperty('aggregates', Schemas::list($aggregate)->setDescription('Aggregate values over relations'));
        $search->addProperty('instructions', Schemas::list($instruction)->setDescription('Named query instructions defined on the resource'));
        $search->addProperty('gates', Schemas::list(new StringType)->setDescription('Gates to evaluate for the current user on each result'));
        $search->addProperty('page', new IntegerType);
        $search->addProperty('limit', new IntegerType);

        Schemas::requestBody($operation, Schemas::object(['search' => $search]));
    }

    /** @param array<int, Relation> $relations */
    private static function include(array $relations): ObjectType
    {
        $names = array_values(array_map(fn (Relation $relation): string => $relation->relation, $relations));

        $include = new ObjectType;
        $include->addProperty('relation', $names === [] ? new StringType : (new StringType)->enum($names)->setDescription(self::relationsDescription($relations)));
        $include->addProperty('alias', (new StringType)->setDescription('Optional output key for this include, used verbatim. Allows including the same relation several times under different keys. Must match ^[A-Za-z_][A-Za-z0-9_]*$, be unique across the includes, and not collide with a field, a relation, the key of an aggregate or the gates key. Not allowed on a dotted relation path.'));
        $include->addProperty('limit', new IntegerType);
        $include->addProperty('filters', new ArrayType);
        $include->addProperty('sorts', new ArrayType);
        $include->addProperty('types', (new ObjectType)->setDescription('MorphTo relations only: the search of each type, keyed by morph type, with selects, filters, scopes, includes, aggregates, instructions and gates'));

        return $include;
    }

    /** @param array<int, Relation> $relations */
    private static function relationsDescription(array $relations): string
    {
        $lines = ["**Available relations:**\n"];

        foreach ($relations as $relation) {
            $lines[] = '- `'.$relation->relation.'` ('.class_basename($relation).')';
        }

        return implode("\n", $lines);
    }
}
