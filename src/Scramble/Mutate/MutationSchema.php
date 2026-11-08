<?php

namespace Lomkit\Rest\Scramble\Mutate;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Components\KeySchema;
use Lomkit\Rest\Scramble\Components\RecordSchema;
use Lomkit\Rest\Scramble\FieldSchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Search\SearchSchema;

final readonly class MutationSchema
{
    private const array RESPONSE_KEYS = ['selects', 'aggregates', 'includes', 'gates'];

    private RecordSchema $records;

    private WritableSchema $writable;

    private RelationEntrySchema $relations;

    public function __construct(
        private ComponentRegistry $components,
    ) {
        $this->records = new RecordSchema($components);
        $this->writable = new WritableSchema($components);
        $this->relations = new RelationEntrySchema($components);
    }

    public function body(Resource $resource): ObjectType
    {
        $mutate = Schemas::list($this->reference($resource));
        $mutate->setMin(1);
        $mutate->setMax($resource->context()->limits()->sizes->mutate);

        return Schemas::required(['mutate' => $mutate, 'response' => $this->response($resource)], ['response']);
    }

    public function reference(Resource $resource): Reference
    {
        $name = $this->records->name($resource);

        return $this->components->schema($name.'Mutation', $resource::class, fn (): Type => $this->components->discriminated('operation', $resource::class, [
            [MutationOperation::Create->value, $name.'MutationCreate', fn (): Type => $this->entry($resource, MutationOperation::Create, WriteOperation::Create)],
            [MutationOperation::Update->value, $name.'MutationUpdate', fn (): Type => $this->entry($resource, MutationOperation::Update, WriteOperation::Update)],
        ])->setDescription('An entry of `mutate`: `create` writes a new row, `update` changes the row of `key`.'));
    }

    private function entry(Resource $resource, MutationOperation $operation, WriteOperation $write): ObjectType
    {
        $properties = array_filter([
            'operation' => (new StringType)->const($operation->value),
            'key' => $operation->requiresKey() ? KeySchema::value($resource) : null,
            'version' => self::version($resource, $operation),
            'attributes' => $this->writable->attributes($resource, $write),
            'relations' => $this->relations->relations($resource, $operation),
        ]);

        return Schemas::closed($properties, array_values(array_intersect(['operation', 'key'], array_keys($properties))));
    }

    public static function version(Resource $resource, MutationOperation $operation): ?Type
    {
        $version = $resource->definition()->version();

        if ($version === null || $operation !== MutationOperation::Update) {
            return null;
        }

        return FieldSchema::value($version->field)->setDescription("The version of the row that the client read: its `{$version->column}` as the search returns it. When a row has another version, the request answers 409 and writes nothing");
    }

    private function response(Resource $resource): Reference
    {
        return $this->components->schema($this->records->name($resource).'MutationResponse', $resource::class, fn (): Type => Schemas::closed(
            (new SearchSchema($this->components))->properties($resource, self::RESPONSE_KEYS, false),
            [],
            'The records to return after the mutation: one for each entry of `mutate`, at the same index, read and rendered as a search reads its rows. '.SearchSchema::rows($resource, 'the entries of `mutate`'),
        ));
    }
}
