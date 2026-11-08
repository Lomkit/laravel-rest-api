<?php

namespace Lomkit\Rest\Validation;

final readonly class ValidateOnly
{
    /**
     * @param  list<list<string>>  $names
     * @param  array<string, list<string>>  $gates
     */
    private function __construct(
        private array $names,
        private array $gates,
    ) {}

    /** @param array<array-key, string> $names */
    public static function of(array $names, ParsedRules $rules): self
    {
        return new self(array_values(array_map(KeyPath::segments(...), $names)), $rules->gates());
    }

    public function covers(string $attribute): bool
    {
        if ($this->meets(KeyPath::segments($attribute), false)) {
            return true;
        }

        foreach ($this->gates[$attribute] ?? [] as $scope) {
            if ($this->meets(KeyPath::segments($scope), true)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $path */
    private function meets(array $path, bool $above): bool
    {
        foreach ($this->names as $name) {
            if ($above && count($path) > count($name)) {
                continue;
            }

            if (self::aligned($path, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $path
     * @param  list<string>  $name
     */
    private static function aligned(array $path, array $name): bool
    {
        foreach (array_slice($name, 0, count($path)) as $index => $segment) {
            if (! self::matches($segment, $path[$index])) {
                return false;
            }
        }

        return true;
    }

    private static function matches(string $pattern, string $segment): bool
    {
        if ($pattern === $segment) {
            return true;
        }

        if (! str_contains($pattern, '*')) {
            return false;
        }

        return preg_match('/^'.str_replace('\*', '[^.]+', preg_quote($pattern, '/')).'$/', $segment) === 1;
    }
}
