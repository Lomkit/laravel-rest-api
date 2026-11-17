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
        if ($orders === []) {
            $stable = $this->stable($key, $attributes);

            return $stable === null ? [] : [[$stable, SortDirection::Desc]];
        }

        $tieBreaker = $this->tieBreaker?->attribute;

        if ($tieBreaker === null || $this->profile->listSortKeys() < 2 || in_array($tieBreaker, array_column($orders, 0), true)) {
            return $orders;
        }

        return [...$orders, [$tieBreaker, SortDirection::Desc]];
    }

    private function stable(string $key, IndexAttributes $attributes): ?string
    {
        return match (true) {
            $this->profile->listSortKeys() < 1 => null,
            $this->tieBreaker !== null => $this->tieBreaker->attribute,
            $attributes->sorts($key), $this->profile instanceof RanksByKey => $key,
            default => null,
        };
    }
}
