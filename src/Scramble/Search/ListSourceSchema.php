<?php

namespace Lomkit\Rest\Scramble\Search;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Components\RecordSchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Types\OneOf;
use Lomkit\Rest\Text\ListSource;

/** @internal */
final readonly class ListSourceSchema
{
    public function __construct(
        private ComponentRegistry $components,
        private RecordSchema $records,
    ) {}

    public function oneOf(Resource $resource, string $name, ObjectType $database, ObjectType $engine): OneOf
    {
        $database->addProperty('source', $this->source(ListSource::Database, 'The default: the search runs as the SQL search of the resource, with its whole grammar. Not the Scout database driver'));
        $engine->addProperty('source', $this->source(ListSource::Engine, 'The search runs on the search engine of the resource, with or without a text'));
        $engine->addRequired(['source']);

        $this->sql($resource, $database);

        return new OneOf([
            $this->components->schema($this->branch($resource, $name, 'Database'), $resource::class, fn (): ObjectType => $database),
            $this->components->schema($this->branch($resource, $name, 'Engine'), $resource::class, fn (): ObjectType => $engine),
        ]);
    }

    private function sql(Resource $resource, ObjectType $database): void
    {
        $text = $database->properties['text'] ?? null;
        $text?->setDescription('A blank text only, without a letter or digit, which keeps the SQL search: a text with a letter or digit runs on the search engine, so send it with source engine');

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

    private function branch(Resource $resource, string $name, string $source): string
    {
        return substr_replace($name, $source, strlen($this->records->name($resource)), 0);
    }

    private function source(ListSource $source, string $description): StringType
    {
        $type = (new StringType)->enum([$source->value]);
        $type->setDescription($description);

        return $type;
    }
}
