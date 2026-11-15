<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;

/** @internal */
final class LimitRequests
{
    /** @var list<int>|null */
    private ?array $sizes = null;

    private ?int $maxSize = null;

    private ?int $maxTotal = null;

    private ?int $includeLimit = null;

    /** @param class-string<Resource> $resource */
    public function __construct(private readonly string $resource) {}

    /** @param list<int> $sizes */
    public function allowSizes(string $extension, string $class, array $sizes): void
    {
        foreach ($sizes as $size) {
            $this->ensurePositive($extension, $class, __FUNCTION__, $size);
        }

        $this->sizes = $this->sizes === null ? array_values(array_unique($sizes)) : array_values(array_intersect($this->sizes, $sizes));
    }

    public function maxSize(string $extension, string $class, int $size): void
    {
        $this->ensurePositive($extension, $class, __FUNCTION__, $size);
        $this->maxSize = min($this->maxSize ?? $size, $size);
    }

    public function maxTotal(string $extension, string $class, int $total): void
    {
        $this->ensurePositive($extension, $class, __FUNCTION__, $total);
        $this->maxTotal = min($this->maxTotal ?? $total, $total);
    }

    public function includeLimit(string $extension, string $class, int $limit): void
    {
        $this->ensurePositive($extension, $class, __FUNCTION__, $limit);
        $this->includeLimit = min($this->includeLimit ?? $limit, $limit);
    }

    public function allows(int $size, ?int $ceiling): bool
    {
        return ($this->sizes === null || in_array($size, $this->sizes, true)) && ($ceiling === null || $size <= $ceiling);
    }

    public function sizeCeiling(): ?int
    {
        return $this->maxSize;
    }

    public function totalCeiling(): ?int
    {
        return $this->maxTotal;
    }

    public function includeCeiling(): ?int
    {
        return $this->includeLimit;
    }

    private function ensurePositive(string $extension, string $class, string $method, int $value): void
    {
        if ($value < 1) {
            throw InvalidQueryExtensionException::invalidLimit($extension, $class, $this->resource, $method, $value);
        }
    }
}
