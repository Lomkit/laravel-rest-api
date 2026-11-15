<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Writes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;
use Lomkit\Rest\Extensions\Contexts\WriteObservationContext;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Mutations\WrittenRow;
use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\HeldShelfGuard;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\LoanWritesExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RecordingGuard;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RecordingWriteObserver;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WriteLog;
use Lomkit\Rest\Tests\Feature\Mutate\Concerns\SeedsLibraryRows;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\LinkCheckedBookResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\RefusingLibraryLinksPolicy;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.debug', false)]
final class NestedWriteExtensionsTest extends PackageTestCase
{
    use SeedsLibraryRows;

    private const array NESTED = ['mutate' => [
        ['operation' => 'update', 'key' => 101, 'attributes' => ['title' => 'Dune Messiah'], 'relations' => [
            'loan' => ['operation' => 'update', 'key' => 101, 'attributes' => ['borrower' => 'Bob']],
            'shelves' => [['operation' => 'attach', 'key' => [102]]],
        ]],
    ]];

    private const array OPERATIONS = ['operations' => [
        ['operation' => 'update', 'key' => 101, 'relations' => [
            'loan' => ['operation' => 'update', 'key' => 101],
            'shelves' => [['operation' => 'attach', 'key' => [102]]],
        ]],
    ]];

