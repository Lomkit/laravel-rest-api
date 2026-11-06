<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Characterization;

use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use PHPUnit\Framework\Attributes\Test;

class OwnerWritesTraceTest extends WriteTraceTestCase
{
    #[Test]
    public function it_writes_each_operation_of_a_belongs_to_relation(): void
    {
        $this->assertWrites('/api/loans/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => ['borrower' => 'Linus'],
                    'relations' => ['book' => ['operation' => 'create', 'attributes' => ['title' => 'Ubik']]],
                ],
                [
                    'operation' => 'update',
                    'key' => 101,
                    'relations' => [
                        'book' => ['operation' => 'update', 'key' => 101, 'attributes' => ['title' => 'Dune Messiah']],
                    ],
                ],
                [
                    'operation' => 'update',
                    'key' => 102,
                    'relations' => ['book' => ['operation' => 'attach', 'key' => 102]],
                ],
                [
                    'operation' => 'update',
                    'key' => 101,
                    'relations' => ['book' => ['operation' => 'detach', 'key' => 101]],
                ],
            ],
        ], [
            [
                'operation' => 'create',
                'key' => 'Loan(Linus)',
                'relations' => ['book' => ['operation' => 'create', 'key' => 'Book(Ubik)']],
            ],
            [
                'operation' => 'update',
                'key' => 101,
                'relations' => ['book' => ['operation' => 'update', 'key' => 101]],
            ],
            [
                'operation' => 'update',
                'key' => 102,
                'relations' => ['book' => ['operation' => 'attach', 'key' => 102]],
            ],
            [
                'operation' => 'update',
                'key' => 101,
                'relations' => ['book' => ['operation' => 'detach', 'key' => 101]],
            ],
        ], [
            'policy create',
            'policy create',
            'sql select * from loans where loans.id in (101, 102)',
            'sql select * from books where books.id in (101, 102)',
            'policy create',
            'policy create',
            'policy update Loan(Ada)',
            'policy update Book(Dune)',
            'policy update Loan(Grace)',
            'policy view Book(Emma)',
            'policy update Loan(Ada)',
            'policy view Book(Dune Messiah)',
            'sql insert into books (title, updated_at, created_at) values (?, ?, ?)',
            'saved Book(Ubik)',
            'sql insert into loans (book_id, borrower, updated_at, created_at) values (?, ?, ?, ?)',
            'saved Loan(Linus)',
            'sql update books set title = ?, updated_at = ? where id = ?',
            'saved Book(Dune Messiah)',
            'saved Loan(Ada)',
            'saved Book(Emma)',
            'sql update loans set book_id = ?, updated_at = ? where id = ?',
            'saved Loan(Grace)',
            'saved Book(Dune Messiah)',
            'sql update loans set book_id = ?, updated_at = ? where id = ?',
            'saved Loan(Ada)',
        ]);
        $this->assertSame(['Book(Dune Messiah) [Shelf(Classics)]', 'Book(Emma) []', 'Book(Ubik) []'], self::rows('books'));
        $this->assertSame(['Loan(Ada) -', 'Loan(Grace) Book(Emma)', 'Loan(Linus) Book(Ubik)'], self::rows('loans'));
    }

    #[Test]
    public function it_writes_each_operation_of_a_morph_to_relation(): void
    {
        $this->assertWrites('/api/labels/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => 102,
                    'relations' => ['labelable' => ['operation' => 'attach', 'type' => Shelf::class, 'key' => 101]],
                ],
                [
                    'operation' => 'create',
                    'attributes' => ['text' => 'fresh'],
                    'relations' => [
                        'labelable' => ['operation' => 'create', 'type' => Book::class, 'attributes' => ['title' => 'Ubik']],
                    ],
                ],
                [
                    'operation' => 'update',
                    'key' => 101,
                    'relations' => ['labelable' => ['operation' => 'detach', 'type' => Book::class, 'key' => 101]],
                ],
            ],
        ], [
            [
                'operation' => 'update',
                'key' => 102,
                'relations' => ['labelable' => ['operation' => 'attach', 'type' => Shelf::class, 'key' => 101]],
            ],
            [
                'operation' => 'create',
                'key' => 'Label(fresh)',
                'relations' => ['labelable' => ['operation' => 'create', 'type' => Book::class, 'key' => 'Book(Ubik)']],
            ],
            [
                'operation' => 'update',
                'key' => 101,
                'relations' => ['labelable' => ['operation' => 'detach', 'type' => Book::class, 'key' => 101]],
            ],
        ], [
            'policy create',
            'policy create',
            'sql select * from labels where labels.id in (102, 101)',
            'sql select * from shelves where shelves.id in (101)',
            'sql select * from books where books.id in (101)',
            'policy update Label(loose)',
            'policy view Shelf(Classics)',
            'policy create',
            'policy create',
            'policy update Label(classic)',
            'policy view Book(Dune)',
            'saved Shelf(Classics)',
            'sql update labels set labelable_type = ?, labelable_id = ?, updated_at = ? where id = ?',
            'saved Label(loose)',
            'sql insert into books (title, updated_at, created_at) values (?, ?, ?)',
            'saved Book(Ubik)',
            'sql insert into labels (labelable_id, labelable_type, text, updated_at, created_at) values (?, ?, ?, ?, ?)',
            'saved Label(fresh)',
            'saved Book(Dune)',
            'sql update labels set labelable_type = ?, labelable_id = ?, updated_at = ? where id = ?',
            'saved Label(classic)',
        ]);
        $this->assertSame(['Book(Dune) [Shelf(Classics)]', 'Book(Emma) []', 'Book(Ubik) []'], self::rows('books'));
        $this->assertSame(['Label(classic) - -', 'Label(fresh) Book Book(Ubik)', 'Label(loose) Shelf Shelf(Classics)'], self::rows('labels'));
    }
}
