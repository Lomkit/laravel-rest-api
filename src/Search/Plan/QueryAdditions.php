<?php

namespace Lomkit\Rest\Search\Plan;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Extensions\ExtensionName;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Search\Plan\Columns\RelationKeys;

/** @internal */
final class QueryAdditions
{
    public const string ALIAS_SEPARATOR = '__';

    public const int MAX_ALIAS = 63;

    private const string COLUMN = '/^[A-Za-z_][A-Za-z0-9_]*\z/';

    /** @var array<string, string> */
    private array $columns = [];

    /** @var array<string, Closure|QueryBuilder|EloquentBuilder<Model>> */
    private array $selects = [];

    /** @var array<string, array{string, Closure|null}> */
    private array $loads = [];

    private bool $sealed = false;

    public function __construct(
        private readonly Resource $resource,
        private readonly RenderedLevel $level,
    ) {}

    public function seal(): void
    {
        $this->sealed = true;
    }

    public function column(string $extension, string $column): void
    {
        $this->ensureOpen($extension, __FUNCTION__);

        if (preg_match(self::COLUMN, $column) !== 1) {
            throw InvalidQueryExtensionException::invalidColumn($extension, $column, $this->resource::class);
        }

        $this->columns[$column] ??= $extension;
    }

    /** @param Closure|QueryBuilder|EloquentBuilder<Model> $subquery */
    public function select(string $extension, string $alias, Closure|QueryBuilder|EloquentBuilder $subquery): string
    {
        $this->ensureOpen($extension, __FUNCTION__);

        if (! ExtensionName::matches($alias)) {
            throw InvalidQueryExtensionException::invalidAlias($extension, $alias);
        }

        $final = $extension.self::ALIAS_SEPARATOR.$alias;

        if (strlen($final) > self::MAX_ALIAS) {
            throw InvalidQueryExtensionException::aliasTooLong($extension, $final, $this->resource::class);
        }

        if (isset($this->selects[$final])) {
            throw InvalidQueryExtensionException::aliasRepeated($extension, $final, $this->resource::class);
        }

        if ($this->resource->definition()->fields()->has($final)) {
            throw InvalidQueryExtensionException::aliasNamesField($extension, $final, $this->resource::class);
        }

        $this->selects[$final] = $subquery;

        return $final;
    }

    public function with(string $extension, string $relation, ?Closure $constraint): void
    {
        $this->ensureOpen($extension, __FUNCTION__);

        AugmentedLoad::ensureDeclared($extension, $this->resource, $relation);

        if (isset($this->loads[$relation])) {
            $owner = $this->loads[$relation][0];

            throw $owner === $extension
                ? InvalidQueryExtensionException::relationRepeated($extension, $relation, $this->resource::class)
                : InvalidQueryExtensionException::relationTaken($relation, $this->resource::class, self::sorted([$owner, $extension]));
        }

        $this->loads[$relation] = [$extension, $constraint];
    }

    /** @param EloquentBuilder<Model> $query */
    public function applyTo(EloquentBuilder $query): void
    {
        $loads = $this->keptLoads($query);
        $keys = [];

        foreach (array_keys($loads) as $relation) {
            array_push($keys, ...RelationKeys::between($query->getModel(), Str::before($relation, AugmentedLoad::SEPARATOR))->parent());
        }

        if ($loads !== []) {
            $query->with($loads);
        }

        AddedColumns::merge($query, [...array_keys($this->columns), ...$keys]);

        foreach ($this->selects as $alias => $subquery) {
            $query->addSelect([$alias => $subquery]);
        }
    }

    /**
     * @param  EloquentBuilder<Model>  $query
     * @return array<string, Closure>
     */
    private function keptLoads(EloquentBuilder $query): array
    {
        $loaded = $query->getEagerLoads();
        $kept = [];

        foreach ($this->loads as $relation => [, $constraint]) {
            $segment = Str::before($relation, AugmentedLoad::SEPARATOR);

            if (in_array($segment, $this->level->relations, true) || isset($loaded[$segment])) {
                continue;
            }

            $kept[$relation] = $constraint ?? static function (): void {};
        }

        return $kept;
    }

    private function ensureOpen(string $extension, string $method): void
    {
        if ($this->sealed) {
            throw InvalidQueryExtensionException::lateAddition($extension, $method, $this->resource::class);
        }
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private static function sorted(array $names): array
    {
        sort($names);

        return $names;
    }
}
