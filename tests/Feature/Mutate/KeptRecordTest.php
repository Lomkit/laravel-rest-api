<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Mutate\Concerns\SeedsLibraryRows;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\KeptBookController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\LockedBookController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PerimeterBookController;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class KeptRecordTest extends PackageTestCase
{
    use AssertsExactErrors;
    use SeedsLibraryRows;

    private const string KEPT = 'field is prohibited: this relation already holds a record and does not detach it.';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('kept-books', KeptBookController::class)->register();
        Rest::resource('locked-books', LockedBookController::class)->register();
        $this->seedLibrary();
    }

    /** @param array<string, mixed> $relations */
    #[Test]
    #[DataProvider('keptRecords')]
    public function it_tells_how_to_replace_the_record_of_a_relation_that_does_not_detach(string $uri, array $relations, string $key, string $advice): void
    {
        $rows = self::library();

        $response = $this->postJson($uri, ['mutate' => [['operation' => 'update', 'key' => 102, 'relations' => $relations]]]);

        $this->assertExactErrors($response, [$key => [rtrim("The {$key} ".self::KEPT.' '.$advice)]]);
        $this->assertSame($rows, self::library());
    }

    /** @return array<string, array{string, array<string, mixed>, string, string}> */
    public static function keptRecords(): array
    {
        return [
            'a relation that updates' => ['/kept-books/mutate', ['loan' => ['operation' => 'attach', 'key' => 103]], 'mutate.0.relations.loan', 'Update that record instead.'],
            'a relation that updates or deletes' => ['/kept-books/mutate', ['label' => ['operation' => 'create', 'attributes' => ['text' => 'recent']]], 'mutate.0.relations.label', 'Update that record instead, or delete it first.'],
            'a relation that deletes' => ['/locked-books/mutate', ['loan' => ['operation' => 'create', 'attributes' => ['borrower' => 'Linus']]], 'mutate.0.relations.loan', 'Delete it first.'],
            'a relation that neither updates nor deletes' => ['/locked-books/mutate', ['label' => ['operation' => 'attach', 'key' => 103]], 'mutate.0.relations.label', ''],
        ];
    }

    #[Test]
    public function it_takes_another_record_once_an_entry_before_deleted_the_current_one(): void
    {
        $response = $this->postJson('/locked-books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 102, 'relations' => ['loan' => ['operation' => 'delete', 'key' => 102]]],
            ['operation' => 'update', 'key' => 102, 'relations' => ['loan' => ['operation' => 'attach', 'key' => 103]]],
        ]]);

        $response->assertOk();
        $this->assertNull(Loan::query()->find(102));
        $this->assertSame(102, Loan::query()->findOrFail(103)->book_id);
    }

    #[Test]
    public function it_tells_how_to_replace_the_record_in_french(): void
    {
        $this->app->setLocale('fr');

        $response = $this->postJson('/kept-books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 102, 'relations' => ['label' => ['operation' => 'attach', 'key' => 103]]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.relations.label' => ['Le champ mutate.0.relations.label est interdit : cette relation contient déjà un élément et ne le détache pas. Modifiez plutôt cet élément, ou supprimez-le d\'abord.']]);
    }

    #[Test]
    public function it_tells_how_to_replace_the_record_of_a_relation_whose_foreign_key_does_not_accept_null(): void
    {
        Rest::resource('perimeter-books', PerimeterBookController::class)->register();
        Schema::drop('loans');
        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('book_id');
            $table->string('borrower');
            $table->timestamps();
        });
        Loan::query()->insert([['id' => 102, 'book_id' => 102, 'borrower' => 'Grace'], ['id' => 103, 'book_id' => 101, 'borrower' => 'Alan']]);

        $response = $this->postJson('/perimeter-books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 102, 'relations' => ['loan' => ['operation' => 'attach', 'key' => 103]]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.relations.loan' => ['The mutate.0.relations.loan '.self::KEPT.' Update that record instead.']]);
    }
}
