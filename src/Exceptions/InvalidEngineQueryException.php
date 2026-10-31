<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class InvalidEngineQueryException extends LogicException
{
    public static function undeclaredAttribute(string $attribute): self
    {
        return new self("The engine query has no attribute [{$attribute}]. Declare it with ScoutEngine::filterable() or among the attributes of ScoutEngine::perimeter().");
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
}
