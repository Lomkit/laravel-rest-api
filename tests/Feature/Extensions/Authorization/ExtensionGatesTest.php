<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Authorization;

use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Extensions\Applicability;
use Lomkit\Rest\Extensions\Contexts\GatesContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\AugmentedPreparingExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstGates;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RowCheckLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondGates;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SharingTicketPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;

class ExtensionGatesTest extends HelpdeskTestCase
{
    private const array TICKET_GATES = ['view', 'create', 'update', 'delete', 'escalate', 'reassignTeam', 'archive'];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        self::reset();
        $this->app->instance(TicketPolicy::class, $this->tickets = new SharingTicketPolicy);
        Team::forceCreate(['id' => 1, 'name' => 'Billing']);
        Ticket::forceCreate(['id' => 1, 'team_id' => 1, 'title' => 'Printer jam', 'status' => 'open']);
        Ticket::forceCreate(['id' => 2, 'team_id' => 1, 'title' => 'Password reset', 'status' => 'closed']);
    }

    #[\Override]
    protected function tearDown(): void
    {
        self::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_lists_extension_gates_after_the_resource_gates_in_details(): void
    {
        config(['rest.extensions' => ['a' => FirstGates::class, 'b' => SecondGates::class]]);
        FirstGates::$gates = ['export', 'share'];
        SecondGates::$gates = ['share', 'archive', 'publish'];

        $this->getJson('/api/tickets?with=gates')
            ->assertOk()
            ->assertJsonPath('data.gates', [...self::TICKET_GATES, 'export', 'share', 'publish']);
    }

    /** @param array<string, class-string<Extension>> $extensions */
    #[Test]
    #[DataProvider('configOrders')]
    public function it_lists_the_gates_in_the_same_order_whatever_the_config_order(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);
        FirstGates::$gates = ['share', 'export'];
        SecondGates::$gates = ['publish', 'share'];

        $this->getJson('/api/tickets?with=gates')
            ->assertOk()
            ->assertJsonPath('data.gates', [...self::TICKET_GATES, 'publish', 'share', 'export']);
    }

    /** @return iterable<string, array{array<string, class-string<Extension>>}> */
    public static function configOrders(): iterable
    {
        yield 'b before a' => [['b' => FirstGates::class, 'a' => SecondGates::class]];
        yield 'a before b' => [['a' => SecondGates::class, 'b' => FirstGates::class]];
    }

    #[Test]
    public function it_answers_an_extension_gate_on_each_row(): void
    {
        config(['rest.extensions' => ['shares' => FirstGates::class], 'rest.gates.names.authorized_to_share' => 'can_share']);
        FirstGates::$gates = ['share'];

        $response = $this->postJson('/api/tickets/search', ['search' => ['gates' => ['share'], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.gates', [
            ['can_share' => ['allowed' => true, 'message' => null]],
            ['can_share' => ['allowed' => false, 'message' => 'Only open tickets are shared.']],
        ]);
        $this->assertSame(['viewAny', 'share 1', 'share 2'], $this->tickets->calls);
    }

    #[Test]
    public function it_accepts_an_extension_gate_in_an_include_of_its_resource(): void
    {
        config(['rest.extensions' => ['shares' => FirstGates::class]]);
        FirstGates::$gates = ['share'];
        FirstGates::$resources = [TicketResource::class];

        $response = $this->postJson('/api/teams/search', ['search' => ['includes' => [['relation' => 'tickets', 'gates' => ['share']]]]]);

        $answers = [];

        foreach ($response->assertOk()->json('data.0.tickets') as $ticket) {
            $answers[$ticket['id']] = $ticket['gates']['authorized_to_share']['allowed'];
        }

        ksort($answers);
        $this->assertSame([1 => true, 2 => false], $answers);
    }

    #[Test]
    public function it_refuses_an_extension_gate_at_the_level_of_another_resource(): void
    {
        config(['rest.extensions' => ['shares' => FirstGates::class]]);
        FirstGates::$gates = ['share'];
        FirstGates::$resources = [TicketResource::class];

        $this->postJson('/api/teams/search', ['search' => ['gates' => ['share']]])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['search.gates.0' => ['The selected search.gates.0 is invalid.']]);
    }

    #[Test]
    public function it_refuses_an_extension_gate_when_the_extension_does_not_apply(): void
    {
        config(['rest.extensions' => ['shares' => FirstGates::class]]);
        FirstGates::$gates = ['share'];
        FirstGates::$resources = [TeamResource::class];

        $this->postJson('/api/tickets/search', ['search' => ['gates' => ['share']]])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['search.gates.0' => ['The selected search.gates.0 is invalid.']]);
        $this->getJson('/api/teams?with=gates')->assertOk()->assertJsonPath('data.gates', ['view', 'create', 'update', 'delete', 'share']);
    }

    #[Test]
    public function it_lists_no_gate_for_a_resource_that_turns_them_off(): void
    {
        config(['rest.extensions' => ['shares' => FirstGates::class]]);
        FirstGates::$gates = ['share'];
        $search = ['selects' => [['field' => 'id']], 'sorts' => [['field' => 'id']]];

        $this->getJson('/api/ungated-tickets?with=gates')->assertOk()->assertJsonPath('data.gates', []);
        $declared = $this->postJson('/api/ungated-tickets/search', ['search' => [...$search, 'gates' => ['escalate']]])->assertOk()->json();
        $extended = $this->postJson('/api/ungated-tickets/search', ['search' => [...$search, 'gates' => ['share']]])->assertOk()->json();

        $this->assertSame([['id' => 1], ['id' => 2]], $extended['data']);
        $this->assertSame($declared, $extended);
        $this->assertSame(['viewAny', 'viewAny', 'viewAny'], $this->tickets->calls);
    }

    /** @param list<mixed> $gates */
    #[Test]
    #[DataProvider('misdeclaredGates')]
    public function it_names_the_extension_of_a_misdeclared_gate(array $gates, int $index, mixed $name): void
    {
        config(['rest.extensions' => ['acl' => FirstGates::class]]);
        FirstGates::$gates = $gates;
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidResourceException::invalidGate('The [acl] extension '.FirstGates::class.' in gates() for '.TicketResource::class, $index, $name));

        $this->getJson('/api/tickets?with=gates');
    }

    /** @return iterable<string, array{list<mixed>, int, mixed}> */
    public static function misdeclaredGates(): iterable
    {
        yield 'a capital first letter' => [['Share'], 0, 'Share'];
        yield 'viewAny' => [['share', 'viewAny'], 1, 'viewAny'];
        yield 'a built-in gate' => [['update'], 0, 'update'];
        yield 'a name listed twice' => [['share', 'export', 'share'], 2, 'share'];
        yield 'an integer' => [[7], 0, 7];
    }

    #[Test]
    public function it_accepts_a_gate_that_the_resource_also_declares(): void
    {
        config(['rest.extensions' => ['acl' => FirstGates::class]]);
        FirstGates::$gates = ['archive', 'escalate'];

        $this->getJson('/api/tickets?with=gates')->assertOk()->assertJsonPath('data.gates', self::TICKET_GATES);
    }

    #[Test]
    public function it_reads_the_extension_gates_once_per_context_and_only_when_gates_are_read(): void
    {
        config(['rest.extensions' => ['acl' => FirstGates::class]]);
        FirstGates::$gates = ['share'];

        $this->postJson('/api/tickets/search', ['search' => ['sorts' => [['field' => 'id']]]])->assertOk();
        $this->assertCount(0, FirstGates::$contexts);
        $definition = $this->contextFor(null)->resource(TicketResource::class)->definition();
        $this->assertSame($definition->gates(), $definition->gates());
        $this->assertCount(1, FirstGates::$contexts);
    }

    #[Test]
    public function it_gives_the_declaration_the_resource_class_only(): void
    {
        config(['rest.extensions' => ['acl' => FirstGates::class]]);
        FirstGates::$gates = ['share'];

        $this->getJson('/api/tickets?with=gates')->assertOk();

        $this->assertSame(TicketResource::class, FirstGates::$contexts[0]->resource);
        $this->assertSame(['resource'], array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(GatesContext::class))->getProperties(ReflectionProperty::IS_PUBLIC),
        ));
    }

    #[Test]
    public function it_hands_an_extension_gate_to_the_augmentation_and_the_preparation(): void
    {
        config(['rest.extensions' => ['shares' => FirstGates::class, 'aug' => AugmentedPreparingExtension::class]]);
        FirstGates::$gates = ['share'];

        $this->postJson('/api/tickets/search', ['search' => ['gates' => ['share'], 'sorts' => [['field' => 'id']]]])->assertOk();

        $this->assertSame(['share'], AugmentedPreparingExtension::$gates);
        $this->assertCount(1, AugmentedPreparingExtension::$received);
        $this->assertSame('share', AugmentedPreparingExtension::$received[0]->ability);
        $this->assertSame([1, 2], AugmentedPreparingExtension::$received[0]->rows->modelKeys());
    }

    private static function reset(): void
    {
        FirstGates::reset();
        SecondGates::reset();
        RowCheckLog::reset();
        Applicability::flush();
    }
}
