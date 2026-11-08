<?php

namespace Lomkit\Rest\Validation\Search;

final readonly class RowNode
{
    /** @param list<list<self>> $branches */
    public function __construct(
        public string $key,
        public int $perParent,
        public array $branches,
    ) {}

    /** @param list<self> $nodes */
    public static function sum(array $nodes, int $parents): int
    {
        $rows = 0;

        foreach ($nodes as $node) {
            $rows = self::plus($rows, $node->rows($parents));
        }

        return $rows;
    }

    public static function plus(int $left, int $right): int
    {
        return $left > PHP_INT_MAX - $right ? PHP_INT_MAX : $left + $right;
    }

    public static function times(int $left, int $right): int
    {
        return $left !== 0 && $right > intdiv(PHP_INT_MAX, $left) ? PHP_INT_MAX : $left * $right;
    }

    public function rows(int $parents): int
    {
        $own = $this->own($parents);

        return self::plus($own, self::sum($this->heaviest($own), $own));
    }

    public function own(int $parents): int
    {
        return self::times($parents, $this->perParent);
    }

    /** @return list<self> */
    public function heaviest(int $own): array
    {
        $heaviest = [];
        $rows = -1;

        foreach ($this->branches as $branch) {
            $branchRows = self::sum($branch, $own);

            if ($branchRows > $rows) {
                $heaviest = $branch;
                $rows = $branchRows;
            }
        }

        return $heaviest;
    }
}
