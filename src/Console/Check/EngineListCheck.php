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
                $report = self::check($resource, $report);
            }
        }

        return $report;
    }

    private static function check(Resource $resource, EngineListReport $report): EngineListReport
    {
        $class = $resource::class;

        try {
            $profile = EngineProfiles::forTarget(TextTarget::of($resource));
        } catch (Throwable $exception) {
            return $report->withProblem("The resource [{$class}] serves its lists through its search engine, which cannot be resolved ({$exception->getMessage()}). Configure the Scout engine of its model, or remove ->servesLists() from its text search.");
        }

        return match (true) {
            ! $profile instanceof ListsWithoutText => $report->withProblem(InvalidTextSearchException::unlistedEngine($class, $profile->name())->getMessage()),
            $profile instanceof NullProfile => $report->withProblem(self::nullEngine($class, $profile)),
            default => $report->withResource($class),
        };
    }

    private static function nullEngine(string $resource, EngineProfile $profile): string
    {
        return "The resource [{$resource}] serves its lists through its search engine, but its model uses the Scout null driver [{$profile->name()}] in this environment, so every list answers an empty page. Set scout.driver to an engine that indexes the rows, or remove ->servesLists() from the text search of [{$resource}].";
    }
}
