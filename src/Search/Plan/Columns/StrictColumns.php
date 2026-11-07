<?php

namespace Lomkit\Rest\Search\Plan\Columns;

use Closure;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\MissingColumnException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Search\Plan\QueryPlan;

final readonly class StrictColumns
{
    /** @param class-string<Resource>|null $resource */
    private function __construct(private ?string $resource) {}

    public static function of(QueryPlan $plan): self
    {
        return new self($plan->columns === null ? null : $plan->resource::class);
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $call
     * @return TResult
     */
    public function run(Closure $call): mixed
    {
        if ($this->resource === null) {
            return $call();
        }

        $previous = Model::preventsAccessingMissingAttributes();
        Model::preventAccessingMissingAttributes();

        try {
            return $call();
        } catch (MissingAttributeException $exception) {
            throw MissingColumnException::readOn($this->resource, $exception);
        } finally {
            Model::preventAccessingMissingAttributes($previous);
        }
    }
}
