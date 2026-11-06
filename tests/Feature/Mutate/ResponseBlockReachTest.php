<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\PerimeterBookController;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ResponseBlockReachTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[Test]
    public function it_returns_null_for_a_record_outside_the_perimeter_of_its_resource(): void
    {
        Rest::resource('perimeter-books', PerimeterBookController::class)->register();
        Book::query()->insert(['id' => 101, 'title' => 'Dune']);

        $this->postJson('/perimeter-books/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => 101, 'attributes' => ['title' => 'Hidden']],
                ['operation' => 'create', 'attributes' => ['title' => 'Emma']],
            ],
            'response' => ['selects' => [['field' => 'title']]],
        ])
            ->assertOk()
            ->assertJsonPath('records', [null, ['title' => 'Emma']]);

        $title = Book::query()
            ->findOrFail(101)
            ->getAttribute('title');
        $this->assertSame('Hidden', $title);
    }
}
