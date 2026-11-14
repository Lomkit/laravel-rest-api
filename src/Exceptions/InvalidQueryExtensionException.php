<?php

namespace Lomkit\Rest\Exceptions;

use Lomkit\Rest\Query\QueryPart;

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
}
