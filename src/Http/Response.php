<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Lomkit\Rest\Authorization\Authorizer;
use Lomkit\Rest\Http\Serialization\GatePrefetch;
use Lomkit\Rest\Http\Serialization\GateReach;
use Lomkit\Rest\Http\Serialization\PageEnvelope;
use Lomkit\Rest\Http\Serialization\PlainEnvelope;
use Lomkit\Rest\Http\Serialization\RowSerializer;
use Lomkit\Rest\Search\Plan\Columns\StrictColumns;
use Lomkit\Rest\Search\Plan\IncludeOverflow;
use Lomkit\Rest\Search\Plan\QueryPlan;

class Response implements Responsable
{
    private QueryPlan $plan;

    /** @var LengthAwarePaginator<int, Model>|Collection<int, Model>|Model */
    private LengthAwarePaginator|Collection|Model $responsable;

    private ?IncludeOverflow $overflow = null;

    public function plan(QueryPlan $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    public function overflow(IncludeOverflow $overflow): static
    {
        $this->overflow = $overflow;

        return $this;
    }

    /** @param LengthAwarePaginator<int, Model>|Collection<int, Model>|Model $responsable */
    public function responsable(LengthAwarePaginator|Collection|Model $responsable): static
    {
        $this->responsable = $responsable;

        return $this;
    }

    #[\Override]
    public function toResponse(mixed $request): JsonResponse
    {
        $models = $this->models();
        $reach = GateReach::of($this->plan, $models);
        $rows = RowSerializer::of($this->plan, reach: $reach, overflow: $this->overflow);
        $envelope = $this->responsable instanceof LengthAwarePaginator
            ? new PageEnvelope($this->responsable)
            : new PlainEnvelope($this->responsable);

        return $this->authorizer()->batch(
            GatePrefetch::page($this->plan, $models, $reach),
            fn (): JsonResponse => new JsonResponse($envelope->wrap(fn (Model $model): array => $this->render($model, $rows), $rows->meta())),
        );
    }

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, Model>  $models
     * @return array<TKey, array<string, mixed>>
     */
    public function rows(array $models): array
    {
        $reach = GateReach::of($this->plan, array_values($models));
        $rows = RowSerializer::of($this->plan, reach: $reach, overflow: $this->overflow);

        return $this->authorizer()->batch(
            GatePrefetch::rows($this->plan, array_values($models), $reach),
            fn (): array => array_map(fn (Model $model): array => $this->render($model, $rows), $models),
        );
    }

    /**
     * @param  array<string, mixed>  $responseModel
     * @return array<string, mixed>
     */
    protected function map(Model $model, array $responseModel): array
    {
        return $responseModel;
    }

    /** @return array<string, mixed> */
    private function render(Model $model, RowSerializer $rows): array
    {
        $row = $rows->row($model);

        return StrictColumns::of($this->plan)->run(fn (): array => $this->map($model, $row));
    }

    private function authorizer(): Authorizer
    {
        return $this->plan->resource
            ->context()
            ->authorizer();
    }

    /** @return list<Model> */
    private function models(): array
    {
        return match (true) {
            $this->responsable instanceof Model => [$this->responsable],
            $this->responsable instanceof LengthAwarePaginator => array_values(array_filter($this->responsable->items(), static fn (mixed $model): bool => $model instanceof Model)),
            default => array_values($this->responsable->all()),
        };
    }
}
