<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTurbopufferEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\UnsortedVoucherController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EnginePageBoundTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(fn () => Rest::resource('unsorted-vouchers', UnsortedVoucherController::class)->register());
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('pagesPastTheBound')]
    public function it_refuses_a_page_whose_offset_passes_the_largest_integer(string $driver, string $resource, array $search, int $maxPage): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search($resource, ['text' => ['value' => 'report'], ...$search]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => "The search.page field must not be greater than {$maxPage}.",
            'errors' => ['search.page' => ["The search.page field must not be greater than {$maxPage}."]],
        ]);
    }

    /** @return array<string, array{string, string, array<string, mixed>, int}> */
    public static function pagesPastTheBound(): array
    {
        return [
            'one page past the bound on the collection engine' => ['collection', 'vouchers', ['page' => 184467440737095518], 184467440737095517],
            'the largest integer on the collection engine' => ['collection', 'vouchers', ['page' => 9223372036854775807], 184467440737095517],
            'the largest integer on the database engine' => ['database', 'vouchers', ['page' => 9223372036854775807, 'limit' => 10], 184467440737095517],
            'one page past the bound of larger limits' => ['collection', 'unsorted-vouchers', ['page' => 30744573456182588], 30744573456182587],
        ];
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_answers_an_empty_page_at_the_bound(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'page' => 184467440737095517]);

        $response->assertOk()->assertExactJson($this->page([], 4, 50, 184467440737095517));
    }

    #[Test]
    public function it_keeps_the_window_of_the_engine_as_the_only_bound_below_it(): void
    {
        $this->useEngine(new FakeTurbopufferEngine);

        $response = $this->search('unsorted-vouchers', ['text' => ['value' => 'report'], 'limit' => 2, 'page' => 9223372036854775807]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.page field must not be greater than 5000.',
            'errors' => ['search.page' => ['The search.page field must not be greater than 5000.']],
        ]);
    }
}
