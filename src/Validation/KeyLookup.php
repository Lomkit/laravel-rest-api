<?php

namespace Lomkit\Rest\Validation;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
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

    /** @var list<Model> */
    private array $rows = [];

    /** @var array<array-key, Model> */
    private array $named = [];

    /** @var Builder<Model>|null */
    private ?Builder $base = null;

    /** @param Closure(): Builder<Model> $query */
    public function __construct(
        private readonly Closure $query,
        private readonly ID $id,
        private readonly KeyRead $read = KeyRead::Keys,
    ) {}

    public function expect(int|string $key): ExistingKey
    {
        $this->register($key);

        return new ExistingKey($this, [$key]);
    }

    /** @param list<int|string> $keys */
    public function expectAll(array $keys): ExistingKey
    {
        foreach ($keys as $key) {
            $this->register($key);
        }

        return new ExistingKey($this, $keys);
    }

    public function has(int|string $key): bool
    {
        $this->resolve();

        return isset($this->found[$key]);
    }

    /** @return list<Model> */
    public function rows(): array
    {
        if ($this->read === KeyRead::Keys) {
            throw new LogicException('The key lookup checks the keys without loading their rows.');
        }

        $this->resolve();

        return $this->rows;
    }

    /** @return array<array-key, Model> */
    public function found(): array
    {
        $this->rows();

        return $this->named;
    }

    private function register(int|string $key): void
    {
        if (! isset($this->expected[$key])) {
            $this->expected[$key] = true;
            $this->pending[] = $key;
        }
    }

    private function resolve(): void
    {
        while ($this->pending !== []) {
            $this->check($this->nextRound());
        }
    }

    /** @return array<array-key, int|string> */
    private function nextRound(): array
    {
        $round = [];
        $later = [];

        foreach ($this->pending as $key) {
            $normalized = $this->id->normalizeKey($key);

            if (isset($round[$normalized])) {
                $later[] = $key;

                continue;
            }

            $round[$normalized] = $key;
        }

        $this->pending = $later;

        return $round;
    }

    /** @param array<array-key, int|string> $round */
    private function check(array $round): void
    {
        $base = $this->base ??= ($this->query)();

        foreach (array_chunk($round, self::CHUNK) as $chunk) {
            $this->collect($round, $this->read->read($this->id->whereKeys(clone $base, $chunk)));
        }
    }

    /**
     * @param  array<array-key, int|string>  $round
     * @param  list<array{mixed, Model|null}>  $values
     */
    private function collect(array $round, array $values): void
    {
        foreach ($values as [$value, $row]) {
            $key = $this->id->parseKey($value);

            if ($key === null || ! isset($round[$normalized = $this->id->normalizeKey($key)])) {
                continue;
            }

            $this->found[$round[$normalized]] = true;

            if ($row !== null) {
                $this->rows[] = $row;
                $this->named[$round[$normalized]] = $row;
            }
        }
    }
}
