<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Characterization;

use PHPUnit\Framework\Attributes\Test;

class PivotWritesTraceTest extends WriteTraceTestCase
{
    #[Test]
    public function it_writes_each_operation_of_a_belongs_to_many_relation(): void
    {
        $this->assertWrites('/api/books/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => 102,
                    'relations' => [
                        'shelves' => [
                            ['operation' => 'create', 'attributes' => ['name' => 'Sci-fi']],
                            ['operation' => 'attach', 'key' => [101]],
                            [
                                'operation' => 'update',
                                'key' => 101,
                                'attributes' => ['name' => 'Old classics'],
                            ],
                            ['operation' => 'detach', 'key' => [101]],
                        ],
                    ],
                ],
            ],
        ], [
            [
                'operation' => 'update',
                'key' => 102,
                'relations' => [
                    'shelves' => [
                        ['operation' => 'create', 'key' => 'Shelf(Sci-fi)'],
                        ['operation' => 'attach', 'key' => [101]],
                        ['operation' => 'update', 'key' => 101],
                        ['operation' => 'detach', 'key' => [101]],
                    ],
                ],
            ],
        ], [
            'policy create',
            'sql select * from books where books.id in (102)',
            'sql select * from shelves where shelves.id in (101)',
            'policy update Book(Emma)',
            'policy create',
            'policy view Shelf(Classics)',
            'policy update Shelf(Classics)',
            'saved Book(Emma)',
            'sql insert into shelves (name, updated_at, created_at) values (?, ?, ?)',
            'saved Shelf(Sci-fi)',
            'sql select * from book_shelf where book_shelf.book_id = ?',
            'sql insert into book_shelf (book_id, shelf_id) values (?, ?)',
            'sql insert into book_shelf (book_id, shelf_id) values (?, ?)',
            'sql update shelves set name = ?, updated_at = ? where id = ?',
            'saved Shelf(Old classics)',
            'sql select * from book_shelf where book_shelf.book_id = ?',
            'sql delete from book_shelf where book_shelf.book_id = ? and book_shelf.shelf_id in (?)',
        ]);
        $this->assertSame(['Book(Dune) [Shelf(Old classics)]', 'Book(Emma) [Shelf(Sci-fi)]'], self::rows('books'));
    }

    #[Test]
    public function it_syncs_and_toggles_with_pivot_values(): void
    {
        $this->assertWrites('/api/tickets/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => 101,
                    'relations' => [
                        'tags' => [
                            [
                                'operation' => 'sync',
                                'key' => [102],
                                'pivot' => ['position' => 2],
                                'without_detaching' => true,
                            ],
                            ['operation' => 'toggle', 'key' => [101]],
                            ['operation' => 'sync', 'key' => [101], 'pivot' => ['position' => 3]],
                        ],
                    ],
                ],
            ],
        ], [
            [
                'operation' => 'update',
                'key' => 101,
                'relations' => [
                    'tags' => [
                        ['operation' => 'sync', 'key' => [102]],
                        ['operation' => 'toggle', 'key' => [101]],
                        ['operation' => 'sync', 'key' => [101]],
                    ],
                ],
            ],
        ], [
            'sql select * from tickets where tickets.id in (101)',
            'sql select * from tags where tags.id in (102, 101) and tags.deleted_at is null',
            'sql select tags.*, tag_ticket.ticket_id as pivot_ticket_id, tag_ticket.tag_id as pivot_tag_id, tag_ticket.position as pivot_position, tag_ticket.weight as pivot_weight, tag_ticket.added_by as pivot_added_by, tag_ticket.note as pivot_note, tag_ticket.created_at as pivot_created_at from tags inner join tag_ticket on tags.id = tag_ticket.tag_id where tag_ticket.ticket_id = ? and tags.deleted_at is null',
            'policy update Ticket(Printer jam)',
            'policy view Tag(Network)',
            'policy attachTag Ticket(Printer jam) Tag(Network)',
            'policy view Tag(Hardware)',
            'policy detachTag Ticket(Printer jam) Tag(Hardware)',
            'policy attachTag Ticket(Printer jam) Tag(Hardware)',
            'policy detachTag Ticket(Printer jam) Tag(Network)',
            'saved Ticket(Printer jam)',
            'sql select * from tag_ticket where tag_ticket.ticket_id = ?',
            'sql insert into tag_ticket (created_at, position, tag_id, ticket_id) values (?, ?, ?, ?)',
            'sql select tag_id from tag_ticket where tag_ticket.ticket_id = ?',
            'sql delete from tag_ticket where tag_ticket.ticket_id = ? and tag_ticket.tag_id in (?)',
            'sql delete from tag_ticket where tag_ticket.ticket_id = ? and tag_ticket.tag_id in (?)',
            'sql select * from tag_ticket where tag_ticket.ticket_id = ?',
            'sql insert into tag_ticket (created_at, position, tag_id, ticket_id) values (?, ?, ?, ?)',
        ]);
        $this->assertSame(['Ticket(Password reset) open [] []', 'Ticket(Printer jam) open [Tag(Hardware) 3] []'], self::rows('tickets'));
    }

    #[Test]
    public function it_writes_a_morphed_by_many_relation_with_pivot_values(): void
    {
        $this->assertWrites('/api/tags/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => 102,
                    'relations' => [
                        'labelledTickets' => [
                            ['operation' => 'attach', 'key' => [101, 102], 'pivot' => ['position' => 4]],
                            ['operation' => 'detach', 'key' => [102]],
                        ],
                    ],
                ],
            ],
        ], [
            [
                'operation' => 'update',
                'key' => 102,
                'relations' => [
                    'labelledTickets' => [
                        ['operation' => 'attach', 'key' => [101, 102]],
                        ['operation' => 'detach', 'key' => [102]],
                    ],
                ],
            ],
        ], [
            'sql select * from tags where tags.id in (102) and tags.deleted_at is null',
            'sql select * from tickets where tickets.id in (101, 102)',
            'policy update Tag(Network)',
            'policy view Ticket(Printer jam)',
            'policy view Ticket(Password reset)',
            'saved Tag(Network)',
            'sql insert into labelables (created_at, labelable_id, labelable_type, position, tag_id) values (?, ?, ?, ?, ?), (?, ?, ?, ?, ?)',
            'sql delete from labelables where labelables.tag_id = ? and labelable_type = ? and labelables.labelable_id in (?)',
        ]);
        $this->assertSame(['Ticket(Password reset) open [] []', 'Ticket(Printer jam) open [Tag(Hardware) 1] [Tag(Network) 4]'], self::rows('tickets'));
    }

    #[Test]
    public function it_writes_each_operation_of_a_morph_to_many_relation(): void
    {
        $this->assertWrites('/api/tickets/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => 101,
                    'relations' => [
                        'labels' => [
                            ['operation' => 'attach', 'key' => [101, 102]],
                            ['operation' => 'detach', 'key' => 101],
                            ['operation' => 'detach', 'key' => [102]],
                        ],
                    ],
                ],
            ],
        ], [
            [
                'operation' => 'update',
                'key' => 101,
                'relations' => [
                    'labels' => [
                        ['operation' => 'attach', 'key' => [101, 102]],
                        ['operation' => 'detach', 'key' => 101],
                        ['operation' => 'detach', 'key' => [102]],
                    ],
                ],
            ],
        ], [
            'sql select * from tickets where tickets.id in (101)',
            'sql select * from tags where tags.id in (101, 102) and tags.deleted_at is null',
            'policy update Ticket(Printer jam)',
            'policy view Tag(Hardware)',
            'policy view Tag(Network)',
            'policy attachTag Ticket(Printer jam) Tag(Hardware)',
            'policy attachTag Ticket(Printer jam) Tag(Network)',
            'policy detachTag Ticket(Printer jam) Tag(Hardware)',
            'policy detachTag Ticket(Printer jam) Tag(Network)',
            'saved Ticket(Printer jam)',
            'sql insert into labelables (created_at, labelable_id, labelable_type, tag_id) values (?, ?, ?, ?), (?, ?, ?, ?)',
            'sql delete from labelables where labelables.labelable_id = ? and labelable_type = ? and labelables.tag_id in (?)',
            'sql delete from labelables where labelables.labelable_id = ? and labelable_type = ? and labelables.tag_id in (?)',
        ]);
        $this->assertSame(['Ticket(Password reset) open [] []', 'Ticket(Printer jam) open [Tag(Hardware) 1] []'], self::rows('tickets'));
    }
}
