<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ShortPageBookController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\UnlistedPageBookController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\UnlistedPageBookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class PageLimitTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('short-page-books', ShortPageBookController::class)->register();
        Rest::resource('unlisted-page-books', UnlistedPageBookController::class)->register();

        foreach (range(1, 51) as $id) {
            Book::query()->create(['id' => $id, 'title' => 'Book '.$id]);
        }
    }

    #[Test]
    public function it_pages_with_the_default_limit_of_the_resource(): void
    {
        $response = $this->postJson('/api/books/search', ['search' => ['selects' => [['field' => 'id']]]]);

        $response->assertOk()
            ->assertJsonPath('per_page', 50)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('data.*.id', range(51, 2));
    }

    #[Test]
    public function it_pages_with_the_limit_of_the_search(): void
    {
        $response = $this->postJson('/api/books/search', ['search' => ['selects' => [['field' => 'id']], 'limit' => 10, 'page' => 2]]);

        $response->assertOk()
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('data.*.id', range(41, 32));
    }

    #[Test]
    public function it_pages_with_the_default_limit_that_the_resource_sets(): void
    {
        $response = $this->postJson('/short-page-books/search', ['search' => ['selects' => [['field' => 'id']]]]);

        $response->assertOk()
            ->assertJsonPath('per_page', 25)
            ->assertJsonPath('last_page', 3)
            ->assertJsonPath('data.*.id', range(51, 27));
    }

    #[Test]
    public function it_refuses_a_default_limit_that_the_limits_of_the_resource_do_not_hold(): void
    {
        $this->withoutExceptionHandling();
        $this->expectExceptionObject(new InvalidResourceException('The resource ['.UnlistedPageBookResource::class.'] sets $defaultLimit to 32, which is not one of its limits() [10, 25, 50]. Return 32 from limits(), or set $defaultLimit to one of them.'));

        $this->postJson('/unlisted-page-books/search');
    }
}
