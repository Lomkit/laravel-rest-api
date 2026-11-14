<?php

namespace Lomkit\Rest\Exceptions;

use Lomkit\Rest\Extensions\ExtensionName;
use Lomkit\Rest\Query\QueryPart;
use Lomkit\Rest\Search\Plan\QueryAdditions;

final class InvalidQueryExtensionException extends InvalidExtensionException
{
    public static function restrictionClause(string $name, string $resource, QueryPart $part): self
    {
        return self::about($name, "The [{$name}] extension adds {$part->value} to the restriction of {$resource}, which holds only where clauses. Restrict through another table with whereHas() or whereExists().");
    }

    /** @param list<string> $scopes */
    public static function restrictionRemovesScopes(string $name, string $resource, array $scopes): self
    {
        $removed = implode(', ', $scopes);

        return self::about($name, "The [{$name}] extension removes the global scope [{$removed}] in the restriction of {$resource}, which would widen the whole query. Keep the global scopes: a restriction holds only where clauses.");
    }

    public static function invalidColumn(string $name, string $column, string $resource): self
    {
        return self::about($name, "The [{$name}] extension adds the column [{$column}] to {$resource}, which is not a column name of its model. Give column() an unqualified column of the model table, or read another table with select().");
    }

    public static function invalidAlias(string $name, string $alias): self
    {
        $length = ExtensionName::MAX_LENGTH;

        return self::about($name, "The [{$name}] extension selects a subquery as [{$alias}], which is not a valid alias. An alias has 1 to {$length} lowercase letters, digits or underscores and starts with a letter; the package prefixes it with [{$name}__].");
    }

    public static function aliasTooLong(string $name, string $alias, string $resource): self
    {
        $length = QueryAdditions::MAX_ALIAS;

        return self::about($name, "The [{$name}] extension selects [{$alias}] in the rows of {$resource}, longer than {$length} characters with its prefix, which PostgreSQL would cut. Shorten the alias.");
    }

    /** @param list<string> $names */
    public static function aliasTaken(string $alias, string $resource, array $names): self
    {
        $extensions = implode(', ', $names);

        return self::about($names[0] ?? '', "The extensions [{$extensions}] select [{$alias}] twice in the rows of {$resource}. Give each select() its own alias.");
    }

    public static function aliasNamesField(string $name, string $alias, string $resource): self
    {
        return self::about($name, "The [{$name}] extension selects [{$alias}], which is also a field of {$resource}, so the field would render the subquery. Choose another alias.");
    }

    public static function unknownRelation(string $name, string $relation, string $resource): self
    {
        return self::about($name, "The [{$name}] extension eager loads [{$relation}], which is not a relation of the model of {$resource}. Give with() the name of a relation method of the model.");
    }

    /** @param list<string> $names */
    public static function relationTaken(string $relation, string $resource, array $names): self
    {
        $extensions = implode(', ', $names);

        return self::about($names[0] ?? '', "The extensions [{$extensions}] eager load [{$relation}] on {$resource} twice. Load it from one extension, once.");
    }

    public static function augmentedQuery(string $name, string $resource, QueryPart $part): self
    {
        return self::about($name, "The [{$name}] extension adds {$part->value} to the query of {$resource} while adding to its rows. An augmentation adds columns, subqueries and eager loads only: restrict rows with RestrictsQueries.");
    }
}
