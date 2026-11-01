<?php

namespace Lomkit\Rest\Validation;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\ID;

final class KeyLookup
{
    private const int CHUNK = 1000;

    /** @var list<int|string> */
    private array $pending = [];

    /** @var array<array-key, true> */
    private array $expected = [];

    /** @var array<array-key, true> */
    private array $found = [];

    /** @param Closure(): Builder<Model> $query */
    public function __construct(
        private readonly Closure $query,
        private readonly ID $id,
    ) {}

    public function expect(int|string $key): ExistingKey
    {
        $normalized = $this->id->normalizeKey($key);

        if (! isset($this->expected[$normalized])) {
            $this->expected[$normalized] = true;
            $this->pending[] = $key;
        }

        return new ExistingKey($this, $key);
    }

    public function has(int|string $key): bool
    {
        $this->resolve();

        return isset($this->found[$this->id->normalizeKey($key)]);
    }

    private function resolve(): void
    {
        if ($this->pending === []) {
            return;
        }

        $base = ($this->query)();
        $column = $base->getModel()->getQualifiedKeyName();

        foreach (array_chunk($this->pending, self::CHUNK) as $chunk) {
            $this->collect($this->id
                ->whereKeys(clone $base, $chunk)
                ->toBase()
                ->pluck($column)
                ->all());
        }

        $this->pending = [];
    }

    /** @param array<array-key, mixed> $values */
    private function collect(array $values): void
    {
        foreach ($values as $value) {
            $key = $this->id->parseKey($value);

            if ($key !== null && isset($this->expected[$normalized = $this->id->normalizeKey($key)])) {
                $this->found[$normalized] = true;
            }
        }
    }
}
