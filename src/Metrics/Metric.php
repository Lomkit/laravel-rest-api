<?php

namespace Lomkit\Rest\Metrics;

use Carbon\CarbonInterface;
use DateTimeZone;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Lomkit\Rest\Concerns\HasMeta;
use Lomkit\Rest\Concerns\HasRouteMiddleware;
use Lomkit\Rest\Concerns\Makeable;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Http\Details\DetailsSelection;
use Lomkit\Rest\Http\Requests\MetricRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Support\DeclarationName;

abstract class Metric
{
    use HasCache;
    use HasMeta;
    use HasRouteMiddleware;
    use Makeable;
    use Resourcable;

    public ?string $name = null;

    public bool $withoutSearching = false;

    public int $weekStartsOn = CarbonInterface::MONDAY;

    abstract public function type(): string;

    /** @param Builder<Model> $query */
    abstract public function calculate(Builder $query): MetricResult;

    public function name(): string
    {
        return DeclarationName::of($this->name, static::class, self::class);
    }

    public function uriKey(): string
    {
        return DeclarationName::uriKey($this->name());
    }

    public function request(): RestRequest
    {
        return $this->resource->request();
    }

    /** @return array<int, string> */
    public function ranges(): array
    {
        return [];
    }

    public function schema(): ?Type
    {
        return null;
    }

    public function range(): ?MetricRange
    {
        $request = $this->request();

        return $request instanceof MetricRequest ? $request->payload()->range : null;
    }

    public function timezone(): DateTimeZone
    {
        $request = $this->request();
        $zone = $request instanceof MetricRequest ? $request->payload()->timezone : null;

        return new DateTimeZone($zone ?? Config::string('app.timezone'));
    }

    /** @param Builder<Model> $query */
    public function periods(Builder $query, string $dateColumn, PeriodUnit $unit, ?int $count = null): Periods
    {
        return $this->periodsFor('periods', $query, $dateColumn, $unit, $count);
    }

    public function withoutSearching(): static
    {
        $this->withoutSearching = true;

        return $this;
    }

    public function weekStartsOn(int $day): static
    {
        $this->weekStartsOn = $day;

        return $this;
    }

    /** @return list<string> */
    public function abilities(): array
    {
        return ['viewAny'];
    }

    /** @return list<string> */
    final public function entryAbilities(): array
    {
        $abilities = $this->abilities();

        return $abilities === [] ? [] : array_values(array_unique(['viewAny', ...$this->resource->abilities(Endpoint::Metric), ...$abilities]));
    }

    public function ensureValid(): static
    {
        $type = $this->type();

        if (preg_match('/^[a-z][a-z0-9-]*$/', $type) !== 1) {
            throw InvalidMetricException::invalidType(static::class, $type);
        }

        if ($this->weekStartsOn < CarbonInterface::SUNDAY || $this->weekStartsOn > CarbonInterface::SATURDAY) {
            throw InvalidMetricException::invalidWeekStart(static::class, $this->weekStartsOn);
        }

        MetricRange::listOf($this->ranges(), static::class);
        $this->ensureValidCache();

        return $this;
    }

    /** @param Builder<Model> $query */
    protected function periodsFor(string $helper, Builder $query, string $dateColumn, PeriodUnit $unit, ?int $count): Periods
    {
        $date = MetricColumn::period($this, $query, $helper, $dateColumn);
        $count ??= $this->range()->value ?? throw InvalidMetricException::withoutPeriods(static::class, $helper);

        return Periods::last($query, $date, $unit, $count, $this->timezone(), $this->weekStartsOn);
    }

    /** @return array<string, mixed> */
    public function toDetails(DetailsSelection $selection): array
    {
        return [
            'name' => $this->name(),
            'uriKey' => $this->uriKey(),
            'type' => $this->type(),
            'ranges' => MetricRange::listOf($this->ranges(), static::class),
            ...$selection->part('metrics.meta', fn (): array => ['meta' => (object) $this->getMeta()]),
        ];
    }
}
