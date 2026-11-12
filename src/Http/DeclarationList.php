<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Routing\DeclarationKind;
use Lomkit\Rest\Http\Routing\DeclaredRoute;
use Lomkit\Rest\Metrics\Metric;

/**
 * @internal
 *
 * @template T of Action|Metric
 */
final class DeclarationList
{
    /** @var array<array-key, T> */
    private array $built = [];

    /** @param array<int, T|class-string<T>> $entries */
    public function __construct(
        private readonly DeclarationKind $kind,
        private readonly array $entries,
        private readonly Resource $resource,
        private readonly Container $container,
    ) {}

    /** @return list<DeclaredRoute> */
    public function routes(): array
    {
        $routes = [];

        foreach ($this->entries as $index => $entry) {
            $routes[] = is_string($entry) ? DeclaredRoute::ofClass($entry, $this->kind) : DeclaredRoute::ofInstance($this->at($index));
        }

        return $routes;
    }

    /** @return list<int> */
    public function indexes(): array
    {
        return array_keys($this->entries);
    }

    /** @return array<int, T> */
    public function all(): array
    {
        $declarations = [];

        foreach ($this->indexes() as $index) {
            $declarations[$index] = $this->at($index);
        }

        return $declarations;
    }

    /** @return T|null */
    public function find(string $key): Action|Metric|null
    {
        foreach ($this->entries as $index => $entry) {
            if ($this->keyOf($entry, $index) === $key) {
                return $this->at($index);
            }
        }

        return null;
    }

    /** @return T */
    public function at(int|string $index): Action|Metric
    {
        return $this->built[$index] ??= $this->build($this->entries[$index]);
    }

    /** @param T|class-string<T> $entry */
    private function keyOf(Action|Metric|string $entry, int|string $index): string
    {
        if (is_string($entry)) {
            return DeclaredRoute::ofClass($entry, $this->kind)->key;
        }

        return $this->at($index)->uriKey();
    }

    /**
     * @param  T|class-string<T>  $entry
     * @return T
     */
    private function build(Action|Metric|string $entry): Action|Metric
    {
        if (! is_string($entry)) {
            return $entry->resource($this->resource)->ensureValid();
        }

        $declared = DeclaredRoute::ofClass($entry, $this->kind);
        $declaration = $this->container->make($entry);

        if (! $declaration instanceof $entry) {
            throw $this->kind->foreignBinding($entry, get_debug_type($declaration));
        }

        $declaration->resource($this->resource)->ensureValid();

        if (DeclaredRoute::ofInstance($declaration)->equals($declared)) {
            return $declaration;
        }

        if ($declaration::class !== $entry) {
            throw $this->kind->reboundRoute($entry, $declaration::class);
        }

        throw $this->kind->changedRoute($entry);
    }
}
