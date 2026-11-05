<?php

namespace Lomkit\Rest\Tests\Feature\MorphTargets;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;

abstract class MorphTargetsTestCase extends PackageTestCase
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

        Relation::morphMap(['book' => Book::class, 'shelf' => Shelf::class], false);
        Book::query()->create(['id' => 1, 'title' => 'Dune']);
        Book::query()->create(['id' => 2, 'title' => 'Emma']);
        Shelf::query()->create(['id' => 1, 'name' => 'Classics']);
        Shelf::query()->create(['id' => 2, 'name' => 'Poetry']);
        Loan::query()->create(['id' => 1, 'book_id' => 1, 'borrower' => 'Ada']);
        Label::query()->create(['id' => 1, 'text' => 'classic', 'labelable_type' => 'book', 'labelable_id' => 1]);
        Label::query()->create(['id' => 2, 'text' => 'shelved', 'labelable_type' => 'shelf', 'labelable_id' => 1]);
        Label::query()->create(['id' => 3, 'text' => 'novel', 'labelable_type' => 'book', 'labelable_id' => 2]);
        Label::query()->create(['id' => 4, 'text' => 'due', 'labelable_type' => Loan::class, 'labelable_id' => 1]);
        Label::query()->create(['id' => 5, 'text' => 'loose', 'labelable_type' => null, 'labelable_id' => null]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        Relation::morphMap([], false);
        Relation::requireMorphMap(false);

        parent::tearDown();
    }

    /** @return list<string> */
    protected static function loggedQueries(): array
    {
        return array_map(static fn (array $query): string => str_replace(['"', '`'], '', $query['query']), DB::getQueryLog());
    }
}
