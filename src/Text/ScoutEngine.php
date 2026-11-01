<?php

namespace Lomkit\Rest\Text;

use Closure;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\Engine\EngineIndex;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\IndexAttributes;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfiles;
use Lomkit\Rest\Text\Engine\ScoutModel;

final readonly class ScoutEngine implements EngineTextSearch
{
    /**
     * @param  array<mixed>  $filterable
     * @param  array<mixed>  $sortable
     * @param  (Closure(EngineQuery, RestRequest): mixed)|null  $perimeter
     * @param  array<mixed>  $perimeterAttributes
     */
    private function __construct(
        private array $filterable = [],
        private array $sortable = [],
        private ?Closure $perimeter = null,
        private array $perimeterAttributes = [],
        private bool $withoutPerimeter = false,
    ) {}

    public static function make(): self
    {
        return new self;
    }

    /** @param array<mixed> $attributes */
    public function filterable(array $attributes): self
    {
        return new self($attributes, $this->sortable, $this->perimeter, $this->perimeterAttributes, $this->withoutPerimeter);
    }

    /** @param array<mixed> $attributes */
    public function sortable(array $attributes): self
    {
        return new self($this->filterable, $attributes, $this->perimeter, $this->perimeterAttributes, $this->withoutPerimeter);
    }

    /**
     * @param  Closure(EngineQuery, RestRequest): mixed  $perimeter
     * @param  array<mixed>  $attributes
     */
    public function perimeter(Closure $perimeter, array $attributes = []): self
    {
        return new self($this->filterable, $this->sortable, $perimeter, $attributes, $this->withoutPerimeter);
    }

    public function withoutPerimeter(): self
    {
        return new self($this->filterable, $this->sortable, $this->perimeter, $this->perimeterAttributes, true);
    }

    #[\Override]
    public function capabilities(TextTarget $target): TextCapabilities
    {
        return $this->index($target)->capabilities();
    }

    #[\Override]
    public function query(TextQuery $text, TextTarget $target, RestRequest $request, ?Trashed $trashed = null): EngineQuery
    {
        $query = new EngineQuery($this->index($target), $text, $trashed);

        if ($this->perimeter !== null) {
            ($this->perimeter)($query, $request);
        }

        return $query;
    }

    private function index(TextTarget $target): EngineIndex
    {
        if ($this->perimeter === null && ! $this->withoutPerimeter) {
            throw InvalidTextSearchException::missingPerimeter($target->resource);
        }

        if ($this->perimeter !== null && $this->withoutPerimeter) {
            throw InvalidTextSearchException::conflictingPerimeter($target->resource);
        }

        $model = ScoutModel::tryFrom($target->model) ?? throw InvalidTextSearchException::unsearchableModel($target->resource, $target->model::class);
        $profile = EngineProfiles::for($model->engine(), $target->resource);

        $profile->ensureUsableBy($target);

        $attributes = IndexAttributes::of($target, $profile, $this->filterable, $this->sortable, $this->perimeterAttributes);

        return new EngineIndex($model, $profile, $attributes);
    }
}
