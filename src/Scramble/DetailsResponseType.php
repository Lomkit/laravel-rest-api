<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Text\TextMode;

final class DetailsResponseType
{
    public static function document(Operation $operation, string $resourceName): void
    {
        $operation->summary("Get {$resourceName} details");

        $schema = new Schema;
        $schema->type = DetailsEntryTypes::objectOf(['data' => self::data()]);

        $response = new Response(200);
        $response->setDescription('The fields, relations, scopes, actions, instructions, rules, text search and gates of the resource');
        $response->setContent('application/json', $schema);

        $operation->responses = [
            ...array_filter(
                $operation->responses ?? [],
                fn (Response|Reference $existing): bool => ! $existing instanceof Response || (string) $existing->code !== '200',
            ),
            $response,
        ];
    }

    private static function data(): ObjectType
    {
        return DetailsEntryTypes::objectOf([
            'actions' => DetailsEntryTypes::listOf(DetailsEntryTypes::action()),
            'metrics' => DetailsEntryTypes::listOf(DetailsEntryTypes::metric()),
            'instructions' => DetailsEntryTypes::listOf(DetailsEntryTypes::instruction()),
            'fields' => DetailsEntryTypes::listOf(DetailsEntryTypes::field()),
            'limits' => DetailsEntryTypes::listOf(new IntegerType),
            'scopes' => DetailsEntryTypes::listOf(DetailsEntryTypes::scope()),
            'relations' => DetailsEntryTypes::listOf(self::relation()),
            'rules' => self::rules(),
            'text' => self::text(),
            'gates' => DetailsEntryTypes::listOf(new StringType, 'The abilities that search.gates accepts: the built-in ones, then those that the resource declares; empty when the resource turns its gates off'),
            'authorizations' => self::authorizations(),
        ]);
    }

    private static function authorizations(): ObjectType
    {
        $answer = DetailsEntryTypes::objectOf(['allowed' => new BooleanType, 'message' => (new StringType)->nullable(true)]);

        $authorizations = new ObjectType;
        $authorizations->additionalProperties($answer);
        $authorizations->setDescription('The answers to the class-level abilities that the routes of the resource check, for the user of the request, keyed by ability: viewAny, create, then those of the endpoints, the actions and the metrics');

        return $authorizations;
    }

    private static function relation(): ObjectType
    {
        $constraints = DetailsEntryTypes::objectOf([
            'required_on_creation' => new BooleanType,
            'prohibited_on_creation' => new BooleanType,
            'required_on_update' => new BooleanType,
            'prohibited_on_update' => new BooleanType,
        ]);

        $types = new ObjectType;
        $types->additionalProperties(new StringType);
        $types->setDescription('MorphTo only: the resource class of each morph type');

        $morph = DetailsEntryTypes::objectOf(
            ['type_column' => new StringType, 'key_column' => new StringType],
            description: 'MorphTo only: the columns of the relation',
        );

        return DetailsEntryTypes::objectOf([
            'resource' => (new StringType)->setDescription('The related resource class; absent on a MorphTo relation with several types'),
            'relation' => new StringType,
            'constraints' => $constraints,
            'name' => new StringType,
            'pivot_fields' => DetailsEntryTypes::listOf(DetailsEntryTypes::pivotField(), 'Many-to-many relations only: the pivot fields of the relation'),
            'types' => $types,
            'morph' => $morph,
        ], ['resource', 'pivot_fields', 'types', 'morph']);
    }

    private static function rules(): ObjectType
    {
        $properties = [];

        foreach (['all', 'create', 'update'] as $operation) {
            $byAttribute = new ObjectType;
            $byAttribute->additionalProperties(DetailsEntryTypes::strings());
            $properties[$operation] = $byAttribute;
        }

        return DetailsEntryTypes::objectOf($properties, description: 'The rules of the resource, keyed by attribute, as strings');
    }

    private static function text(): Type
    {
        $text = DetailsEntryTypes::objectOf([
            'fields' => DetailsEntryTypes::listOf(new StringType, 'The searchable fields; empty with ScoutEngine, whose index decides what the text matches'),
            'modes' => DetailsEntryTypes::listOf((new StringType)->enum(array_column(TextMode::cases(), 'value'))),
            'relevance' => (new BooleanType)->setDescription('The @relevance sort is accepted with a text'),
            'trashed' => (new BooleanType)->setDescription('search.trashed can be sent with a text'),
            'max_length' => new IntegerType,
            'engine' => self::engine(),
        ], description: 'The text search of the resource; null without one');

        return $text->nullable(true);
    }

    private static function engine(): Type
    {
        $filter = DetailsEntryTypes::objectOf([
            'name' => new StringType,
            'type' => new StringType,
            'operators' => DetailsEntryTypes::operators(),
            'values' => DetailsEntryTypes::values('Enum attributes only: the backing values'),
        ], ['values']);

        $engine = DetailsEntryTypes::objectOf([
            'filters' => DetailsEntryTypes::listOf($filter),
            'sorts' => DetailsEntryTypes::strings(),
            'max_sorts' => (new IntegerType)->nullable(true)->setDescription('The most sorts in one search; null when unbounded, 0 when only @relevance is accepted'),
            'max_page_size' => (new IntegerType)->nullable(true),
            'window' => (new IntegerType)->nullable(true)->setDescription('page times limit must not exceed it; null when unbounded'),
        ], description: 'The grammar of a search with a filled text on a search engine, which replaces the sort and filter of the field entries: filters combined with and only, no scopes, @relevance alone and desc only; null on the database');

        return $engine->nullable(true);
    }
}
