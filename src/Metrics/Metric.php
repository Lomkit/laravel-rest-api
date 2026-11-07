<?php

namespace Lomkit\Rest\Metrics;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use JsonSerializable;
use Lomkit\Rest\Concerns\HasMeta;
use Lomkit\Rest\Concerns\HasRouteMiddleware;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Http\Requests\RestRequest;

/** @phpstan-consistent-constructor */
abstract class Metric implements JsonSerializable
{
    use HasMeta;
    use HasRouteMiddleware;
    use Makeable;
    use Resourcable;

    public ?string $name = null;

    public bool $withoutSearching = false;

    abstract public function type(): string;

    /** @param Builder<Model> $query */
    abstract public function calculate(Builder $query): MetricResult;

    public function name(): string
    {
        return $this->name ?: Str::of(class_basename(static::class))->beforeLast('Metric')->snake(' ')->title()->toString();
    }

    public function uriKey(): string
    {
        return Str::slug($this->name(), '-', null);
    }

    public function request(): RestRequest
    {
        return $this->resource->request();
    }

    public function withoutSearching(): static
    {
        $this->withoutSearching = true;

        return $this;
    }

    /** @return list<string> */
    public function abilities(): array
    {
        return ['viewAny'];
    }

    public function ensureValid(): static
    {
        $type = $this->type();

        if (preg_match('/^[a-z][a-z0-9-]*$/', $type) !== 1) {
            throw InvalidMetricException::invalidType(static::class, $type);
        }

        return $this;
    }

    /** @return array{name: string, uriKey: string, type: string, meta: \stdClass} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name(),
            'uriKey' => $this->uriKey(),
            'type' => $this->type(),
            'meta' => (object) $this->getMeta(),
        ];
    }
}
