<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class GatesTest extends HelpdeskTestCase
{
    private const array ALLOWED = ['allowed' => true, 'message' => null];

    private const array NOT_DELETED = ['allowed' => false, 'message' => 'Only the helpdesk lead deletes tickets.'];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Team::create(['id' => 1, 'name' => 'Billing']);
        Ticket::create(['id' => 1, 'team_id' => 1, 'title' => 'Printer jam', 'status' => 'open']);
        Ticket::create(['id' => 2, 'team_id' => 1, 'title' => 'Password reset', 'status' => 'closed']);
    }

    #[Test]
    public function it_answers_each_gate_with_allowed_and_message(): void
    {
        $this->withoutDeprecationHandling();

        $response = $this->postJson('/api/tickets/search', ['search' => [
            'gates' => ['view', 'update', 'delete', 'restore', 'forceDelete', 'create'],
            'selects' => [['field' => 'id']],
            'sorts' => [['field' => 'id']],
        ]]);

        $response
            ->assertOk()
            ->assertJsonPath('data.*.gates', [
                [
                    'authorized_to_view' => self::ALLOWED,
                    'authorized_to_update' => self::ALLOWED,
                    'authorized_to_delete' => self::NOT_DELETED,
                    'authorized_to_restore' => self::ALLOWED,
                    'authorized_to_force_delete' => self::ALLOWED,
                ],
                [
                    'authorized_to_view' => self::ALLOWED,
                    'authorized_to_update' => ['allowed' => false, 'message' => 'Closed tickets are read-only.'],
                    'authorized_to_delete' => self::NOT_DELETED,
                    'authorized_to_restore' => self::ALLOWED,
                    'authorized_to_force_delete' => self::ALLOWED,
                ],
            ])
            ->assertJsonPath('meta.gates', ['authorized_to_create' => self::ALLOWED]);
    }

    #[Test]
    public function it_answers_the_gates_of_an_included_row(): void
    {
        $this->teams->answers['view'] = Response::deny();

        $response = $this->postJson('/api/tickets/search', ['search' => [
            'selects' => [['field' => 'id']],
            'sorts' => [['field' => 'id']],
            'includes' => [['relation' => 'team', 'selects' => [['field' => 'id']], 'gates' => ['view']]],
        ]]);

        $response
            ->assertOk()
            ->assertJsonPath('data', [
                ['id' => 1, 'team' => ['id' => 1, 'gates' => ['authorized_to_view' => ['allowed' => false, 'message' => null]]]],
                ['id' => 2, 'team' => ['id' => 1, 'gates' => ['authorized_to_view' => ['allowed' => false, 'message' => null]]]],
            ]);
    }

    #[Test]
    public function it_leaves_out_the_gates_of_a_resource_that_turns_them_off(): void
    {
        $response = $this->postJson('/api/ungated-tickets/search', ['search' => [
            'gates' => ['view'],
            'selects' => [['field' => 'id']],
            'sorts' => [['field' => 'id']],
            'includes' => [['relation' => 'team', 'selects' => [['field' => 'id']], 'gates' => ['view']]],
        ]]);

        $response
            ->assertOk()
            ->assertJsonPath('data.0', ['id' => 1, 'team' => ['id' => 1, 'gates' => ['authorized_to_view' => self::ALLOWED]]])
            ->assertJsonPath('meta', []);
    }

    /** @param list<string> $gates */
    #[Test]
    #[DataProvider('rowsWithoutAnswers')]
    public function it_returns_an_empty_object_for_a_row_without_answers(array $gates, string $meta): void
    {
        $response = $this->postJson('/api/tickets/search', ['search' => ['gates' => $gates, 'selects' => [['field' => 'id']], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk();
        $this->assertSame(
            '{"current_page":1,"data":[{"id":1,"gates":{}},{"id":2,"gates":{}}],"from":1,"last_page":1,"per_page":50,"to":2,"total":2,"meta":'.$meta.'}',
            $response->getContent(),
        );
    }

    /** @return array<string, array{list<string>, string}> */
    public static function rowsWithoutAnswers(): array
    {
        return [
            'no gate' => [[], '{}'],
            'the create gate only' => [['create'], '{"gates":{"authorized_to_create":{"allowed":true,"message":null}}}'],
        ];
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('refusedGates')]
    public function it_refuses_a_gate_that_its_level_does_not_accept(string $uri, array $search, array $errors): void
    {
        $response = $this->postJson($uri, ['search' => $search]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{string, array<string, mixed>, array<string, list<string>>}> */
    public static function refusedGates(): array
    {
        $prohibited = static fn (string $key): array => [$key => ["The {$key} field is prohibited."]];
        $invalid = static fn (string $key): array => [$key => ["The selected {$key} is invalid."]];

        return [
            'viewAny' => ['/api/tickets/search', ['gates' => ['view', 'viewAny']], $invalid('search.gates.1')],
            'an unknown ability' => ['/api/tickets/search', ['gates' => ['approve']], $invalid('search.gates.0')],
            'a value that is not a string' => ['/api/tickets/search', ['gates' => [null]], $invalid('search.gates.0')],
            'a repeated ability' => ['/api/tickets/search', ['gates' => ['view', 'update', 'view']], [
                'search.gates.0' => ['The search.gates.0 field has a duplicate value.'],
                'search.gates.2' => ['The search.gates.2 field has a duplicate value.'],
            ]],
            'create in an include' => [
                '/api/tickets/search', ['includes' => [['relation' => 'team', 'gates' => ['view', 'create']]]], $prohibited('search.includes.0.gates.1'),
            ],
            'create in a nested include' => [
                '/api/tickets/search',
                ['includes' => [['relation' => 'team', 'includes' => [['relation' => 'tickets', 'gates' => ['create']]]]]],
                $prohibited('search.includes.0.includes.0.gates.0'),
            ],
            'viewAny in an include' => ['/api/tickets/search', ['includes' => [['relation' => 'team', 'gates' => ['viewAny']]]], $invalid('search.includes.0.gates.0')],
            'a resource that turns its gates off' => ['/api/ungated-tickets/search', ['gates' => ['viewAny']], $invalid('search.gates.0')],
            'the search of an action' => ['/api/tickets/actions/close-tickets', ['gates' => ['viewAny']], $invalid('search.gates.0')],
        ];
    }

    #[Test]
    public function it_counts_the_gates_in_the_entries_of_a_request(): void
    {
        config(['rest.limits.totals.entries' => 2]);
        $message = 'The search.gates field goes over the limit of 2 list entries in one request.';

        $response = $this->postJson('/api/tickets/search', ['search' => ['gates' => ['view', 'update', 'delete']]]);

        $response
            ->assertUnprocessable()
            ->assertExactJson(['message' => $message, 'errors' => ['search.gates' => [$message]]]);
    }
}
