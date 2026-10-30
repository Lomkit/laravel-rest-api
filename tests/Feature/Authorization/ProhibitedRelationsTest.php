<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\LockedTeamTicketController;
use PHPUnit\Framework\Attributes\Test;

class ProhibitedRelationsTest extends HelpdeskTestCase
{
    private const array NEW_TEAM = ['operation' => 'create', 'attributes' => ['name' => 'Billing']];

    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('locked-team-tickets', LockedTeamTicketController::class)->register();
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

        $this->assertSame([[], ['create']], [$this->tickets->calls, $this->teams->calls]);
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

        $this->assertSame([['create'], ['create']], [$this->teams->calls, $this->replies->calls]);
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
