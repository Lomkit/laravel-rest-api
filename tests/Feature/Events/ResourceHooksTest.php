<?php

namespace Lomkit\Rest\Tests\Feature\Events;

use ArrayObject;
use Illuminate\Auth\GenericUser;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Feature\Events\Stubs\HookDocumentController;
use Lomkit\Rest\Tests\Feature\Events\Stubs\HookTicketController;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ResourceHooksTest extends HelpdeskTestCase
{
    /** @var array<string, int> */
    private array $ids = [];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk, new Archive];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        EventLog::reset();
        Rest::resource('hook-tickets', HookTicketController::class)->register();
        Rest::resource('hook-documents', HookDocumentController::class)
            ->withSoftDeletes()
            ->register();
        $this->ids = array_map(fn (Ticket $ticket): int => $ticket->getKey(), $this->seedTickets());
        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]));
    }

    #[Test]
    public function it_passes_each_entry_of_the_payload_and_its_row_to_mutating_and_mutated(): void
    {
        $billing = Team::query()
            ->where('name', 'Billing')
            ->value('id');
        $toner = $this->ids['Printer toner'];

        $response = $this->postJson('/hook-tickets/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['team_id' => $billing, 'title' => 'Fan noise', 'status' => 'open']],
            ['operation' => 'update', 'key' => $toner, 'attributes' => ['title' => 'Toner low'], 'relations' => ['team' => ['operation' => 'create', 'attributes' => ['name' => 'Hardware']]]],
        ]]);

        $response->assertOk();
        $fan = $response->json('operations.0.key');
        $hardware = $response->json('operations.1.relations.team.key');
        $this->assertSame([
            "mutating create null {\"team_id\":{$billing},\"title\":\"Fan noise\",\"status\":\"open\"} - new MutateRequest",
            "mutated create null {\"team_id\":{$billing},\"title\":\"Fan noise\",\"status\":\"open\"} - {$fan} MutateRequest",
            'mutating create null {"name":"Hardware"} - new MutateRequest',
            "mutated create null {\"name\":\"Hardware\"} - {$hardware} MutateRequest",
            "mutating update {$toner} {\"title\":\"Toner low\"} team {$toner} MutateRequest",
            "mutated update {$toner} {\"title\":\"Toner low\"} team {$toner} MutateRequest",
        ], EventLog::$lines);

        [$entries, $creating, $created, $nesting, $nested, $updating, $updated] = EventLog::$payloads;
        $this->assertInstanceOf(ArrayObject::class, $entries);
        $this->assertInstanceOf(MutationNode::class, $updating);
        $this->assertSame($entries[0], $creating);
        $this->assertSame($entries[0], $created);
        $this->assertSame($entries[1], $updating);
        $this->assertSame($entries[1], $updated);
        $this->assertSame($updating->relations['team'] ?? null, $nesting);
        $this->assertSame($nesting, $nested);
    }

    /** @return array<string, array{string, string, list<string>}> */
    public static function deletions(): array
    {
        return [
            'destroy' => ['DELETE', '/hook-tickets', ['destroying # DestroyRequest', 'performDelete # DestroyRequest', 'destroyed # DestroyRequest']],
            'restore' => ['POST', '/hook-documents/restore', ['restoring # RestoreRequest', 'performRestore # RestoreRequest', 'restored # RestoreRequest']],
            'force destroy' => ['DELETE', '/hook-documents/force', ['forceDestroying # ForceDestroyRequest', 'performForceDelete # ForceDestroyRequest', 'forceDestroyed # ForceDestroyRequest']],
        ];
    }

    /** @param list<string> $steps */
    #[Test]
    #[DataProvider('deletions')]
    public function it_passes_each_row_to_the_deletion_hooks_and_perform_methods(string $method, string $uri, array $steps): void
    {
        Document::query()->insert([
            ['id' => 1, 'title' => 'Annual report', 'deleted_at' => '2026-01-01 00:00:00'],
            ['id' => 2, 'title' => 'Budget', 'deleted_at' => '2026-01-01 00:00:00'],
        ]);
        $keys = str_starts_with($uri, '/hook-tickets') ? [$this->ids['Laptop setup'], $this->ids['Password reset']] : [2, 1];

        $response = $this->json($method, $uri, ['resources' => $keys]);

        $response->assertOk();
        $this->assertSame([
            ...str_replace('#', (string) $keys[0], $steps),
            ...str_replace('#', (string) $keys[1], $steps),
        ], EventLog::$lines);
    }

    /** @return array<string, array{int, list<string>}> */
    public static function users(): array
    {
        return [
            'the lead reaches every ticket' => [TicketPolicy::LEAD, ['Laptop setup', 'Printer toner', 'Password reset', 'Printer jam']],
            'another user reaches the open tickets' => [7, ['Printer toner', 'Printer jam']],
        ];
    }

    /** @param list<string> $titles */
    #[Test]
    #[DataProvider('users')]
    public function it_reads_the_user_of_the_request_in_perimeter_query(int $user, array $titles): void
    {
        $this->actingAs(new GenericUser(['id' => $user]));

        $response = $this->postJson('/hook-tickets/search');

        $response->assertOk()->assertJsonPath('data.*.id', array_map(fn (string $title): int => $this->ids[$title], $titles));
    }
}
