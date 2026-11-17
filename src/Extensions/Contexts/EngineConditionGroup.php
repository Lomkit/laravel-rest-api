<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Closure;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Text\Engine\Groups\FilterLeaf;
use Lomkit\Rest\Text\Engine\Groups\FilterTree;
use Lomkit\Rest\Text\Engine\IndexLeaves;

final class EngineConditionGroup
{
    /** @var list<FilterTree|FilterLeaf|bool> */
    private array $members = [];

    private function __construct(
        private readonly IndexLeaves $leaves,
        private readonly bool $any,
    ) {}

    /**
     * @internal
     *
     * @param  Closure(self): mixed  $conditions
     */
    public static function compile(IndexLeaves $leaves, bool $any, Closure $conditions): FilterTree|FilterLeaf|bool
    {
        $group = new self($leaves, $any);

        $conditions($group);

        return $group->folded();
    }

    public function where(string $attribute, FilterOperator|string $operator, mixed $value): static
    {
        $this->members[] = $this->leaves->compare($attribute, $operator, $value);

        return $this;
    }

    /** @param array<mixed> $values */
    public function whereIn(string $attribute, array $values): static
    {
        $this->members[] = $this->leaves->members($attribute, FilterOperator::In, $values);

        return $this;
    }

    /** @param array<mixed> $values */
    public function whereNotIn(string $attribute, array $values): static
    {
        $this->members[] = $this->leaves->members($attribute, FilterOperator::NotIn, $values);

        return $this;
    }

    /** @param Closure(self): mixed $group */
    public function whereAll(Closure $group): static
    {
        $this->members[] = self::compile($this->leaves, false, $group);

        return $this;
    }

    /** @param Closure(self): mixed $group */
    public function whereAny(Closure $group): static
    {
        $this->members[] = self::compile($this->leaves, true, $group);

        return $this;
    }

    private function folded(): FilterTree|FilterLeaf|bool
    {
        if ($this->members === []) {
            return false;
        }

        if (in_array($this->any, $this->members, true)) {
            return $this->any;
        }

        $members = array_values(array_filter($this->members, fn (FilterTree|FilterLeaf|bool $member): bool => ! is_bool($member)));

        return match (count($members)) {
            0 => ! $this->any,
            1 => $members[0],
            default => new FilterTree($this->any, $members),
        };
    }
}
