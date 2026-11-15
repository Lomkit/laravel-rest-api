<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Limits;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Extensions\Contexts\LimitsContext;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\CappedBookLimitsController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstNarrowing;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\LimitShelfResource;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', ['plan' => FirstNarrowing::class])]
class NarrowedLimitsTest extends PackageTestCase
{
    use AssertsExactErrors;
    use CapturesQueries;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        FirstNarrowing::$narrow = static function (LimitsContext $context): void {
            if ($context->user === null) {
                return;
            }

            if ($context->resource instanceof LimitShelfResource) {
                $context->includeLimit(1);

                return;
            }

            $context->allowSizes([10, 25]);
            $context->maxTotal(30);
        };
        Rest::resource('capped-limit-books', CappedBookLimitsController::class)->register();
        Book::query()->insert(array_map(static fn (int $id): array => ['id' => $id, 'title' => 'Book '.$id], range(1, 40)));
        DB::table('shelves')->insert([['id' => 1, 'name' => 'Classics'], ['id' => 2, 'name' => 'Poetry']]);
        DB::table('book_shelf')->insert([['book_id' => 40, 'shelf_id' => 1], ['book_id' => 40, 'shelf_id' => 2]]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        FirstNarrowing::$narrow = null;

        parent::tearDown();
    }

    #[Test]
    public function it_refuses_a_page_size_that_an_extension_removes(): void
    {
        $guest = $this->postJson('/api/books/search', ['search' => ['limit' => 50]]);
        $user = $this->actingAs(new GenericUser(['id' => 1]))->postJson('/api/books/search', ['search' => ['limit' => 50]]);

        $guest->assertOk();
        $this->assertExactErrors($user, ['search.limit' => ['The selected search.limit is invalid.']]);
    }

    #[Test]
    public function it_reads_the_narrowed_default_page_size(): void
    {
        $guest = $this->postJson('/api/books/search', ['search' => []]);
        $user = $this->actingAs(new GenericUser(['id' => 1]))->postJson('/api/books/search', ['search' => []]);

        $this->assertSame([50, 25], [$guest->assertOk()->json('per_page'), $user->assertOk()->json('per_page')]);
    }

    #[Test]
    public function it_reads_includes_up_to_the_narrowed_include_limit(): void
    {
        $this->captureQueries();

        $response = $this->actingAs(new GenericUser(['id' => 1]))->postJson('/capped-limit-books/search', ['search' => ['limit' => 10, 'includes' => [['relation' => 'shelves']]]]);

        $response->assertOk()->assertJsonPath('data.0.shelves.*.id', [2]);
        $this->assertStringContainsString('where laravel_row <= 2', $this->readsOf('shelves')[0][0]);
    }

    #[Test]
    public function it_caps_the_total_with_the_narrowed_max_total(): void
    {
        $response = $this->actingAs(new GenericUser(['id' => 1]))->postJson('/api/books/search', ['search' => ['limit' => 10]]);

        $response->assertOk()->assertJsonPath('total', 30)->assertJsonPath('total_capped', true);
    }

    #[Test]
    public function it_lists_the_narrowed_sizes_of_the_current_user_in_details(): void
    {
        $guest = $this->getJson('/api/books');
        $user = $this->actingAs(new GenericUser(['id' => 1]))->getJson('/api/books');

        $this->assertSame([[10, 25, 50], [10, 25]], [$guest->assertOk()->json('data.limits'), $user->assertOk()->json('data.limits')]);
    }

    #[Test]
    public function it_narrows_for_the_user_of_a_payload_search(): void
    {
        $guest = Rest::search(BookResource::class)->as(null)->payload([])->page();
        $user = Rest::search(BookResource::class)->as(new GenericUser(['id' => 1]))->payload([])->page();

        $this->assertSame([50, 25], [$guest->perPage(), $user->perPage()]);
    }
}
