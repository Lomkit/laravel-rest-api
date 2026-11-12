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
    public function it_checks_and_writes_the_sync_again_when_it_runs_a_deadlocked_transaction_again(string $relation, string $removal, array $parents): void
    {
        config(['rest.transactions.attempts' => 2]);
        Interruptions::reset([Interruptions::deadlock()]);

        $response = $this->postJson('/syncing-documents/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'relations' => [$relation => [['operation' => 'sync', 'key' => [2]], ['operation' => $removal, 'key' => [3, 4]]]]],
        ]]);

        $response->assertOk();
        $this->assertSame(['mutatedAll', 'mutatedAll'], Interruptions::$runs);
        $this->assertSame(['begin secondary', 'rollback secondary', 'begin secondary', 'commit secondary'], $this->transactions);
        $this->assertSame($parents, self::parents());
    }

    /** @return array<string, array{string, string, array<int, int|null>}> */
    public static function removals(): array
    {
        return [
            'delete' => ['children', 'delete', [1 => null, 2 => 1]],
            'detach' => ['detachedChildren', 'detach', [1 => null, 2 => 1, 3 => null, 4 => null]],
        ];
    }

    #[Test]
    public function it_refuses_the_sync_when_a_child_appears_before_the_transaction_runs_again(): void
    {
        config(['rest.transactions.attempts' => 2]);
        Interruptions::reset([Interruptions::deadlock()]);
        Event::listen(TransactionRolledBack::class, function (): void {
            if (SyncingDocument::query()->where('title', 'Addendum')->doesntExist()) {
                SyncingDocument::query()->create(['title' => 'Addendum', 'parent_id' => 1]);
            }
        });

        $response = $this->postJson('/syncing-documents/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Final report'], 'relations' => ['children' => [['operation' => 'sync', 'key' => [2, 3, 4]]]]],
        ]]);

        $response->assertConflict()->assertExactJson([
            'message' => 'The relation has a related row that the sync neither lists nor removes. Read the relation again, without include limits or filters, then send the request again.',
            'conflicts' => [['reason' => 'unlisted', 'path' => 'mutate.0.relations.children.0', 'key' => 5]],
        ]);
        $this->assertSame(['mutatedAll'], Interruptions::$runs);
        $this->assertSame(['begin secondary', 'rollback secondary', 'begin secondary', 'rollback secondary'], $this->transactions);
        $this->assertSame('Annual report', SyncingDocument::query()->findOrFail(1)->getAttribute('title'));
    }

    #[Test]
    public function it_checks_the_sync_of_each_parent_of_a_key_list(): void
    {
        SyncingDocument::query()->create(['title' => 'Appendix', 'parent_id' => 2]);
        $sync = fn (array $keys): array => ['mutate' => [['operation' => 'update', 'key' => 1, 'relations' => ['children' => [
            ['operation' => 'sync', 'key' => [2, 3, 4]],
            ['operation' => 'update', 'key' => $keys, 'relations' => ['children' => [
                ['operation' => 'sync', 'key' => []],
                ['operation' => 'create', 'attributes' => ['title' => 'Kickoff']],
            ]]],
        ]]]]];

        $response = $this->postJson('/syncing-documents/mutate', $sync([3, 2]));

        $response->assertConflict()->assertJsonPath('conflicts', [['reason' => 'unlisted', 'path' => 'mutate.0.relations.children.1.relations.children.0', 'key' => 5]]);
        $this->assertSame(0, SyncingDocument::query()->where('title', 'Kickoff')->count());

        $this->postJson('/syncing-documents/mutate', $sync([3, 4]))->assertOk();
        $this->assertSame([3, 4], SyncingDocument::query()
            ->where('title', 'Kickoff')
            ->orderBy('parent_id')
            ->pluck('parent_id')
            ->all());
    }

    #[Test]
    public function it_locks_the_parent_of_a_sync_until_the_commit(): void
    {
        $driver = DB::connection('secondary')->getDriverName();

        if ($driver === 'sqlite') {
            $this->markTestSkipped('SQLite has no row locks: it locks the whole database for a write.');
        }

        $refusal = null;
        SyncingDocumentResource::$whileWriting = function () use (&$refusal): void {
            try {
                DB::connection('competitor')
                    ->table('documents')
                    ->where('id', 1)
                    ->lock('for update nowait')
                    ->first();
            } catch (QueryException $exception) {
                $refusal = self::code($exception);
            }
        };

        $response = $this->postJson('/syncing-documents/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'relations' => ['children' => [['operation' => 'sync', 'key' => [2]], ['operation' => 'delete', 'key' => [3, 4]]]]],
        ]]);

        $response->assertOk();
        $this->assertSame($driver === 'mysql' ? '3572' : '55P03', $refusal);
        $this->assertSame([1 => null, 2 => 1], self::parents());
    }

    #[Test]
    public function it_checks_the_children_when_it_runs_and_lets_another_writer_add_one_after_on_postgresql(): void
    {
        $driver = DB::connection('secondary')->getDriverName();

        if ($driver === 'sqlite') {
            $this->markTestSkipped('SQLite has no row locks: it locks the whole database for a write.');
        }

        $refusal = null;
        SyncingDocumentResource::$whileWriting = function () use ($driver, &$refusal): void {
            if ($driver === 'mysql') {
                DB::connection('competitor')->statement('set session innodb_lock_wait_timeout = 1');
            }

            try {
                DB::connection('competitor')
                    ->table('documents')
                    ->insert(['title' => 'Late addendum', 'parent_id' => 1]);
            } catch (QueryException $exception) {
                $refusal = self::code($exception);
            }
        };

        $response = $this->postJson('/syncing-documents/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'relations' => ['children' => [['operation' => 'sync', 'key' => [2, 3, 4]]]]],
        ]]);

        $response->assertOk();
        $this->assertSame($driver === 'mysql' ? '1205' : null, $refusal);
        $this->assertSame($driver === 'mysql' ? [2, 3, 4] : [2, 3, 4, 5], SyncingDocument::query()->where('parent_id', 1)->orderBy('id')->pluck('id')->all());
    }

    private static function code(QueryException $exception): string
    {
        [$state, $code] = [...($exception->errorInfo ?? []), null, null];

        return (string) ($state === 'HY000' ? $code : $state);
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
