<?php

namespace Lomkit\Rest\Scramble;

use Illuminate\Validation\ValidationRuleParser;
use Lomkit\Rest\Fields\Details\RuleDescriptions;

final readonly class RuleExample
{
    private const array TEXTS = [
        'Email' => 'user@example.com',
        'Url' => 'https://example.com',
        'ActiveUrl' => 'https://example.com',
        'Uuid' => '00000000-0000-0000-0000-000000000000',
        'Ulid' => '00000000000000000000000000',
        'Ip' => '127.0.0.1',
        'Ipv4' => '127.0.0.1',
        'Ipv6' => '::1',
        'MacAddress' => '00:00:00:00:00:00',
        'Timezone' => 'UTC',
        'Json' => '{}',
    ];

    /** @param array<string, list<string>> $rules */
    private function __construct(private array $rules) {}

    /** @param list<mixed> $rules */
    public static function honour(array $rules, mixed $value): mixed
    {
        $parsed = [];

        foreach ((new RuleDescriptions($rules))->toArray() as $rule) {
            [$name, $parameters] = ValidationRuleParser::parse($rule);

            if (is_string($name) && is_array($parameters)) {
                $parsed[$name] = array_values(array_map(static fn (mixed $parameter): string => is_scalar($parameter) ? (string) $parameter : '', $parameters));
            }
        }

        return (new self($parsed))->value($value);
    }

    private function value(mixed $value): mixed
    {
        if (isset($this->rules['In'][0])) {
            return self::cast($this->rules['In'][0], $value);
        }

        return match (true) {
            is_bool($value) => $this->boolean($value),
            is_int($value), is_float($value) => (new RuleBounds($this->rules))->number($value),
            is_string($value) => $this->text($value),
            default => $value,
        };
    }

    private function boolean(bool $value): bool
    {
        return match (true) {
            isset($this->rules['Accepted']) => true,
            isset($this->rules['Declined']) => false,
            default => $value,
        };
    }

    private function text(string $value): string
    {
        foreach (self::TEXTS as $rule => $text) {
            if (isset($this->rules[$rule])) {
                return $text;
            }
        }

        if (isset($this->rules['Date']) && ! self::isDate($value)) {
            return '2026-01-01';
        }

        if (isset($this->rules['Digits'][0]) && is_numeric($this->rules['Digits'][0])) {
            return str_repeat('1', (int) $this->rules['Digits'][0]);
        }

        if (isset($this->rules['Numeric']) || isset($this->rules['Integer'])) {
            return (string) (new RuleBounds($this->rules))->number(1);
        }

        return isset($this->rules['Date']) ? $value : (new RuleBounds($this->rules))->text($value);
    }

    private static function isDate(string $value): bool
    {
        $date = date_parse($value);

        return strtotime($value) !== false && is_int($date['year']) && is_int($date['month']) && is_int($date['day']) && checkdate($date['month'], $date['day'], $date['year']);
    }

    private static function cast(string $allowed, mixed $value): mixed
    {
        return match (true) {
            is_int($value) && is_numeric($allowed) => (int) $allowed,
            is_float($value) && is_numeric($allowed) => (float) $allowed,
            is_bool($value) => filter_var($allowed, FILTER_VALIDATE_BOOLEAN),
            is_string($value) => $allowed,
            default => $value,
        };
    }
}
