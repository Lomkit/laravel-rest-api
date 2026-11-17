<?php

namespace Lomkit\Rest\Console\Check;

use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\Engine\Profiles\ListsWithoutText;
use Lomkit\Rest\Text\Engine\ScoutModel;
use Lomkit\Rest\Text\ListingTextSearch;
use Lomkit\Rest\Text\TextTarget;
use Throwable;

/** @internal */
final readonly class EngineListOrder
{
    public static function note(Resource $resource, ListingTextSearch $text, ListsWithoutText $profile): ?string
    {
        try {
            $target = TextTarget::of($resource);
            $key = ScoutModel::tryFrom($target->model)?->keyName();
            $sorts = $profile->listSortKeys() < 1 ? [] : $text->capabilities($target)->engine->sorts ?? [];
        } catch (Throwable) {
            return null;
        }

        return match (true) {
            $key === null => null,
            $profile->listSortKeys() < 1 => self::unstable($resource::class, $profile->name(), $key),
            in_array($key, $sorts, true) => null,
            default => self::unsortedKey($resource::class, $key),
        };
    }

    private static function unsortedKey(string $resource, string $key): string
    {
        return "{$resource} serves its lists through its search engine but sortable() does not declare its key [{$key}]: a list sent without a sort follows the engine order, which can repeat or skip rows between pages. Add [{$key}] to sortable() (and to the sortable settings of the index).";
    }

    private static function unstable(string $resource, string $engine, string $key): string
    {
        return "{$resource} serves its lists through its search engine [{$engine}], which takes no sort without text: its lists follow the engine order, which is not stable and can repeat or skip rows between pages. Give the index a ranking that ends on a unique attribute, such as [{$key}], so that the engine order is stable.";
    }
}
