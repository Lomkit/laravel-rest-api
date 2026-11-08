<?php

namespace Lomkit\Rest\Scramble\Search;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Components\RecordSchema;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Types\Nothing;
use Lomkit\Rest\Validation\Search\RowKeys;
use Lomkit\Rest\Validation\Search\SearchShape;

final readonly class SearchSchema
{
    private RecordSchema $records;

    public function __construct(
        private ComponentRegistry $components,
    ) {
        $this->records = new RecordSchema($components);
    }

    public static function alias(string $description): StringType
    {
        $alias = new StringType;
        $alias->setMax(255);
        $alias->pattern(trim(RowKeys::PATTERN, '/'));
        $alias->setDescription($description);

        return $alias;
    }

    public function reference(Resource $resource, SearchShape $shape = SearchShape::Page): Reference
    {
        $name = $this->records->name($resource).match ($shape) {
            SearchShape::Page => 'Search',
            SearchShape::Rows => 'ActionSearch',
            SearchShape::Aggregate => 'MetricSearch',
        };

        return $this->components->schema($name, $resource::class, fn (): Type => $this->search($resource, array_keys($shape->keys()), $shape === SearchShape::Page));
    }

    public static function rows(Resource $resource, string $roots): string
    {
        $rows = $resource->context()->limits()->sizes->rows;

        return "A response renders at most {$rows} rows, included rows counted, from the limits of the request: {$roots}, and for each to-many include its limit for each parent row, nested includes multiplying. Over it, the request answers 422.";
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, Type>
     */
    public function properties(Resource $resource, array $keys, bool $root): array
    {
        $properties = [];

        foreach ($keys as $key) {
            $type = $this->property($resource, $key, $root);

            if ($type !== null) {
                $properties[$key] = $type;
            }
        }

        return $properties;
    }

    /** @param list<string> $keys */
    private function search(Resource $resource, array $keys, bool $paged): Type
    {
        $refused = array_fill_keys(array_intersect(['text', 'trashed'], $keys), new Nothing);
        $search = Schemas::object(array_replace($refused, $this->properties($resource, $keys, true)));

        if ($paged) {
            $search->setDescription(self::rows($resource, 'the page limit'));
        }

        return $search;
    }

    private function property(Resource $resource, string $key, bool $root): ?Type
    {
        $sizes = $resource->context()->limits()->sizes;

        return match ($key) {
            'text' => TextSearchType::of($resource)->text(),
            'trashed' => TextSearchType::of($resource)->trashed(),
            'filters' => (new FilterSchema($this->components))->list($resource),
            'gates' => $this->gates($resource, $root),
            'scopes' => self::capped((new InputSchema($this->components))->scope($resource), $sizes->scopes),
            'sorts' => self::capped($this->sort($resource, $root), $sizes->sorts),
            'selects' => self::capped($this->select($resource), $sizes->selects),
            'aggregates' => self::capped((new AggregateSchema($this->components))->reference($resource), $sizes->aggregates),
            'includes' => self::capped((new IncludeSchema($this->components))->reference($resource), $sizes->includes),
            'instructions' => self::capped((new InputSchema($this->components))->instruction($resource), $sizes->instructions),
            'limit' => (new IntegerType)->enum($resource->definition()->limits()->allowed),
            'page' => self::page($resource),
            default => null,
        };
    }

    private function gates(Resource $resource, bool $root): ?ArrayType
    {
        if (! $resource->isGatingEnabled()) {
            return null;
        }

        $vocabulary = $resource->definition()->gates();
        $names = array_values(array_filter($vocabulary->names(), fn (string $name): bool => $root || $vocabulary->find($name)?->isClassLevel() !== true));
        $key = $resource->context()->config()->gatesKey;
        $gates = Schemas::list((new StringType)->enum($names), "The abilities to answer for each row, under `{$key}` in the row".($root ? ', and for `create` under `'.$key.'` in `meta`' : ''));
        $gates->setUniqueItems(true);

        return $gates;
    }

    private function sort(Resource $resource, bool $root): ?Type
    {
        $sortable = $resource
            ->definition()
            ->fields()
            ->sortable();
        $names = array_values(array_unique([...$sortable->names(), ...self::relationPaths($resource), ...AggregateSchema::keys($resource)]));
        $ranked = $root ? TextSearchType::of($resource)->sortNames($names) : $names;

        if ($ranked === []) {
            return null;
        }

        $sort = fn (): Type => Schemas::closed([
            'field' => (new StringType)->enum($ranked),
            'direction' => (new StringType)->enum(array_column(SortDirection::cases(), 'value')),
        ], ['field'], '`field` lists the default key of each aggregate, such as `comments_count`: it sorts only when the same search declares that aggregate. The key of an aggregate with an `alias` is accepted too, and is not listed.');

        return $ranked === $names ? $this->components->schema($this->records->name($resource).'Sort', $resource::class, $sort) : $sort();
    }

    private function select(Resource $resource): ?Reference
    {
        $names = $resource
            ->definition()
            ->fields()
            ->selectable()
            ->names();

        if ($names === []) {
            return null;
        }

        return $this->components->schema($this->records->name($resource).'Select', $resource::class, fn (): Type => Schemas::closed(['field' => (new StringType)->enum($names)], ['field']));
    }

    /** @return list<string> */
    private static function relationPaths(Resource $resource): array
    {
        $paths = [];

        foreach ($resource->definition()->relations() as $relation) {
            if ($relation->hasMultipleEntries() || $relation instanceof MorphTo) {
                continue;
            }

            $fields = $relation
                ->resource()
                ->definition()
                ->fields();

            foreach (AggregateFunction::Max->acceptedFieldNames($fields) as $field) {
                $paths[] = $relation->relation.'.'.$field;
            }
        }

        return $paths;
    }

    private static function capped(?Type $items, int $max): ?ArrayType
    {
        if ($items === null) {
            return null;
        }

        $list = Schemas::list($items);
        $list->setMax($max);

        return $list;
    }

    private static function page(Resource $resource): IntegerType
    {
        $page = new IntegerType;
        $page->setMin(1);
        $page->setMax($resource->definition()->limits()->maxPage);

        return $page;
    }
}
