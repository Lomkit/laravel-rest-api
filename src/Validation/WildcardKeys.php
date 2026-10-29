<?php

namespace Lomkit\Rest\Validation;

final class WildcardKeys
{
    /** @var list<array{string, list<string>}> */
    private array $matches = [];

    /** @var array<string, list<int|string>> */
    private array $unnamed = [];

    private function __construct() {}

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<array{string, list<string>}>
     */
    public static function expand(array $data, string $key): array
    {
        return self::of($data, $key)->matches;
    }

    /** @param array<array-key, mixed> $data */
    public static function of(array $data, string $key): self
    {
        $keys = new self;
        $keys->walk($data, KeyPath::segments($key), [], []);

        return $keys;
    }

    /** @return list<array{string, list<string>}> */
    public function matches(): array
    {
        return $this->matches;
    }

    /** @return array<string, list<int|string>> */
    public function unnamed(): array
    {
        return $this->unnamed;
    }

    /**
     * @param  list<string>  $segments
     * @param  list<string>  $path
     * @param  list<string>  $indices
     */
    private function walk(mixed $value, array $segments, array $path, array $indices): void
    {
        if ($segments === []) {
            $this->matches[] = [implode('.', $path), $indices];

            return;
        }

        $segment = array_shift($segments);

        if ($segment === '*') {
            foreach (is_array($value) ? $value : [] as $index => $child) {
                if (! KeyPath::canName($index)) {
                    $this->unnamed[implode('.', $path)][] = $index;

                    continue;
                }

                $matched = KeyPath::segment($index);
                $this->walk($child, $segments, [...$path, $matched], [...$indices, $matched]);
            }

            return;
        }

        if (is_array($value) && array_key_exists($segment, $value)) {
            $this->walk($value[$segment], $segments, [...$path, KeyPath::segment($segment)], $indices);

            return;
        }

        if (! in_array('*', $segments, true)) {
            $this->matches[] = [implode('.', [...$path, ...array_map(KeyPath::segment(...), [$segment, ...$segments])]), $indices];
        }
    }
}
