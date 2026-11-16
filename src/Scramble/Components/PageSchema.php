<?php

namespace Lomkit\Rest\Scramble\Components;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Http\LimitNarrowing;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Search\Run\SearchMeta;
use Lomkit\Rest\Support\Overrides;
use Lomkit\Rest\Text\TextBackend;

final readonly class PageSchema
{
    private RecordSchema $records;

    public function __construct(
        private ComponentRegistry $components,
    ) {
        $this->records = new RecordSchema($components);
    }

    public function paginated(Resource $resource): Reference
    {
        return $this->components->schema($this->records->name($resource).'Paginated', $resource::class, fn (): Type => $this->page($resource));
    }

    public function rows(Resource $resource): Reference
    {
        return $this->components->schema($this->records->name($resource).'Rows', $resource::class, fn (): Type => Schemas::required([
            'data' => Schemas::list($this->records->reference($resource)),
            'meta' => new ObjectType,
        ], description: 'The rows that the request changed'));
    }

    private function page(Resource $resource): ObjectType
    {
        $capped = $resource
            ->definition()
            ->declaredLimits()
            ->maxTotal !== null || LimitNarrowing::appliesTo($resource);
        $engine = $resource->context()->text($resource)->backend === TextBackend::Engine;
        $optional = $engine || $resource->maxTotal === null;

        return Schemas::required([
            'current_page' => new IntegerType,
            'data' => Schemas::list($this->records->reference($resource)),
            'from' => (new IntegerType)->nullable(true),
            'last_page' => new IntegerType,
            'per_page' => new IntegerType,
            'to' => (new IntegerType)->nullable(true),
            'total' => new IntegerType,
            ...($capped || $engine ? ['total_capped' => self::capped($engine)] : []),
            ...($engine ? ['dropped' => self::dropped()] : []),
            'meta' => $this->meta($resource),
        ], $optional ? ['total_capped', 'dropped'] : [], 'A page of rows');
    }

    private static function capped(bool $engine): BooleanType
    {
        $type = new BooleanType;
        $type->setDescription($engine
            ? 'True when more rows match than the total counts: total is then a lower bound. On a page of the search engine, present only when the engine caps its total, such as Turbopuffer at 10,000'
            : 'True when more rows match than the resource counts: total is then a lower bound');

        return $type;
    }

    private static function dropped(): IntegerType
    {
        $type = new IntegerType;
        $type->setDescription('Present on a page of the search engine: the rows that the engine returned for the page and that the database read left out (a deleted row, a global scope, a row restriction). They are missing from data and still counted in total. Above 0, the index is stale, or the restrictions of the engine and of the database disagree');

        return $type;
    }

    private function meta(Resource $resource): ObjectType
    {
        $properties = [];
        $gates = $this->records->gates($resource, true);

        if ($gates !== null) {
            $properties[$resource->context()->config()->gatesKey] = $gates->setDescription('Present when the search lists the create gate');
        }

        if (self::instructionsAddMeta($resource)) {
            $properties[SearchMeta::INSTRUCTIONS] = self::instructions();
        }

        $extensions = MetaSchema::search($resource);
        $properties = [...$properties, ...$extensions];

        if (! Overrides::method($resource, 'searchMeta', Resource::class)) {
            return Schemas::closed($properties, array_keys($extensions));
        }

        $meta = Schemas::object($properties);
        $meta->setRequired(array_keys($extensions));
        $meta->additionalProperties(new MixedType);
        $meta->setDescription('Also holds the keys that searchMeta() of the resource returns for the page');

        return $meta;
    }

    private static function instructionsAddMeta(Resource $resource): bool
    {
        foreach ($resource->definition()->instructions() as $instruction) {
            if (Overrides::method($instruction, 'searchMeta', Instruction::class)) {
                return true;
            }
        }

        return false;
    }

    private static function instructions(): ObjectType
    {
        $instruction = new ObjectType;
        $instruction->additionalProperties(new MixedType);

        $instructions = new ObjectType;
        $instructions->additionalProperties($instruction);
        $instructions->setDescription('Present when an instruction of the search returns keys from searchMeta(): its keys, under the alias of the instruction or else its uriKey');

        return $instructions;
    }
}
