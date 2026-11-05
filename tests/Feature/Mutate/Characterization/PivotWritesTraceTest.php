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
            'sql select books.id from books where books.id in (102)',
            'sql select shelves.id from shelves where shelves.id in (101)',
            'sql select * from books where books.id = ? limit 1',
            'policy update Book(Emma)',
            'saved Book(Emma)',
            'sql insert into shelves (name, updated_at, created_at) values (?, ?, ?)',
            'saved Shelf(Sci-fi)',
            'policy attachShelf Book(Emma) Shelf(Sci-fi)',
            'sql select * from book_shelf where book_shelf.book_id = ?',
            'sql insert into book_shelf (book_id, shelf_id) values (?, ?)',
            'sql select * from shelves where shelves.id = ? limit 1',
            'policy view Shelf(Classics)',
            'saved Shelf(Classics)',
            'policy attachShelf Book(Emma) Shelf(Classics)',
            'sql insert into book_shelf (book_id, shelf_id) values (?, ?)',
            'sql select * from shelves where shelves.id = ? limit 1',
            'policy update Shelf(Classics)',
            'sql update shelves set name = ?, updated_at = ? where id = ?',
            'saved Shelf(Old classics)',
            'policy attachShelf Book(Emma) Shelf(Old classics)',
            'sql select * from book_shelf where book_shelf.book_id = ?',
            'sql select * from shelves where shelves.id = ? limit 1',
            'policy view Shelf(Old classics)',
            'saved Shelf(Old classics)',
            'policy detachShelf Book(Emma) Shelf(Old classics)',
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
            'sql select tickets.id from tickets where tickets.id in (101)',
            'sql select tags.id from tags where tags.id in (102, 101) and tags.deleted_at is null',
            'sql select * from tickets where tickets.id = ? limit 1',
            'policy update Ticket(Printer jam)',
            'saved Ticket(Printer jam)',
            'sql select * from tags where tags.id = ? and tags.deleted_at is null limit 1',
            'policy view Tag(Network)',
            'saved Tag(Network)',
            'sql select * from tag_ticket where tag_ticket.ticket_id = ?',
            'sql insert into tag_ticket (created_at, position, tag_id, ticket_id) values (?, ?, ?, ?)',
            'sql select * from tags where tags.id = ? and tags.deleted_at is null limit 1',
            'policy attachTag Ticket(Printer jam) Tag(Network)',
            'sql select * from tags where tags.id = ? and tags.deleted_at is null limit 1',
            'policy view Tag(Hardware)',
            'saved Tag(Hardware)',
            'sql select tag_id from tag_ticket where tag_ticket.ticket_id = ?',
            'sql delete from tag_ticket where tag_ticket.ticket_id = ? and tag_ticket.tag_id in (?)',
            'sql select * from tags where tags.id = ? and tags.deleted_at is null limit 1',
            'policy detachTag Ticket(Printer jam) Tag(Hardware)',
            'sql select * from tags where tags.id = ? and tags.deleted_at is null limit 1',
            'saved Tag(Hardware)',
            'sql select * from tag_ticket where tag_ticket.ticket_id = ?',
            'sql delete from tag_ticket where tag_ticket.ticket_id = ? and tag_ticket.tag_id in (?)',
            'sql insert into tag_ticket (created_at, position, tag_id, ticket_id) values (?, ?, ?, ?)',
            'sql select * from tags where tags.id = ? and tags.deleted_at is null limit 1',
            'policy attachTag Ticket(Printer jam) Tag(Hardware)',
            'sql select * from tags where tags.id = ? and tags.deleted_at is null limit 1',
            'policy detachTag Ticket(Printer jam) Tag(Network)',
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
            'sql select tags.id from tags where tags.id in (102) and tags.deleted_at is null',
            'sql select tickets.id from tickets where tickets.id in (101, 102)',
            'sql select * from tags where tags.id = ? and tags.deleted_at is null limit 1',
            'policy update Tag(Network)',
            'saved Tag(Network)',
            'sql select * from tickets where tickets.id = ? limit 1',
            'policy view Ticket(Printer jam)',
            'saved Ticket(Printer jam)',
            'sql select * from tickets where tickets.id = ? limit 1',
            'policy view Ticket(Password reset)',
            'saved Ticket(Password reset)',
            'policy attachTicket Tag(Network) Ticket(Printer jam)',
            'policy attachTicket Tag(Network) Ticket(Password reset)',
            'sql insert into labelables (created_at, labelable_id, labelable_type, position, tag_id) values (?, ?, ?, ?, ?), (?, ?, ?, ?, ?)',
            'sql select * from tickets where tickets.id = ? limit 1',
            'saved Ticket(Password reset)',
            'policy detachTicket Tag(Network) Ticket(Password reset)',
            'sql delete from labelables where labelables.tag_id = ? and labelable_type = ? and labelables.labelable_id in (?)',
        ]);
        $this->assertSame(['Ticket(Password reset) open [] []', 'Ticket(Printer jam) open [Tag(Hardware) 1] [Tag(Network) 4]'], self::rows('tickets'));
    }
}
