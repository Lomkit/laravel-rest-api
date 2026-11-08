<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Scramble\Components\RecordSchema;
use Lomkit\Rest\Support\Overrides;
use Lomkit\Rest\Text\TextMode;

final class DetailsResponseType
{
    public static function document(Operation $operation, DocumentationContext $context): void
    {
        $operation->summary("Get {$context->name} details");

        $resource = $context->resource;
        $details = $context->components->schema((new RecordSchema($context->components))->name($resource).'Details', $resource::class, fn (): Type => Schemas::required(['data' => self::data($resource)]));

        $operation->addParameters([DetailsParameter::make()]);
        $operation->addResponse(Schemas::response(200, 'The fields, limits and meta of the resource, then the sections and parts that the with parameter asks for', $details));
    }

    private static function data(Resource $resource): ObjectType
    {
        $definition = $resource->definition();

        return Schemas::required([
            'actions' => Schemas::list(DetailsEntryTypes::action(array_values(array_map(fn (Action $action): string => $action->uriKey(), $definition->actions()))), 'With actions or one of its parts'),
            'metrics' => Schemas::list(DetailsEntryTypes::metric(array_values(array_map(fn (Metric $metric): string => $metric->uriKey(), $definition->metrics()))), 'With metrics or one of its parts'),
            'instructions' => Schemas::list(DetailsEntryTypes::instruction(array_values(array_map(fn (Instruction $instruction): string => $instruction->uriKey(), $definition->instructions()))), 'With instructions or one of its parts'),
            'fields' => Schemas::list(DetailsEntryTypes::field($definition->fields()->names())),
            'limits' => Schemas::list(new IntegerType),
            'scopes' => Schemas::list(DetailsEntryTypes::scope(array_map(fn (Scope $scope): string => $scope->name, $definition->scopes())), 'With scopes or one of its parts'),
            'relations' => Schemas::list(self::relation(array_values(array_map(fn (Relation $relation): string => $relation->relation, $definition->relations()))), 'With relations or one of its parts'),
            'rules' => self::rules(),
            'text' => self::text(),
            'gates' => Schemas::list((new StringType)->enum($resource->isGatingEnabled() ? $definition->gates()->names() : []), 'With gates: the abilities that search.gates accepts, the built-in ones first, then those that the resource declares; empty when the resource turns its gates off'),
            'confirmations' => self::confirmations($resource),
            'authorizations' => self::authorizations(),
            'meta' => self::meta($resource),
        ], ['actions', 'metrics', 'instructions', 'scopes', 'relations', 'rules', 'text', 'gates', 'confirmations', 'authorizations']);
    }

    private static function meta(Resource $resource): ObjectType
    {
        if (! Overrides::method($resource, 'detailsMeta', Resource::class)) {
            return Schemas::closed([], description: 'Empty: the resource adds no keys with detailsMeta()');
        }

        $meta = new ObjectType;
        $meta->additionalProperties(new MixedType);
        $meta->setDescription('The keys that detailsMeta() of the resource returns for the user of the request');

        return $meta;
    }

    private static function confirmations(Resource $resource): ObjectType
    {
        $writes = array_map(fn (): BooleanType => new BooleanType, $resource->confirmations());

        return Schemas::closed($writes, array_keys($writes), 'With confirmations: true for each write whose hook the resource declares (mutateConfirmation(), destroyConfirmation(), and restoreConfirmation() and forceDestroyConfirmation() when the model uses SoftDeletes): the endpoint can answer 428 with a signed token, and writes when the same body comes back with that token');
    }

    private static function authorizations(): ObjectType
    {
        $answer = Schemas::required(['allowed' => new BooleanType, 'message' => (new StringType)->nullable(true)]);

        $authorizations = new ObjectType;
        $authorizations->additionalProperties($answer);
        $authorizations->setDescription('With authorizations: the answers to the class-level abilities that the routes of the resource check, for the user of the request, keyed by ability: viewAny, create, then those of the endpoints, the actions and the metrics');

        return $authorizations;
    }

    /** @param list<string> $names */
    private static function relation(array $names): ObjectType
    {
        $constraints = Schemas::required([
            'required_on_creation' => new BooleanType,
            'prohibited_on_creation' => new BooleanType,
            'required_on_update' => new BooleanType,
            'prohibited_on_update' => new BooleanType,
        ], description: 'With relations.constraints');

        $types = new ObjectType;
        $types->additionalProperties(new StringType);
        $types->setDescription('MorphTo only: the resource class of each morph type');

        $morph = Schemas::required(
            ['type_column' => new StringType, 'key_column' => new StringType],
            description: 'MorphTo only: the columns of the relation',
        );

        return Schemas::required([
            'resource' => (new StringType)->setDescription('The related resource class; absent on a MorphTo relation with several types'),
            'relation' => (new StringType)->enum($names),
            'constraints' => $constraints,
            'operations' => Schemas::list((new StringType)->enum(array_column(MutationOperation::cases(), 'value')), 'With relations.operations: the operations that the entries of the relation accept in mutate; empty when the relation takes no entry'),
            'name' => new StringType,
            'pivot_fields' => Schemas::list(DetailsEntryTypes::pivotField(), 'With relations.pivot_fields, on many-to-many relations only: the pivot fields of the relation'),
            'types' => $types,
            'morph' => $morph,
        ], ['resource', 'constraints', 'operations', 'pivot_fields', 'types', 'morph']);
    }

    private static function rules(): ObjectType
    {
        $properties = [];

        foreach (['all', 'create', 'update'] as $operation) {
            $byAttribute = new ObjectType;
            $byAttribute->additionalProperties(DetailsEntryTypes::strings());
            $properties[$operation] = $byAttribute;
        }

        return Schemas::required($properties, description: 'With rules: the rules of the resource, keyed by attribute, as strings');
    }

    private static function text(): Type
    {
        $text = Schemas::required([
            'fields' => Schemas::list(new StringType, 'The searchable fields; empty with ScoutEngine, whose index decides what the text matches'),
            'modes' => Schemas::list((new StringType)->enum(array_column(TextMode::cases(), 'value'))),
            'relevance' => (new BooleanType)->setDescription('The @relevance sort is accepted with a text'),
            'trashed' => (new BooleanType)->setDescription('search.trashed can be sent with a text'),
            'max_length' => new IntegerType,
            'engine' => self::engine(),
        ], description: 'With text: the text search of the resource; null without one');

        return $text->nullable(true);
    }

    private static function engine(): Type
    {
        $filter = Schemas::required([
            'name' => new StringType,
            'type' => new StringType,
            'operators' => DetailsEntryTypes::operators(),
            'values' => DetailsEntryTypes::values('Enum attributes only: the backing values'),
        ], ['values']);

        $engine = Schemas::required([
            'filters' => Schemas::list($filter),
            'sorts' => DetailsEntryTypes::strings(),
            'max_sorts' => (new IntegerType)->nullable(true)->setDescription('The most sorts in one search; null when unbounded, 0 when only @relevance is accepted'),
            'max_page_size' => (new IntegerType)->nullable(true),
            'window' => (new IntegerType)->nullable(true)->setDescription('page times limit must not exceed it; null when unbounded'),
        ], description: 'The grammar of a search with a filled text on a search engine, which replaces the sort and filter of the field entries: filters combined with and only, no scopes, @relevance alone and desc only; null on the database');

        return $engine->nullable(true);
    }
}
