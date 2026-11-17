<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Text\Engine\Profiles\ListsWithoutText;
use Lomkit\Rest\Text\Engine\Profiles\RanksByKey;

/** @internal */
final readonly class ListOrder
{
    public function __construct(
        private ListsWithoutText $profile,
        public ?TieBreaker $tieBreaker,
    ) {}

    public function sortKeys(): int
    {
        $keys = $this->profile->listSortKeys();

        return $this->tieBreaker !== null && $keys > 1 && $keys !== PHP_INT_MAX ? $keys - 1 : $keys;
    }

    /**
     * @param  list<array{string, SortDirection}>  $orders
     * @return list<array{string, SortDirection}>
     */
    public function orders(array $orders, string $key, IndexAttributes $attributes): array
    {
        $tieBreaker = $this->tieBreaker($key, $attributes, $orders === []);

        if ($tieBreaker === null || in_array($tieBreaker, array_column($orders, 0), true)) {
            return $orders;
        }

        return $orders === [] || $this->profile->listSortKeys() > 1 ? [...$orders, [$tieBreaker, SortDirection::Desc]] : $orders;
    }

    private function tieBreaker(string $key, IndexAttributes $attributes, bool $unsorted): ?string
    {
        return match (true) {
            $this->profile->listSortKeys() < 1 => null,
            $this->tieBreaker !== null => $this->tieBreaker->attribute,
            $this->profile instanceof RanksByKey => $key,
            $unsorted && $attributes->sorts($key) => $key,
            default => null,
        };
    }
}
