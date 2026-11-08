<?php

namespace Lomkit\Rest\Scramble\Mutate;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Components\RecordSchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Types\OneOf;

final readonly class ResultSchema
{
    private RecordSchema $records;

    public function __construct(
        private ComponentRegistry $components,
    ) {
        $this->records = new RecordSchema($components);
    }

    public function reference(Resource $resource): Reference
    {
        return $this->components->schema($this->records->name($resource).'MutateResult', $resource::class, fn (): Type => Schemas::required([
            'operations' => Schemas::list($this->operation(), 'One entry for each entry of `mutate`, at the same index'),
            'records' => Schemas::list($this->records->reference($resource)->nullable(true), 'Present when the request sends `response`: one record for each entry of `mutate`, at the same index, or `null` for a row that the search of the resource does not return'),
        ], ['records'], 'The operations that the mutation wrote'));
    }

    private function operation(): Reference
    {
        return $this->components->schema('RestMutateOperation', self::class, function (): Type {
            $relations = new ObjectType;
            $relations->additionalProperties((new OneOf([$this->operation(), Schemas::list($this->operation())]))->nullable(true));
            $relations->setDescription('Present when the entry sends `relations`: the entries of each relation that it sends, an object for a relation to one, a list for a relation to many');

            return Schemas::required([
                'operation' => (new StringType)->enum(array_column(MutationOperation::cases(), 'value')),
                'type' => (new StringType)->setDescription('The morph type of the row, on an entry of a `MorphTo` relation'),
                'key' => self::key(),
                'relations' => $relations,
            ], ['type', 'relations'], 'The operation of one entry and the key of its row');
        });
    }

    private static function key(): OneOf
    {
        $key = new OneOf([new IntegerType, new StringType, Schemas::list(new OneOf([new IntegerType, new StringType]))]);
        $key->nullable(true);
        $key->setDescription('The key of the row that a `create` wrote, else the key that the entry sent. An entry with a list of keys answers a list.');

        return $key;
    }
}
