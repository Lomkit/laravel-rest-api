<?php

namespace Lomkit\Rest\Http\Details;

use Closure;

final readonly class DetailsSelection
{
    private const array BASE = ['fields', 'limits', 'meta'];

    private const array SECTIONS = [
        'fields' => ['capabilities', 'filter', 'rules', 'meta'],
        'relations' => ['constraints', 'operations', 'pivot_fields'],
        'actions' => ['fields', 'authorized', 'meta'],
        'metrics' => ['authorized', 'meta'],
        'instructions' => ['fields', 'meta'],
        'scopes' => ['fields', 'meta'],
        'rules' => [],
        'text' => [],
        'gates' => [],
        'confirmations' => [],
        'authorizations' => [],
    ];

    /** @param array<string, true> $requested */
    private function __construct(private array $requested) {}

    public static function base(): self
    {
        return new self([]);
    }

    /** @param list<string> $values */
    public static function of(array $values): self
    {
        $requested = [];

        foreach ($values as $value) {
            $requested[$value] = true;
            $requested[explode('.', $value)[0]] = true;
        }

        return new self($requested);
    }

    public static function parse(?string $with): self
    {
        return self::of(self::split($with));
    }

    /** @return list<string> */
    public static function values(): array
    {
        $values = [];

        foreach (self::SECTIONS as $section => $parts) {
            if (! in_array($section, self::BASE, true)) {
                $values[] = $section;
            }

            foreach ($parts as $part) {
                $values[] = "{$section}.{$part}";
            }
        }

        return $values;
    }

    /** @return list<string> */
    public static function unknown(string $with): array
    {
        return array_values(array_diff(self::split($with), self::values()));
    }

    /**
     * @param  Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public function part(string $value, Closure $build): array
    {
        return $this->has($value) ? $build() : [];
    }

    public function has(string $value): bool
    {
        return in_array($value, self::BASE, true) || isset($this->requested[$value]);
    }

    /** @return list<string> */
    public function parts(string $section): array
    {
        return array_values(array_filter(self::SECTIONS[$section] ?? [], fn (string $part): bool => $this->has("{$section}.{$part}")));
    }

    /** @return list<string> */
    private static function split(?string $with): array
    {
        return $with === null ? [] : array_values(array_unique(explode(',', $with)));
    }
}
