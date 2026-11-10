<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\LockedTeamTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\LockedTicketsTagController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\ReadOnlyTicketsTeamController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Illustrated\LeadDeskController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Newsroom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ProhibitedRelationsTest extends HelpdeskTestCase
{
    private const array NEW_TEAM = ['operation' => 'create', 'attributes' => ['name' => 'Billing']];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk, new Newsroom];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('locked-team-tickets', LockedTeamTicketController::class)->register();
        Rest::resource('locked-tickets-tags', LockedTicketsTagController::class)->register();
        Rest::resource('lead-desks', LeadDeskController::class)->register();
        Rest::resource('read-only-tickets-teams', ReadOnlyTicketsTeamController::class)->register();
    }

    #[Test]
    public function it_checks_the_creation_of_a_related_resource_before_refusing_a_relation_prohibited_on_creation(): void
    {
        $this->teams->answers['create'] = Response::deny('Teams are created by the helpdesk lead.');

        $this->postJson('/locked-team-tickets/mutate', ['mutate' => [$this->creation(['team' => self::NEW_TEAM])]])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Teams are created by the helpdesk lead.']);
    }

    #[Test]
    public function it_refuses_a_relation_prohibited_on_creation_once_its_creation_is_allowed(): void
    {
        $this->postJson('/locked-team-tickets/mutate', ['mutate' => [$this->creation(['team' => self::NEW_TEAM])]])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'The mutate.0.relations.team field is prohibited.',
                'errors' => ['mutate.0.relations.team' => ['The mutate.0.relations.team field is prohibited.']],
            ]);

        $this->assertSame(['create'], $this->teams->calls);
    }

    #[Test]
    public function it_refuses_a_relation_prohibited_on_update_once_its_creation_is_allowed(): void
    {
        $jam = $this->seedTickets()['Printer jam'];

        $this->postJson('/locked-team-tickets/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $jam->id,
            'attributes' => ['title' => 'Paper jam'],
            'relations' => ['team' => self::NEW_TEAM],
        ]]])->assertUnprocessable()->assertExactJson([
            'message' => 'The mutate.0.relations.team field is prohibited.',
            'errors' => ['mutate.0.relations.team' => ['The mutate.0.relations.team field is prohibited.']],
        ]);

        $this->assertSame([['viewAny'], ['create']], [$this->tickets->calls, $this->teams->calls]);
    }

    #[Test]
    #[DataProvider('linkOperations')]
    public function it_refuses_a_relation_prohibited_on_update_under_a_link_entry(string $operation): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $tag = Tag::create(['name' => 'Hardware']);
        $path = 'mutate.0.relations.labelledTickets.0.relations.team';

        if ($operation !== 'attach') {
            $tag->labelledTickets()->attach($jam->id);
        }

        $response = $this->postJson('/locked-tickets-tags/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $tag->id,
            'relations' => ['labelledTickets' => [['operation' => $operation, 'key' => [$jam->id], 'relations' => ['team' => self::NEW_TEAM]]]],
        ]]]);

        $response->assertUnprocessable()->assertExactJson(['message' => "The {$path} field is prohibited.", 'errors' => [$path => ["The {$path} field is prohibited."]]]);
        $this->assertSame(['Billing', 'Support'], DB::table('teams')->orderBy('name')->pluck('name')->all());
    }

    /** @return array<string, array{string}> */
    public static function linkOperations(): array
    {
        return ['attach' => ['attach'], 'detach' => ['detach'], 'sync' => ['sync'], 'toggle' => ['toggle']];
    }

    #[Test]
    public function it_checks_a_creation_through_a_has_many_through_relation_before_refusing_it(): void
    {
        $this->replies->answers['create'] = Response::deny('Replies are written on tickets.');

        $this->postJson('/api/teams/mutate', ['mutate' => [$this->newTeamWithReplies()]])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Replies are written on tickets.']);
    }

    #[Test]
    public function it_refuses_a_creation_through_a_has_many_through_relation_once_it_is_allowed(): void
    {
        $this->postJson('/api/teams/mutate', ['mutate' => [$this->newTeamWithReplies()]])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'The mutate.0.relations.replies field is prohibited.',
                'errors' => ['mutate.0.relations.replies' => ['The mutate.0.relations.replies field is prohibited.']],
            ]);

        $this->assertSame([['viewAny', 'create'], ['create']], [$this->teams->calls, $this->replies->calls]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<array-key, mixed>  $entry
     */
    #[Test]
    #[DataProvider('entriesWithoutOperations')]
    public function it_refuses_every_operation_on_a_relation_that_accepts_none(string $uri, array $attributes, string $relation, array $entry): void
    {
        DB::table('desks')->insert(['id' => 101, 'name' => 'Day desk']);
        DB::table('stories')->insert(['id' => 101, 'desk_id' => 101, 'headline' => 'Flood warning']);
        DB::table('summaries')->insert(['id' => 101, 'story_id' => 101, 'body' => 'Rain until noon']);
        DB::table('teams')->insert(['id' => 101, 'name' => 'Support']);
        DB::table('tickets')->insert(['id' => 101, 'team_id' => 101, 'title' => 'Printer jam', 'status' => 'open']);
        DB::table('replies')->insert(['id' => 101, 'ticket_id' => 101, 'body' => 'On it.']);
        $message = 'The mutate.0.relations.'.$relation.' field is prohibited.';

        $response = $this->postJson($uri, ['mutate' => [['operation' => 'create', 'attributes' => $attributes, 'relations' => [$relation => $entry]]]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['mutate.0.relations.'.$relation => [$message]]]);
        $this->assertSame([1, 1], [DB::table('desks')->count(), DB::table('teams')->count()]);
    }

    /** @return array<string, array{string, array<string, mixed>, string, array<array-key, mixed>}> */
    public static function entriesWithoutOperations(): array
    {
        $desk = ['name' => 'Night desk'];
        $team = ['name' => 'Billing'];

        return [
            'has one through create' => ['/lead-desks/mutate', $desk, 'leadSummary', ['operation' => 'create', 'attributes' => ['body' => 'Late shift']]],
            'has one through attach' => ['/lead-desks/mutate', $desk, 'leadSummary', ['operation' => 'attach', 'key' => 101]],
            'has one through update' => ['/lead-desks/mutate', $desk, 'leadSummary', ['operation' => 'update', 'key' => 101, 'attributes' => ['body' => 'Late shift']]],
            'has many through attach' => ['/api/teams/mutate', $team, 'replies', [['operation' => 'attach', 'key' => [101]]]],
            'has many through update' => ['/api/teams/mutate', $team, 'replies', [['operation' => 'update', 'key' => 101, 'attributes' => ['body' => 'On it.']]]],
            'has many through sync' => ['/api/teams/mutate', $team, 'replies', [['operation' => 'sync', 'key' => [101]]]],
            'has many through delete' => ['/api/teams/mutate', $team, 'replies', [['operation' => 'delete', 'key' => [101]]]],
            'relation without operations create' => ['/read-only-tickets-teams/mutate', $team, 'tickets', [['operation' => 'create', 'attributes' => ['title' => 'Printer jam', 'status' => 'open']]]],
            'relation without operations detach' => ['/read-only-tickets-teams/mutate', $team, 'tickets', [['operation' => 'detach', 'key' => [101]]]],
        ];
    }

    /**
     * @param  array<string, mixed>  $relations
     * @return array<string, mixed>
     */
    private function creation(array $relations): array
    {
        return ['operation' => 'create', 'attributes' => ['title' => 'Printer jam', 'status' => 'open'], 'relations' => $relations];
    }

    /** @return array<string, mixed> */
    private function newTeamWithReplies(): array
    {
        return [
            'operation' => 'create',
            'attributes' => ['name' => 'Billing'],
            'relations' => ['replies' => [['operation' => 'create', 'attributes' => ['body' => 'On it.']]]],
        ];
    }
}
