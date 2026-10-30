<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Exceptions\AuthorizationDenied;
use Lomkit\Rest\Exceptions\UnplannedCheckException;
use Lomkit\Rest\Http\Resource;

final class Authorizer
{
    /** @var array<class-string<Resource>, true> */
    private array $entered = [];

    public function __construct(
        private readonly Gate $gate,
        private readonly DecisionStore $store = new NullDecisionStore,
        private readonly DecisionMemo $memo = new DecisionMemo,
        private readonly bool $strict = false,
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

    /** @param list<Check> $checks */
    public function enter(Resource $resource, array $checks): void
    {
        $this->plan($checks);

        $this->entered[$resource::class] = true;
    }

    /** @param list<Check> $checks */
    public function plan(array $checks): void
    {
        self::enforce($checks, array_map($this->planned(...), $checks));
    }

    public function ensurePlanned(Check $check): void
    {
        if ($this->isPlanned($check)) {
            $this->plan([$check]);

            return;
        }

        if ($check->ability === 'viewAny' && isset($this->entered[$check->resource::class])) {
            return;
        }

        if ($this->strict) {
            throw UnplannedCheckException::for($check);
        }

        $this->plan([$check]);
    }

    public function forget(Model $model): void
    {
        $this->memo->forget($model);
    }

    /**
     * @param  list<Check>  $checks
     * @param  list<Decision>  $decisions
     */
    private static function enforce(array $checks, array $decisions): void
    {
        $denials = [];

        foreach ($decisions as $index => $decision) {
            if (! $decision->allowed) {
                $denials[] = Denial::of($checks[$index], $decision);
            }
        }

        if ($denials !== []) {
            throw new AuthorizationDenied($denials);
        }
    }

    private static function keyOf(Check $check): ?string
    {
        return $check->resource->isAuthorizingEnabled() ? $check->key() : null;
    }

    private function isPlanned(Check $check): bool
    {
        $key = self::keyOf($check);

        return $key === null || $this->memo->has($key);
    }

    private function planned(Check $check): Decision
    {
        $key = self::keyOf($check);
        $decide = fn (): Decision => Decision::fromResponse($check->resource->authorizedTo($check->ability, $check->subject));

        return $key === null ? $decide() : $this->memo->remember($key, $check, $decide);
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
