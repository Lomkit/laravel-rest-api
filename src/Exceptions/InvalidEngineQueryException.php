<?php

namespace Lomkit\Rest\Exceptions;

use Illuminate\Support\Arr;
use LogicException;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Text\Engine\RestrictingExtension;

class InvalidEngineQueryException extends LogicException
{
    public static function closedGroup(): self
    {
        return new self('A condition reached an EngineConditionGroup after the closure of its whereAny() or whereAll() returned, so it would be dropped. Add every condition of a group inside its closure.');
    }

    public static function unmatchedStringList(Instruction|RestrictingExtension|null $source, string $resource, string $attribute, string $engine): self
    {
        $check = $source instanceof RestrictingExtension ? ' Check $context->matchesStringLists before comparing the list.' : '';

        return new self(self::source($source, $resource)." compares the list attribute [{$attribute}], but the engine [{$engine}] cannot compare a list of strings: only an engine whose profile implements Lomkit\\Rest\\Text\\Engine\\Profiles\\MatchesStringLists does, such as the built-in Typesense, Meilisearch, Turbopuffer and Algolia profiles. Index the model on such an engine, or compare an attribute that holds a single value on this one.{$check}");
    }

    public static function ungroupedFilters(string $resource, string $engine): self
    {
        return new self("The search of {$resource} groups its filters with or or nested, but the profile of the engine [{$engine}] does not accept or and nested filters: only the built-in Typesense, Meilisearch, Turbopuffer, database, collection and null profiles do. Combine the filters with and only.");
    }

    public static function ungroupedConditions(Instruction|RestrictingExtension|null $source, string $resource, string $engine): self
    {
        $check = $source instanceof RestrictingExtension ? '$context->filtersWithGroups' : '$query->filtersWithGroups()';

        return new self(self::source($source, $resource)." groups engine conditions with whereAny(), but the engine [{$engine}] does not accept grouped conditions: only the built-in Typesense, Meilisearch, Turbopuffer, database, collection and null profiles do. Check {$check} before grouping, and use where(), whereIn() and whereNotIn() on this engine.");
    }

    public static function undeclaredAttribute(string $attribute): self
    {
        return new self("The engine query has no attribute [{$attribute}]. Declare it with ScoutEngine::filterable() for clients, with ScoutEngine::indexAttributes() for the server only, or in indexAttributes() of an extension that implements DeclaresIndexAttributes.");
    }

    public static function unappliedRowRestriction(string $resource, string $profile, string $engine): self
    {
        return new self("The engine profile {$profile} of [{$engine}] implements AppliesRowRestrictions, but its restrictedPage() returned without applying the RowRestriction it received for a text search of {$resource}. Apply it to the SQL query of the page, or do not implement AppliesRowRestrictions so that the extensions restrict the engine with RestrictsEngineQueries.");
    }

    /** @param non-empty-list<array{string, string}> $extensions */
    public static function repeatedRowRestriction(string $resource, array $extensions): self
    {
        usort($extensions, static fn (array $first, array $second): int => strcmp($first[0], $second[0]));
        $named = Arr::join(array_map(static fn (array $extension): string => "the [{$extension[0]}] extension {$extension[1]}", $extensions), ', ', ' and ');

        return new self("The engine query of {$resource} already carries the row restrictions of {$named}, and the package passes them once per engine query. Restrict the engine query with where(), whereIn() or whereNotIn() in ScoutEngine::perimeter() or in an instruction instead of restrictRows().");
    }

    public static function unsupportedOperator(string $attribute, string $operator): self
    {
        return new self("The engine query does not compare the attribute [{$attribute}] with the operator [{$operator}].");
    }

    public static function invalidValue(string $attribute, mixed $value): InvalidEngineValueException
    {
        $type = get_debug_type($value);

        return new InvalidEngineValueException("The engine query does not compare the attribute [{$attribute}] with this value of type [{$type}].", 'rest::validation.engine_value');
    }

    public static function nonLiteralValue(string $attribute): InvalidEngineValueException
    {
        return new InvalidEngineValueException("The search engine cannot match this value of the attribute [{$attribute}] as it is.", 'rest::validation.engine_characters');
    }

    public static function notSortable(string $attribute): self
    {
        return new self("The engine query does not sort on the attribute [{$attribute}]. Declare it with ScoutEngine::sortable().");
    }

    public static function tooManySorts(int $maximum): self
    {
        return new self("The search engine sorts on at most {$maximum} attributes.");
    }

    public static function invalidOption(mixed $key): self
    {
        $name = is_scalar($key) ? (string) $key : get_debug_type($key);

        return new self("The engine query option [{$name}] is not named by a string.");
    }

    public static function ownedOption(string $key): self
    {
        return new self("The engine query option [{$key}] is owned by the package, which sends or refuses it to keep the search and every hit inside the filter of the engine.");
    }

    public static function conflictingOption(string $key): self
    {
        return new self("The engine query option [{$key}] is already set to another value.");
    }

    public static function trashedUnavailable(string $model): self
    {
        return new self("The index of the model [{$model}] holds no trashed models: the model must use soft deletes and scout.soft_delete must be enabled.");
    }

    public static function invalidPage(int $perPage, int $page): self
    {
        return new self("The engine query cannot read page {$page} of {$perPage} results.");
    }

    public static function pageTooLarge(int $perPage, int $maximum): self
    {
        return new self("The search engine returns at most {$maximum} results per page, not {$perPage}.");
    }

    public static function beyondWindow(int $window): self
    {
        return new self("The search engine does not paginate beyond the first {$window} results.");
    }

    private static function source(Instruction|RestrictingExtension|null $source, string $resource): string
    {
        return match (true) {
            $source instanceof Instruction => "The instruction [{$source->publicUriKey()}] ".$source::class." of {$resource}",
            $source instanceof RestrictingExtension => "The [{$source->name}] extension {$source->class} of {$resource}",
            default => "ScoutEngine::perimeter() of {$resource}",
        };
    }
}
