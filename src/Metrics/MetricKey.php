<?php

namespace Lomkit\Rest\Metrics;

use BackedEnum;
use Lomkit\Rest\Authorization\CacheContext;
use Lomkit\Rest\Authorization\UserIdentity;

final readonly class MetricKey
{
    private const string PREFIX = 'rest.metrics.';

    private function __construct(public string $parts) {}

    public static function of(MetricNode $node): self
    {
        $metric = $node->metric;
        $resource = $metric->resource;

        return new self(json_encode([
            $resource::class,
            $metric::class,
            $metric->uriKey(),
            self::settings($metric),
            $node->range?->value,
            $metric->timezone()->getName(),
            self::canonical($metric->request()->input('search', [])),
            CacheContext::of($resource->authorizationCacheContext(), $resource::class),
        ], JSON_THROW_ON_ERROR));
    }

    public function for(?UserIdentity $user, string $stamps): string
    {
        return self::PREFIX.hash('sha256', implode('|', [$stamps, $user?->key() ?? 'global', $this->parts]));
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
