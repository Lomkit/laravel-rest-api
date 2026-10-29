<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;

class DecisionStoreTest extends HelpdeskTestCase
{
    /** @var list<mixed> */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();

        Event::listen(KeyWritten::class, function (KeyWritten $event): void {
            $this->written[] = $event->value;
        });
    }

    #[Test]
    #[DefineEnvironment('useFileStore')]
    public function it_serves_a_second_request_from_the_file_store(): void
    {
        $this->assertSecondRequestServedFromTheStore(FileStore::class);
    }

    #[Test]
    #[DefineEnvironment('useDatabaseStore')]
    public function it_serves_a_second_request_from_the_database_store(): void
    {
        $this->assertSecondRequestServedFromTheStore(DatabaseStore::class);
        $this->assertDatabaseCount('cache', 1);
    }

    #[Test]
    #[DefineEnvironment('useFileStore')]
    public function it_keeps_the_message_and_status_of_a_denial_in_the_file_store(): void
    {
        $this->assertDenialServedFromTheStore(FileStore::class);
    }

    #[Test]
    #[DefineEnvironment('useDatabaseStore')]
    public function it_keeps_the_message_and_status_of_a_denial_in_the_database_store(): void
    {
        $this->assertDenialServedFromTheStore(DatabaseStore::class);
        $this->assertDatabaseCount('cache', 1);
    }

    #[Test]
    #[DefineEnvironment('useFileStore')]
    public function it_stores_each_ability_row_and_resource_of_a_user_apart(): void
    {
        ['Printer jam' => $jam, 'Password reset' => $reset, 'Printer toner' => $toner, 'Laptop setup' => $laptop] = $this->seedTickets();
        $gates = ['Laptop setup' => [true, false], 'Printer toner' => [true, true], 'Password reset' => [true, false], 'Printer jam' => [true, true]];
        $evaluations = ['viewAny'];

        foreach ([$laptop, $toner, $reset, $jam] as $ticket) {
            array_push($evaluations, ...$this->calls('view', $ticket), ...$this->calls('update', $ticket));
        }

        foreach (['/api/tickets/search', '/api/archived-tickets/search', '/api/tickets/search', '/api/archived-tickets/search'] as $uri) {
            $this->assertSame($gates, $this->gatesOf($uri));
        }

        $this->assertSame([...$evaluations, ...$evaluations], $this->tickets->calls);
    }

    #[Test]
    #[DefineEnvironment('useFileStore')]
    public function it_keeps_a_decision_for_the_duration_its_resource_sets(): void
    {
        $this->freezeSecond();
        $evaluations = [];

        foreach ([0, 299, 1, 3300] as $seconds) {
            $this->travel($seconds)->seconds();
            $evaluations[] = [$this->evaluationsOf('/api/tickets/search'), $this->evaluationsOf('/api/archived-tickets/search')];
        }

        $this->assertSame([[1, 1], [0, 0], [1, 0], [1, 1]], $evaluations);
    }

    protected function useFileStore(Application $app): void
    {
        $directory = sys_get_temp_dir().'/lomkit-rest-decisions';
        (new Filesystem)->deleteDirectory($directory);

        $app['config']->set('cache.default', 'file');
        $app['config']->set('cache.stores.file.path', $directory);
        $this->enableSerializingCache($app);
    }

    protected function useDatabaseStore(Application $app): void
    {
        $app['config']->set('cache.default', 'database');
        $app['config']->set('cache.stores.database.connection', null);
        $this->enableSerializingCache($app);
    }

    private function enableSerializingCache(Application $app): void
    {
        $app['config']->set('cache.serializable_classes', false);
        $app['config']->set('rest.authorizations.cache.enabled', true);
    }

    /** @param class-string $store */
    private function assertSecondRequestServedFromTheStore(string $store): void
    {
        $this->assertInstanceOf($store, $this->app->make(Repository::class)->getStore());
        $this->seedTickets();

        $this->postJson('/api/tickets/search')->assertOk()->assertJsonPath('total', 4);
        $this->postJson('/api/tickets/search')->assertOk()->assertJsonPath('total', 4);

        $this->assertSame(['viewAny'], $this->tickets->calls);
        $this->assertSame([['allowed' => true, 'message' => null, 'code' => null, 'status' => null]], $this->written);
    }

    /** @param class-string $store */
    private function assertDenialServedFromTheStore(string $store): void
    {
        $this->assertInstanceOf($store, $this->app->make(Repository::class)->getStore());
        $this->tickets->open = false;
        $this->postJson('/api/tickets/search')->assertStatus(423)->assertJsonPath('message', 'The helpdesk is closed.');

        $this->tickets->open = true;
        $this->postJson('/api/tickets/search')->assertStatus(423)->assertJsonPath('message', 'The helpdesk is closed.');

        $this->assertSame(['viewAny'], $this->tickets->calls);
        $this->assertSame([['allowed' => false, 'message' => 'The helpdesk is closed.', 'code' => null, 'status' => 423]], $this->written);
    }

    /** @return array<string, list<bool>> */
    private function gatesOf(string $uri): array
    {
        $rows = $this->postJson($uri, ['search' => ['gates' => ['view', 'update']]])->assertOk()->json('data');

        return collect($rows)
            ->mapWithKeys(fn (array $row): array => [$row['title'] => [$row['gates']['authorized_to_view'], $row['gates']['authorized_to_update']]])
            ->all();
    }

    private function evaluationsOf(string $uri): int
    {
        $before = count($this->tickets->calls);

        $this->postJson($uri)->assertOk();

        return count($this->tickets->calls) - $before;
    }
}
