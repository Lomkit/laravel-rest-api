<?php

namespace Lomkit\Rest\Console\Check;

use Lomkit\Rest\Extensions\Kinds\RestrictsEngineQueries;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\ScoutEngine;
use ReflectionMethod;
use Throwable;

/** @internal */
final readonly class DatabaseListReach
{
    /** @return list<string> */
    public static function problems(Resource $resource, bool $extensions): array
    {
        try {
            $text = $resource->text();
        } catch (Throwable) {
            return [];
        }

        if (! $text instanceof ScoutEngine || ! $text->databaseListsAllowed()) {
            return [];
        }

        $problems = $text->declaresPerimeter() && ! self::sqlPerimeter($resource) ? [self::unmirroredPerimeter($resource::class)] : [];

        return $extensions ? [...$problems, ...self::engineOnly($resource)] : $problems;
    }

    private static function sqlPerimeter(Resource $resource): bool
    {
        $overridden = (new ReflectionMethod($resource, 'perimeterQuery'))->getDeclaringClass()->getName() !== Resource::class;

        return $overridden && ! PerimeterCheck::marksWithoutPerimeter($resource::class);
    }

    /** @return list<string> */
    private static function engineOnly(Resource $resource): array
    {
        $registry = $resource->context()->extensions();
        $problems = [];

        foreach ($registry->applicable($resource::class, RestrictsEngineQueries::class) as $name => $extension) {
            if (! $extension instanceof RestrictsQueries) {
                $problems[] = self::unrestrictedSql($resource::class, $name, $registry->classOf($name));
            }
        }

        return $problems;
    }

    private static function unmirroredPerimeter(string $resource): string
    {
        return "{$resource} allows database lists and restricts its engine search with ScoutEngine::perimeter(), but its SQL search has no perimeterQuery() of its own: a request with source: database lists the rows that the engine perimeter keeps out. Mirror ScoutEngine::perimeter() in perimeterQuery(), or remove ->allowsDatabaseLists().";
    }

    private static function unrestrictedSql(string $resource, string $name, string $class): string
    {
        return "The [{$name}] extension {$class} restricts the engine search of {$resource} through RestrictsEngineQueries but not its SQL search, which {$resource} runs on request with ->allowsDatabaseLists(): a request with source: database lists the rows that the extension keeps out. Implement RestrictsQueries in {$class} too, or remove ->allowsDatabaseLists().";
    }
}
