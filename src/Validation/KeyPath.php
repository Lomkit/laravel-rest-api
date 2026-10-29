<?php

namespace Lomkit\Rest\Validation;

final class KeyPath
{
    public static function segment(int|string $key): string
    {
        return str_replace('.', '\.', (string) $key);
    }

    public static function canName(int|string $key): bool
    {
        $key = (string) $key;

        return ! str_contains($key, '*') && ! str_ends_with($key, '\\');
    }

    public static function unescape(string $key): string
    {
        return str_replace('\.', '.', $key);
    }

    /** @return list<string> */
    public static function segments(string $key): array
    {
        return array_map(fn (string $segment): string => str_replace("\0", '.', $segment), explode('.', str_replace('\.', "\0", $key)));
    }

    /** @param array<array-key, mixed> $data */
    public static function has(array $data, string $key): bool
    {
        $value = $data;

        foreach (self::segments($key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /** @param array<array-key, mixed> $data */
    public static function get(array $data, string $key): mixed
    {
        $value = $data;

        foreach (self::segments($key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /** @param array<array-key, mixed> $data */
    public static function set(array &$data, string $key, mixed $value): void
    {
        $target = &$data;

        foreach (self::segments($key) as $segment) {
            if (! is_array($target)) {
                $target = [];
            }

            $target = &$target[$segment];
        }

        $target = $value;
    }
}
