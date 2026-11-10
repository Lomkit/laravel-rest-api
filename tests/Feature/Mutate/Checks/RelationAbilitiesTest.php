<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks;

use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\PinningBookResource;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use PHPUnit\Framework\Attributes\Test;

class RelationAbilitiesTest extends WriteChecksTestCase
{
    #[Test]
    public function it_asks_update_on_the_parent_for_a_link_whose_relation_declares_no_ability(): void
    {
        $response = $this->mutate('/api/loans/mutate', [[
            'operation' => 'update',
            'key' => 101,
            'relations' => ['book' => ['operation' => 'attach', 'key' => 101, 'relations' => ['labels' => [['operation' => 'attach', 'key' => [102]]]]]],
        ]]);

        $response->assertOk();
        $this->assertSame([
            'policy update Loan(Ada) {"book_id":101}',
            'policy view Book(Dune)',
            'policy update Label(loose) {"labelable_id":101,"labelable_type":"Lomkit\\\\Rest\\\\Tests\\\\Fixtures\\\\Library\\\\Book"}',
            'policy update Book(Dune) []',
        ], self::policies());
        $this->assertSame(['Label(classic) Book Book(Dune)', 'Label(loose) Book Book(Dune)'], self::rows('labels'));
    }

    #[Test]
    public function it_checks_the_links_of_a_created_row_with_its_creation(): void
    {
        $response = $this->mutate('/api/books/mutate', [[
            'operation' => 'create',
            'attributes' => ['title' => 'Walden'],
            'relations' => ['labels' => [['operation' => 'attach', 'key' => [102]]]],
        ]]);

        $response->assertOk();
        $this->assertSame(['policy create', 'policy create Book* {"title":"Walden"}', 'policy update Label(loose) {"labelable_id":null,"labelable_type":"Lomkit\\\\Rest\\\\Tests\\\\Fixtures\\\\Library\\\\Book"}'], self::policies());
        $this->assertSame(['Label(classic) Book Book(Dune)', 'Label(loose) Book Book(Walden)'], self::rows('labels'));
    }

    #[Test]
    public function it_refuses_a_declared_ability_that_no_policy_defines(): void
    {
        $this->app?->bind(BookResource::class, PinningBookResource::class);

        $response = $this->mutate('/api/books/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => [['operation' => 'attach', 'key' => [102]]]]]]);

        $response->assertForbidden();
        $response->assertExactJson(['message' => 'This action is unauthorized.', 'denials' => [
            ['path' => 'mutate.0.relations.shelves.0', 'ability' => 'pinShelf', 'key' => 102, 'message' => 'This action is unauthorized.'],
        ]]);
        $this->assertSame(['policy update Book(Dune) []', 'policy view Shelf(Poetry)'], self::policies());
        $this->assertSame([], self::writes());
    }

    /** @return list<string> */
    private static function policies(): array
    {
        return array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => str_starts_with($line, 'policy ')));
    }
}
