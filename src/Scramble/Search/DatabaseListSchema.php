<?php

namespace Lomkit\Rest\Scramble\Search;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Components\RecordSchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Types\Nothing;
use Lomkit\Rest\Scramble\Types\OneOf;
use Lomkit\Rest\Text\ListSource;

/** @internal */
final readonly class DatabaseListSchema
{
    public function __construct(
        private ComponentRegistry $components,
        private RecordSchema $records,
    ) {}

    public function oneOf(Resource $resource, string $name, ObjectType $engine, ObjectType $database): OneOf
    {
        $engine->addProperty('source', $this->source(ListSource::Engine, 'The search runs on the search engine, the default'));
        $database->addProperty('source', $this->source(ListSource::Database, 'The search runs as the SQL search of the resource, with its whole grammar; a blank text is tolerated. Not the Scout database driver'));
        $database->addProperty('text', new Nothing);
        $database->setRequired(['source']);

        $this->sql($resource, $database);

        return new OneOf([
            $this->components->schema(substr_replace($name, 'Engine', strlen($this->records->name($resource)), 0), $resource::class, fn (): ObjectType => $engine),
            $this->components->schema(substr_replace($name, 'Database', strlen($this->records->name($resource)), 0), $resource::class, fn (): ObjectType => $database),
        ]);
    }

    private function sql(Resource $resource, ObjectType $database): void
    {
        $sort = (new SortSchema($this->components, $this->records))->sort($resource, false);

        if ($sort !== null) {
            $sorts = Schemas::list($sort);
            $sorts->setMax($resource->context()->limits()->sizes->sorts);
            $database->addProperty('sorts', $sorts);
        }

        if (Trashed::supportedBy($resource::modelClass()) && $database->hasProperty('trashed')) {
            $database->addProperty('trashed', (new StringType)->enum(array_column(Trashed::cases(), 'value')));
        }
    }

    private function source(ListSource $source, string $description): StringType
    {
        $type = (new StringType)->enum([$source->value]);
        $type->setDescription($description);

        return $type;
    }
}
