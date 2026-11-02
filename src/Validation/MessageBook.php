<?php

namespace Lomkit\Rest\Validation;

use Lomkit\Rest\Fields\Field;

final class MessageBook
{
    /** @var array<string, array<string, Field>> */
    private array $owners = [];

    /** @var array<string, string> */
    private array $names = [];

    /** @var array<string, string> */
    private array $written = [];

    public function field(Cursor $value, Field $field): void
    {
        $this->owners[KeyPath::unescape($value->pattern)][KeyPath::unescape($value->path)] = $field;
    }

    public function label(string $key, string $label): void
    {
        $this->names[KeyPath::unescape($key)] ??= $label;
    }

    /** @param list<string> $keys */
    public function written(array $keys): void
    {
        foreach (array_map(KeyPath::unescape(...), $keys) as $key) {
            if (! str_contains($key, '*') && (preg_match('/[_\s]/u', $key) === 1 || mb_strtolower($key) !== $key)) {
                $this->written[$key] = $key;
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $custom
     * @param  array<array-key, mixed>  $translated
     * @return array<array-key, mixed>
     */
    public function attributeNames(array $custom, array $translated): array
    {
        $declared = [];
        $labelled = [];

        foreach ($this->owners as $pattern => $owners) {
            $labels = array_map(fn (Field $field): ?string => $field->getLabel(), $owners);
            $declared += self::entries($pattern, $labels);
            $labelled += array_filter($labels, fn (?string $label): bool => $label !== null);
        }

        $defaults = array_diff_key($this->names + $this->written, $labelled);

        return $custom + self::unnamed($declared, array_keys($custom)) + self::unnamed($defaults, array_keys($custom + $translated));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $messages = [];

        foreach ($this->owners as $pattern => $owners) {
            $declared = array_map(fn (Field $field): array => $field->getMessages(), $owners);
            $rules = [];

            foreach ($declared as $given) {
                $rules += $given;
            }

            foreach (array_keys($rules) as $rule) {
                $texts = array_map(fn (array $given): ?string => $given[$rule] ?? null, $declared);
                $messages += self::entries($pattern, $texts, '.'.$rule);
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, string>
     */
    private static function entries(string $pattern, array $values, string $suffix = ''): array
    {
        $given = array_filter($values, fn (?string $value): bool => $value !== null);
        $distinct = array_values(array_unique($given));

        if (count($given) === count($values) && count($distinct) === 1) {
            return [$pattern.$suffix => $distinct[0]];
        }

        $entries = [];

        foreach ($given as $key => $value) {
            $entries[$key.$suffix] = $value;
        }

        return $entries;
    }

    /**
     * @param  array<string, string>  $names
     * @param  list<array-key>  $keys
     * @return array<string, string>
     */
    private static function unnamed(array $names, array $keys): array
    {
        $expressions = [];

        foreach ($keys as $key) {
            if (str_contains((string) $key, '*')) {
                $expressions[] = '#^'.str_replace('\*', '([^.]*)', preg_quote((string) $key, '#')).'\z#u';
            }
        }

        return array_filter(
            array_diff_key($names, array_flip($keys)),
            fn (string $key): bool => ! self::matchesAny($key, $expressions),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** @param list<string> $expressions */
    private static function matchesAny(string $key, array $expressions): bool
    {
        foreach ($expressions as $expression) {
            if (preg_match($expression, $key) === 1) {
                return true;
            }
        }

        return false;
    }
}
