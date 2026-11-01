<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;

final readonly class EnginePaging
{
    public function __construct(private EngineProfile $profile) {}

    public function exactTotal(): bool
    {
        return $this->profile->exactTotal();
    }

    public function window(): ?int
    {
        return $this->profile->window();
    }

    public function pageSize(int $wanted): int
    {
        $bounds = array_filter([$this->profile->maxPageSize(), $this->profile->window()], is_int(...));

        return max(1, min([$wanted, ...$bounds]));
    }

    public function reaches(int $perPage, int $page): bool
    {
        $window = $this->profile->window();

        return $window === null || $perPage * $page <= $window;
    }

    public function ensure(int $perPage, int $page): void
    {
        $maximum = $this->profile->maxPageSize();

        if ($perPage < 1 || $page < 1) {
            throw InvalidEngineQueryException::invalidPage($perPage, $page);
        }

        if ($maximum !== null && $perPage > $maximum) {
            throw InvalidEngineQueryException::pageTooLarge($perPage, $maximum);
        }

        if (! $this->reaches($perPage, $page)) {
            throw InvalidEngineQueryException::beyondWindow($this->profile->window() ?? 0);
        }
    }
}
