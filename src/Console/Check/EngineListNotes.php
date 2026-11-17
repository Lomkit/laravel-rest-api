<?php

namespace Lomkit\Rest\Console\Check;

/** @internal */
final readonly class EngineListNotes
{
    public static function unsortedKey(string $resource, string $key): string
    {
        return "{$resource} serves its lists through its search engine but declares neither ->tieBreaker() nor its key [{$key}] in sortable(): a list sent without a sort follows the engine order, which can repeat or skip rows between pages. Declare ->tieBreaker() with a unique attribute that the index sorts, or add [{$key}] to sortable() (and to the sortable settings of the index): a list sent without a sort then follows it, descending.";
    }

    /** @param list<string> $reserved */
    public static function untiedReserved(string $resource, string $engine, array $reserved): string
    {
        $names = implode('], [', $reserved);

        return "{$resource} serves its lists through its search engine [{$engine}] without ->tieBreaker(): rows with equal sort values can repeat or skip between pages. Declare ->tieBreaker() with a unique attribute that the index sorts, never [{$names}], which the engine does not sort: every list then ends its order on it, descending, and the client keeps one sort less.";
    }

    public static function singleSort(string $resource, string $engine): string
    {
        return "{$resource} serves its lists through its search engine [{$engine}], which takes one sort without text: a list that the client sorts follows that sort alone, so its order is not stable unless the sorted attribute is unique.";
    }

    public static function unappliedDefaultOrder(string $resource): string
    {
        return "{$resource} overrides defaultOrderBy(), which does not apply to its lists: a list sent without a sort follows the tie-breaker or the key of its search engine, descending. Declare ScoutEngine::tieBreaker() with the attribute to order by.";
    }

    public static function unranked(string $resource, string $engine, string $key): string
    {
        return "{$resource} serves its lists through its search engine [{$engine}], which takes no sort without text: its lists follow the ranking of the index, which can repeat or skip rows between pages. End the customRanking of the index on a unique attribute, such as [{$key}], so that the order is stable.";
    }
}
