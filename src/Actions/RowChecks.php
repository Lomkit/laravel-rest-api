<?php

namespace Lomkit\Rest\Actions;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Authorization\Authorizer;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Authorization\Decision;
use Lomkit\Rest\Authorization\Denial;
use Lomkit\Rest\Exceptions\AuthorizationDenied;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Resource;

final readonly class RowChecks
{
    /** @param Closure(Model): string $path */
    private function __construct(
        private Authorizer $authorizer,
        private Resource $resource,
        private string $ability,
        private Closure $path,
        private bool $skip,
    ) {}

    public static function of(OperateRequest $request, Action $action): ?self
    {
        $ability = $action->authorizeEach();

        if ($ability === null) {
            return null;
        }

        return new self(
            $request->context()->authorizer(),
            $request->resource,
            $ability,
            self::paths($request, $action),
            $action->skipUnauthorized,
        );
    }

    /** @param Closure(Closure(Collection<int, Model>): int): int $read */
    public function verify(Closure $read): void
    {
        if ($this->skip) {
            return;
        }

        $denials = [];

        $read(function (Collection $models) use (&$denials): int {
            array_push($denials, ...$this->denials($models));

            return $models->count();
        });

        if ($denials !== []) {
            throw new AuthorizationDenied($denials);
        }
    }

    /**
     * @param  Collection<array-key, Model>  $models
     * @return Collection<array-key, Model>
     */
    public function filter(Collection $models): Collection
    {
        if (! $this->skip) {
            return $models;
        }

        $models = $models->values();
        $decisions = $this->decide($this->checks($models));

        return $models
            ->filter(fn (Model $model, int $index): bool => $decisions[$index]->allowed)
            ->values();
    }

    /**
     * @param  Collection<int, Model>  $models
     * @return list<Denial>
     */
    private function denials(Collection $models): array
    {
        $checks = $this->checks($models);
        $denials = [];

        foreach ($this->decide($checks) as $index => $decision) {
            if (! $decision->allowed) {
                $denials[] = Denial::of($checks[$index], $decision);
            }
        }

        return $denials;
    }

    /**
     * @param  Collection<int, Model>  $models
     * @return list<Check>
     */
    private function checks(Collection $models): array
    {
        return array_values($models->map(fn (Model $model): Check => new Check($this->resource, $this->ability, $model, ($this->path)($model)))->all());
    }

    /**
     * @param  list<Check>  $checks
     * @return list<Decision>
     */
    private function decide(array $checks): array
    {
        $decisions = $this->authorizer->batch($checks, fn (): array => array_map(
            fn (Check $check): Decision => Decision::fromResponse($this->resource->authorizedTo($this->ability, $check->subject)),
            $checks,
        ));

        foreach ($checks as $check) {
            if ($check->subject instanceof Model) {
                $this->authorizer->forget($check->subject);
            }
        }

        return $decisions;
    }

    /** @return Closure(Model): string */
    private static function paths(OperateRequest $request, Action $action): Closure
    {
        if ($action->target !== ActionTarget::Resources) {
            return fn (Model $model): string => 'search';
        }

        $position = $request->payload()->keys()->positions();

        return fn (Model $model): string => 'resources.'.$position($model);
    }
}
