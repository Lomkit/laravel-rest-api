<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Tests\Fixtures\Library\Book;

class BookTitle extends Instruction
{
    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        $query->addSelect([
            'book_title' => Book::query()
                ->select('title')
                ->whereColumn('books.id', 'loans.book_id')
                ->limit(1),
        ]);
    }
}
