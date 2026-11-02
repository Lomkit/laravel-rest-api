<?php

namespace Lomkit\Rest\Validation;

final readonly class Violation
{
    /** @param array<string, string> $replace */
    public function __construct(
        public string $key,
        public string $message,
        public array $replace = [],
    ) {}

    public static function tooMany(string $key, int $max): self
    {
        return new self($key, 'validation.max.array', ['max' => (string) $max]);
    }

    public static function notList(string $key): self
    {
        return new self($key, 'validation.list');
    }

    public static function repeated(string $key): self
    {
        return new self($key, 'validation.distinct');
    }

    public static function tooManyFilters(string $key, int $max): self
    {
        return new self($key, 'The :attribute field goes over the limit of :max filters in one search.', ['max' => (string) $max]);
    }

    public static function tooManyEntries(string $key, int $max): self
    {
        return new self($key, 'The :attribute field goes over the limit of :max list entries in one request.', ['max' => (string) $max]);
    }

    public static function unknown(string $key): self
    {
        return new self($key, 'validation.prohibited');
    }

    public static function unnamedKey(string $container, string $key): self
    {
        return new self($container, 'The :key key is not valid for the :attribute field.', ['key' => $key]);
    }

    public static function tooDeep(string $key, int $max): self
    {
        return new self($key, 'The :attribute field goes deeper than the limit of :max include levels.', ['max' => (string) $max]);
    }
}
