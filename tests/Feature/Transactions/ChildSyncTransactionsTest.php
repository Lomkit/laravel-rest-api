<?php

namespace Lomkit\Rest\Tests\Feature\Transactions;

use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Transactions\Stubs\Interruptions;
use Lomkit\Rest\Tests\Feature\Transactions\Stubs\SyncingDocument;
use Lomkit\Rest\Tests\Feature\Transactions\Stubs\SyncingDocumentController;
use Lomkit\Rest\Tests\Feature\Transactions\Stubs\SyncingDocumentResource;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.debug', false)]
class ChildSyncTransactionsTest extends PackageTestCase
{
    /** @var list<string> */
    private array $transactions = [];

    #[\Override]
    protected function defineEnvironment($app): void
    {
        $default = $app['config']->get('database.default');
        $secondary = [...$app['config']->get("database.connections.{$default}"), 'prefix' => 'secondary_'];

        $app['config']->set('database.connections.secondary', $secondary);
        $app['config']->set('database.connections.competitor', $secondary);
    }

    #[\Override]
    protected function defineRoutes($router): void
    {
        Rest::resource('syncing-documents', SyncingDocumentController::class);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection('secondary')->create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->string('title');
            $table->timestamps();
        });

        $this->beforeApplicationDestroyed(fn () => Schema::connection('secondary')->dropIfExists('documents'));
        Interruptions::reset([]);
        SyncingDocumentResource::$whileWriting = null;
        SyncingDocument::query()->create(['title' => 'Annual report']);

        foreach (['Roadmap', 'Minutes', 'Agenda'] as $title) {
            SyncingDocument::query()->create(['title' => $title, 'parent_id' => 1]);
        }

        Event::listen([TransactionBeginning::class, TransactionCommitted::class, TransactionRolledBack::class], function (object $event): void {
            $verb = match ($event::class) {
                TransactionBeginning::class => 'begin',
                TransactionCommitted::class => 'commit',
                default => 'rollback',
            };

            $this->transactions[] = "{$verb} {$event->connectionName}";
        });
    }

    /** @param array<int, int|null> $parents */
    #[Test]
    #[DataProvider('removals')]
    public function it_reads_the_children_again_when_it_runs_a_deadlocked_transaction_again(string $relation, array $parents): void
    {
        config(['rest.transactions.attempts' => 2]);
        Interruptions::reset([Interruptions::deadlock()]);

        $response = $this->postJson('/syncing-documents/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'relations' => [$relation => [['operation' => 'sync', 'key' => [2]]]]],
        ]]);

        $response->assertOk();
        $this->assertSame(['mutatedAll', 'mutatedAll'], Interruptions::$runs);
        $this->assertSame(['begin secondary', 'rollback secondary', 'begin secondary', 'commit secondary'], $this->transactions);
        $this->assertSame($parents, self::parents());
    }

    /** @return array<string, array{string, array<int, int|null>}> */
    public static function removals(): array
    {
        return [
            'delete' => ['children', [1 => null, 2 => 1]],
            'detach' => ['detachedChildren', [1 => null, 2 => 1, 3 => null, 4 => null]],
        ];
    }

    #[Test]
    public function it_locks_the_parent_and_the_children_it_removes_until_the_commit(): void
    {
        if (DB::connection('secondary')->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite has no row locks: it locks the whole database for a write.');
        }

        $locked = [];
        SyncingDocumentResource::$whileWriting = function () use (&$locked): void {
            foreach ([1, 3] as $id) {
                try {
                    DB::connection('competitor')
                        ->table('documents')
                        ->where('id', $id)
                        ->lock('for update nowait')
                        ->first();
                    $locked[$id] = false;
                } catch (QueryException) {
                    $locked[$id] = true;
                }
            }
        };

        $response = $this->postJson('/syncing-documents/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'relations' => ['children' => [['operation' => 'sync', 'key' => [2]]]]],
        ]]);

        $response->assertOk();
        $this->assertSame([1 => true, 3 => true], $locked);
        $this->assertSame([1 => null, 2 => 1], self::parents());
    }

    /** @return array<int, int|null> */
    private static function parents(): array
    {
        return SyncingDocument::query()
            ->orderBy('id')
            ->pluck('parent_id', 'id')
            ->all();
    }
}
