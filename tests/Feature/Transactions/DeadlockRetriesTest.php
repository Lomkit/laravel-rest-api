<?php

namespace Lomkit\Rest\Tests\Feature\Transactions;

use Illuminate\Database\DeadlockException;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Events\ActionOperated;
use Lomkit\Rest\Events\ResourceDestroyed;
use Lomkit\Rest\Events\ResourceMutated;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\SecondaryDocument;
use Lomkit\Rest\Tests\Feature\Transactions\Stubs\Interruptions;
use Lomkit\Rest\Tests\Feature\Transactions\Stubs\NestedDocumentController;
use Lomkit\Rest\Tests\Feature\Transactions\Stubs\RetriedDocumentController;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

#[WithConfig('app.debug', false)]
class DeadlockRetriesTest extends PackageTestCase
{
    private const array MUTATE = ['mutate' => [
        ['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Final report']],
        ['operation' => 'create', 'attributes' => ['title' => 'Agenda']],
    ]];

    /** @var list<string> */
    private array $transactions = [];

    #[\Override]
    protected function defineEnvironment($app): void
    {
        $default = $app['config']->get('database.default');

        $app['config']->set('database.connections.secondary', [
            ...$app['config']->get("database.connections.{$default}"),
            'prefix' => 'secondary_',
        ]);
    }

    #[\Override]
    protected function defineRoutes($router): void
    {
        Rest::resource('retried-documents', RetriedDocumentController::class);
        Rest::resource('nested-documents', NestedDocumentController::class);
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
        foreach (['Annual report', 'Roadmap', 'Minutes'] as $title) {
            SecondaryDocument::query()->create(['title' => $title]);
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

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $runs
     * @param  list<string>  $transactions
     * @param  list<string>  $titles
     */
    #[Test]
    #[DataProvider('retriedWrites')]
    public function it_runs_a_deadlocked_transaction_again_from_the_rows_as_they_were_read(string $method, string $uri, array $payload, int $deadlockAt, array $runs, array $transactions, array $titles): void
    {
        config(['rest.transactions.attempts' => 2]);
        Interruptions::reset([...array_fill(0, $deadlockAt, null), Interruptions::deadlock()]);

        $response = $this->json($method, $uri, $payload);

        $response->assertOk();
        $this->assertSame($runs, Interruptions::$runs);
        $this->assertSame($transactions, $this->transactions);
        $this->assertSame($titles, $this->titles());
    }

    /** @return array<string, array{string, string, array<string, mixed>, int, list<string>, list<string>, list<string>}> */
    public static function retriedWrites(): array
    {
        $retried = ['begin secondary', 'rollback secondary', 'begin secondary', 'commit secondary'];
        $renamed = ['Renamed', 'Renamed', 'Renamed'];

        return [
            'mutate' => ['POST', '/retried-documents/mutate', self::MUTATE, 0, ['mutatedAll', 'mutatedAll'], $retried, ['Final report', 'Roadmap', 'Minutes', 'Agenda']],
            'destroy' => ['DELETE', '/retried-documents', ['resources' => [1, 3]], 0, ['destroyedAll', 'destroyedAll'], $retried, ['Roadmap']],
            'an action chunk' => ['POST', '/retried-documents/actions/rename-retried-documents', [], 1, ['handle 1,2', 'handle 3', 'handle 3'], ['begin secondary', 'commit secondary', ...$retried], $renamed],
            'a queued action chunk' => ['POST', '/retried-documents/actions/queued-rename-retried-documents', [], 1, ['handle 1,2', 'handle 3', 'handle 3'], ['begin secondary', 'commit secondary', ...$retried], $renamed],
            'a whole action' => ['POST', '/retried-documents/actions/atomic-rename-retried-documents', [], 1, ['handle 1,2', 'handle 3', 'handle 1,2', 'handle 3'], $retried, $renamed],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $runs
     * @param  list<string>  $transactions
     * @param  list<string>  $titles
     */
    #[Test]
    #[DataProvider('failedWrites')]
    public function it_answers_the_deadlock_of_the_last_attempt(string $method, string $uri, array $payload, int $deadlockAt, array $runs, array $transactions, array $titles): void
    {
        Interruptions::reset([...array_fill(0, $deadlockAt, null), Interruptions::deadlock()]);

        $response = $this->json($method, $uri, $payload);

        $response->assertInternalServerError();
        $this->assertSame($runs, Interruptions::$runs);
        $this->assertSame($transactions, $this->transactions);
        $this->assertSame($titles, $this->titles());
    }

    /** @return array<string, array{string, string, array<string, mixed>, int, list<string>, list<string>, list<string>}> */
    public static function failedWrites(): array
    {
        $rolledBack = ['begin secondary', 'rollback secondary'];
        $seeded = ['Annual report', 'Roadmap', 'Minutes'];

        return [
            'mutate' => ['POST', '/retried-documents/mutate', self::MUTATE, 0, ['mutatedAll'], $rolledBack, $seeded],
            'destroy' => ['DELETE', '/retried-documents', ['resources' => [1, 3]], 0, ['destroyedAll'], $rolledBack, $seeded],
            'an action chunk' => ['POST', '/retried-documents/actions/rename-retried-documents', [], 1, ['handle 1,2', 'handle 3'], ['begin secondary', 'commit secondary', ...$rolledBack], ['Renamed', 'Renamed', 'Minutes']],
            'a whole action' => ['POST', '/retried-documents/actions/atomic-rename-retried-documents', [], 1, ['handle 1,2', 'handle 3'], $rolledBack, $seeded],
        ];
    }

    /** @return array<string, array{string, string, array<string, mixed>, int, list<string>}> */
    public static function retriedEvents(): array
    {
        $retried = ['begin secondary', 'rollback secondary', 'begin secondary', 'commit secondary'];

        return [
            'mutate' => ['POST', '/retried-documents/mutate', self::MUTATE, 0, [...$retried, 'ResourceMutated']],
            'destroy' => ['DELETE', '/retried-documents', ['resources' => [1, 3]], 0, [...$retried, 'ResourceDestroyed']],
            'an action chunk' => ['POST', '/retried-documents/actions/rename-retried-documents', [], 1, ['begin secondary', 'commit secondary', ...$retried, 'ActionOperated']],
            'a queued action chunk' => ['POST', '/retried-documents/actions/queued-rename-retried-documents', [], 1, ['begin secondary', 'commit secondary', ...$retried, 'ActionOperated']],
            'a whole action' => ['POST', '/retried-documents/actions/atomic-rename-retried-documents', [], 1, [...$retried, 'ActionOperated']],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $log
     */
    #[Test]
    #[DataProvider('retriedEvents')]
    public function it_dispatches_the_event_of_a_retried_write_once_after_the_commit_of_the_last_attempt(string $method, string $uri, array $payload, int $deadlockAt, array $log): void
    {
        config(['rest.transactions.attempts' => 2]);
        Interruptions::reset([...array_fill(0, $deadlockAt, null), Interruptions::deadlock()]);
        $this->listenToWriteEvents();

        $response = $this->json($method, $uri, $payload);

        $response->assertOk();
        $this->assertSame($log, $this->transactions);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $runs
     * @param  list<string>  $transactions
     * @param  list<string>  $titles
     */
    #[Test]
    #[DataProvider('failedWrites')]
    public function it_dispatches_no_event_when_the_last_attempt_is_deadlocked(string $method, string $uri, array $payload, int $deadlockAt, array $runs, array $transactions, array $titles): void
    {
        Interruptions::reset([...array_fill(0, $deadlockAt, null), Interruptions::deadlock()]);
        $this->listenToWriteEvents();

        $response = $this->json($method, $uri, $payload);

        $response->assertInternalServerError();
        $this->assertSame($runs, Interruptions::$runs);
        $this->assertSame($transactions, $this->transactions);
        $this->assertSame($titles, $this->titles());
    }

    #[Test]
    public function it_does_not_run_a_transaction_again_after_another_error(): void
    {
        config(['rest.transactions.attempts' => 3]);
        Interruptions::reset([new RuntimeException('The documents are archived.')]);

        $response = $this->deleteJson('/retried-documents', ['resources' => [1, 3]]);

        $response->assertInternalServerError();
        $this->assertSame(['destroyedAll'], Interruptions::$runs);
        $this->assertSame(['begin secondary', 'rollback secondary'], $this->transactions);
        $this->assertSame(['Annual report', 'Roadmap', 'Minutes'], $this->titles());
    }

    #[Test]
    public function it_throws_the_deadlock_of_a_transaction_inside_a_transaction_of_the_application(): void
    {
        config(['rest.transactions.attempts' => 3]);
        Interruptions::reset([Interruptions::deadlock()]);
        $connection = DB::connection('secondary');
        $connection->beginTransaction();
        $this->withoutExceptionHandling();

        try {
            $this->deleteJson('/retried-documents', ['resources' => [1, 3]]);
            $this->fail('The deadlock was not thrown.');
        } catch (DeadlockException $exception) {
            $this->assertStringContainsString('Deadlock found when trying to get lock', $exception->getMessage());
        } finally {
            $connection->rollBack();
        }

        $this->assertSame(['destroyedAll'], Interruptions::$runs);
        $this->assertSame(['begin secondary', 'begin secondary', 'rollback secondary'], $this->transactions);
        $this->assertSame(['Annual report', 'Roadmap', 'Minutes'], $this->titles());
    }

    #[Test]
    public function it_deletes_the_children_of_a_retried_mutation_again(): void
    {
        config(['rest.transactions.attempts' => 2]);
        SecondaryDocument::query()
            ->whereKey([2, 3])
            ->update(['parent_id' => 1]);
        Interruptions::reset([Interruptions::deadlock()]);

        $response = $this->postJson('/nested-documents/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 1, 'relations' => ['children' => [['operation' => 'delete', 'key' => [2, 3]]]]],
        ]]);

        $response->assertOk();
        $this->assertSame(['mutatedAll', 'mutatedAll'], Interruptions::$runs);
        $this->assertSame(['begin secondary', 'rollback secondary', 'begin secondary', 'commit secondary'], $this->transactions);
        $this->assertSame(['Annual report'], $this->titles());
    }

    private function listenToWriteEvents(): void
    {
        Event::listen([ResourceMutated::class, ResourceDestroyed::class, ActionOperated::class], function (object $event): void {
            $this->transactions[] = class_basename($event);
        });
    }

    /** @return list<string> */
    private function titles(): array
    {
        return SecondaryDocument::query()
            ->orderBy('id')
            ->pluck('title')
            ->all();
    }
}
