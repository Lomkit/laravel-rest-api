<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Tests\Feature\Mutate\Concerns\SeedsLibraryRows;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class OneOfManyChildTest extends PackageTestCase
{
    use SeedsLibraryRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[Test]
    public function it_creates_a_row_under_a_has_one_of_many_relation(): void
    {
        $this->seedLibrary();

        $response = $this->postJson('/api/books/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 102,
            'relations' => ['latestLoan' => ['operation' => 'create', 'attributes' => ['borrower' => 'Linus']]],
        ]]]);

        $response->assertOk();
        $this->assertSame(['Ada > Dune', 'Alan > -', 'Grace > Emma', 'Hidden > Ulysses', 'Linus > Emma'], self::library()['loans']);
    }
}
