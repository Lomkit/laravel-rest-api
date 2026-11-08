<?php

namespace Lomkit\Rest\Validation;

use Lomkit\Rest\Fields\WriteOperation;

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

    public static function notWritable(string $key, string $attribute, WriteOperation $operation): self
    {
        return new self($key, 'The :key key of the :attribute field is not writable on :operation.', ['key' => $attribute, 'operation' => $operation->value]);
    }

    public static function tooDeep(string $key, int $max): self
    {
        return new self($key, 'The :attribute field goes deeper than the limit of :max include levels.', ['max' => (string) $max]);
    }

    public static function takenKey(string $key, string $name): self
    {
        return new self($key, 'The :attribute field is required, because the key :key is already used.', ['key' => $name]);
    }

    public static function ownerInclude(string $key): self
    {
        return new self($key, 'The :attribute field is prohibited: this relation loads at most one record per parent.');
    }

    public static function severalTypes(string $key): self
    {
        return new self($key, 'The :attribute field is prohibited: this relation has several types. Set it for each type, under types.');
    }

    public static function morphToAggregate(string $key): self
    {
        return new self($key, 'The :attribute field cannot be a MorphTo relation.');
    }

    public static function notLinked(string $key): self
    {
        return new self($key, 'validation.exists');
    }

    public static function heldRecord(string $key): self
    {
        return new self($key, 'The :attribute field is prohibited: this relation already holds a record. Detach it first.');
    }

    public static function throughMorphTo(string $key): self
    {
        return new self($key, 'The :attribute field cannot go through a MorphTo relation.');
    }
}
