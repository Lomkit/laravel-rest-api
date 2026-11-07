<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;

class ObservedTeamResource extends TeamResource
{
    public static string $response = ObservedTeamResponse::class;

    /** @var array<int, int> */
    public static array $searched = [];

    #[\Override]
    public function searched(LengthAwarePaginator $page): void
    {
        foreach ($page->items() as $team) {
            if ($team instanceof Model) {
                self::$searched[(int) $team->getKey()] = self::ticketCount($team);
            }
        }
    }

    public static function ticketCount(Model $team): int
    {
        $tickets = $team->getRelation('tickets');

        return $tickets instanceof Collection ? $tickets->count() : 0;
    }
}
