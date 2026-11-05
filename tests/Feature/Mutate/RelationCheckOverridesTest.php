<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Tests\Feature\Mutate\Characterization\WriteTraceTestCase;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\RefusingBookResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\RefusingLabelResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\RefusingShelfResource;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RelationCheckOverridesTest extends WriteTraceTestCase
{
    /**
     * @param  array<string, mixed>  $mutation
     * @param  list<string>  $rows
     */
    #[Test]
    #[DataProvider('refusedWrites')]
    public function it_runs_the_attach_and_detach_checks_that_the_related_resource_overrides(string $endpoint, array $mutation, string $message, string $table, array $rows): void
    {
        $this->app?->bind(BookResource::class, RefusingBookResource::class);
        $this->app?->bind(LabelResource::class, RefusingLabelResource::class);
        $this->app?->bind(ShelfResource::class, RefusingShelfResource::class);

        $response = $this->postJson($endpoint, ['mutate' => [$mutation]]);

        $response->assertForbidden();
        $response->assertJsonPath('message', $message);
        $this->assertSame($rows, self::rows($table));
    }

    /** @return array<string, array{string, array<string, mixed>, string, string, list<string>}> */
    public static function refusedWrites(): array
    {
        return [
            'an attach on a belongs to relation' => [
                '/api/loans/mutate',
                ['operation' => 'update', 'key' => 102, 'relations' => ['book' => ['operation' => 'attach', 'key' => 102]]],
                'The resource refuses to attach Book(Emma) to Loan(Grace).',
                'loans',
                ['Loan(Ada) Book(Dune)', 'Loan(Grace) -'],
            ],
            'a detach on a belongs to relation' => [
                '/api/loans/mutate',
                ['operation' => 'update', 'key' => 101, 'relations' => ['book' => ['operation' => 'detach', 'key' => 101]]],
                'The resource refuses to detach Book(Dune) from Loan(Ada).',
                'loans',
                ['Loan(Ada) Book(Dune)', 'Loan(Grace) -'],
            ],
            'an attach on a morph many relation' => [
                '/api/books/mutate',
                ['operation' => 'update', 'key' => 102, 'relations' => ['labels' => [['operation' => 'attach', 'key' => [102]]]]],
                'The resource refuses to attach Label(loose) to Book(Emma).',
                'labels',
                ['Label(classic) Book Book(Dune)', 'Label(loose) - -'],
            ],
            'an attach on a belongs to many relation' => [
                '/api/books/mutate',
                ['operation' => 'update', 'key' => 102, 'relations' => ['shelves' => [['operation' => 'attach', 'key' => [101]]]]],
                'The resource refuses to attach Shelf(Classics) to Book(Emma).',
                'books',
                ['Book(Dune) [Shelf(Classics)]', 'Book(Emma) []'],
            ],
        ];
    }
}
