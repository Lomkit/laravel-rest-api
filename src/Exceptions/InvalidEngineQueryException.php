<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;
use Lomkit\Rest\Text\Engine\RestrictingExtension;

final class InvalidEngineQueryException extends LogicException
{
    public static function undeclaredAttribute(string $attribute): self
    {
        return new self("The engine query has no attribute [{$attribute}]. Declare it with ScoutEngine::filterable(), among the attributes of ScoutEngine::perimeter(), or in indexAttributes() of an extension that implements DeclaresIndexAttributes.");
    }

    public static function unappliedRowRestriction(string $resource, string $profile, string $engine): self
    {
        return new self("The engine profile {$profile} of [{$engine}] answers true to appliesRowRestrictions(), but its page() returned without applying the RowRestriction it received for a text search of {$resource}. Apply it to the SQL query of the page, or answer false so that the extensions restrict the engine with RestrictsEngineQueries.");
    }

    /** @param list<string> $extensions */
    public static function repeatedRowRestriction(string $resource, array $extensions): self
    {
        $names = implode(', ', $extensions);

        return new self("The engine query of {$resource} already carries the row restrictions of the extensions [{$names}], and the package passes them once per engine query. Restrict the engine query with where(), whereIn() or whereNotIn() in ScoutEngine::perimeter() or in an instruction instead of restrictRows().");
    }

    public static function conflictingListMemberships(string $resource, string $attribute, ?RestrictingExtension $first, ?RestrictingExtension $second): self
    {
        $sources = $first === null && $second === null ? 'ScoutEngine::perimeter() or instructions' : self::listSource($first).' and '.self::listSource($second);

        return new self("The engine query of {$resource} keeps the rows whose list attribute [{$attribute}] shares a value with each of two lists, set by {$sources}, and neither list holds the other. The engine matches one list per attribute: restrict [{$attribute}] with one list, or with lists that hold one another.");
    }

    public static function unsupportedOperator(string $attribute, string $operator): self
    {
        return new self("The engine query does not compare the attribute [{$attribute}] with the operator [{$operator}].");
    }

    public static function invalidValue(string $attribute, mixed $value): self
    {
        $type = get_debug_type($value);

        return new self("The engine query does not compare the attribute [{$attribute}] with this value of type [{$type}].");
    }

    public static function nonLiteralValue(string $attribute): self
    {
        return new self("The search engine cannot match this value of the attribute [{$attribute}] as it is.");
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
        return new self("The engine query option [{$key}] is set by the package from the search.");
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

    private static function listSource(?RestrictingExtension $source): string
    {
        return $source === null ? 'ScoutEngine::perimeter() or an instruction' : "the [{$source->name}] extension {$source->class}";
    }
}
