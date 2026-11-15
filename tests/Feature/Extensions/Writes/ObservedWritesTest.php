<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Writes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Events\ActionOperated;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Mutations\WrittenRow;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ArchiveUser;
use Lomkit\Rest\Tests\Feature\Deletion\Concerns\SeedsDocuments;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ArchiveDocumentsByOne;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ArchiveEachDocument;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\AtomicArchiveDocuments;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ChangingWriteObserver;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\DirectArchiveDocuments;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FailingWriteObserver;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ModeChangingExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RecordingWriteObserver;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WriteLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WrittenDocumentController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WrittenDocumentResource;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\Attributes\WithMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

#[WithMigration('queue')]
#[WithConfig('auth.providers.users.model', ArchiveUser::class)]
#[WithConfig('app.debug', false)]
final class ObservedWritesTest extends PackageTestCase
{
    use SeedsDocuments;

    private const string TRASHED = '2026-01-01 00:00:00';

    private const array MUTATE = ['mutate' => [
        ['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Annual summary']],
        ['operation' => 'create', 'attributes' => ['title' => 'Minutes']],
    ]];

    private const array DOCUMENTS = ['data' => [
        ['id' => 1, 'title' => 'Annual report', 'archived' => false],
        ['id' => 2, 'title' => 'Budget', 'archived' => false],
    ], 'meta' => []];

    private const array IMPACTED = ['data' => ['impacted' => 2, 'result' => null]];

    private ArchiveUser $grace;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ExtensionLog::reset();
        EventLog::reset();
        WriteLog::reset();
        config(['rest.extensions' => ['history' => RecordingWriteObserver::class]]);
        Rest::resource('written-documents', WrittenDocumentController::class)
            ->withSoftDeletes()
            ->register();
        ArchiveUser::create(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
        $this->grace = ArchiveUser::create(['id' => 2, 'name' => 'Grace', 'email' => 'grace@example.com', 'password' => 'secret']);
        $this->actingAs($this->grace);
    }

    #[\Override]
    protected function tearDown(): void
    {
        ExtensionLog::reset();
        EventLog::reset();
        WriteLog::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_notifies_a_mutation_after_its_commit(): void
    {
        $this->seedDocuments(null, null);
        EventLog::listen();

        $response = $this->postJson('/written-documents/mutate', self::MUTATE)->assertOk();

        $minutes = $this->minutes();
        $response->assertExactJson(self::operations($minutes));
        $this->assertSame([
            'begin level 2',
            'mutatingAll update,create',
            'sql update documents',
            'sql insert documents',
            'mutatedAll 2',
            'commit level 1',
            "observe mutate update:1,create:{$minutes}",
        ], $this->writeLines());
        $context = WriteLog::$writes[0];
        $this->assertCount(1, WriteLog::$writes);
        $this->assertInstanceOf(WrittenDocumentResource::class, $context->resource);
        $this->assertSame(Endpoint::Mutate, $context->endpoint);
        $this->assertSame(2, $context->user?->getAuthIdentifier());
        $this->assertSame(self::operations($minutes)['operations'], $context->operations);
        $this->assertSame([
            [WrittenDocumentResource::class, MutationOperation::Update, ['title' => 'Annual summary'], 1, 'Annual summary'],
            [WrittenDocumentResource::class, MutationOperation::Create, ['title' => 'Minutes'], $minutes, 'Minutes'],
        ], array_map(fn (WrittenRow $row): array => [$row->resource::class, $row->entry->operation, $row->entry->attributes, $row->model->getKey(), $row->model->getAttribute('title')], $context->written ?? []));
        $this->assertNull($context->rows);
        $this->assertNull($context->action);
        $this->assertNull($context->fields);
        $this->assertNull($context->impacted);
    }

    /** @return array<string, array{string, string, ?string, Endpoint, list<string>}> */
    public static function rowBatches(): array
    {
        return [
            'destroy' => ['DELETE', '/written-documents', null, Endpoint::Destroy, ['destroyingAll 1,2', 'sql update documents', 'sql update documents', 'destroyedAll 1,2']],
            'restore' => ['POST', '/written-documents/restore', self::TRASHED, Endpoint::Restore, ['restoringAll 1,2', 'sql update documents', 'sql update documents', 'restoredAll 1,2']],
            'force destroy' => ['DELETE', '/written-documents/force', self::TRASHED, Endpoint::ForceDestroy, ['forceDestroyingAll 1,2', 'sql delete documents', 'sql delete documents', 'forceDestroyedAll 1,2']],
        ];
    }

    /** @param list<string> $writes */
    #[Test]
    #[DataProvider('rowBatches')]
    public function it_notifies_each_endpoint_of_a_row_batch(string $method, string $uri, ?string $deletedAt, Endpoint $endpoint, array $writes): void
    {
        $this->seedDocuments($deletedAt, $deletedAt);
        EventLog::listen();

        $this->json($method, $uri, ['resources' => [1, 2]])->assertOk()->assertExactJson(self::DOCUMENTS);

        $this->assertSame(['begin level 2', ...$writes, 'commit level 1', "observe {$endpoint->value} 1,2"], $this->writeLines());
        $this->assertCount(1, WriteLog::$writes);
        $this->assertSame($endpoint, WriteLog::$writes[0]->endpoint);
        $this->assertSame(2, WriteLog::$writes[0]->user?->getAuthIdentifier());
        $this->assertSame([[1, 'Annual report'], [2, 'Budget']], array_map(fn (Model $row): array => [$row->getKey(), $row->getAttribute('title')], WriteLog::$writes[0]->rows ?? []));
        $this->assertNull(WriteLog::$writes[0]->operations);
        $this->assertNull(WriteLog::$writes[0]->written);
    }

    /** @return array<string, array{string, class-string, list<string>}> */
    public static function actions(): array
    {
        return [
            'chunk' => ['archive-documents-by-one', ArchiveDocumentsByOne::class, [
                'operating archive-documents-by-one',
                'begin level 2', 'handle 1', 'sql update documents', 'commit level 1', 'observe operate 1 (1)',
                'begin level 2', 'handle 2', 'sql update documents', 'commit level 1', 'observe operate 2 (1)',
                'operated archive-documents-by-one 2',
            ]],
            'atomic' => ['atomic-archive-documents', AtomicArchiveDocuments::class, [
                'begin level 2',
                'operating atomic-archive-documents',
                'handle 1', 'sql update documents', 'handle 2', 'sql update documents',
                'operated atomic-archive-documents 2',
                'commit level 1', 'observe operate * (2)',
            ]],
            'direct' => ['direct-archive-documents', DirectArchiveDocuments::class, [
                'operating direct-archive-documents',
                'handle 1', 'sql update documents', 'observe operate 1 (1)',
                'handle 2', 'sql update documents', 'observe operate 2 (1)',
                'operated direct-archive-documents 2',
            ]],
            'per row' => ['archive-each-document', ArchiveEachDocument::class, [
                'operating archive-each-document',
                'before 1', 'begin level 2', 'row 1', 'sql update documents', 'commit level 1', 'observe operate 1 (1)',
                'before 2', 'begin level 2', 'row 2', 'sql update documents', 'commit level 1', 'observe operate 2 (1)',
                'operated archive-each-document 2',
            ]],
        ];
    }

    /**
     * @param  class-string  $action
     * @param  list<string>  $lines
     */
    #[Test]
    #[DataProvider('actions')]
    public function it_notifies_each_committed_transaction_of_an_action(string $uriKey, string $action, array $lines): void
    {
        $operated = 0;
        Event::listen(ActionOperated::class, function () use (&$operated): void {
            $operated++;
        });
        $this->seedDocuments(null, null);
        EventLog::listen();

        $this->postJson("/written-documents/actions/{$uriKey}", ['search' => []])->assertOk()->assertExactJson(self::IMPACTED);

        $this->assertSame($lines, $this->writeLines());
        $this->assertSame(1, $operated);

        foreach (WriteLog::$writes as $context) {
            $this->assertSame(Endpoint::Operate, $context->endpoint);
            $this->assertInstanceOf($action, $context->action);
            $this->assertInstanceOf(ActionFields::class, $context->fields);
            $this->assertSame(2, $context->user?->getAuthIdentifier());
            $this->assertNull($context->operations);
        }
    }

    #[Test]
    public function it_runs_each_chunk_in_its_transaction_when_an_observer_changes_the_action(): void
    {
        config(['rest.extensions' => ['history' => RecordingWriteObserver::class, 'mode' => ModeChangingExtension::class]]);
        $this->seedDocuments(null, null);
        EventLog::listen();

        $this->postJson('/written-documents/actions/archive-documents-by-one', ['search' => []])
            ->assertOk()
            ->assertExactJson(self::IMPACTED);

        $this->assertSame([
            'operating archive-documents-by-one',
            'begin level 2', 'handle 1', 'sql update documents', 'commit level 1', 'observe operate 1 (1)',
            'begin level 2', 'handle 2', 'sql update documents', 'commit level 1', 'observe operate 2 (1)',
            'operated archive-documents-by-one 2',
        ], $this->writeLines());
        $this->assertSame([[1, 'Annual report', true], [2, 'Budget', true]], $this->documents());
    }

    /** @return array<string, array{string, string, string, array<string, mixed>, ?string}> */
    public static function rolledBackWrites(): array
    {
        return [
            'mutate' => ['mutatedAll', 'POST', '/written-documents/mutate', self::MUTATE, null],
            'destroy' => ['destroyedAll', 'DELETE', '/written-documents', ['resources' => [1, 2]], null],
            'restore' => ['restoredAll', 'POST', '/written-documents/restore', ['resources' => [1, 2]], self::TRASHED],
            'atomic action' => ['operated', 'POST', '/written-documents/actions/atomic-archive-documents', ['search' => []], null],
        ];
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('rolledBackWrites')]
    public function it_does_not_notify_a_write_that_rolls_back(string $failure, string $method, string $uri, array $body, ?string $deletedAt): void
    {
        $this->seedDocuments($deletedAt, $deletedAt);
        EventLog::$failure = $failure;

        $this->json($method, $uri, $body)->assertInternalServerError()->assertExactJson(['message' => 'Server Error']);

        $this->assertSame([], WriteLog::$writes);
        $this->assertSame([[1, $deletedAt], [2, $deletedAt]], $this->rows());
        $this->assertSame([[1, 'Annual report', false], [2, 'Budget', false]], $this->documents());
    }

    #[Test]
    public function it_notifies_the_chunks_committed_before_a_failure(): void
    {
        $this->seedDocuments(null, null);

        $this->postJson('/written-documents/actions/failing-archive-documents', ['search' => []])->assertInternalServerError()->assertExactJson(['message' => 'Server Error']);

        $this->assertSame(['operating failing-archive-documents', 'handle 1', 'observe operate 1 (1)', 'handle 2'], EventLog::$lines);
        $this->assertSame([[1, 'Annual report', true], [2, 'Budget', false]], $this->documents());
    }

    /** @return array<string, array{array<string, class-string>, list<string>}> */
    public static function failingOrders(): array
    {
        $recorded = 'observe mutate update:1,create:#';

        return [
            'failing last' => [['history' => RecordingWriteObserver::class, 'webhooks' => FailingWriteObserver::class], [$recorded, 'observe failing']],
            'failing last, configured first' => [['webhooks' => FailingWriteObserver::class, 'history' => RecordingWriteObserver::class], [$recorded, 'observe failing']],
            'failing first' => [['archive' => FailingWriteObserver::class, 'history' => RecordingWriteObserver::class], ['observe failing', $recorded]],
        ];
    }

    /**
     * @param  array<string, class-string>  $extensions
     * @param  list<string>  $lines
     */
    #[Test]
    #[DataProvider('failingOrders')]
    public function it_reports_an_observer_exception_and_answers(array $extensions, array $lines): void
    {
        Exceptions::fake();
        config(['rest.extensions' => $extensions]);
        $this->seedDocuments(null, null);

        $response = $this->postJson('/written-documents/mutate', self::MUTATE)->assertOk();

        $minutes = $this->minutes();
        $response->assertExactJson(self::operations($minutes));
        $this->assertSame(str_replace('#', (string) $minutes, $lines), $this->observedLines());
        Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'The history store is offline.');
        Exceptions::assertReportedCount(1);
        $this->assertCount(1, WriteLog::$writes);
        $this->assertSame([[1, 'Annual summary', false], [2, 'Budget', false], [$minutes, 'Minutes', false]], $this->documents());
    }

    #[Test]
    public function it_waits_for_the_commit_of_an_application_transaction(): void
    {
        $titles = [];
        WriteLog::$observing = function () use (&$titles): void {
            $titles[] = Document::query()->findOrFail(1)->title;
        };
        $this->seedDocuments(null, null);
        DB::beginTransaction();

        $this->postJson('/written-documents/mutate', self::MUTATE)->assertOk()->assertExactJson(self::operations($this->minutes()));

        $this->assertSame([], WriteLog::$writes);
        DB::commit();
        $this->assertCount(1, WriteLog::$writes);
        $this->assertSame(['Annual summary'], $titles);
    }

    #[Test]
    public function it_drops_the_notification_when_the_application_transaction_rolls_back(): void
    {
        $this->seedDocuments(null, null);
        DB::beginTransaction();

        $this->postJson('/written-documents/mutate', self::MUTATE)->assertOk()->assertExactJson(self::operations($this->minutes()));
        DB::rollBack();

        $this->assertSame([], WriteLog::$writes);
        $this->assertSame([[1, 'Annual report', false], [2, 'Budget', false]], $this->documents());
    }

    /** @return array<string, array{string, string, array<string, mixed>, int, Closure(?int): array<string, mixed>}> */
    public static function scheduledWrites(): array
    {
        return [
            'mutate' => ['POST', '/written-documents/mutate', self::MUTATE, 1, fn (int $minutes): array => self::operations($minutes)],
            'destroy' => ['DELETE', '/written-documents', ['resources' => [1, 2]], 1, fn (): array => self::DOCUMENTS],
            'action chunks' => ['POST', '/written-documents/actions/archive-documents-by-one', ['search' => []], 2, fn (): array => self::IMPACTED],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  Closure(?int): array<string, mixed>  $answer
     */
    #[Test]
    #[DataProvider('scheduledWrites')]
    public function it_schedules_nothing_without_an_observer(string $method, string $uri, array $body, int $transactions, Closure $answer): void
    {
        $this->seedDocuments(null, null);

        $without = $this->scheduledCallbacks([], $method, $uri, $body, $answer);
        $with = $this->scheduledCallbacks(['history' => RecordingWriteObserver::class], $method, $uri, $body, $answer);

        $this->assertSame($without + $transactions, $with);
        $this->assertSame([], WriteLog::$writes);
    }

    #[Test]
    #[WithConfig('queue.default', 'database')]
    public function it_notifies_a_queued_action_in_the_worker_after_each_job(): void
    {
        $this->seedDocuments(null, null);

        $this->postJson('/written-documents/actions/queued-archive-documents-by-one', ['search' => []])->assertOk()->assertExactJson(self::IMPACTED);
        $this->assertSame([], WriteLog::$writes);
        $this->app['auth']->forgetGuards();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->assertSame(['observe operate 1 (1)', 'observe operate 2 (1)'], $this->observedLines());

        foreach (WriteLog::$writes as $context) {
            $this->assertSame(Endpoint::Operate, $context->endpoint);
            $this->assertInstanceOf(ArchiveUser::class, $context->user);
            $this->assertSame(2, $context->user->getAuthIdentifier());
            $this->assertNotSame($this->grace, $context->user);
        }
    }

    #[Test]
    public function it_hands_copies_that_do_not_change_the_response(): void
    {
        config(['rest.extensions' => ['history' => ChangingWriteObserver::class]]);
        $this->seedDocuments(null, null);

        $destroyed = $this->deleteJson('/written-documents', ['resources' => [1, 2]]);
        $mutated = $this->postJson('/written-documents/mutate', ['mutate' => [['operation' => 'create', 'attributes' => ['title' => 'Minutes']]], 'response' => ['fields' => ['id', 'title']]]);

        $destroyed->assertOk()->assertExactJson(self::DOCUMENTS);
        $minutes = $this->minutes();
        $mutated->assertOk()->assertExactJson(['operations' => [['operation' => 'create', 'key' => $minutes]], 'records' => [['id' => $minutes, 'title' => 'Minutes', 'archived' => false]]]);
        $this->assertSame([[1, 'Annual report', false], [2, 'Budget', false], [$minutes, 'Minutes', false]], $this->documents());
    }

    /**
     * @param  array<string, class-string>  $extensions
     * @param  array<string, mixed>  $body
     * @param  Closure(?int): array<string, mixed>  $answer
     */
    private function scheduledCallbacks(array $extensions, string $method, string $uri, array $body, Closure $answer): int
    {
        config(['rest.extensions' => $extensions]);
        DB::beginTransaction();

        try {
            $this->json($method, $uri, $body)->assertOk()->assertExactJson($answer($this->minutes()));

            return count($this->app['db.transactions']->callbackApplicableTransactions()->last()->getCallbacks());
        } finally {
            DB::rollBack();
        }
    }

    /** @return array{operations: list<array{operation: string, key: int}>} */
    private static function operations(int $minutes): array
    {
        return ['operations' => [['operation' => 'update', 'key' => 1], ['operation' => 'create', 'key' => $minutes]]];
    }

    private function minutes(): ?int
    {
        return Document::withTrashed()
            ->where('title', 'Minutes')
            ->value('id');
    }

    /** @return list<string> */
    private function writeLines(): array
    {
        return array_values(array_filter(EventLog::$lines, fn (string $line): bool => ! str_starts_with($line, 'sql select')));
    }

    /** @return list<string> */
    private function observedLines(): array
    {
        return array_values(array_filter(EventLog::$lines, fn (string $line): bool => str_starts_with($line, 'observe')));
    }

    /** @return list<array{int, string, bool}> */
    private function documents(): array
    {
        return Document::withTrashed()
            ->orderBy('id')
            ->get()
            ->map(fn (Document $document): array => [$document->id, $document->title, $document->archived])
            ->all();
    }
}
