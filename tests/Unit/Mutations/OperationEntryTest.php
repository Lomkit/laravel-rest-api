<?php

namespace Lomkit\Rest\Tests\Unit\Mutations;

use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Mutations\OperationEntry;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasOne;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Channel;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OperationEntryTest extends TestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        Relation::morphMap([], false);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $mutation
     * @param  array<string, mixed>  $entry
     */
    #[Test]
    #[DataProvider('entries')]
    public function it_echoes_the_operation_and_the_key_of_an_entry(array $mutation, array $entry): void
    {
        $this->assertSame($entry, OperationEntry::of($mutation)->jsonSerialize());
    }

    /** @return array<string, array{array<string, mixed>, array<string, mixed>}> */
    public static function entries(): array
    {
        return [
            'an update' => [['operation' => 'update', 'key' => 7, 'attributes' => ['title' => 'Emma']], ['operation' => 'update', 'key' => 7]],
            'a string key' => [['operation' => 'attach', 'key' => 'a-7'], ['operation' => 'attach', 'key' => 'a-7']],
            'a key list' => [['operation' => 'sync', 'key' => [3, 7], 'without_detaching' => true], ['operation' => 'sync', 'key' => [3, 7]]],
            'a create that wrote no row' => [['operation' => 'create', 'attributes' => ['title' => 'Emma']], ['operation' => 'create', 'key' => null]],
            'empty relations' => [['operation' => 'update', 'key' => 7, 'relations' => []], ['operation' => 'update', 'key' => 7]],
        ];
    }

    #[Test]
    public function it_keeps_the_key_of_each_row_that_an_entry_wrote(): void
    {
        $entry = OperationEntry::of(['operation' => 'attach', 'key' => [3, 7]]);

        $entry->wrote(new Book(['id' => 3]));
        $entry->wrote(new Book(['id' => 7]));

        $this->assertSame([3, 7], $entry->writtenKeys());
        $this->assertSame(['operation' => 'attach', 'key' => [3, 7]], $entry->jsonSerialize());
    }

    #[Test]
    public function it_reports_the_key_of_each_row_that_a_create_wrote(): void
    {
        $once = OperationEntry::of(['operation' => 'create']);
        $twice = OperationEntry::of(['operation' => 'create']);
        $channel = OperationEntry::of(['operation' => 'create']);

        $once->wrote(new Book(['id' => 5]));
        $twice->wrote(new Book(['id' => 5]));
        $twice->wrote(new Book(['id' => 6]));
        $channel->wrote(new Channel(['code' => 'email']));

        $this->assertSame(['operation' => 'create', 'key' => 5], $once->jsonSerialize());
        $this->assertSame(['operation' => 'create', 'key' => [5, 6]], $twice->jsonSerialize());
        $this->assertSame('{"operation":"create","key":"email"}', json_encode($channel));
    }

    #[Test]
    public function it_lists_the_relations_in_the_order_of_the_request(): void
    {
        $book = OperationEntry::of(['operation' => 'update', 'key' => 1, 'relations' => [
            'shelves' => [['operation' => 'attach', 'key' => [2, 3]]],
            'loan' => ['operation' => 'create'],
        ]]);

        $book->within(HasOne::make('loan', LoanResource::class));
        $book->child(['operation' => 'create'])->wrote(new Loan(['id' => 9]));
        $book->within(BelongsToMany::make('shelves', ShelfResource::class));
        $book->child(['operation' => 'attach', 'key' => [2, 3]]);

        $this->assertSame(['operation' => 'update', 'key' => 1, 'relations' => [
            'shelves' => [['operation' => 'attach', 'key' => [2, 3]]],
            'loan' => ['operation' => 'create', 'key' => 9],
        ]], json_decode((string) json_encode($book), true));
    }

    #[Test]
    public function it_gives_the_same_entry_to_each_row_of_a_relation_entry(): void
    {
        $shelf = OperationEntry::of(['operation' => 'update', 'key' => 1, 'relations' => ['loan' => ['operation' => 'create']]]);
        $shelf->within(HasOne::make('loan', LoanResource::class));
        $first = $shelf->child(['operation' => 'create']);

        $shelf->rewind();

        $this->assertSame($first, $shelf->child(['operation' => 'create']));
        $this->assertNotSame($first, $shelf->child(['operation' => 'create']));
    }

    #[Test]
    #[DataProvider('morphMaps')]
    public function it_echoes_the_type_of_a_morph_to_entry(array $map, string $type): void
    {
        Relation::morphMap($map, false);
        $label = OperationEntry::of(['operation' => 'update', 'key' => 4, 'relations' => ['labelable' => ['operation' => 'attach', 'key' => 2]]]);
        $label->within(MorphTo::make('labelable', BookResource::class));

        $label->child(['operation' => 'attach', 'key' => 2])->wrote(new Book(['id' => 2]));

        $this->assertSame(['operation' => 'update', 'key' => 4, 'relations' => [
            'labelable' => ['operation' => 'attach', 'type' => $type, 'key' => 2],
        ]], json_decode((string) json_encode($label), true));
    }

    /** @return array<string, array{array<string, class-string>, string}> */
    public static function morphMaps(): array
    {
        return [
            'the class without a morph map' => [[], Book::class],
            'the alias of the morph map' => [['book' => Book::class], 'book'],
        ];
    }

    #[Test]
    public function it_answers_a_key_list_for_a_create_under_a_key_list(): void
    {
        $loans = OperationEntry::of(['operation' => 'attach', 'key' => [3], 'relations' => ['book' => ['operation' => 'create']]]);
        $loans->within(BelongsTo::make('book', BookResource::class));

        $loans->child(['operation' => 'create'])->wrote(new Book(['id' => 11]));

        $this->assertSame(['operation' => 'attach', 'key' => [3], 'relations' => [
            'book' => ['operation' => 'create', 'key' => [11]],
        ]], json_decode((string) json_encode($loans), true));
    }

    #[Test]
    public function it_answers_a_key_list_for_a_create_nested_under_a_create_under_a_key_list(): void
    {
        $book = ['operation' => 'create', 'relations' => ['labels' => [['operation' => 'create']]]];
        $loans = OperationEntry::of(['operation' => 'attach', 'key' => [3], 'relations' => ['book' => $book]]);
        $loans->within(BelongsTo::make('book', BookResource::class));
        $created = $loans->child($book);
        $created->within(HasMany::make('labels', LabelResource::class));

        $created->child(['operation' => 'create'])->wrote(new Label(['id' => 21]));
        $created->wrote(new Book(['id' => 11]));

        $this->assertSame(['operation' => 'attach', 'key' => [3], 'relations' => [
            'book' => ['operation' => 'create', 'key' => [11], 'relations' => ['labels' => [['operation' => 'create', 'key' => [21]]]]],
        ]], json_decode((string) json_encode($loans), true));
    }
}
