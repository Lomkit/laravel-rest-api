<?php

namespace Lomkit\Rest\Authorization;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Exceptions\InvalidResourceException;

/** @internal */
final class CollectedVersions
{
    /** @var array<int, string> */
    private array $rows = [];

    /** @var array<string, array<string, int|string>> */
    private array $versions = [];

    /** @var array<string, true> */
    private array $uncacheable = [];

    /** @var array{string, string}|null */
    private ?array $extension = null;

    /** @param Collection<int, Model> $rows */
    public function __construct(
        private readonly string $resource,
        private readonly string $ability,
        private readonly int $since,
        Collection $rows,
    ) {
        foreach ($rows->all() as $row) {
            $this->rows[spl_object_id($row)] = DependentVersions::rowOf($row);
        }
    }

    public function from(string $name, string $class): void
    {
        $this->extension = [$name, $class];
    }

    public function add(Model $row, string $key, DateTimeInterface|int|string $version): void
    {
        $id = $this->rows[spl_object_id($row)] ?? throw $this->unknownRow($row);

        if (preg_match(CacheContributions::KEY, $key) !== 1) {
            throw $this->invalidKey($key);
        }

        if ($this->extension !== null) {
            $key = $this->extension[0].CacheContributions::SEPARATOR.$key;
        }

        if ($version instanceof DateTimeInterface) {
            if ($version->getTimestamp() >= $this->since) {
                $this->uncacheable[$id] = true;
            }

            $version = $version->format('U.u');
        }

        if (isset($this->versions[$id][$key]) && $this->versions[$id][$key] !== $version) {
            $this->uncacheable[$id] = true;
        }

        $this->versions[$id][$key] = $version;
    }

    public function collected(): DependentVersions
    {
        $parts = [];

        foreach ($this->versions as $id => $versions) {
            ksort($versions, SORT_STRING);
            $parts[$id] = isset($this->uncacheable[$id]) ? null : serialize($versions);
        }

        return new DependentVersions($parts);
    }

    private function unknownRow(Model $row): LogicException
    {
        return $this->extension === null
            ? InvalidResourceException::unknownVersionedRow($this->resource, $this->ability, $row::class)
            : InvalidAuthorizationExtensionException::unknownVersionedRow($this->extension[0], $this->extension[1], $this->resource, $this->ability, $row::class);
    }

    private function invalidKey(string $key): LogicException
    {
        return $this->extension === null
            ? InvalidResourceException::invalidVersionKey($this->resource, $key)
            : InvalidAuthorizationExtensionException::invalidVersionKey($this->extension[0], $this->extension[1], $this->resource, $key);
    }
}
