<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Characterization;

use PHPUnit\Framework\Attributes\Test;

class ToManyChildWritesTraceTest extends WriteTraceTestCase
{
    #[Test]
    public function it_writes_a_morph_many_relation_with_a_key_list(): void
    {
        $this->assertWrites('/api/books/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => 102,
                    'relations' => [
                        'labels' => [
                            ['operation' => 'create', 'attributes' => ['text' => 'first']],
                            ['operation' => 'attach', 'key' => [101, 102]],
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
                    'labels' => [
                        ['operation' => 'create', 'key' => 'Label(first)'],
                        ['operation' => 'attach', 'key' => [101, 102]],
                        ['operation' => 'detach', 'key' => [101]],
                    ],
                ],
            ],
        ], [
            'policy create',
            'sql select * from books where books.id in (102)',
            'sql select * from labels where labels.id in (101, 102)',
            'policy update Book(Emma)',
            'saved Book(Emma)',
            'sql insert into labels (text, labelable_id, labelable_type, updated_at, created_at) values (?, ?, ?, ?, ?)',
            'saved Label(first)',
            'policy attachLabel Book(Emma) Label(first)',
            'policy view Label(classic)',
            'sql update labels set labelable_id = ?, updated_at = ? where id = ?',
            'saved Label(classic)',
            'policy view Label(loose)',
            'sql update labels set labelable_type = ?, labelable_id = ?, updated_at = ? where id = ?',
            'saved Label(loose)',
            'policy attachLabel Book(Emma) Label(classic)',
            'policy attachLabel Book(Emma) Label(loose)',
            'policy view Label(classic)',
            'sql update labels set labelable_type = ?, labelable_id = ?, updated_at = ? where id = ?',
            'saved Label(classic)',
            'policy detachLabel Book(Emma) Label(classic)',
        ]);
        $this->assertSame(['Label(classic) - -', 'Label(first) Book Book(Emma)', 'Label(loose) Book Book(Emma)'], self::rows('labels'));
    }

    #[Test]
    public function it_writes_a_has_many_relation_with_a_key_list(): void
    {
        $this->assertWrites('/api/teams/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => 101,
                    'relations' => [
                        'tickets' => [
                            [
                                'operation' => 'create',
                                'attributes' => ['title' => 'Broken screen', 'status' => 'open'],
                            ],
                            [
                                'operation' => 'update',
                                'key' => [101, 102],
                                'attributes' => ['status' => 'closed'],
                            ],
                        ],
                    ],
                ],
            ],
        ], [
            [
                'operation' => 'update',
                'key' => 101,
                'relations' => [
                    'tickets' => [
                        ['operation' => 'create', 'key' => 'Ticket(Broken screen)'],
                        ['operation' => 'update', 'key' => [101, 102]],
                    ],
                ],
            ],
        ], [
            'policy create',
            'sql select * from teams where teams.id in (101)',
            'sql select * from tickets where tickets.id in (101, 102)',
            'policy update Team(Support)',
            'saved Team(Support)',
            'sql insert into tickets (title, status, team_id, updated_at, created_at) values (?, ?, ?, ?, ?)',
            'saved Ticket(Broken screen)',
            'policy attachTicket Team(Support) Ticket(Broken screen)',
            'policy update Ticket(Printer jam)',
            'sql update tickets set status = ?, updated_at = ? where id = ?',
            'saved Ticket(Printer jam)',
            'policy update Ticket(Password reset)',
            'sql update tickets set status = ?, updated_at = ? where id = ?',
            'saved Ticket(Password reset)',
            'policy attachTicket Team(Support) Ticket(Printer jam)',
            'policy attachTicket Team(Support) Ticket(Password reset)',
        ]);
        $this->assertSame([
            'Ticket(Broken screen) open [] []',
            'Ticket(Password reset) closed [] []',
            'Ticket(Printer jam) closed [Tag(Hardware) 1] []',
        ], self::rows('tickets'));
    }
}
