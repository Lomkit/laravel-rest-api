<?php

namespace Lomkit\Rest\Scramble\Components;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
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
            ->limits()
            ->maxTotal !== null;
        $engine = $resource->context()->text($resource)->backend === TextBackend::Engine;

        return Schemas::required([
            'current_page' => new IntegerType,
            'data' => Schemas::list($this->records->reference($resource)),
            'from' => (new IntegerType)->nullable(true),
            'last_page' => new IntegerType,
            'per_page' => new IntegerType,
            'to' => (new IntegerType)->nullable(true),
            'total' => new IntegerType,
            ...($capped ? ['total_capped' => self::capped($engine)] : []),
            'meta' => $this->meta($resource),
        ], $engine ? ['total_capped'] : [], 'A page of rows');
    }

    private static function capped(bool $engine): BooleanType
    {
        $type = new BooleanType;
        $type->setDescription($engine
            ? 'True when more rows match than the resource counts: total is then a lower bound. Absent on a text search, which takes its total from the search index'
            : 'True when more rows match than the resource counts: total is then a lower bound');

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

        if (! Overrides::method($resource, 'searchMeta', Resource::class)) {
            return Schemas::closed($properties);
        }

        $meta = Schemas::object($properties);
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
