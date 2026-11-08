<?php

namespace Lomkit\Rest\Scramble\Components;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Schemas;

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
        return $this->components->schema($this->records->name($resource).'Paginated', $resource::class, fn (): Type => Schemas::required([
            'current_page' => new IntegerType,
            'data' => Schemas::list($this->records->reference($resource)),
            'from' => (new IntegerType)->nullable(true),
            'last_page' => new IntegerType,
            'per_page' => new IntegerType,
            'to' => (new IntegerType)->nullable(true),
            'total' => new IntegerType,
            'meta' => $this->meta($resource),
        ], description: 'A page of rows'));
    }

    public function rows(Resource $resource): Reference
    {
        return $this->components->schema($this->records->name($resource).'Rows', $resource::class, fn (): Type => Schemas::required([
            'data' => Schemas::list($this->records->reference($resource)),
            'meta' => new ObjectType,
        ], description: 'The rows that the request changed'));
    }

    private function meta(Resource $resource): ObjectType
    {
        $meta = new ObjectType;
        $gates = $this->records->gates($resource, true);

        if ($gates !== null) {
            $meta->addProperty($resource->context()->config()->gatesKey, $gates->setDescription('Present when the search lists the create gate'));
        }

        return $meta;
    }
}
