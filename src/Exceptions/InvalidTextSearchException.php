<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;
use Throwable;

final class InvalidTextSearchException extends LogicException
{
    public static function noSearchableField(string $resource): self
    {
        return new self("The resource [{$resource}] declares a text search but no searchable field. Declare the text fields to search with ->searchable().");
    }

    public static function notSearchable(string $resource, string $column): self
    {
        return new self("The resource [{$resource}] declares the full-text index column [{$column}], which is not a searchable field. Declare it with ->searchable().");
    }

    public static function emptyIndex(): self
    {
        return new self('FullText::index() needs at least one column.');
    }

    public static function invalidLanguage(string $language): self
    {
        return new self("The full-text language [{$language}] is not a valid text search configuration name.");
    }

    public static function unsupportedDriver(string $resource, string $driver): self
    {
        return new self("The resource [{$resource}] declares a FullText search, which the [{$driver}] database driver does not run. Declare a fallback with ->otherwise(Contains::make()).");
    }

    public static function noRelevance(string $resource): self
    {
        return new self("The text search of the resource [{$resource}] does not order by relevance.");
    }

    /** @param list<list<string>> $groups */
    public static function missingFullTextIndex(string $table, array $groups, Throwable $previous): self
    {
        $columns = implode(' and ', array_map(fn (array $group): string => '('.implode(', ', $group).')', $groups));

        return new self("The table [{$table}] has no FULLTEXT index on {$columns}. Add it with \$table->fullText([...]) in a migration.", 0, $previous);
    }
}
