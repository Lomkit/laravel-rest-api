<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Characterization;

use PHPUnit\Framework\Attributes\Test;

class ToOneChildWritesTraceTest extends WriteTraceTestCase
{
    #[Test]
    public function it_writes_a_has_one_and_a_morph_one_relation(): void
    {
        $this->assertWrites('/api/books/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => 102,
                    'relations' => [
                        'loan' => ['operation' => 'attach', 'key' => 102],
                        'label' => ['operation' => 'attach', 'key' => 102],
                    ],
                ],
                [
                    'operation' => 'update',
                    'key' => 101,
                    'relations' => [
                        'loan' => [
                            'operation' => 'update',
                            'key' => 101,
                            'attributes' => ['borrower' => 'Ada Lovelace'],
                        ],
                        'label' => ['operation' => 'detach', 'key' => 101],
                    ],
                ],
            ],
        ], [
            [
                'operation' => 'update',
                'key' => 102,
                'relations' => [
                    'loan' => ['operation' => 'attach', 'key' => 102],
                    'label' => ['operation' => 'attach', 'key' => 102],
                ],
            ],
            [
                'operation' => 'update',
                'key' => 101,
                'relations' => [
                    'loan' => ['operation' => 'update', 'key' => 101],
                    'label' => ['operation' => 'detach', 'key' => 101],
                ],
            ],
        ], [
            'sql select * from books where books.id in (102, 101)',
            'sql select * from loans where loans.id in (102, 101)',
            'sql select * from labels where labels.id in (102, 101)',
            'sql select loans.id, loans.book_id from loans where loans.book_id in (?)',
            'sql select labels.id, labels.labelable_id from labels where labels.labelable_id in (?) and labels.labelable_type = ?',
            'policy update Book(Emma)',
            'policy update Loan(Grace)',
            'policy update Label(loose)',
            'policy update Book(Dune)',
            'policy update Loan(Ada)',
            'policy update Label(classic)',
            'saved Book(Emma)',
            'sql update loans set book_id = ?, updated_at = ? where id = ?',
            'saved Loan(Grace)',
            'sql update labels set labelable_type = ?, labelable_id = ?, updated_at = ? where id = ?',
            'saved Label(loose)',
            'saved Book(Dune)',
            'sql update loans set borrower = ?, updated_at = ? where id = ?',
            'saved Loan(Ada Lovelace)',
            'sql update labels set labelable_type = ?, labelable_id = ?, updated_at = ? where id = ?',
            'saved Label(classic)',
        ]);
        $this->assertSame(['Loan(Ada Lovelace) Book(Dune)', 'Loan(Grace) Book(Emma)'], self::rows('loans'));
        $this->assertSame(['Label(classic) - -', 'Label(loose) Book Book(Emma)'], self::rows('labels'));
    }

    #[Test]
    public function it_writes_the_one_of_many_relations(): void
    {
        $this->assertWrites('/api/books/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => 101,
                    'relations' => [
                        'latestLoan' => ['operation' => 'detach', 'key' => 101],
                        'latestLabel' => ['operation' => 'create', 'attributes' => ['text' => 'recent']],
                    ],
                ],
            ],
        ], [
            [
                'operation' => 'update',
                'key' => 101,
                'relations' => [
                    'latestLoan' => ['operation' => 'detach', 'key' => 101],
                    'latestLabel' => ['operation' => 'create', 'key' => 'Label(recent)'],
                ],
            ],
        ], [
            'policy create',
            'sql select * from books where books.id in (101)',
            'sql select * from loans where loans.id in (101)',
            'policy update Book(Dune)',
            'policy update Loan(Ada)',
            'policy create',
            'saved Book(Dune)',
            'sql update loans set book_id = ?, updated_at = ? where id = ?',
            'saved Loan(Ada)',
            'sql insert into labels (text, labelable_id, labelable_type, updated_at, created_at) values (?, ?, ?, ?, ?)',
            'saved Label(recent)',
        ]);
        $this->assertSame(['Loan(Ada) -', 'Loan(Grace) -'], self::rows('loans'));
        $this->assertSame(['Label(classic) Book Book(Dune)', 'Label(loose) - -', 'Label(recent) Book Book(Dune)'], self::rows('labels'));
    }
}
