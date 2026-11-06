<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\ChecksPolicy;
use PHPUnit\Framework\Attributes\Test;

class PolicyArgumentsTest extends WriteChecksTestCase
{
    #[Test]
    public function it_passes_the_draft_to_create_and_the_changes_to_update_through_the_gate(): void
    {
        Gate::before(function (?Authenticatable $user, string $ability, array $arguments): ?bool {
            if ($ability !== 'viewAny') {
                TracingPolicy::$trace[] = 'before '.implode(' ', [$ability, ...array_map(ChecksPolicy::describe(...), $arguments)]);
            }

            return $ability === 'update' ? true : null;
        });

        $response = $this->mutate('/api/books/mutate', [
            ['operation' => 'create', 'attributes' => ['title' => 'Walden']],
            ['operation' => 'update', 'key' => 101, 'attributes' => ['title' => 'Dune II']],
        ]);

        $response->assertOk();
        $this->assertSame([
            'before create Book',
            'policy create',
            'sql select * from books where books.id in (101)',
            'begin',
            'before create Book* {"title":"Walden"}',
            'policy create Book* {"title":"Walden"}',
            'before update Book(Dune) {"title":"Dune II"}',
        ], array_slice(TracingPolicy::$trace, 0, 7));
    }

    #[Test]
    public function it_fills_the_draft_with_the_keys_of_its_owner_relations(): void
    {
        $response = $this->mutate('/api/loans/mutate', [[
            'operation' => 'create',
            'attributes' => ['borrower' => 'Linus'],
            'relations' => ['book' => ['operation' => 'attach', 'key' => 101]],
        ]]);

        $response->assertOk();
        $this->assertSame([
            'policy create',
            'sql select * from books where books.id in (101)',
            'begin',
            'policy create Loan* {"borrower":"Linus","book_id":101}',
            'policy view Book(Dune)',
            'hook mutating Loan* {"book_id":101}',
            'sql insert into loans (book_id, borrower, updated_at, created_at) values (?, ?, ?, ?)',
            'saved Loan(Linus)',
            'hook mutated Loan(Linus)',
            'commit',
        ], TracingPolicy::$trace);
    }

    #[Test]
    public function it_passes_the_keys_that_an_owner_relation_writes_in_the_changes(): void
    {
        $response = $this->mutate('/api/loans/mutate', [['operation' => 'update', 'key' => 102, 'relations' => ['book' => ['operation' => 'attach', 'key' => 101]]]]);

        $response->assertOk();
        $this->assertSame(['policy update Loan(Grace) {"book_id":101}', 'policy view Book(Dune)'], self::policies());
        $this->assertSame(['Loan(Ada) Book(Dune)', 'Loan(Grace) Book(Dune)'], self::rows('loans'));
    }

    #[Test]
    public function it_checks_the_second_update_of_a_row_against_the_changes_of_the_first(): void
    {
        ChecksPolicy::$refused = ['update Book(Emma II) {"title":"Emma III"}'];

        $response = $this->mutate('/api/books/mutate', [
            ['operation' => 'update', 'key' => 102, 'attributes' => ['title' => 'Emma II']],
            ['operation' => 'update', 'key' => 102, 'attributes' => ['title' => 'Emma III']],
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('denials', [['path' => 'mutate.1', 'ability' => 'update', 'key' => 102, 'message' => 'Refused: update Book(Emma II) {"title":"Emma III"}.']]);
        $this->assertSame(['policy update Book(Emma) {"title":"Emma II"}', 'policy update Book(Emma II) {"title":"Emma III"}'], self::policies());
        $this->assertSame([], self::writes());
    }

    #[Test]
    public function it_checks_the_link_of_a_later_entry_against_the_changes_of_the_entries_before_it(): void
    {
        $response = $this->mutate('/api/loans/mutate', [
            ['operation' => 'update', 'key' => 101, 'relations' => ['book' => ['operation' => 'update', 'key' => 101, 'attributes' => ['title' => 'Dune II']]]],
            ['operation' => 'update', 'key' => 102, 'relations' => ['book' => ['operation' => 'attach', 'key' => 101, 'relations' => ['labels' => [['operation' => 'attach', 'key' => 102]]]]]],
        ]);

        $response->assertOk();
        $this->assertSame([
            'policy update Loan(Ada) {"book_id":101}',
            'policy update Book(Dune) {"title":"Dune II"}',
            'policy update Loan(Grace) {"book_id":101}',
            'policy view Book(Dune II)',
            'policy update Label(loose) {"labelable_id":101,"labelable_type":"Lomkit\\\\Rest\\\\Tests\\\\Fixtures\\\\Library\\\\Book"}',
            'policy update Book(Dune II) []',
        ], self::policies());
    }

    #[Test]
    public function it_passes_the_changes_of_the_entries_before_it_to_the_ability_of_a_link(): void
    {
        ChecksPolicy::$refused = ['attachShelf Book(Emma II) Shelf(Classics)'];

        $response = $this->mutate('/api/books/mutate', [
            ['operation' => 'update', 'key' => 102, 'attributes' => ['title' => 'Emma II']],
            ['operation' => 'update', 'key' => 102, 'relations' => ['shelves' => [['operation' => 'attach', 'key' => 101]]]],
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('denials', [['path' => 'mutate.1.relations.shelves.0', 'ability' => 'attachShelf', 'key' => 101, 'message' => 'Refused: attachShelf Book(Emma II) Shelf(Classics).']]);
        $this->assertSame([
            'policy update Book(Emma) {"title":"Emma II"}',
            'policy update Book(Emma II) []',
            'policy view Shelf(Classics)',
            'policy attachShelf Book(Emma II) Shelf(Classics)',
        ], self::policies());
        $this->assertSame([], self::writes());
    }

    /** @return list<string> */
    private static function policies(): array
    {
        return array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => str_starts_with($line, 'policy ')));
    }
}
