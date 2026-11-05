<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\EagerLoanController;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\UnrelatedEagerLoanController;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class PlannedRelationsTest extends PackageTestCase
{
    private const array LOANS = [['id' => 1, 'borrower' => 'Ada', 'book_id' => 1], ['id' => 2, 'borrower' => 'Bob', 'book_id' => null]];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('eager-loans', EagerLoanController::class)->register();
        Rest::resource('unrelated-eager-loans', UnrelatedEagerLoanController::class)->register();
        Book::create(['id' => 1, 'title' => 'Dune']);
        Loan::create(['id' => 1, 'book_id' => 1, 'borrower' => 'Ada']);
        Loan::create(['id' => 2, 'book_id' => null, 'borrower' => 'Bob']);
    }

    #[Test]
    public function it_loads_a_relation_of_the_model_without_rendering_it(): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/eager-loans/search', ['search' => ['sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data', self::LOANS);
        $this->assertCount(2, DB::getQueryLog());
    }

    #[Test]
    public function it_renders_a_relation_of_the_model_that_the_search_includes(): void
    {
        $response = $this->postJson('/eager-loans/search', ['search' => ['includes' => [['relation' => 'book']], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.book', [['id' => 1, 'title' => 'Dune'], null]);
    }

    #[Test]
    public function it_ignores_a_loaded_relation_that_the_resource_does_not_declare(): void
    {
        $response = $this->postJson('/unrelated-eager-loans/search', ['search' => ['sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data', self::LOANS);
    }
}
