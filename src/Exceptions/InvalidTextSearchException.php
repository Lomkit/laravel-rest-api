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

    public static function unsupportedStrategy(string $resource, string $strategy): self
    {
        return new self("The resource [{$resource}] declares the text search [{$strategy}], which implements neither QueryTextSearch nor EngineTextSearch.");
    }

    public static function missingEngineGrammar(string $resource, string $strategy): self
    {
        return new self("The resource [{$resource}] declares the text search [{$strategy}], which implements EngineTextSearch but states no engine grammar. Return its capabilities with the attributes the engine filters and sorts in TextCapabilities::\$engine.");
    }

    public static function missingPerimeter(string $resource): self
    {
        return new self("The ScoutEngine of the resource [{$resource}] declares no index perimeter. Declare ->perimeter() with the conditions every search must meet, or ->withoutPerimeter() when every user who can list the resource may see every indexed row.");
    }

    public static function conflictingPerimeter(string $resource): self
    {
        return new self("The ScoutEngine of the resource [{$resource}] declares both ->perimeter() and ->withoutPerimeter(). Keep one of them.");
    }

    public static function unsearchableModel(string $resource, string $model): self
    {
        return new self("The resource [{$resource}] declares a ScoutEngine text search, but its model [{$model}] does not use Laravel\\Scout\\Searchable.");
    }

    public static function unsupportedEngine(string $resource, string $engine): self
    {
        return new self("The resource [{$resource}] declares a ScoutEngine text search, but its model is indexed by [{$engine}], which extends none of the Scout engines.");
    }

    public static function engineNotAnObject(string $resource, string $model, string $engine): self
    {
        return new self("An engine search of the resource [{$resource}] runs an engine restriction, but searchableUsing() of its model [{$model}] returns [{$engine}] instead of the Scout engine. Return the engine from searchableUsing(), as Laravel\\Scout\\Searchable does.");
    }

    public static function notAnAttribute(string $resource, string $declaration, int $index, mixed $attribute): self
    {
        $type = get_debug_type($attribute);

        return new self("The ScoutEngine of the resource [{$resource}] declares in {$declaration} a value of type [{$type}] at position {$index}. Declare a field, or the name of a field of the resource.");
    }

    public static function notAnAttributeName(string $resource, int $index, mixed $attribute): self
    {
        $type = get_debug_type($attribute);

        return new self("The ScoutEngine of the resource [{$resource}] declares in sortable() a value of type [{$type}] at position {$index}. Name one of its filterable() attributes.");
    }

    public static function unknownAttribute(string $resource, string $attribute): self
    {
        return new self("The ScoutEngine of the resource [{$resource}] names the attribute [{$attribute}], which is not a field of the resource. Declare it as a field, for example Integer::make('{$attribute}').");
    }

    public static function unsupportedAttributeType(string $resource, string $attribute, string $type): self
    {
        return new self("The ScoutEngine of the resource [{$resource}] declares the attribute [{$attribute}] as [{$type}]. Index attributes are ID, Integer, Number, Boolean, Text or Enum fields; declare a timestamp as an Integer.");
    }

    public static function callbackAttribute(string $resource, string $attribute): self
    {
        return new self("The ScoutEngine of the resource [{$resource}] declares the attribute [{$attribute}], which filters or sorts through a callback. Declare index attributes without filterUsing() or sortUsing().");
    }

    public static function invalidAttributeName(string $resource, string $attribute): self
    {
        return new self("The ScoutEngine of the resource [{$resource}] declares the attribute [{$attribute}]. Attribute names start with a letter or an underscore and hold only letters, digits and underscores, and [__soft_deleted] is reserved.");
    }

    public static function duplicateAttribute(string $resource, string $attribute): self
    {
        return new self("The ScoutEngine of the resource [{$resource}] declares the attribute [{$attribute}] more than once.");
    }

    public static function unfilterableAttribute(string $resource, string $attribute): self
    {
        return new self("The ScoutEngine of the resource [{$resource}] declares the attribute [{$attribute}], which accepts no filter operator.");
    }

    public static function unsortableAttribute(string $resource, string $attribute): self
    {
        return new self("The ScoutEngine of the resource [{$resource}] sorts on [{$attribute}], which is not one of its filterable() attributes.");
    }

    public static function unsortableEngine(string $resource, string $engine): self
    {
        return new self("The ScoutEngine of the resource [{$resource}] declares sortable() attributes, but the engine [{$engine}] does not sort text searches.");
    }

    public static function reservedSortAttribute(string $resource, string $attribute, string $engine): self
    {
        return new self("The ScoutEngine of the resource [{$resource}] sorts on [{$attribute}], which the engine [{$engine}] reserves and does not sort. Index a unique copy of the key under another name, sortable in the index, and declare it in sortable() or tieBreaker() instead.");
    }

    public static function unlistedTieBreaker(string $resource, string $attribute): self
    {
        return new self("The ScoutEngine of the resource [{$resource}] declares tieBreaker('{$attribute}') without ->servesLists(): the tie-breaker orders the lists served through the engine, and a text search keeps its relevance order. Add ->servesLists(), or remove ->tieBreaker().");
    }

    public static function unsortedTieBreaker(string $resource, string $attribute, string $engine): self
    {
        return new self("The ScoutEngine of the resource [{$resource}] declares tieBreaker('{$attribute}'), but the engine [{$engine}] takes no sort without text, so its lists follow the ranking of the index. Remove ->tieBreaker() and end the customRanking of the index on a unique attribute, such as [{$attribute}].");
    }

    public static function missingQueryBy(string $resource, string $model): self
    {
        return new self("The ScoutEngine of the resource [{$resource}] runs on Typesense, which needs the attributes to search. Set scout.typesense.model-settings.{$model}.search-parameters.query_by.");
    }

    public static function ownedSearchParameter(string $resource, string $model, string $parameter): self
    {
        return new self("The model [{$model}] of the resource [{$resource}] returns [{$parameter}] from typesenseSearchParameters(), which would replace what the ScoutEngine sends to Typesense, such as the text, the page, the sorts, or the perimeter and the restrictions of the filter_by. Remove [{$parameter}] from typesenseSearchParameters(), and declare filters in filterable() or perimeter().");
    }

    public static function unexpectedBuilder(string $builder): self
    {
        return new self("The engine query runs on a plain Laravel\\Scout\\Builder without search or query callback, not on [{$builder}].");
    }

    public static function runsOnEngine(string $resource): self
    {
        return new self("The text search of the resource [{$resource}] runs on its search engine, not in a database query.");
    }

    public static function unlistedEngine(string $resource, string $engine): self
    {
        return new self("The ScoutEngine of the resource [{$resource}] serves its lists with ->servesLists(), but the profile of the engine [{$engine}] does not implement Lomkit\\Rest\\Text\\Engine\\Profiles\\ListsWithoutText, so a list without text cannot run on it. Implement ListsWithoutText in the profile of the engine, or remove ->servesLists() from the text search of [{$resource}] to list its rows in SQL.");
    }

    public static function notOnEngine(string $resource): self
    {
        return new self("The text search of the resource [{$resource}] does not run on a search engine.");
    }

    public static function unrestrictedEngine(string $resource, string $name, string $class, string $engine): self
    {
        return new self("The [{$name}] extension {$class} restricts the rows of {$resource} in SQL, but the text search of {$resource} runs on [{$engine}], which does not apply SQL restrictions. Implement Lomkit\\Rest\\Extensions\\Kinds\\RestrictsEngineQueries in {$class} to restrict the engine search too, index the model with the Scout database or collection driver, leave {$resource} out of the extension with appliesTo(), or add {$class} to the [rest.disabled_extensions] config.");
    }

    public static function unresolvedEngine(string $resource, string $name, string $class, Throwable $reason): self
    {
        return new self("The [{$name}] extension {$class} restricts the rows of {$resource} in SQL but does not implement Lomkit\\Rest\\Extensions\\Kinds\\RestrictsEngineQueries, and the text search or the Scout engine of {$resource} cannot be resolved ({$reason->getMessage()}). A text search of {$resource} is refused on an engine that does not apply SQL restrictions. Implement RestrictsEngineQueries in {$class} to restrict the engine search too, index the model with the Scout database or collection driver, leave {$resource} out of the extension with appliesTo(), or add {$class} to the [rest.disabled_extensions] config.", previous: $reason);
    }
}
