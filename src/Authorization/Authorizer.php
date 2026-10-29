<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Http\Resource;

final readonly class Authorizer
{
    public function __construct(
        private Gate $gate,
        private DecisionStore $store = new NullDecisionStore,
        private DecisionMemo $memo = new DecisionMemo,
    ) {}

    public function inspect(Check $check): Decision
    {
        return $this->inspectAll([$check])[0];
    }

    /**
     * @param  list<Check>  $checks
     * @return list<Decision>
     */
    public function inspectAll(array $checks): array
    {
        $keys = array_map(self::keyOf(...), $checks);
        $decisions = $this->resolve($this->pending($checks, $keys));

        return array_map(
            fn (Check $check, ?string $key): Decision => $key === null
                ? Decision::allow()
                : $this->memo->remember($key, $check, fn (): Decision => $decisions[$key]),
            $checks,
            $keys,
        );
    }

    public function authorize(Check $check): void
    {
        $this->inspect($check)->toResponse()->authorize();
    }

    /** @param list<Check> $checks */
    public function authorizeAll(array $checks): void
    {
        foreach ($this->inspectAll($checks) as $decision) {
            $decision->toResponse()->authorize();
        }
    }

    /**
     * @template TKey of array-key
     *
     * @param  Collection<TKey, Model>  $models
     * @return array<TKey, Decision>
     */
    public function inspectEach(Resource $resource, string $ability, Collection $models): array
    {
        $checks = $models->map(fn (Model $model): Check => new Check($resource, $ability, $model));

        return array_combine($checks->keys()->all(), $this->inspectAll(array_values($checks->all())));
    }

    /** @param Collection<array-key, Model> $models */
    public function authorizeEach(Resource $resource, string $ability, Collection $models, string $path): void
    {
        $checks = $models->map(fn (Model $model, int|string $key): Check => new Check($resource, $ability, $model, $path.'.'.$key));

        $this->authorizeAll(array_values($checks->all()));
    }

    public function forget(Model $model): void
    {
        $this->memo->forget($model);
    }

    private static function keyOf(Check $check): ?string
    {
        return $check->resource->isAuthorizingEnabled() ? $check->key() : null;
    }

    /**
     * @param  list<Check>  $checks
     * @param  list<?string>  $keys
     * @return array<string, Check>
     */
    private function pending(array $checks, array $keys): array
    {
        $pending = [];

        foreach ($checks as $index => $check) {
            $key = $keys[$index];

            if ($key !== null && ! $this->memo->has($key)) {
                $pending[$key] = $check;
            }
        }

        return $pending;
    }

    /**
     * @param  array<string, Check>  $checks
     * @return array<string, Decision>
     */
    private function resolve(array $checks): array
    {
        $decisions = $this->store->many(array_filter($checks, fn (Check $check): bool => ! $this->memo->involvesForgotten($check)));
        $evaluated = array_diff_key($checks, $decisions);

        foreach ($evaluated as $key => $check) {
            $decisions[$key] = Decision::fromResponse($this->gate->inspect($check->ability, $check->arguments()));
        }

        $this->store->putMany($evaluated, $decisions);

        return $decisions;
    }
}
