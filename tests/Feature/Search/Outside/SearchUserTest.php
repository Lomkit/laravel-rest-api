<?php

namespace Lomkit\Rest\Tests\Feature\Search\Outside;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\SearchRunException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Search\Outside\Stubs\DeskTeamResource;
use Lomkit\Rest\Tests\Feature\Search\Outside\Stubs\DeskTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use PHPUnit\Framework\Attributes\Test;

class SearchUserTest extends HelpdeskTestCase
{
    private const array BY_ID = ['sorts' => [['field' => 'id']]];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-01-02 00:00:00');

        $old = '2026-01-01 00:00:00';
        DB::table('teams')->insert([['id' => 1, 'name' => 'Billing', 'created_at' => $old, 'updated_at' => $old], ['id' => 2, 'name' => 'Support', 'created_at' => $old, 'updated_at' => $old]]);

        foreach ([[1, 1, 'Printer jam', 'open'], [2, 1, 'Paper jam', 'closed'], [3, 2, 'Password reset', 'open'], [4, 2, 'Laptop setup', 'closed']] as [$id, $team, $title, $status]) {
            DB::table('tickets')->insert(['id' => $id, 'team_id' => $team, 'title' => $title, 'status' => $status, 'created_at' => $old, 'updated_at' => $old]);
        }
    }

    #[Test]
    public function it_reads_the_rows_of_the_given_user_rather_than_the_logged_in_user(): void
    {
        $this->actingAs($lead = new GenericUser(['id' => TicketPolicy::LEAD]));
        $guard = Auth::getDefaultDriver();

        $body = Rest::search(DeskTicketResource::class)
            ->as(new GenericUser(['id' => 2]))
            ->payload(self::BY_ID)
            ->render();

        $this->assertSame([3, 4], array_column($body['data'], 'id'));
        $this->assertSame($lead, Auth::user());
        $this->assertSame($guard, Auth::getDefaultDriver());
    }

    #[Test]
    public function it_applies_the_perimeter_of_the_given_user_to_the_includes(): void
    {
        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]));

        $body = Rest::search(DeskTeamResource::class)
            ->as(new GenericUser(['id' => 2]))
            ->payload([...self::BY_ID, 'includes' => [['relation' => 'tickets', ...self::BY_ID]]])
            ->render();

        $this->assertSame([[], [3, 4]], array_map(fn (array $team): array => array_column($team['tickets'], 'id'), $body['data']));
    }

    #[Test]
    public function it_searches_as_a_guest_while_a_user_is_logged_in(): void
    {
        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]));

        $tickets = Rest::search(DeskTicketResource::class)
            ->as(null)
            ->payload([...self::BY_ID, 'gates' => ['delete']])
            ->render();
        $teams = Rest::search(DeskTeamResource::class)
            ->as(null)
            ->payload([...self::BY_ID, 'includes' => [['relation' => 'tickets', ...self::BY_ID]]])
            ->render();

        $denied = ['authorized_to_delete' => ['allowed' => false, 'message' => 'Only the helpdesk lead deletes tickets.']];
        $this->assertSame([
            ['id' => 1, 'team_id' => 1, 'title' => 'Printer jam', 'status' => 'open', 'gates' => $denied],
            ['id' => 3, 'team_id' => 2, 'title' => 'Password reset', 'status' => 'open', 'gates' => $denied],
        ], json_decode(json_encode($tickets['data']), true));
        $this->assertSame([[1], [3]], array_map(fn (array $team): array => array_column($team['tickets'], 'id'), $teams['data']));
    }

    #[Test]
    public function it_keeps_the_decisions_of_two_users_apart(): void
    {
        $search = Rest::search(CachedTicketResource::class)->payload([...self::BY_ID, 'gates' => ['delete']]);

        $lead = $search->as(new GenericUser(['id' => TicketPolicy::LEAD]))->render();
        $agent = $search->as(new GenericUser(['id' => 2]))->render();
        $cached = $search->as(new GenericUser(['id' => TicketPolicy::LEAD]))->render();

        $this->assertSame([true, true, true, true], $this->deletable($lead));
        $this->assertSame([false, false, false, false], $this->deletable($agent));
        $this->assertSame([true, true, true, true], $this->deletable($cached));
        $this->assertSame([
            'viewAny', 'delete 1', 'delete 2', 'delete 3', 'delete 4',
            'viewAny', 'delete 1', 'delete 2', 'delete 3', 'delete 4',
        ], $this->tickets->calls);
    }

    #[Test]
    public function it_keeps_the_decisions_of_two_guards_apart(): void
    {
        $search = Rest::search(CachedTicketResource::class);
        $user = new GenericUser(['id' => TicketPolicy::LEAD]);

        $search->as($user, 'web')->render();
        $search->as($user, 'web')->render();
        $search->as($user, 'api')->render();

        $this->assertSame(['viewAny', 'viewAny'], $this->tickets->calls);
    }

    #[Test]
    public function it_refuses_to_search_without_a_user(): void
    {
        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]));

        $this->expectExceptionObject(SearchRunException::missingUser(DeskTicketResource::class));

        Rest::search(DeskTicketResource::class)
            ->payload(self::BY_ID)
            ->page();
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return list<bool>
     */
    private function deletable(array $body): array
    {
        $rows = json_decode((string) json_encode($body['data']), true);

        return array_map(fn (array $row): bool => $row['gates']['authorized_to_delete']['allowed'], $rows);
    }
}
