<?php

namespace Lomkit\Rest\Validation\Search;

use Illuminate\Support\Str;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Search\AggregateKey;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;

final readonly class RowKeys
{
    public const string PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * @param  list<string>  $fields
     * @param  list<string>  $relations
     * @param  list<string>  $reserved
     * @param  list<string>  $aliases
     * @param  array<string, true>  $includes
     * @param  list<string>  $aggregates
     */
    private function __construct(
        private array $fields,
        private array $relations,
        private array $reserved,
        private array $aliases,
        private array $includes,
        private array $aggregates,
    ) {}

    /**
     * @param  list<Cursor>  $includes
     * @param  list<Cursor>  $aggregates
     */
    public static function of(ValidationContext $context, array $includes, array $aggregates): self
    {
        $resource = $context->resource;
        $request = $resource->context()->request;

        return new self(
            $resource->getFields($request)->names(),
            array_column($resource->getRelations($request), 'relation'),
            [$resource->context()->config()->gatesKey],
            self::aliases($includes),
            self::includes($includes),
            self::aggregates($aggregates),
        );
    }

    public static function includeKey(string $relation): string
    {
        return Str::snake(Str::before($relation, '.'));
    }

    public function isReserved(string $key): bool
    {
        return in_array($key, $this->reserved, true);
    }

    public function isField(string $key): bool
    {
        return in_array($key, $this->fields, true);
    }

    public function isRelation(string $key): bool
    {
        return in_array($key, $this->relations, true);
    }

    public function includeUses(string $key): int
    {
        return count(array_keys($this->aliases, $key, true)) + (isset($this->includes[$key]) ? 1 : 0);
    }

    public function aggregateUses(string $key): int
    {
        return count(array_keys($this->aggregates, $key, true));
    }

    /**
     * @param  list<Cursor>  $includes
     * @return list<string>
     */
    private static function aliases(array $includes): array
    {
        $aliases = [];

        foreach ($includes as $include) {
            $alias = $include->at('alias')->value;

            if (is_string($alias)) {
                $aliases[] = $alias;
            }
        }

        return $aliases;
    }

    /**
     * @param  list<Cursor>  $includes
     * @return array<string, true>
     */
    private static function includes(array $includes): array
    {
        $keys = [];

        foreach ($includes as $include) {
            $relation = $include->at('relation')->value;

            if (! $include->at('alias')->present && is_string($relation)) {
                $keys[self::includeKey($relation)] = true;
            }
        }

        return $keys;
    }

    /**
     * @param  list<Cursor>  $aggregates
     * @return list<string>
     */
    private static function aggregates(array $aggregates): array
    {
        $keys = [];

        foreach ($aggregates as $aggregate) {
            $key = self::aggregateKey($aggregate);

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private static function aggregateKey(Cursor $aggregate): ?string
    {
        $alias = $aggregate->at('alias');

        if ($alias->present) {
            return is_string($alias->value) ? $alias->value : null;
        }

        $relation = $aggregate->at('relation')->value;
        $type = $aggregate->at('type')->value;
        $function = is_string($type) ? AggregateFunction::tryFrom($type) : null;

        if (! is_string($relation) || $function === null) {
            return null;
        }

        if (! $function->needsField()) {
            return AggregateKey::named($relation, $function, null, null)->value;
        }

        $field = $aggregate->at('field')->value;

        return is_string($field) ? AggregateKey::named($relation, $function, $field, null)->value : null;
    }
}