    private const array SYNC = ['mutate' => [
        ['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => [['operation' => 'sync', 'key' => [102]]]]],
    ]];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ExtensionLog::reset();
        EventLog::reset();
        WriteLog::reset();
        $this->seedLibrary();
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
    public function it_refuses_a_nested_write_with_the_guard_of_the_related_resource(): void
    {
        config(['rest.extensions' => ['loans' => LoanWritesExtension::class]]);
        WriteLog::$refuseAt = 1;
        WriteLog::$refusal = 'Loans are frozen.';
        WriteLog::$status = 423;
        $library = self::library();

        $this->postJson('/api/books/mutate', self::NESTED)
            ->assertStatus(423)
            ->assertExactJson(['message' => 'Loans are frozen.']);

        $this->assertSame($library, self::library());
        $this->assertSame([['LoanResource', ['update 101'], [[101, 'Ada']]]], self::guarded());
        $this->assertSame([], WriteLog::$writes);
    }

    #[Test]
    public function it_asks_a_guard_once_for_each_resource_of_a_mutation(): void
    {
        config(['rest.extensions' => ['quota' => RecordingGuard::class]]);

        $this->postJson('/api/books/mutate', self::NESTED)
            ->assertOk()
            ->assertExactJson(self::OPERATIONS);

        $this->assertSame([
            ['BookResource', ['update 101'], [[101, 'Dune']]],
            ['LoanResource', ['update 101'], [[101, 'Ada']]],
            ['ShelfResource', ['attach 102'], [[102, 'Poetry']]],
        ], self::guarded());
    }

    #[Test]
    public function it_notifies_the_observer_of_the_related_resource_of_its_nested_rows(): void
    {
        config(['rest.extensions' => ['loans' => LoanWritesExtension::class]]);

        $this->postJson('/api/books/mutate', self::NESTED)
            ->assertOk()
            ->assertExactJson(self::OPERATIONS);

        $this->assertSame([['LoanResource', [['LoanResource', 'update', 101, 'Bob']]]], self::observed());
        $this->assertSame(self::OPERATIONS['operations'], WriteLog::$writes[0]->operations);
    }

    #[Test]
    public function it_notifies_an_observer_once_for_each_resource_with_its_own_rows(): void
    {
        config(['rest.extensions' => ['history' => RecordingWriteObserver::class]]);

        $this->postJson('/api/books/mutate', self::NESTED)
            ->assertOk()
            ->assertExactJson(self::OPERATIONS);

        $this->assertSame([
            ['BookResource', [['BookResource', 'update', 101, 'Dune Messiah']]],
            ['LoanResource', [['LoanResource', 'update', 101, 'Bob']]],
            ['ShelfResource', [['ShelfResource', 'attach', 102, 'Poetry']]],
        ], self::observed());
    }

    #[Test]
    public function it_hands_the_rows_that_a_sync_detaches_to_the_guard_and_the_observer(): void
    {
        config(['rest.extensions' => ['history' => RecordingWriteObserver::class, 'quota' => RecordingGuard::class]]);

        $this->postJson('/api/books/mutate', self::SYNC)
            ->assertOk()
            ->assertExactJson(['operations' => [
                ['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => [['operation' => 'sync', 'key' => [102]]]]],
            ]]);

        $this->assertSame([
            ['BookResource', ['update 101'], [[101, 'Dune']]],
            ['ShelfResource', ['sync 102'], [[101, 'Classics'], [102, 'Poetry'], [103, 'Hidden']]],
        ], self::sortedRows(self::guarded()));
        $this->assertSame([
            ['BookResource', [['BookResource', 'update', 101, 'Dune']]],
            ['ShelfResource', [['ShelfResource', 'sync', 101, 'Classics'], ['ShelfResource', 'sync', 102, 'Poetry'], ['ShelfResource', 'sync', 103, 'Hidden']]],
        ], self::sortedRows(self::observed()));
        $this->assertSame(['Dune > Poetry'], self::library()['shelves']);
    }

    /** @return array<string, array{list<array<string, mixed>>}> */
    public static function unlinkedShelves(): array
    {
        return [
            'detach' => [[['operation' => 'detach', 'key' => [101]]]],
            'sync' => [[['operation' => 'sync', 'key' => [102]]]],
        ];
    }

    /** @param list<array<string, mixed>> $shelves */
    #[Test]
    #[DataProvider('unlinkedShelves')]
    public function it_refuses_the_unlinking_of_a_held_row_whether_named_or_left_out_of_a_sync(array $shelves): void
    {
        config(['rest.extensions' => ['hold' => HeldShelfGuard::class]]);
        $library = self::library();

        $this->postJson('/api/books/mutate', ['mutate' => [['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => $shelves]]]])
            ->assertStatus(423)
            ->assertExactJson(['message' => 'The shelf is on hold.']);

        $this->assertSame($library, self::library());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function linkedCreations(): array
    {
        return [
            'created book with an attached label' => [['operation' => 'create', 'attributes' => ['title' => 'Middlemarch'], 'relations' => ['labels' => [['operation' => 'attach', 'key' => [103]]]]]],
            'created book with an attached loan' => [['operation' => 'create', 'attributes' => ['title' => 'Middlemarch'], 'relations' => ['loan' => ['operation' => 'attach', 'key' => 103]]]],
            'created loan of a stored book' => [['operation' => 'update', 'key' => 107, 'relations' => ['loan' => ['operation' => 'create', 'attributes' => ['borrower' => 'Linus']]]]],
        ];
    }

    /** @param array<string, mixed> $entry */
    #[Test]
    #[DataProvider('linkedCreations')]
    public function it_asks_the_guard_before_the_link_checks_of_a_created_row(array $entry): void
    {
        foreach ([Book::class, Loan::class, Label::class] as $model) {
            Gate::policy($model, RefusingLibraryLinksPolicy::class);
        }

        $this->app?->bind(BookResource::class, LinkCheckedBookResource::class);
        config(['rest.extensions' => ['quota' => RecordingGuard::class]]);
        $library = self::library();

        $this->postJson('/api/books/mutate', ['mutate' => [$entry]])
            ->assertForbidden()
            ->assertExactJson(['message' => 'This action is unauthorized.']);

        $this->assertNotSame([], WriteLog::$guards);
        $this->assertSame($library, self::library());

        WriteLog::reset();
        WriteLog::$refuseAt = 1;

        $this->postJson('/api/books/mutate', ['mutate' => [$entry]])
            ->assertStatus(429)
            ->assertExactJson(['message' => 'The monthly quota is reached.']);

        $this->assertCount(1, WriteLog::$guards);
        $this->assertSame($library, self::library());
    }

    #[Test]
    public function it_neither_asks_nor_schedules_an_extension_of_a_resource_that_a_mutation_does_not_reach(): void
    {
        $without = $this->scheduledCallbacks([]);
        $with = $this->scheduledCallbacks(['loans' => LoanWritesExtension::class]);

        $this->assertSame($without, $with);
        $this->assertSame([], WriteLog::$guards);
        $this->assertSame([], WriteLog::$writes);
    }

    /** @param array<string, class-string> $extensions */
    private function scheduledCallbacks(array $extensions): int
    {
        config(['rest.extensions' => $extensions]);
        DB::beginTransaction();

        try {
            $this->postJson('/api/books/mutate', ['mutate' => [['operation' => 'update', 'key' => 102, 'attributes' => ['title' => 'Persuasion']]]])
                ->assertOk()
                ->assertExactJson(['operations' => [['operation' => 'update', 'key' => 102]]]);

            return count($this->app['db.transactions']->callbackApplicableTransactions()->last()->getCallbacks());
        } finally {
            DB::rollBack();
        }
    }

    /** @return list<array{string, list<string>, list<array{mixed, mixed}>}> */
    private static function guarded(): array
    {
        return array_map(fn (WriteGuardContext $context): array => [
            class_basename($context->resource),
            array_map(fn (MutationNode $node): string => $node->operation->value.' '.implode(',', (array) $node->key), $context->entries ?? []),
            array_map(fn (Model $row): array => [$row->getKey(), self::label($row)], $context->rows ?? []),
        ], WriteLog::$guards);
    }

    /** @return list<array{string, list<array{string, string, mixed, mixed}>}> */
    private static function observed(): array
    {
        return array_map(fn (WriteObservationContext $context): array => [
            class_basename($context->resource),
            array_map(fn (WrittenRow $row): array => [
                class_basename($row->resource),
                $row->entry->operation->value,
                $row->model->getKey(),
                self::label($row->model),
            ], $context->written ?? []),
        ], WriteLog::$writes);
    }

    /**
     * @param  list<array<int, mixed>>  $calls
     * @return list<array<int, mixed>>
     */
    private static function sortedRows(array $calls): array
    {
        return array_map(function (array $call): array {
            $rows = end($call);
            usort($rows, fn (array $left, array $right): int => array_values($left) <=> array_values($right));
            $call[array_key_last($call)] = $rows;

            return $call;
        }, $calls);
    }

    private static function label(Model $row): mixed
    {
        $attributes = $row->getAttributes();

        return $attributes['title'] ?? $attributes['borrower'] ?? $attributes['name'] ?? null;
    }
}
