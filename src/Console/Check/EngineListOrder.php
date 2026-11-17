<?php

namespace Lomkit\Rest\Console\Check;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\Engine\Profiles\ListsWithoutText;
use Lomkit\Rest\Text\Engine\Profiles\ReservesSortAttributes;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\ListingTextSearch;
use Lomkit\Rest\Text\TextTarget;
use Throwable;

/** @internal */
final readonly class EngineListOrder
{
    public static function check(Resource $resource, ListingTextSearch $text, ListsWithoutText $profile, EngineListReport $report): EngineListReport
    {
        try {
            $target = TextTarget::of($resource);
            $key = ScoutModel::tryFrom($target->model)?->keyName();
            $sorts = $text->capabilities($target)->engine->sorts ?? [];
        } catch (InvalidTextSearchException $exception) {
            return $report->withProblem($exception->getMessage());
        } catch (Throwable) {
            return $report;
        }

        $note = $key === null ? null : self::note($resource::class, $profile, $text->listTieBreaker(), $key, $sorts);

        return $note === null ? $report : $report->withNote($note);
    }

    /** @param list<string> $sorts */
    private static function note(string $resource, ListsWithoutText $profile, ?string $tieBreaker, string $key, array $sorts): ?string
    {
        return match (true) {
            $profile->listSortKeys() < 1 => EngineListNotes::unranked($resource, $profile->name(), $key),
            $profile->listSortKeys() === 1 => array_diff($sorts, [$key, $tieBreaker]) === [] ? null : EngineListNotes::singleSort($resource, $profile->name()),
            $tieBreaker !== null => null,
            $profile instanceof ReservesSortAttributes => EngineListNotes::untiedReserved($resource, $profile->name(), $profile->reservedSorts()),
            in_array($key, $sorts, true) => null,
            default => EngineListNotes::unsortedKey($resource, $key),
        };
    }
}
