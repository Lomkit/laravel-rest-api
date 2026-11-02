<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Lomkit\Rest\Http\Serialization\PageEnvelope;
use Lomkit\Rest\Http\Serialization\PlainEnvelope;
use Lomkit\Rest\Http\Serialization\RowSerializer;
use Lomkit\Rest\Search\Plan\QueryPlan;

class Response implements Responsable
{
    private QueryPlan $plan;

    /** @var LengthAwarePaginator<int, Model>|Collection<int, Model>|Model */
    private LengthAwarePaginator|Collection|Model $responsable;

    public function plan(QueryPlan $plan): static
    {
        $this->plan = $plan;

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
        $rows = RowSerializer::of($this->plan);
        $envelope = $this->responsable instanceof LengthAwarePaginator
            ? new PageEnvelope($this->responsable)
            : new PlainEnvelope($this->responsable);

        return new JsonResponse($envelope->wrap(fn (Model $model): array => $this->map($model, $rows->row($model)), $rows->meta()));
    }

    /**
     * @param  array<string, mixed>  $responseModel
     * @return array<string, mixed>
     */
    protected function map(Model $model, array $responseModel): array
    {
        return $responseModel;
    }
}
