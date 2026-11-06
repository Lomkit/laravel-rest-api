<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\WritingManyKeys;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\MirrorTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CachedTicketResource;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('cache.serializable_classes', false)]
class DecisionStoreTest extends HelpdeskTestCase
{
    /** @var list<array{string|null, mixed, int|null}> */
    private array $written = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('mirror-tickets', MirrorTicketController::class)->register();
        Event::listen(KeyWritten::class, function (KeyWritten $event): void {
            $this->written[] = [$event->storeName, $event->value, $event->seconds];
        });
    }

    #[Test]
    #[DefineEnvironment('useFileStore')]
    public function it_serves_a_second_request_from_the_file_store(): void
    {
        $this->assertSecondRequestServedFromTheStore('file');
    }

    #[Test]
    #[WithConfig('rest.authorizations.cache.store', 'database')]
    #[WithConfig('cache.stores.database.connection', null)]
    public function it_serves_a_second_request_from_the_database_store(): void
    {
        $this->assertSecondRequestServedFromTheStore('database');
        $this->assertDatabaseCount('cache', 1);
    }

    #[Test]
    #[DefineEnvironment('useFileStore')]
    public function it_keeps_the_message_and_status_of_a_denial_in_the_file_store(): void
    {
        $this->assertDenialServedFromTheStore('file');
    }

    #[Test]
    #[WithConfig('rest.authorizations.cache.store', 'database')]
    #[WithConfig('cache.stores.database.connection', null)]
    public function it_keeps_the_message_and_status_of_a_denial_in_the_database_store(): void
    {
        $this->assertDenialServedFromTheStore('database');
        $this->assertDatabaseCount('cache', 1);
    }

    #[Test]
    #[DefineEnvironment('useFileStore')]
    public function it_stores_each_ability_row_and_resource_of_a_user_apart(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();
        $this->travel(1)->seconds();
        $gates = ['Laptop setup' => [true, false], 'Printer toner' => [true, true], 'Password reset' => [true, false], 'Printer jam' => [true, true]];
        $evaluations = ['viewAny'];

        foreach ([$laptop, $toner, $reset, $jam] as $ticket) {
            array_push($evaluations, ...$this->calls('view', $ticket), ...$this->calls('update', $ticket));
        }

        foreach (['/api/cached-tickets/search', '/mirror-tickets/search', '/api/cached-tickets/search', '/mirror-tickets/search'] as $uri) {
            $this->assertSame($gates, $this->gatesOf($uri));
        }

        $this->assertSame([...$evaluations, ...$evaluations], $this->tickets->calls);
    }

    #[Test]
    #[DefineEnvironment('useFileStore')]
    public function it_keeps_each_decision_for_the_seconds_of_its_ability(): void
    {
        $this->seedTickets();
        $this->freezeSecond();
        $evaluations = [];

        foreach ([1, 59, 2, 240] as $seconds) {
            $this->travel($seconds)->seconds();
            $evaluations[] = $this->evaluationsOf('/api/cached-tickets/search', ['search' => ['gates' => ['view']]]);
        }

        $this->assertSame([5, 0, 4, 5], $evaluations);
        $this->assertSame([300 => 2, 60 => 12], array_count_values(array_column($this->written, 2)));
    }

    #[Test]
    public function it_writes_the_decisions_of_one_batch_once_per_duration(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset] = $this->seedTickets();
        $this->travel(1)->seconds();
        $resource = $this->contextFor(null)->resource(CachedTicketResource::class);
        $writes = [];
        Event::listen(WritingManyKeys::class, function (WritingManyKeys $event) use (&$writes): void {
            $writes[] = [$event->seconds, count($event->keys)];
        });

        $resource
            ->context()
            ->authorizer()
            ->inspectAll([
                Check::onClass($resource, 'viewAny'),
                new Check($resource, 'view', $jam),
                new Check($resource, 'view', $reset),
                new Check($resource, 'escalate', $jam),
            ]);

        $this->assertSame([[300, 1], [60, 2]], $writes);
        $this->assertSame(['viewAny', 'view '.$jam->id, 'view '.$reset->id, 'escalate '.$jam->id], $this->tickets->calls);
    }

    protected function useFileStore(Application $app): void
    {
        $directory = sys_get_temp_dir().'/lomkit-rest-decisions';
        (new Filesystem)->deleteDirectory($directory);

        $app['config']->set('rest.authorizations.cache.store', 'file');
        $app['config']->set('cache.stores.file.path', $directory);
    }

    private function assertSecondRequestServedFromTheStore(string $store): void
    {
        $this->seedTickets();

        $this->postJson('/api/cached-tickets/search')
            ->assertOk()
            ->assertJsonPath('total', 4);
        $this->postJson('/api/cached-tickets/search')
            ->assertOk()
            ->assertJsonPath('total', 4);

        $this->assertSame(['viewAny'], $this->tickets->calls);
        $this->assertSame([[$store, ['allowed' => true, 'message' => null, 'code' => null, 'status' => null], 300]], $this->written);
    }

    private function assertDenialServedFromTheStore(string $store): void
    {
        $this->tickets->open = false;
        $this->postJson('/api/cached-tickets/search')
            ->assertStatus(423)
            ->assertJsonPath('message', 'The helpdesk is closed.');

        $this->tickets->open = true;
        $this->postJson('/api/cached-tickets/search')
            ->assertStatus(423)
            ->assertJsonPath('message', 'The helpdesk is closed.');

        $this->assertSame(['viewAny'], $this->tickets->calls);
        $this->assertSame([[$store, ['allowed' => false, 'message' => 'The helpdesk is closed.', 'code' => null, 'status' => 423], 300]], $this->written);
    }

    /** @return array<string, list<bool>> */
    private function gatesOf(string $uri): array
    {
        $rows = $this->postJson($uri, ['search' => ['gates' => ['view', 'update']]])->assertOk()->json('data');

        return collect($rows)
            ->mapWithKeys(fn (array $row): array => [$row['title'] => [$row['gates']['authorized_to_view']['allowed'], $row['gates']['authorized_to_update']['allowed']]])
            ->all();
    }

    /** @param array<string, mixed> $payload */
    private function evaluationsOf(string $uri, array $payload): int
    {
        $before = count($this->tickets->calls);

        $this->postJson($uri, $payload)->assertOk();

        return count($this->tickets->calls) - $before;
    }
}
