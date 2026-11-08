<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Database\Eloquent\Builder;
use LogicException;

final readonly class LimitPolicy
{
    private function __construct(
        private ?int $limit,
        private ?int $page,
        private bool $perParent = false,
    ) {}

    public static function paged(int $perPage, int $page): self
    {
        return new self($perPage, $page);
    }

    public static function explicit(?int $limit): self
    {
        return new self($limit, null);
    }

    public static function perParent(int $limit): self
    {
        return new self($limit, null, true);
    }

    public static function none(): self
    {
        return new self(null, null);
    }

    public function apply(Builder $query): void
    {
        if ($this->page !== null || $this->limit === null) {
            return;
        }

        $query->limit($this->perParent ? $this->limit + 1 : $this->limit);
    }

    public function parentLimit(): ?int
    {
        return $this->perParent ? $this->limit : null;
    }

    public function perPage(): int
    {
        if ($this->page === null || $this->limit === null) {
            throw self::notPaged();
        }

        return $this->limit;
    }

    public function page(): int
    {
        return $this->page ?? throw self::notPaged();
    }

    private static function notPaged(): LogicException
    {
        return new LogicException('The plan of this search is not paged.');
    }
}
