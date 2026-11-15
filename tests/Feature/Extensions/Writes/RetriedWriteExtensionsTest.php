<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Writes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Mutations\WrittenRow;
use Lomkit\Rest\Tests\Feature\Deletion\Stubs\SecondaryDocument;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RecordingGuard;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RecordingWriteObserver;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WriteLog;
use Lomkit\Rest\Tests\Feature\Transactions\Stubs\Interruptions;
use Lomkit\Rest\Tests\Feature\Transactions\Stubs\RetriedDocumentController;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.debug', false)]
final class RetriedWriteExtensionsTest extends PackageTestCase
{
    private const array MUTATE = ['mutate' => [
        ['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Final report']],
        ['operation' => 'create', 'attributes' => ['title' => 'Agenda']],
    ]];

    private const array OPERATIONS = ['operations' => [['operation' => 'update', 'key' => 1], ['operation' => 'create', 'key' => 4]]];

    private const array DESTROYED = ['data' => [['id' => 1, 'title' => 'Annual report'], ['id' => 3, 'title' => 'Minutes']], 'meta' => []];

    private const array IMPACTED = ['data' => ['impacted' => 3, 'result' => null]];

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
        $this->beforeApplicationDestroyed(function (): void {
            Schema::connection('secondary')->dropIfExists('documents');
        });
        ExtensionLog::reset();
        EventLog::reset();
        WriteLog::reset();
        Interruptions::reset([]);
        config(['rest.extensions' => ['quota' => RecordingGuard::class, 'history' => RecordingWriteObserver::class]]);

        foreach (['Annual report', 'Roadmap', 'Minutes'] as $title) {
            SecondaryDocument::query()->create(['title' => $title]);
        }

        Event::listen([TransactionBeginning::class, TransactionCommitted::class, TransactionRolledBack::class], function (object $event): void {
            $verb = match ($event::class) {
                TransactionBeginning::class => 'begin',
                TransactionCommitted::class => 'commit',
                default => 'rollback',
            };

            EventLog::$lines[] = "{$verb} {$event->connectionName}";
        });
    }

    #[\Override]
    protected function tearDown(): void
    {
        ExtensionLog::reset();
        EventLog::reset();
        WriteLog::reset();

        parent::tearDown();
    }

    /** @return array<string, array{string, string, array<string, mixed>, int, list<string>, array<string, mixed>}> */
    public static function retriedWrites(): array
    {
        $search = ['search' => []];

        return [
            'mutate' => ['POST', '/retried-documents/mutate', self::MUTATE, 0, [
                'begin secondary', 'guard mutate update,create', 'rollback secondary',
                'begin secondary', 'guard mutate update,create', 'commit secondary',
                'observe mutate update:1,create:4',
            ], self::OPERATIONS],
            'destroy' => ['DELETE', '/retried-documents', ['resources' => [1, 3]], 0, [
                'begin secondary', 'guard destroy 1,3', 'rollback secondary',
                'begin secondary', 'guard destroy 1,3', 'commit secondary',
                'observe destroy 1,3',
            ], self::DESTROYED],
            'an action chunk' => ['POST', '/retried-documents/actions/rename-retried-documents', $search, 1, [
                'begin secondary', 'guard operate 1,2', 'commit secondary', 'observe operate 1,2 (2)',
                'begin secondary', 'guard operate 3', 'rollback secondary',
                'begin secondary', 'guard operate 3', 'commit secondary', 'observe operate 3 (1)',
            ], self::IMPACTED],
            'a queued action chunk' => ['POST', '/retried-documents/actions/queued-rename-retried-documents', $search, 1, [
                'begin secondary', 'guard operate 1,2', 'commit secondary', 'observe operate 1,2 (2)',
                'begin secondary', 'guard operate 3', 'rollback secondary',
                'begin secondary', 'guard operate 3', 'commit secondary', 'observe operate 3 (1)',
            ], self::IMPACTED],
            'a whole action' => ['POST', '/retried-documents/actions/atomic-rename-retried-documents', $search, 1, [
                'begin secondary', 'guard operate *', 'rollback secondary',
                'begin secondary', 'guard operate *', 'commit secondary', 'observe operate * (3)',
            ], self::IMPACTED],
            'an action row' => ['POST', '/retried-documents/actions/rename-retried-documents-per-row', $search, 1, [
                'begin secondary', 'guard operate 1', 'commit secondary', 'observe operate 1 (1)',
                'begin secondary', 'guard operate 2', 'rollback secondary',
                'begin secondary', 'guard operate 2', 'commit secondary', 'observe operate 2 (1)',
                'begin secondary', 'guard operate 3', 'commit secondary', 'observe operate 3 (1)',
            ], self::IMPACTED],
            'a queued action row' => ['POST', '/retried-documents/actions/queued-rename-retried-documents-per-row', $search, 1, [
                'begin secondary', 'guard operate 1', 'commit secondary', 'observe operate 1 (1)',
                'begin secondary', 'guard operate 2', 'rollback secondary',
                'begin secondary', 'guard operate 2', 'commit secondary', 'observe operate 2 (1)',
                'begin secondary', 'guard operate 3', 'commit secondary', 'observe operate 3 (1)',
            ], self::IMPACTED],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $lines
     * @param  array<string, mixed>  $answer
     */
    #[Test]
    #[DataProvider('retriedWrites')]
    public function it_guards_each_attempt_and_notifies_once_after_the_last_commit(string $method, string $uri, array $payload, int $deadlockAt, array $lines, array $answer): void
    {
        config(['rest.transactions.attempts' => 2]);
        Interruptions::reset([...array_fill(0, $deadlockAt, null), Interruptions::deadlock()]);

        $this->json($method, $uri, $payload)->assertOk()->assertExactJson($answer);

        $this->assertSame($lines, EventLog::$lines);
    }

    /** @return array<string, array{string, string, array<string, mixed>, array<string, mixed>}> */
    public static function rebuiltWrites(): array
    {
        return [
            'mutate' => ['POST', '/retried-documents/mutate', self::MUTATE, self::OPERATIONS],
            'destroy' => ['DELETE', '/retried-documents', ['resources' => [1, 3]], self::DESTROYED],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $answer
     */
    #[Test]
    #[DataProvider('rebuiltWrites')]
    public function it_rebuilds_the_context_of_each_attempt(string $method, string $uri, array $payload, array $answer): void
    {
        config(['rest.transactions.attempts' => 2]);
        Interruptions::reset([Interruptions::deadlock()]);

        $this->json($method, $uri, $payload)->assertOk()->assertExactJson($answer);

        [$first, $second] = WriteLog::$guards;
        $this->assertCount(2, WriteLog::$guards);
        $this->assertNotSame($first, $second);
        $this->assertNotSame($first->rows[0] ?? null, $second->rows[0] ?? null);
        $this->assertSame(self::keys($first->rows), self::keys($second->rows));
        $this->assertNotSame([], self::keys($first->rows));
    }

    #[Test]
    public function it_notifies_the_rows_written_by_the_last_attempt_only(): void
    {
        config(['rest.transactions.attempts' => 2]);
        Interruptions::reset([Interruptions::deadlock()]);

        $this->postJson('/retried-documents/mutate', self::MUTATE)->assertOk()->assertExactJson(self::OPERATIONS);

        $this->assertCount(1, WriteLog::$writes);
        $this->assertSame([[1, 'Final report'], [4, 'Agenda']], array_map(
            fn (WrittenRow $row): array => [$row->model->getKey(), $row->model->getAttribute('title')],
            WriteLog::$writes[0]->written ?? [],
        ));
    }

    /** @return array<string, array{string, string, array<string, mixed>, int, list<string>, list<string>}> */
    public static function failedWrites(): array
    {
        $search = ['search' => []];

        return [
            'mutate' => ['POST', '/retried-documents/mutate', self::MUTATE, 0, [
                'begin secondary', 'guard mutate update,create', 'rollback secondary',
            ], ['Annual report', 'Roadmap', 'Minutes']],
            'destroy' => ['DELETE', '/retried-documents', ['resources' => [1, 3]], 0, [
                'begin secondary', 'guard destroy 1,3', 'rollback secondary',
            ], ['Annual report', 'Roadmap', 'Minutes']],
            'an action chunk' => ['POST', '/retried-documents/actions/rename-retried-documents', $search, 1, [
                'begin secondary', 'guard operate 1,2', 'commit secondary', 'observe operate 1,2 (2)',
                'begin secondary', 'guard operate 3', 'rollback secondary',
            ], ['Renamed', 'Renamed', 'Minutes']],
            'a whole action' => ['POST', '/retried-documents/actions/atomic-rename-retried-documents', $search, 1, [
                'begin secondary', 'guard operate *', 'rollback secondary',
            ], ['Annual report', 'Roadmap', 'Minutes']],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $lines
     * @param  list<string>  $titles
     */
    #[Test]
    #[DataProvider('failedWrites')]
    public function it_notifies_nothing_when_the_last_attempt_is_deadlocked(string $method, string $uri, array $payload, int $deadlockAt, array $lines, array $titles): void
    {
        Interruptions::reset([...array_fill(0, $deadlockAt, null), Interruptions::deadlock()]);

        $this->json($method, $uri, $payload)->assertInternalServerError()->assertExactJson(['message' => 'Server Error']);

        $this->assertSame($lines, EventLog::$lines);
        $this->assertSame($titles, SecondaryDocument::query()
            ->orderBy('id')
            ->pluck('title')
            ->all());
    }

    /**
     * @param  list<Model>|null  $rows
     * @return list<mixed>
     */
    private static function keys(?array $rows): array
    {
        return array_map(fn (Model $row): mixed => $row->getKey(), $rows ?? []);
    }
}
