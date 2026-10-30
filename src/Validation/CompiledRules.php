<?php

namespace Lomkit\Rest\Validation;

use Lomkit\Rest\Authorization\Check;

final readonly class CompiledRules
{
    /**
     * @param  array<string, list<mixed>>  $rules
     * @param  array<string, string>  $labels
     * @param  array<string, mixed>  $projections
     * @param  list<Check>  $reach
     */
    public function __construct(
        public array $rules,
        public array $labels,
        public array $projections,
        public array $reach = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    public function data(array $input): array
    {
        foreach ($this->projections as $key => $value) {
            KeyPath::set($input, $key, $value);
        }

        return $input;
    }

    /**
     * @param  array<array-key, mixed>  $custom
     * @param  array<array-key, mixed>  $translated
     * @return array<array-key, mixed>
     */
    public function attributeNames(array $custom, array $translated = []): array
    {
        $names = array_keys($custom + $translated);
        $patterns = [];

        foreach ($names as $name) {
            if (str_contains((string) $name, '*')) {
                $patterns[] = '#^'.str_replace('\*', '([^.]*)', preg_quote((string) $name, '#')).'\z#u';
            }
        }

        $labels = array_filter(
            array_diff_key($this->labels, array_flip($names)),
            fn (string $key): bool => ! self::matchesAny($key, $patterns),
            ARRAY_FILTER_USE_KEY,
        );

        return $custom + $labels;
    }

    /** @param list<string> $patterns */
    private static function matchesAny(string $key, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $key) === 1) {
                return true;
            }
        }

        return false;
    }
}
