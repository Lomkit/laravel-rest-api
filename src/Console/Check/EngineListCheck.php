<?php

namespace Lomkit\Rest\Console\Check;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfiles;
use Lomkit\Rest\Text\Engine\Profiles\ListsWithoutText;
use Lomkit\Rest\Text\Engine\Profiles\NullProfile;
use Lomkit\Rest\Text\ListingTextSearch;
use Lomkit\Rest\Text\TextTarget;
use ReflectionMethod;
use Throwable;

/** @internal */
final readonly class EngineListCheck
{
    /** @param list<string> $controllers */
    public function run(array $controllers, RestContext $context): EngineListReport
    {
        $report = new EngineListReport;

        foreach (ControllerResources::of($controllers) as $class) {
            try {
                $resource = $context->resource($class);
                $text = $resource->text();
            } catch (Throwable) {
                continue;
            }

            if ($text instanceof ListingTextSearch && $text->servingLists()) {
                $report = self::check($resource, $text, $report);
                $report = $text->databaseListsAllowed() ? $report->withDatabaseLists($resource::class) : $report;
                $report = self::overrides($resource, 'perimeterQuery') ? $report->withNote(self::unappliedPerimeterQuery($resource::class)) : $report;
                $report = self::overrides($resource, 'defaultOrderBy') ? $report->withNote(EngineListNotes::unappliedDefaultOrder($resource::class)) : $report;
            }
        }

        return $report;
    }

    private static function check(Resource $resource, ListingTextSearch $text, EngineListReport $report): EngineListReport
    {
        $class = $resource::class;

        try {
            $profile = EngineProfiles::forTarget(TextTarget::of($resource));
        } catch (Throwable $exception) {
            return $report->withProblem("The resource [{$class}] serves its lists through its search engine, which cannot be resolved ({$exception->getMessage()}). Configure the Scout engine of its model, or remove ->servesLists() from its text search.");
        }

        return match (true) {
            ! $profile instanceof ListsWithoutText => $report->withProblem(InvalidTextSearchException::unlistedEngine($class, $profile->name())->getMessage()),
            $profile instanceof NullProfile => EngineListOrder::check($resource, $text, $profile, $report->withResource($class)->withNote(self::nullEngine($class, $profile))),
            default => EngineListOrder::check($resource, $text, $profile, $report->withResource($class)),
        };
    }

    private static function overrides(Resource $resource, string $method): bool
    {
        return (new ReflectionMethod($resource, $method))->getDeclaringClass()->getName() !== Resource::class;
    }

    private static function unappliedPerimeterQuery(string $resource): string
    {
        return "{$resource} overrides perimeterQuery(), which does not apply to its lists: they are served through its search engine, restricted by ScoutEngine::perimeter() and the RestrictsEngineQueries extensions. Mirror the perimeter in ScoutEngine::perimeter().";
    }

    private static function nullEngine(string $resource, EngineProfile $profile): string
    {
        return "The resource [{$resource}] serves its lists through its search engine, but its model uses the Scout null driver [{$profile->name()}] in this environment, so its lists will be empty. Run rest:check with the Scout driver of production (SCOUT_DRIVER).";
    }
}
