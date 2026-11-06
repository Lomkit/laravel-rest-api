<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Mutate\Concerns\SeedsLibraryRows;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class OwnerLinksTest extends PackageTestCase
{
    use AssertsExactErrors;
    use SeedsLibraryRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedLibrary();
    }

    /** @param array<string, mixed> $entry */
    #[Test]
    #[DataProvider('otherOwners')]
    public function it_refuses_a_detach_that_does_not_name_the_current_owner(string $uri, array $entry, string $key): void
    {
        $rows = self::library();

        $response = $this->postJson($uri, ['mutate' => [$entry]]);

        $this->assertExactErrors($response, [$key => ["The selected {$key} is invalid."]]);
        $this->assertSame($rows, self::library());
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function otherOwners(): array
    {
        return [
            'belongs to' => ['/api/loans/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['book' => ['operation' => 'detach', 'key' => 101]]], 'mutate.0.relations.book.key'],
            'morph to' => ['/api/labels/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['labelable' => ['operation' => 'detach', 'type' => Book::class, 'key' => 101]]], 'mutate.0.relations.labelable.key'],
            'morph to, an owner of another type' => ['/api/labels/mutate', ['operation' => 'update', 'key' => 104, 'relations' => ['labelable' => ['operation' => 'detach', 'type' => Book::class, 'key' => 101]]], 'mutate.0.relations.labelable.key'],
            'belongs to, under a created row' => ['/api/loans/mutate', ['operation' => 'create', 'attributes' => ['borrower' => 'Linus'], 'relations' => ['book' => ['operation' => 'detach', 'key' => 101]]], 'mutate.0.relations.book.key'],
            'morph to, under a created row' => ['/api/labels/mutate', ['operation' => 'create', 'attributes' => ['text' => 'recent'], 'relations' => ['labelable' => ['operation' => 'detach', 'type' => Shelf::class, 'key' => 101]]], 'mutate.0.relations.labelable.key'],
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $rows
     */
    #[Test]
    #[DataProvider('currentOwners')]
    public function it_detaches_the_current_owner(string $uri, array $entry, string $table, array $rows): void
    {
        $response = $this->postJson($uri, ['mutate' => [$entry]]);

        $response->assertOk();
        $this->assertSame($rows, self::library()[$table]);
    }

    /** @return array<string, array{string, array<string, mixed>, string, list<string>}> */
    public static function currentOwners(): array
    {
        return [
            'belongs to' => ['/api/loans/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['book' => ['operation' => 'detach', 'key' => 102]]], 'loans', ['Ada > Dune', 'Alan > -', 'Grace > -', 'Hidden by search > Ulysses']],
            'morph to' => ['/api/labels/mutate', ['operation' => 'update', 'key' => 104, 'relations' => ['labelable' => ['operation' => 'detach', 'type' => Shelf::class, 'key' => 101]]], 'labels', ['classic > Book Dune', 'loose > - -', 'modern > Book Emma', 'shelved > - -']],
        ];
    }
}
