<?php

namespace Lomkit\Rest\Tests\Feature\Details;

use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\LeadTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\TrashableLeadTicketController;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricTicketController;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class UserAuthorizationsTest extends HelpdeskTestCase
{
    private const array ALLOWED = ['allowed' => true, 'message' => null];

    /** @var list<string> */
    private array $evaluated = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        MetricTicketResource::reset();
        Rest::resource('metric-tickets', MetricTicketController::class)
            ->middleware('auth')
            ->register();
        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]));
        Event::listen(GateEvaluated::class, function (GateEvaluated $event): void {
            $this->evaluated[] = $event->ability;
        });
    }

    /**
     * @param  array<string, array{allowed: bool, message: ?string}>  $authorizations
     * @param  list<bool>  $actions
     * @param  list<bool>  $metrics
     */
    #[Test]
    #[DataProvider('users')]
    public function it_answers_the_class_level_abilities_of_the_user(bool $agent, array $authorizations, array $actions, array $metrics): void
    {
        $this->decideFor($agent);

        $this->getJson('/metric-tickets?with=authorizations,actions.authorized,metrics.authorized')
            ->assertOk()
            ->assertJsonPath('data.authorizations', $authorizations)
            ->assertJsonPath('data.actions.*.authorized', $actions)
            ->assertJsonPath('data.metrics.*.authorized', $metrics);

        $this->assertSame(['viewAny', 'create'], $this->tickets->calls);
        $this->assertSame(['viewAny', 'create', 'viewReports'], $this->evaluated);
    }

    /** @return array<string, array{bool, array<string, array{allowed: bool, message: ?string}>, list<bool>, list<bool>}> */
    public static function users(): array
    {
        return [
            'a user who can create and read reports' => [
                true,
                ['viewAny' => self::ALLOWED, 'create' => self::ALLOWED, 'viewReports' => self::ALLOWED],
                [true, true, true, true, true],
                [true, true, true, true, true, true, true],
            ],
            'a user who cannot' => [
                false,
                [
                    'viewAny' => self::ALLOWED,
                    'create' => ['allowed' => false, 'message' => 'Only agents create tickets.'],
                    'viewReports' => ['allowed' => false, 'message' => 'Reports are for the helpdesk lead.'],
                ],
                [true, true, false, true, true],
                [true, true, true, false, true, true, true],
            ],
        ];
    }

    /**
     * @param  list<string>  $calls
     * @param  list<string>  $evaluated
     * @param  list<string>  $keys
     */
    #[Test]
    #[DataProvider('parts')]
    public function it_calls_only_the_policies_that_the_requested_parts_need(string $with, array $calls, array $evaluated, array $keys): void
    {
        $this->decideFor(false);

        $data = $this->getJson("/metric-tickets{$with}")
            ->assertOk()
            ->json('data');

        $this->assertSame($calls, $this->tickets->calls);
        $this->assertSame($evaluated, $this->evaluated);
        $this->assertSame($keys, array_keys($data));
    }

    /** @return array<string, array{string, list<string>, list<string>, list<string>}> */
    public static function parts(): array
    {
        $static = 'fields.capabilities,fields.filter,fields.rules,fields.meta,relations.constraints,relations.operations,relations.pivot_fields,actions.fields,actions.meta,metrics.meta,instructions.fields,instructions.meta,scopes.fields,scopes.meta,rules,text,gates';

        return [
            'no part' => ['', ['viewAny'], ['viewAny'], ['fields', 'limits', 'meta']],
            'every part without a policy' => ["?with={$static}", ['viewAny'], ['viewAny'], ['actions', 'metrics', 'instructions', 'fields', 'limits', 'scopes', 'relations', 'rules', 'text', 'gates', 'meta']],
            'the flags of the actions' => ['?with=actions.authorized', ['viewAny', 'create'], ['viewAny', 'create'], ['actions', 'fields', 'limits', 'meta']],
            'the flags of the metrics' => ['?with=metrics.authorized', ['viewAny'], ['viewAny', 'viewReports'], ['metrics', 'fields', 'limits', 'meta']],
            'the authorizations' => ['?with=authorizations', ['viewAny', 'create'], ['viewAny', 'create', 'viewReports'], ['fields', 'limits', 'authorizations', 'meta']],
        ];
    }

    #[Test]
    public function it_flags_the_actions_without_the_authorizations(): void
    {
        $this->decideFor(false);

        $this->getJson('/metric-tickets?with=actions.authorized')
            ->assertOk()
            ->assertJsonPath('data.actions.*.authorized', [true, true, false, true, true])
            ->assertJsonMissingPath('data.authorizations')
            ->assertJsonMissingPath('data.metrics');
    }

    /**
     * @param  array<string, int>  $actions
     * @param  array<string, int>  $metrics
     */
    #[Test]
    #[DataProvider('routes')]
    public function it_flags_each_action_and_metric_as_its_route_answers(bool $agent, array $actions, array $metrics): void
    {
        $this->seedTickets();
        $this->decideFor($agent);
        $details = $this->getJson('/metric-tickets?with=authorizations,actions.authorized,metrics.authorized')->assertOk();

        $targets = array_column($details->json('data.actions'), 'target', 'uriKey');

        foreach ($actions as $key => $status) {
            $this->assertSame($status, $this->postJson("/metric-tickets/actions/{$key}", $targets[$key] === 'search' ? ['search' => []] : [])->status());
        }

        foreach ($metrics as $key => $status) {
            $this->assertSame($status, $this->postJson("/metric-tickets/metrics/{$key}")->status());
        }

        $this->assertSame(self::flags($actions), array_column($details->json('data.actions'), 'authorized', 'uriKey'));
        $this->assertSame(self::flags($metrics), array_intersect_key(array_column($details->json('data.metrics'), 'authorized', 'uriKey'), $metrics));
    }

    /** @return array<string, array{bool, array<string, int>, array<string, int>}> */
    public static function routes(): array
    {
        $actions = ['close-tickets' => 200, 'refresh-tickets' => 200, 'import-tickets' => 200, 'report-outage' => 200, 'watch-tickets' => 200];
        $metrics = ['ticket-backlog' => 200, 'tickets-by-status' => 200, 'guest-ticket-backlog' => 200, 'reported-ticket-backlog' => 200, 'unchecked-ticket-backlog' => 200, 'quiet-ticket-backlog' => 200];

        return [
            'a user who can create and read reports' => [true, $actions, $metrics],
            'a user who cannot' => [false, array_replace($actions, ['import-tickets' => 403]), array_replace($metrics, ['reported-ticket-backlog' => 403])],
        ];
    }

    /**
     * @param  class-string<Controller>  $controller
     * @param  list<string>  $soft
     */
    #[Test]
    #[DataProvider('leads')]
    public function it_answers_the_abilities_that_the_resource_adds_to_its_endpoints(string $controller, array $soft): void
    {
        Rest::resource('lead-tickets', $controller)->register();
        $this->tickets->answers['runActions'] = Response::deny('Actions are for the helpdesk lead.');

        $this->getJson('/lead-tickets?with=authorizations,actions.authorized,metrics.authorized')
            ->assertOk()
            ->assertJsonPath('data.authorizations', [
                'viewAny' => self::ALLOWED,
                'create' => self::ALLOWED,
                'updateAny' => self::ALLOWED,
                'runActions' => ['allowed' => false, 'message' => 'Actions are for the helpdesk lead.'],
                'deleteAny' => self::ALLOWED,
                ...array_fill_keys($soft, self::ALLOWED),
            ])
            ->assertJsonPath('data.actions.*.authorized', [false, false, false, true, true]);

        $this->assertSame(['viewAny', 'create', 'updateAny', 'runActions', 'deleteAny', ...$soft], $this->tickets->calls);
    }

    /** @return array<string, array{class-string<Controller>, list<string>}> */
    public static function leads(): array
    {
        return [
            'a model without soft deletes' => [LeadTicketController::class, []],
            'a soft-deletable model' => [TrashableLeadTicketController::class, ['restoreAny', 'forceDeleteAny']],
        ];
    }

    #[Test]
    public function it_allows_every_ability_of_a_resource_that_turns_authorizing_off(): void
    {
        $this->tickets->answers['create'] = Response::deny('Only agents create tickets.');

        $this->getJson('/api/public-tickets?with=authorizations,actions.authorized,metrics.authorized')
            ->assertOk()
            ->assertJsonPath('data.authorizations', ['viewAny' => self::ALLOWED, 'create' => self::ALLOWED])
            ->assertJsonPath('data.actions.*.authorized', [true, true, true, true, true]);

        $this->assertSame([], $this->tickets->calls);
    }

    /**
     * @param  array<string, int>  $statuses
     * @return array<string, bool>
     */
    private static function flags(array $statuses): array
    {
        return array_map(fn (int $status): bool => $status !== 403, $statuses);
    }

    private function decideFor(bool $agent): void
    {
        if ($agent) {
            Gate::define('viewReports', fn (?GenericUser $user): Response => Response::allow());

            return;
        }

        $this->tickets->answers['create'] = Response::deny('Only agents create tickets.');
        Gate::define('viewReports', fn (?GenericUser $user): Response => Response::deny('Reports are for the helpdesk lead.'));
    }
}
