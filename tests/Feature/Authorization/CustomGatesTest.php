<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class CustomGatesTest extends HelpdeskTestCase
{
    private const array ALLOWED = ['allowed' => true, 'message' => null];

    private const array NOT_ESCALATED = ['allowed' => false, 'message' => 'Closed tickets are not escalated.'];

    private const array DENIED = ['allowed' => false, 'message' => null];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Team::create(['id' => 1, 'name' => 'Billing']);
        Ticket::create(['id' => 1, 'team_id' => 1, 'title' => 'Printer jam', 'status' => 'open']);
        Ticket::create(['id' => 2, 'team_id' => 1, 'title' => 'Password reset', 'status' => 'closed']);
    }

    #[Test]
    public function it_answers_the_gates_that_a_resource_declares(): void
    {
        $response = $this->postJson('/api/tickets/search', ['search' => [
            'gates' => ['view', 'escalate', 'reassignTeam', 'archive'],
            'selects' => [['field' => 'id']],
            'sorts' => [['field' => 'id']],
        ]]);

        $response
            ->assertOk()
            ->assertJsonPath('data', [
                ['id' => 1, 'gates' => [
                    'authorized_to_view' => self::ALLOWED,
                    'authorized_to_escalate' => self::ALLOWED,
                    'authorized_to_reassign_team' => self::ALLOWED,
                    'authorized_to_archive' => self::DENIED,
                ]],
                ['id' => 2, 'gates' => [
                    'authorized_to_view' => self::ALLOWED,
                    'authorized_to_escalate' => self::NOT_ESCALATED,
                    'authorized_to_reassign_team' => self::ALLOWED,
                    'authorized_to_archive' => self::DENIED,
                ]],
            ])
            ->assertJsonPath('meta', []);
        $this->assertSame(['viewAny', 'view 1', 'escalate 1', 'reassignTeam 1', 'view 2', 'escalate 2', 'reassignTeam 2'], $this->tickets->calls);
    }

    #[Test]
    public function it_answers_a_declared_gate_under_the_name_of_the_config(): void
    {
        config(['rest.gates.names.authorized_to_reassign_team' => 'can_move']);

        $response = $this->postJson('/api/tickets/search', ['search' => [
            'gates' => ['reassignTeam', 'escalate'],
            'selects' => [['field' => 'id']],
            'sorts' => [['field' => 'id']],
        ]]);

        $response
            ->assertOk()
            ->assertJsonPath('data.*.gates', [
                ['can_move' => self::ALLOWED, 'authorized_to_escalate' => self::ALLOWED],
                ['can_move' => self::ALLOWED, 'authorized_to_escalate' => self::NOT_ESCALATED],
            ]);
    }

    #[Test]
    public function it_answers_the_declared_gates_of_an_included_resource(): void
    {
        $response = $this->postJson('/api/teams/search', ['search' => [
            'selects' => [['field' => 'id']],
            'includes' => [['relation' => 'tickets', 'selects' => [['field' => 'id']], 'sorts' => [['field' => 'id']], 'gates' => ['escalate']]],
        ]]);

        $response
            ->assertOk()
            ->assertJsonPath('data.0.tickets', [
                ['id' => 1, 'gates' => ['authorized_to_escalate' => self::ALLOWED]],
                ['id' => 2, 'gates' => ['authorized_to_escalate' => self::NOT_ESCALATED]],
            ]);
    }

    #[Test]
    public function it_asks_the_policy_once_for_each_distinct_row(): void
    {
        $response = $this->postJson('/api/tickets/search', ['search' => [
            'gates' => ['escalate'],
            'selects' => [['field' => 'id']],
            'sorts' => [['field' => 'id']],
            'includes' => [['relation' => 'team', 'includes' => [['relation' => 'tickets', 'sorts' => [['field' => 'id']], 'gates' => ['escalate']]]]],
        ]]);

        $response
            ->assertOk()
            ->assertJsonPath('data.1.team.tickets.0.gates', ['authorized_to_escalate' => self::ALLOWED]);
        $this->assertSame(['escalate 1', 'escalate 2'], array_values(array_filter(
            $this->tickets->calls,
            static fn (string $call): bool => str_starts_with($call, 'escalate'),
        )));
    }

    #[Test]
    public function it_leaves_out_the_declared_gates_of_a_resource_that_turns_them_off(): void
    {
        $response = $this->postJson('/api/ungated-tickets/search', ['search' => [
            'gates' => ['escalate'],
            'selects' => [['field' => 'id']],
            'sorts' => [['field' => 'id']],
        ]]);

        $response
            ->assertOk()
            ->assertJsonPath('data', [['id' => 1], ['id' => 2]]);
        $this->assertSame(['viewAny'], $this->tickets->calls);
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('undeclaredGates')]
    public function it_refuses_a_gate_that_the_resource_of_its_level_does_not_declare(string $uri, array $search, array $errors): void
    {
        $response = $this->postJson($uri, ['search' => $search]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{string, array<string, mixed>, array<string, list<string>>}> */
    public static function undeclaredGates(): array
    {
        $invalid = static fn (string $key): array => [$key => ["The selected {$key} is invalid."]];

        return [
            'a gate of another resource' => ['/api/teams/search', ['gates' => ['escalate']], $invalid('search.gates.0')],
            'in an include of another resource' => ['/api/tickets/search', ['includes' => [['relation' => 'team', 'gates' => ['escalate']]]], $invalid('search.includes.0.gates.0')],
            'another case' => ['/api/tickets/search', ['gates' => ['Escalate']], $invalid('search.gates.0')],
            'the snake case name' => ['/api/tickets/search', ['gates' => ['reassign_team']], $invalid('search.gates.0')],
            'a repeated declared gate' => ['/api/tickets/search', ['gates' => ['escalate', 'view', 'escalate']], [
                'search.gates.0' => ['The search.gates.0 field has a duplicate value.'],
                'search.gates.2' => ['The search.gates.2 field has a duplicate value.'],
            ]],
        ];
    }

    /** @param list<string> $gates */
    #[Test]
    #[DataProvider('detailedGates')]
    public function it_lists_the_gates_of_a_resource_in_its_details(string $uri, array $gates): void
    {
        $response = $this->getJson($uri);

        $response
            ->assertOk()
            ->assertJsonPath('data.gates', $gates);
    }

    /** @return array<string, array{string, list<string>}> */
    public static function detailedGates(): array
    {
        return [
            'built-in and declared gates' => ['/api/tickets', ['view', 'create', 'update', 'delete', 'restore', 'forceDelete', 'escalate', 'reassignTeam', 'archive']],
            'built-in gates only' => ['/api/teams', ['view', 'create', 'update', 'delete', 'restore', 'forceDelete']],
            'a resource that turns its gates off' => ['/api/ungated-tickets', []],
        ];
    }
}
