<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Rest\Exceptions\EngineSearchException;
use Lomkit\Rest\Text\Engine\ScoutModel;
use ReflectionMethod;
use Throwable;

/** @internal */
final readonly class TypesenseLongSearch
{
    public const int MAX_QUERY_STRING = 3500;

    public function __construct(private TypesenseEngine $engine) {}

    /** @param Builder<Model> $builder */
    public function route(Builder $builder, ScoutModel $model, int $perPage, int $page): void
    {
        $parameters = $this->engine->buildSearchParameters($builder, $page, $perPage);

        if (isset($parameters['vector_query']) || self::queryString($parameters) <= self::MAX_QUERY_STRING) {
            return;
        }

        $collection = $builder->index ?? $model->indexName();

        $builder->callback = fn (mixed $documents, mixed $query, array $options): array => $this->search($collection, $options);
    }

    /** @param array<mixed> $parameters */
    private static function queryString(array $parameters): int
    {
        return strlen(http_build_query(array_map(static fn (mixed $value): mixed => is_bool($value) ? ($value ? 'true' : 'false') : $value, $parameters)));
    }

    /**
     * @param  array<mixed>  $options
     * @return array<mixed>
     */
    private function search(string $collection, array $options): array
    {
        $search = [...array_filter($options, static fn (mixed $value): bool => $value !== null), 'collection' => $collection];
        $multiSearch = $this->engine->__call('getMultiSearch', []);
        $results = is_object($multiSearch) && method_exists($multiSearch, 'perform') ? $multiSearch->perform(['searches' => [$search]]) : null;
        $result = is_array($results) && is_array($results['results'] ?? null) ? ($results['results'][0] ?? null) : null;

        if (! is_array($result)) {
            throw EngineSearchException::unexpectedMultiSearch($collection);
        }

        return isset($result['error']) ? throw EngineSearchException::typesenseMultiSearch($collection, $result, $this->clientError($result)) : $result;
    }

    /** @param array<mixed> $result */
    private function clientError(array $result): ?Throwable
    {
        $error = (new ReflectionMethod($this->engine, 'marshalMultiSearchException'))->invoke($this->engine, $result);

        return $error instanceof Throwable ? $error : null;
    }
}
