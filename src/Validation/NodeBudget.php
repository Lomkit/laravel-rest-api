<?php

namespace Lomkit\Rest\Validation;

use Illuminate\Support\Str;

final class NodeBudget
{
    private int $entriesLeft;

    private int $filtersLeft;

    private int $relationsLeft;

    private int $patternsLeft;

    /** @var array<string, true> */
    private array $reported = [];

    private readonly Violations $violations;

    /** @var array<string, true> */
    private array $refused = [];

    public function __construct(
        private readonly int $entries = PHP_INT_MAX,
        private readonly int $filters = PHP_INT_MAX,
        private readonly int $relations = PHP_INT_MAX,
        private readonly int $patterns = PHP_INT_MAX,
    ) {
        $this->entriesLeft = $entries;
        $this->filtersLeft = $filters;
        $this->relationsLeft = $relations;
        $this->patternsLeft = $patterns;
        $this->violations = new Violations;
    }

    public static function of(Limits $limits): self
    {
        return new self($limits->totals->entries, $limits->totals->filters, $limits->totals->relations, $limits->totals->patterns);
    }

    public function admit(string $key, int $count, int $max, bool $filters, FilterCounts $counts = new FilterCounts, int $runs = 1): bool
    {
        if ($count > $max) {
            return $this->refuse($key, Violation::tooMany($key, $max));
        }

        if ($filters && $count > $this->filtersLeft) {
            return $this->overTotal($key, Violation::tooManyFilters($key, $this->filters));
        }

        if ($counts->relations > $this->relationsLeft) {
            return $this->overTotal($key, Violation::tooManyRelationFilters($key, $this->relations));
        }

        if ($counts->patterns > $this->patternsLeft) {
            return $this->overTotal($key, Violation::tooManyPatternFilters($key, $this->patterns));
        }

        if ($count > intdiv($this->entriesLeft, $runs)) {
            return $this->overTotal($key, Violation::tooManyEntries($key, $this->entries));
        }

        $this->entriesLeft -= $count * $runs;
        $this->relationsLeft -= $counts->relations;
        $this->patternsLeft -= $counts->patterns;

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

    private function overTotal(string $key, Violation $violation): bool
    {
        $reported = isset($this->reported[$violation->message]);
        $this->reported[$violation->message] = true;

        return $this->refuse($key, $reported ? null : $violation);
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
