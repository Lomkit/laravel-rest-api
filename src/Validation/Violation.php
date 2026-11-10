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
        return new self($key, 'rest::validation.filters_total', ['max' => (string) $max]);
    }

    public static function tooManyRelationFilters(string $key, int $max): self
    {
        return new self($key, 'rest::validation.relation_filters_total', ['max' => (string) $max]);
    }

    public static function tooManyPatternFilters(string $key, int $max): self
    {
        return new self($key, 'rest::validation.pattern_filters_total', ['max' => (string) $max]);
    }

    public static function tooManyEntries(string $key, int $max): self
    {
        return new self($key, 'rest::validation.entries_total', ['max' => (string) $max]);
    }

    public static function tooManyRelationEntries(string $key, int $max): self
    {
        return new self($key, 'rest::validation.relation_entries_total', ['max' => (string) $max]);
    }

    public static function tooManyRows(string $key, int $rows, int $max): self
    {
        return new self($key, 'rest::validation.response_rows', ['max' => (string) $max, 'rows' => (string) $rows]);
    }

    public static function unknown(string $key): self
    {
        return new self($key, 'validation.prohibited');
    }

    public static function unnamedKey(string $container, string $key): self
    {
        return new self($container, 'rest::validation.unknown_key', ['key' => $key]);
    }

    public static function notWritable(string $key, string $attribute, WriteOperation $operation): self
    {
        return new self($key, 'rest::validation.not_writable', ['key' => $attribute, 'operation' => $operation->value]);
    }

    public static function tooDeep(string $key, int $max): self
    {
        return new self($key, 'rest::validation.include_depth', ['max' => (string) $max]);
    }

    public static function tooManyRelations(string $key, int $max): self
    {
        return new self($key, 'rest::validation.relation_depth', ['max' => (string) $max]);
    }

    public static function takenKey(string $key, string $name): self
    {
        return new self($key, 'rest::validation.taken_key', ['key' => $name]);
    }

    public static function repeatedInclude(string $key): self
    {
        return new self($key, 'rest::validation.repeated_include');
    }

    public static function ownerInclude(string $key): self
    {
        return new self($key, 'rest::validation.owner_include');
    }

    public static function gatesOff(string $key): self
    {
        return new self($key, 'rest::validation.gates_off');
    }

    public static function severalTypes(string $key): self
    {
        return new self($key, 'rest::validation.several_types');
    }

    public static function morphToAggregate(string $key): self
    {
        return new self($key, 'rest::validation.morph_to_aggregate');
    }

    public static function notLinked(string $key): self
    {
        return new self($key, 'validation.exists');
    }

    public static function heldRecord(string $key): self
    {
        return new self($key, 'rest::validation.held_record');
    }

    public static function throughMorphTo(string $key): self
    {
        return new self($key, 'rest::validation.through_morph_to');
    }
}
