<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Characterization;

use PHPUnit\Framework\Attributes\Test;

class RootWritesTraceTest extends WriteTraceTestCase
{
    #[Test]
    public function it_writes_a_root_create_and_a_root_update(): void
    {
        $this->assertWrites('/api/books/mutate', [
            'mutate' => [
                ['operation' => 'create', 'attributes' => ['title' => 'Solaris']],
                ['operation' => 'update', 'key' => 102, 'attributes' => ['title' => 'Emma Woodhouse']],
            ],
        ], [['operation' => 'create', 'key' => 'Book(Solaris)'], ['operation' => 'update', 'key' => 102]], [
            'policy create',
            'sql select * from books where books.id in (102)',
            'policy create',
            'policy update Book(Emma)',
            'sql insert into books (title, updated_at, created_at) values (?, ?, ?)',
            'saved Book(Solaris)',
            'sql update books set title = ?, updated_at = ? where id = ?',
            'saved Book(Emma Woodhouse)',
        ]);
        $this->assertSame(['Book(Dune) [Shelf(Classics)]', 'Book(Emma Woodhouse) []', 'Book(Solaris) []'], self::rows('books'));
    }

    #[Test]
    public function it_writes_the_entries_of_a_create_nested_two_levels_deep(): void
    {
        $this->assertWrites('/api/loans/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => ['borrower' => 'Margaret'],
                    'relations' => [
                        'book' => [
                            'operation' => 'create',
                            'attributes' => ['title' => 'Ubik'],
                            'relations' => [
                                'labels' => [['operation' => 'create', 'attributes' => ['text' => 'new']]],
                                'shelves' => [['operation' => 'attach', 'key' => [102]]],
                            ],
                        ],
                    ],
                ],
            ],
        ], [
            [
                'operation' => 'create',
                'key' => 'Loan(Margaret)',
                'relations' => [
                    'book' => [
                        'operation' => 'create',
                        'key' => 'Book(Ubik)',
                        'relations' => [
                            'labels' => [['operation' => 'create', 'key' => 'Label(new)']],
                            'shelves' => [['operation' => 'attach', 'key' => [102]]],
                        ],
                    ],
                ],
            ],
        ], [
            'policy create',
            'policy create',
            'policy create',
            'sql select * from shelves where shelves.id in (102)',
            'policy create',
            'policy create',
            'policy create',
            'policy view Shelf(Poetry)',
            'sql insert into books (title, updated_at, created_at) values (?, ?, ?)',
            'saved Book(Ubik)',
            'sql insert into labels (text, labelable_id, labelable_type, updated_at, created_at) values (?, ?, ?, ?, ?)',
            'saved Label(new)',
            'sql insert into book_shelf (book_id, shelf_id) values (?, ?)',
            'sql insert into loans (book_id, borrower, updated_at, created_at) values (?, ?, ?, ?)',
            'saved Loan(Margaret)',
        ]);
        $this->assertSame(['Book(Dune) [Shelf(Classics)]', 'Book(Emma) []', 'Book(Ubik) [Shelf(Poetry)]'], self::rows('books'));
        $this->assertSame(['Loan(Ada) Book(Dune)', 'Loan(Grace) -', 'Loan(Margaret) Book(Ubik)'], self::rows('loans'));
        $this->assertSame(['Label(classic) Book Book(Dune)', 'Label(loose) - -', 'Label(new) Book Book(Ubik)'], self::rows('labels'));
    }
}
