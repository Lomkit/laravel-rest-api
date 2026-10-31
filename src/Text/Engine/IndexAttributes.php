<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\TextTarget;

final readonly class IndexAttributes
{
    private const string NAME = '/\A[A-Za-z_][A-Za-z0-9_]*\z/';

    private const string SOFT_DELETED = '__soft_deleted';

    private const array TYPES = [ID::class, Integer::class, Number::class, Boolean::class, Text::class, Enum::class];

    /**
     * @param  array<string, IndexAttribute>  $filterable
     * @param  array<string, IndexAttribute>  $perimeter
     * @param  list<string>  $sortable
     */
    private function __construct(
        private array $filterable,
        private array $perimeter,
        private array $sortable,
    ) {}

    /**
     * @param  array<mixed>  $filterable
     * @param  array<mixed>  $sortable
     * @param  array<mixed>  $perimeter
     */
    public static function of(TextTarget $target, EngineProfile $profile, array $filterable, array $sortable, array $perimeter): self
    {
        $client = self::attributes($target, $profile, $filterable, 'filterable()');
        $restricted = self::attributes($target, $profile, $perimeter, 'perimeter()');

        foreach (array_keys($restricted) as $name) {
            if (isset($client[$name])) {
                throw InvalidTextSearchException::duplicateAttribute($target->resource, $name);
            }
        }

        return new self($client, $restricted, self::sortNames($target, $profile, $client, $sortable));
    }

    public function find(string $name): ?IndexAttribute
    {
        return $this->filterable[$name] ?? $this->perimeter[$name] ?? null;
    }

    public function sorts(string $name): bool
    {
        return in_array($name, $this->sortable, true);
    }

    public function grammar(EngineProfile $profile): EngineGrammar
    {
        return new EngineGrammar($this->filterable, $this->sortable, $profile->sortKeys(), $profile->maxPageSize(), $profile->window());
    }

    /**
     * @param  array<mixed>  $declared
     * @return array<string, IndexAttribute>
     */
    private static function attributes(TextTarget $target, EngineProfile $profile, array $declared, string $declaration): array
    {
        $attributes = [];

        foreach (array_values($declared) as $index => $attribute) {
            $field = match (true) {
                $attribute instanceof Field => $attribute->boundTo($target->model),
                is_string($attribute) => $target->declared->find($attribute) ?? throw InvalidTextSearchException::unknownAttribute($target->resource, $attribute),
                default => throw InvalidTextSearchException::notAnAttribute($target->resource, $declaration, $index, $attribute),
            };

            if (isset($attributes[$field->name])) {
                throw InvalidTextSearchException::duplicateAttribute($target->resource, $field->name);
            }

            $attributes[$field->name] = self::attribute($target, $profile, $field);
        }

        return $attributes;
    }

    private static function attribute(TextTarget $target, EngineProfile $profile, Field $field): IndexAttribute
    {
        if (preg_match(self::NAME, $field->name) !== 1 || $field->name === self::SOFT_DELETED) {
            throw InvalidTextSearchException::invalidAttributeName($target->resource, $field->name);
        }

        if (! in_array($field::class, self::TYPES, true)) {
            throw InvalidTextSearchException::unsupportedAttributeType($target->resource, $field->name, $field::class);
        }

        if ($field->filtersThroughCallback() || $field->sortsThroughCallback()) {
            throw InvalidTextSearchException::callbackAttribute($target->resource, $field->name);
        }

        if ($field instanceof Enum) {
            $field->enumClass();
        }

        $supported = $profile->operators($field);
        $operators = array_filter($field->filterOperators(), fn (FilterOperator $operator): bool => in_array($operator, $supported, true));

        return $operators === []
            ? throw InvalidTextSearchException::unfilterableAttribute($target->resource, $field->name)
            : new IndexAttribute($field, array_values($operators));
    }

    /**
     * @param  array<string, IndexAttribute>  $filterable
     * @param  array<mixed>  $sortable
     * @return list<string>
     */
    private static function sortNames(TextTarget $target, EngineProfile $profile, array $filterable, array $sortable): array
    {
        $sorts = [];

        foreach (array_values($sortable) as $index => $name) {
            if (! is_string($name)) {
                throw InvalidTextSearchException::notAnAttributeName($target->resource, $index, $name);
            }

            if (! isset($filterable[$name])) {
                throw InvalidTextSearchException::unsortableAttribute($target->resource, $name);
            }

            if (in_array($name, $sorts, true)) {
                throw InvalidTextSearchException::duplicateAttribute($target->resource, $name);
            }

            $sorts[] = $name;
        }

        if ($sorts !== [] && $profile->sortKeys() === 0) {
            throw InvalidTextSearchException::unsortableEngine($target->resource, $profile->name());
        }

        return $sorts;
    }
}
