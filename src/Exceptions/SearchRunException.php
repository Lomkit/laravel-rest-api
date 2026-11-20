<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class SearchRunException extends LogicException
{
    public static function missingUser(string $resource): self
    {
        return new self("The search of the resource [{$resource}] has no user. Call as() with the user, or as(null) to search as a guest.");
    }

    public static function engineWindow(string $resource, ?int $window): self
    {
        if ($window === null) {
            return new self("The search of the resource [{$resource}] runs on a search engine that does not report how many results it can return. lazy() reads every row: give lazy() a max, or read the pages with page().");
        }

        return new self("The search of the resource [{$resource}] runs on a search engine, which returns at most its first {$window} results, and the search can match more. Give lazy() a max of at most {$window}, narrow the search, or read the pages with page().");
    }

    public static function engineResultsChanged(string $resource): self
    {
        return new self("The search engine results of the resource [{$resource}] changed while lazy() read their keys. Call lazy() again, and set scout.after_commit to true so that the index changes once the database transactions commit.");
    }

    public static function totalsOnPage(string $resource): self
    {
        return new self("The search of the resource [{$resource}] asks for totals, which page() cannot return: a paginator holds the rows only. Call render(), which returns the totals at the root of the page, or send the search without totals.");
    }

    public static function invalidChunk(int $chunk): self
    {
        return new self("The chunk of lazy() must be at least 1, [{$chunk}] given.");
    }

    public static function invalidMax(int $max): self
    {
        return new self("The max of lazy() must be at least 0, [{$max}] given.");
    }
}
