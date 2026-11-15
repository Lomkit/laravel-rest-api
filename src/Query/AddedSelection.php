<?php

namespace Lomkit\Rest\Query;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;

/** @internal */
final readonly class AddedSelection
{
    /**
     * @param  array<Expression|string>|null  $planned
     * @param  list<Expression|string>  $prefix
     * @param  non-empty-list<Expression|string>  $columns
     * @param  list<mixed>  $plannedBindings
     * @param  list<mixed>  $bindings
     */
    private function __construct(
        private ?array $planned,
        private array $prefix,
        private array $columns,
        private array $plannedBindings,
        private array $bindings,
    ) {}

    /**
     * @param  array<Expression|string>|null  $planned
     * @param  list<mixed>  $plannedBindings
     */
    public static function between(?array $planned, array $plannedBindings, Builder $augmented): ?self
    {
        $columns = array_values($augmented->columns ?? []);
        $bindings = $augmented->bindings['select'];
        $prefix = $planned === null ? array_slice($columns, 0, 1) : array_values($planned);
        $added = array_slice($columns, count($prefix));

        if ($added === [] || array_slice($columns, 0, count($prefix)) !== $prefix || array_slice($bindings, 0, count($plannedBindings)) !== $plannedBindings) {
            return null;
        }

        return new self($planned, $prefix, $added, $plannedBindings, array_slice($bindings, count($plannedBindings)));
    }

    public function removeFrom(Builder $query): bool
    {
        $columns = array_values($query->columns ?? []);
        $bindings = $query->bindings['select'];
        $augmented = [...$this->plannedBindings, ...$this->bindings];

        if (array_slice($bindings, 0, count($augmented)) !== $augmented || array_any($this->columns, static fn (Expression|string $column): bool => ! in_array($column, $columns, true))) {
            return false;
        }

        $remaining = array_values(array_filter($columns, fn (Expression|string $column): bool => ! in_array($column, $this->columns, true)));
        $query->columns = $remaining === $this->prefix ? $this->planned : $remaining;
        $query->bindings['select'] = [...$this->plannedBindings, ...array_slice($bindings, count($augmented))];

        return true;
    }
}
