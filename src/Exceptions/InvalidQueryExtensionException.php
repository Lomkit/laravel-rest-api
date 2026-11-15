<?php

namespace Lomkit\Rest\Exceptions;

use Lomkit\Rest\Extensions\ExtensionName;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Query\QueryPart;
use Lomkit\Rest\Search\Plan\QueryAdditions;

final class InvalidQueryExtensionException extends ExtensionException
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

    public static function aliasRepeated(string $name, string $alias, string $resource): self
    {
        return self::about($name, "The [{$name}] extension selects [{$alias}] twice in the rows of {$resource}. Give each select() its own alias.");
    }

    public static function aliasNamesField(string $name, string $alias, string $resource): self
    {
        return self::about($name, "The [{$name}] extension selects [{$alias}], which is also a field of {$resource}, so the field would render the subquery. Choose another alias.");
    }

    public static function unknownRelation(string $name, string $relation, string $resource): self
    {
        return self::about($name, "The [{$name}] extension eager loads [{$relation}], which is not a path of relations from the model of {$resource}. Give with() the name of a public relation method of the model, declared with a Relation return type, and a dot before each further relation.");
    }

    public static function loadThroughMorphTo(string $name, string $relation, string $morphTo, string $resource): self
    {
        return self::about($name, "The [{$name}] extension eager loads [{$relation}] on {$resource}, past the MorphTo relation [{$morphTo}], whose target models are not known before the rows load. Load [{$morphTo}] alone.");
    }

    /** @param list<string> $names */
    public static function relationTaken(string $relation, string $resource, array $names): self
    {
        $extensions = implode(', ', $names);

        return self::about($names[0] ?? '', "The extensions [{$extensions}] eager load [{$relation}] on {$resource} twice. Load it from one extension, once.");
    }

    public static function relationRepeated(string $name, string $relation, string $resource): self
    {
        return self::about($name, "The [{$name}] extension eager loads [{$relation}] on {$resource} twice. Call with() once for each relation.");
    }

    public static function augmentedQuery(string $name, string $resource, QueryPart $part): self
    {
        return self::about($name, "The [{$name}] extension adds {$part->value} to the query of {$resource} while adding to its rows. An augmentation adds columns, subqueries and eager loads only: restrict rows with RestrictsQueries.");
    }

    public static function invalidLimit(string $name, string $resource, string $method, int $value): self
    {
        return self::about($name, "The [{$name}] extension passes {$value} to {$method}() for {$resource}. Page sizes, totals and include limits are 1 or more.");
    }

    public static function lateAddition(string $name, string $method, string $resource): self
    {
        return self::about($name, "The [{$name}] extension calls {$method}() on the rows of {$resource} after augment() returned. Call column(), select() and with() inside augment().");
    }

    public static function limitsReadWhileNarrowing(string $name, string $resource): self
    {
        return self::about($name, "The [{$name}] extension reads the limits of {$resource} inside narrowLimits(), which computes them. Read \$context->declared instead.");
    }

    /** @param list<string> $names */
    public static function noPageSize(string $resource, array $names): self
    {
        $extensions = implode(', ', $names);

        return self::about($names[0] ?? '', "The extensions [{$extensions}] narrow the page sizes of {$resource} to none. Keep at least one size of its limits() that every allowSizes() lists, at or below every maxSize() and maxTotal() they set.");
    }

    /**
     * @param  list<string>  $names
     * @param  list<int>  $allowed
     */
    public static function defaultAboveSizes(string $resource, array $names, int $default, array $allowed): self
    {
        $extensions = implode(', ', $names);
        $sizes = implode(', ', $allowed);

        return self::about($names[0] ?? '', "The extensions [{$extensions}] narrow the page sizes of {$resource} to [{$sizes}], all above its default limit {$default}, and narrowing never raises it. Keep a size of {$default} or less.");
    }

    /**
     * @param  list<string>  $names
     * @param  list<int>  $allowed
     */
    public static function includeLimitAboveSizes(string $resource, array $names, int $includeLimit, array $allowed): self
    {
        $extensions = implode(', ', $names);
        $sizes = implode(', ', $allowed);

        return self::about($names[0] ?? '', "The extensions [{$extensions}] narrow the include limit of {$resource} to {$includeLimit}, below every page size [{$sizes}], and narrowing never raises it. Keep a page size of {$includeLimit} or less.");
    }

    /** @param list<?string> $owners */
    public static function instructionTaken(string $key, string $resource, array $owners): self
    {
        $names = array_map(static fn (?string $owner): string => $owner ?? 'the resource', $owners);
        sort($names);
        $declaring = implode(', ', $names);
        $extension = array_find($owners, static fn (?string $owner): bool => $owner !== null) ?? '';

        return self::about($extension, "The instruction key [{$key}] of {$resource} is declared by [{$declaring}]. Rename one: the key of an extension instruction is the extension name, a dot and the uriKey() of the instruction.");
    }

    public static function instructionNotAnInstruction(string $name, string $resource, string $entry): self
    {
        return self::about($name, "The [{$name}] extension declares, for {$resource}, the instruction [{$entry}], which is not a class that extends ".Instruction::class.'.');
    }

    public static function instructionBaseListed(string $name, string $resource): self
    {
        return self::about($name, "The [{$name}] extension declares, for {$resource}, ".Instruction::class.' itself. Declare a class that extends it.');
    }

    public static function instructionNotInstantiable(string $name, string $resource, string $class): self
    {
        return self::about($name, "The [{$name}] extension declares, for {$resource}, the instruction {$class}, which is abstract or cannot be instantiated. Declare a concrete class.");
    }

    public static function instructionForeignBinding(string $name, string $resource, string $class, string $bound): self
    {
        return self::about($name, "The container builds [{$bound}] for {$class}, which the [{$name}] extension declares for {$resource}: bind {$class} to itself or to a class that extends it.");
    }
}
