<?php

namespace Lomkit\Rest\Metrics;

use BackedEnum;
use Lomkit\Rest\Authorization\CacheContext;
use Lomkit\Rest\Authorization\UserIdentity;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\PerimeterClause;

final readonly class MetricKey
{
    private const string PREFIX = 'rest.metrics.';

    private function __construct(public string $parts) {}

    public static function of(MetricNode $node): self
    {
        $metric = $node->metric;
        $resource = $metric->resource;
        $others = self::others($resource, $node->reached);

        $parts = [
            $resource::class,
            $metric::class,
            $metric->uriKey(),
            self::settings($metric),
            $node->range?->value,
            $metric->timezone()->getName(),
            self::canonical($metric->request()->input('search', [])),
            CacheContext::of($resource->authorizationCacheContext(), $resource::class),
        ];
        $contexts = self::contexts($others);

        if ($contexts !== []) {
            $parts[] = $contexts;
        }

        if ($metric->cacheScope === CacheScope::Global) {
            $parts[] = self::perimeter($resource);

            if ($others !== []) {
                $parts[] = array_map(self::perimeter(...), $others);
            }
        }

        return new self(json_encode($parts, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function for(?UserIdentity $user, string $stamps): string
    {
        return self::PREFIX.hash('sha256', implode('|', [$stamps, $user?->key() ?? 'global', $this->parts]));
    }

    /**
     * @param  list<Resource>  $reached
     * @return array<class-string<Resource>, Resource>
     */
    private static function others(Resource $root, array $reached): array
    {
        $others = [];

        foreach ($reached as $resource) {
            if ($resource::class !== $root::class) {
                $others[$resource::class] ??= $resource;
            }
        }

        ksort($others, SORT_STRING);

        return $others;
    }

    /**
     * @param  array<class-string<Resource>, Resource>  $others
     * @return array<class-string<Resource>, string>
     */
    private static function contexts(array $others): array
    {
        $contexts = [];

        foreach ($others as $class => $resource) {
            $context = $resource->authorizationCacheContext();

            if ($context !== []) {
                $contexts[$class] = CacheContext::of($context, $class);
            }
        }

        return $contexts;
    }

    private static function perimeter(Resource $resource): string
    {
        $query = $resource::newModel()->newQuery();
        (new PerimeterClause($resource))->apply($query);
        $base = $query->getQuery();

        return hash('sha256', serialize([$base->toSql(), $base->getBindings()]));
    }

    /** @return array<int|float|string|bool|null> */
    private static function settings(Metric $metric): array
    {
        $settings = [];

        foreach (get_object_vars($metric) as $name => $value) {
            $value = $value instanceof BackedEnum ? $value->value : $value;

            if ($name !== 'cacheFor' && (is_scalar($value) || $value === null)) {
                $settings[$name] = $value;
            }
        }

        ksort($settings, SORT_STRING);

        return $settings;
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::canonical(...), $value);

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
