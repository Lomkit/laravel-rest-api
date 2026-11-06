<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Mutate\Concerns\SeedsLibraryRows;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PerimeterBookController;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class OneChildTest extends PackageTestCase
{
    use AssertsExactErrors;
    use CapturesQueries;
    use SeedsLibraryRows;

    private const string HELD = 'field is prohibited: this relation already holds a record. Detach it first.';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('perimeter-books', PerimeterBookController::class)->register();
        $this->seedLibrary();
    }

    /** @param list<array<string, mixed>> $mutate */
    #[Test]
    #[DataProvider('secondRecords')]
    public function it_refuses_a_second_record_on_a_has_one_or_morph_one_relation(array $mutate, string $key): void
    {
        $rows = self::library();

        $response = $this->postJson('/perimeter-books/mutate', ['mutate' => $mutate]);

        $this->assertExactErrors($response, [$key => ["The {$key} ".self::HELD]]);
        $this->assertSame($rows, self::library());
    }

    /** @return array<string, array{list<array<string, mixed>>, string}> */
    public static function secondRecords(): array
    {
        return [
            'has one attach' => [[['operation' => 'update', 'key' => 102, 'relations' => ['loan' => ['operation' => 'attach', 'key' => 103]]]], 'mutate.0.relations.loan'],
            'has one create' => [[['operation' => 'update', 'key' => 102, 'relations' => ['loan' => ['operation' => 'create', 'attributes' => ['borrower' => 'Linus']]]]], 'mutate.0.relations.loan'],
            'morph one attach' => [[['operation' => 'update', 'key' => 102, 'relations' => ['label' => ['operation' => 'attach', 'key' => 103]]]], 'mutate.0.relations.label'],
            'morph one create' => [[['operation' => 'update', 'key' => 102, 'relations' => ['label' => ['operation' => 'create', 'attributes' => ['text' => 'recent']]]]], 'mutate.0.relations.label'],
            'a current record outside the perimeter' => [[['operation' => 'update', 'key' => 106, 'relations' => ['loan' => ['operation' => 'attach', 'key' => 103]]]], 'mutate.0.relations.loan'],
            'a record that an entry before attached' => [[
                ['operation' => 'update', 'key' => 107, 'relations' => ['loan' => ['operation' => 'attach', 'key' => 103]]],
                ['operation' => 'update', 'key' => 107, 'relations' => ['loan' => ['operation' => 'create', 'attributes' => ['borrower' => 'Linus']]]],
            ], 'mutate.1.relations.loan'],
        ];
    }

    #[Test]
    public function it_gives_no_held_record_error_while_an_entry_cannot_be_read(): void
    {
        $rows = self::library();

        $response = $this->postJson('/perimeter-books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 102, 'relations' => ['loan' => ['operation' => 'remove', 'key' => 102]]],
            ['operation' => 'update', 'key' => 102, 'relations' => ['loan' => ['operation' => 'attach', 'key' => 103]]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.relations.loan.operation' => ['The selected mutate.0.relations.loan.operation is invalid.']]);
        $this->assertSame($rows, self::library());
    }

    #[Test]
    public function it_reads_the_current_records_with_one_query_per_relation(): void
    {
        $this->captureQueries();

        $this->postJson('/perimeter-books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 107, 'relations' => ['loan' => ['operation' => 'attach', 'key' => 103], 'label' => ['operation' => 'attach', 'key' => 103]]],
            ['operation' => 'create', 'attributes' => ['title' => 'Persuasion'], 'relations' => ['loan' => ['operation' => 'create', 'attributes' => ['borrower' => 'Linus']]]],
            ['operation' => 'update', 'key' => 101, 'relations' => ['loan' => ['operation' => 'detach', 'key' => 101]]],
            ['operation' => 'update', 'key' => 101, 'relations' => ['loan' => ['operation' => 'attach', 'key' => 101]]],
        ]])->assertOk();

        $this->assertSame([
            ['select loans.id, loans.book_id from loans where loans.book_id in (?, ?)', [107, 101]],
            ['select labels.id, labels.labelable_id from labels where labels.labelable_id in (?) and labels.labelable_type = ?', [107, Book::class]],
        ], array_values(array_filter($this->statements, fn (array $statement): bool => str_contains($statement[0], '_id in (?'))));
        $this->assertSame(['Ada > Dune', 'Alan > Walden', 'Grace > Emma', 'Hidden > Ulysses', 'Linus > Persuasion'], self::library()['loans']);
    }

    /**
     * @param  list<array<string, mixed>>  $mutate
     * @param  list<string>  $rows
     */
    #[Test]
    #[DataProvider('replacements')]
    public function it_takes_a_record_once_an_entry_before_detached_the_current_one(array $mutate, string $table, array $rows): void
    {
        $response = $this->postJson('/perimeter-books/mutate', ['mutate' => $mutate]);

        $response->assertOk();
        $this->assertSame($rows, self::library()[$table]);
    }

    /** @return array<string, array{list<array<string, mixed>>, string, list<string>}> */
    public static function replacements(): array
    {
        return [
            'has one' => [[
                ['operation' => 'update', 'key' => 102, 'relations' => ['loan' => ['operation' => 'detach', 'key' => 102]]],
                ['operation' => 'update', 'key' => 102, 'relations' => ['loan' => ['operation' => 'attach', 'key' => 103]]],
            ], 'loans', ['Ada > Dune', 'Alan > Emma', 'Grace > -', 'Hidden > Ulysses']],
            'morph one' => [[
                ['operation' => 'update', 'key' => 102, 'relations' => ['label' => ['operation' => 'detach', 'key' => 102]]],
                ['operation' => 'update', 'key' => 102, 'relations' => ['label' => ['operation' => 'create', 'attributes' => ['text' => 'recent']]]],
            ], 'labels', ['classic > Book Dune', 'loose > - -', 'modern > - -', 'recent > Book Emma', 'shelved > Shelf Classics']],
        ];
    }

    /**
     * @param  array<string, mixed>  $relations
     * @param  list<string>  $rows
     */
    #[Test]
    #[DataProvider('otherRecords')]
    public function it_takes_another_record_on_a_one_of_many_relation_or_the_current_record_again(array $relations, string $table, array $rows): void
    {
        $response = $this->postJson('/perimeter-books/mutate', ['mutate' => [['operation' => 'update', 'key' => 102, 'relations' => $relations]]]);

        $response->assertOk();
        $this->assertSame($rows, self::library()[$table]);
    }

    /** @return array<string, array{array<string, mixed>, string, list<string>}> */
    public static function otherRecords(): array
    {
        return [
            'has one of many attach' => [['latestLoan' => ['operation' => 'attach', 'key' => 103]], 'loans', ['Ada > Dune', 'Alan > Emma', 'Grace > Emma', 'Hidden > Ulysses']],
            'morph one of many create' => [['latestLabel' => ['operation' => 'create', 'attributes' => ['text' => 'recent']]], 'labels', ['classic > Book Dune', 'loose > - -', 'modern > Book Emma', 'recent > Book Emma', 'shelved > Shelf Classics']],
            'has one attach of the current record' => [['loan' => ['operation' => 'attach', 'key' => 102]], 'loans', ['Ada > Dune', 'Alan > -', 'Grace > Emma', 'Hidden > Ulysses']],
        ];
    }
}
