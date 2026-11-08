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
        $results = $window === null ? 'its results page by page' : "at most its first {$window} results";

        return new self("The text search of the resource [{$resource}] runs on a search engine, which returns {$results}. lazy() reads every row: search without text, or read the pages with page().");
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
