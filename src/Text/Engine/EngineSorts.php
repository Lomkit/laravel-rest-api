<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Text\Engine\Profiles\ListsWithoutText;

/** @internal */
final class EngineSorts
{
    /** @var list<array{string, SortDirection}> */
    private array $orders = [];

    public function __construct(
        private readonly EngineIndex $index,
        private readonly bool $listing,
    ) {}

    public function add(string $attribute, SortDirection|string $direction): void
    {
        if (! $this->index->attributes->sorts($attribute)) {
            throw InvalidEngineQueryException::notSortable($attribute);
        }

        if (count($this->orders) >= $this->keys()) {
            throw InvalidEngineQueryException::tooManySorts($this->keys());
        }

        $this->orders[] = [$attribute, SortDirection::parse($direction)];
    }

    /** @return list<array{string, SortDirection}> */
    public function all(): array
    {
        return $this->orders;
    }

    private function keys(): int
    {
        $profile = $this->index->profile;

        return $this->listing && $profile instanceof ListsWithoutText ? $profile->listSortKeys() : $profile->sortKeys();
    }
}
