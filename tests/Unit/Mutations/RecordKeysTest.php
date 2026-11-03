<?php

namespace Lomkit\Rest\Tests\Unit\Mutations;

use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Mutations\OperationEntry;
use Lomkit\Rest\Mutations\RecordKeys;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Channel;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Publishing\Draft;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RecordKeysTest extends PackageTestCase
{
    private const string OUTLINE = '0B9E6C3A-2F4D-4E5B-8A7C-1D2E3F4A5B6C';

    #[Test]
    public function it_constrains_the_query_to_each_written_key_once(): void
    {
        $query = Book::query();

        $this->bookKeys()->constrain($query);

        $this->assertSame([['type' => 'InRaw', 'column' => 'books.id', 'values' => [3, 5], 'boolean' => 'and']], $query->getQuery()->wheres);
    }

    #[Test]
    public function it_refuses_a_query_that_is_not_an_eloquent_builder(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('Record keys constrain the Eloquent builder of the mutated resource, not Illuminate\Database\Eloquent\Relations\BelongsToMany.'));

        RecordKeys::of(ID::make('id'), [])->constrain((new Book)->shelves());
    }

    #[Test]
    public function it_aligns_the_rows_with_the_entries_that_wrote_them(): void
    {
        $emma = new Book(['id' => 3]);

        $this->assertSame([$emma, null, $emma], $this->bookKeys()->align(new Collection([$emma])));
    }

    #[Test]
    public function it_matches_a_uuid_without_regard_to_case(): void
    {
        $stored = new Draft(['id' => strtolower(self::OUTLINE)]);
        $keys = RecordKeys::of(ID::make('id')->keyType('uuid'), [$this->wrote('create', new Draft(['id' => self::OUTLINE]))]);

        $this->assertSame([$stored], $keys->align(new Collection([$stored])));
    }

    #[Test]
    public function it_matches_a_uuid_row_loaded_in_upper_case(): void
    {
        $stored = new Draft(['id' => self::OUTLINE]);
        $keys = RecordKeys::of(ID::make('id')->keyType('uuid'), [$this->wrote('create', new Draft(['id' => strtolower(self::OUTLINE)]))]);

        $this->assertSame([$stored], $keys->align(new Collection([$stored])));
    }

    #[Test]
    public function it_matches_a_key_cast_to_an_enum(): void
    {
        $stored = new Channel(['code' => 'phone']);
        $keys = RecordKeys::of(ID::make('code')->keyType('string'), [$this->wrote('update', new Channel(['code' => 'phone']))]);

        $this->assertSame([$stored], $keys->align(new Collection([$stored])));
    }

    private function bookKeys(): RecordKeys
    {
        return RecordKeys::of(ID::make('id'), [
            $this->wrote('update', new Book(['id' => 3])),
            $this->wrote('create', new Book(['id' => 5])),
            $this->wrote('update', new Book(['id' => 3])),
        ]);
    }

    private function wrote(string $operation, Book|Draft|Channel $model): OperationEntry
    {
        $entry = OperationEntry::of(['operation' => $operation, 'key' => 1]);
        $entry->wrote($model);

        return $entry;
    }
}
