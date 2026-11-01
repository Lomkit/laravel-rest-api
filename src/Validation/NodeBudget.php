<?php

namespace Lomkit\Rest\Validation;

use Illuminate\Support\Str;

final class NodeBudget
{
    private int $nodesLeft;

    private int $filtersLeft;

    private bool $nodesReported = false;

    private bool $filtersReported = false;

    private readonly Violations $violations;

    /** @var array<string, true> */
    private array $refused = [];

    public function __construct(
        private readonly int $nodes = PHP_INT_MAX,
        private readonly int $filterNodes = PHP_INT_MAX,
    ) {
        $this->nodesLeft = $nodes;
        $this->filtersLeft = $filterNodes;
        $this->violations = new Violations;
    }

    public static function of(Limits $limits): self
    {
        return new self($limits->nodes, $limits->filterNodes);
    }

    public function admit(string $key, int $count, int $max, bool $filters): bool
    {
        if ($count > $max) {
            return $this->refuse($key, Violation::tooMany($key, $max));
        }

        if ($filters && $count > $this->filtersLeft) {
            $reported = $this->filtersReported;
            $this->filtersReported = true;

            return $this->refuse($key, $reported ? null : Violation::tooManyFilters($key, $this->filterNodes));
        }

        if ($count > $this->nodesLeft) {
            $reported = $this->nodesReported;
            $this->nodesReported = true;

            return $this->refuse($key, $reported ? null : Violation::tooManyEntries($key, $this->nodes));
        }

        $this->nodesLeft -= $count;

        if ($filters) {
            $this->filtersLeft -= $count;
        }

        return true;
    }

    public function refused(string $key): bool
    {
        return isset($this->refused[$key]);
    }

    public function close(string $key): void
    {
        $this->refused[$key] = true;
    }

    /**
     * @template T
     *
     * @param  array<string, T>  $wildcards
     * @return array<string, T>
     */
    public function open(array $wildcards): array
    {
        return array_filter($wildcards, fn (string $key): bool => ! $this->refused(Str::before($key, '.*')), ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, list<mixed>>
     */
    public function applyTo(array $rules): array
    {
        return $this->violations->applyTo($rules);
    }

    private function refuse(string $key, ?Violation $violation): bool
    {
        $this->refused[$key] = true;

        if ($violation !== null) {
            $this->violations->add($violation);
        }

        return false;
    }
}
