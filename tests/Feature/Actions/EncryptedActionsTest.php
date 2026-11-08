<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ArchiveUser;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\QueuedRuns;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\StaffArchivePolicy;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\StaffDocumentController;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\Attributes\WithMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithMigration('queue')]
#[WithConfig('app.key', 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=')]
#[WithConfig('auth.providers.users.model', ArchiveUser::class)]
#[WithConfig('queue.default', 'database')]
class EncryptedActionsTest extends PackageTestCase
{
    private const array FIELDS = ['fields' => [['name' => 'due_at', 'value' => '2026-12-01 10:00:00'], ['name' => 'note', 'value' => 'Quarterly figures']]];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('queue.batching.database', $app['config']->get('database.default'));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        QueuedRuns::reset();
        Rest::resource('staff-documents', StaffDocumentController::class)->register();
        Gate::policy(Document::class, StaffArchivePolicy::class);
        Document::create(['id' => 1, 'title' => 'Budget']);
        $this->actingAs(ArchiveUser::create(['id' => 2, 'name' => 'Grace', 'email' => 'grace@staff.example', 'password' => 'secret']));
    }

    #[Test]
    #[DataProvider('encryptedActions')]
    public function it_encrypts_the_queued_payload_of_an_action_that_should_be_encrypted(string $action, ?string $batch): void
    {
        $this->postJson("/staff-documents/actions/{$action}", ['search' => [], ...self::FIELDS])->assertOk();

        $this->assertStringNotContainsString('Quarterly figures', $this->payload());

        $this->work();

        $this->assertSame([['note' => 'Quarterly figures', 'keys' => [1], 'batch' => $batch]], $this->runs());
        $this->assertDatabaseCount('jobs', 0);
    }

    /** @return array<string, array{string, ?string}> */
    public static function encryptedActions(): array
    {
        return [
            'a queued action' => ['encrypted-export-documents', null],
            'a batched action' => ['batched-encrypted-export-documents', 'batched-encrypted-export-documents'],
        ];
    }

    #[Test]
    #[DataProvider('plainActions')]
    public function it_keeps_the_queued_payload_of_another_action_readable(string $action, ?string $batch): void
    {
        $this->postJson("/staff-documents/actions/{$action}", ['search' => [], ...self::FIELDS])->assertOk();

        $this->assertStringContainsString('s:4:\"note\";s:17:\"Quarterly figures\";', $this->payload());

        $this->work();

        $this->assertSame([['note' => 'Quarterly figures', 'keys' => [1], 'batch' => $batch]], $this->runs());
    }

    /** @return array<string, array{string, ?string}> */
    public static function plainActions(): array
    {
        return [
            'a queued action' => ['export-documents', null],
            'a batched action' => ['batched-export-documents', 'batched-export-documents'],
        ];
    }

    private function payload(): string
    {
        $this->assertDatabaseCount('jobs', 1);

        return (string) DB::table('jobs')->value('payload');
    }

    private function work(): void
    {
        $this->app['auth']->forgetGuards();

        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
    }

    /** @return list<array{note: mixed, keys: mixed, batch: mixed}> */
    private function runs(): array
    {
        return array_map(fn (array $run): array => ['note' => $run['fields']['note'], 'keys' => $run['keys'], 'batch' => $run['batch']], QueuedRuns::$runs);
    }
}
