<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Events\ResourceMutated;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\ChecksBookResource;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\ChecksPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\DeletingBookResource;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\DeletingLoanResource;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\DeletingTeamResource;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\DeletingTicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ChildDeletesTest extends WriteChecksTestCase
{
    use AssertsExactErrors;

    private const array UNTOUCHED = ['Broken screen open', 'Hidden open', 'Password reset open', 'Printer jam open', 'Slow network open'];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(Team::class, ChecksPolicy::class);
        Gate::policy(Ticket::class, ChecksPolicy::class);
        $this->app?->bind(BookResource::class, DeletingBookResource::class);
        $this->app?->bind(LoanResource::class, DeletingLoanResource::class);
        $this->app?->bind(TeamResource::class, DeletingTeamResource::class);
        $this->app?->bind(TicketResource::class, DeletingTicketResource::class);

        Team::query()->insert(['id' => 102, 'name' => 'Ops']);
        Ticket::query()->insert([
            ['id' => 103, 'team_id' => 101, 'title' => 'Broken screen', 'status' => 'open'],
            ['id' => 104, 'team_id' => 102, 'title' => 'Slow network', 'status' => 'open'],
            ['id' => 105, 'team_id' => 101, 'title' => 'Hidden', 'status' => 'open'],
        ]);
        Loan::query()->insert(['id' => 103, 'book_id' => 102, 'borrower' => 'Hidden']);

        Event::listen(['eloquent.deleting: *', 'eloquent.deleted: *'], function (string $event, array $models): void {
            TracingPolicy::$trace[] = 'model '.substr($event, 9, (int) strpos($event, ':') - 9).' '.TracingPolicy::name($models[0]);
        });
        Event::listen(ResourceMutated::class, function (ResourceMutated $event): void {
            TracingPolicy::$trace[] = 'event '.json_encode($event->operations);
        });
        TracingPolicy::$trace = [];
    }

    #[Test]
    public function it_deletes_the_children_that_the_entries_of_a_to_many_relation_name(): void
    {
        $response = $this->mutate('/api/teams/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [
            ['operation' => 'update', 'key' => 101, 'attributes' => ['status' => 'closed']],
            ['operation' => 'delete', 'key' => [103]],
            ['operation' => 'delete', 'key' => 102],
        ]]]]);

        $operations = ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [
            ['operation' => 'update', 'key' => 101],
            ['operation' => 'delete', 'key' => [103]],
            ['operation' => 'delete', 'key' => 102],
        ]]];
        $response->assertOk();
        $response->assertExactJson(['operations' => [$operations]]);
        $this->assertSame([
            'begin',
            'policy update Team(Support) []',
            'policy update Ticket(Printer jam) {"status":"closed","team_id":101}',
            'policy delete Ticket(Broken screen)',
            'policy delete Ticket(Password reset)',
            'hook mutatingAll update 101, delete [103], delete 102',
            'hook mutating Team(Support)',
            'saved Team(Support)',
            'hook mutating Ticket(Printer jam)',
            'saved Ticket(Printer jam)',
            'hook mutated Ticket(Printer jam)',
            'model deleting Ticket(Broken screen)',
            'model deleted Ticket(Broken screen)',
            'model deleting Ticket(Password reset)',
            'model deleted Ticket(Password reset)',
            'hook mutated Team(Support)',
            'hook mutatedAll '.json_encode([$operations]),
            'commit',
            'event '.json_encode([$operations]),
        ], array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => ! str_starts_with($line, 'sql '))));
        $this->assertSame(['Hidden open', 'Printer jam closed', 'Slow network open'], self::tickets());
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $entry
     * @param  list<string>  $rows
     */
    #[Test]
    #[DataProvider('childDeletes')]
    public function it_deletes_a_child_of_each_relation_to_one_or_many(string $relation, array $entry, string $table, array $rows): void
    {
        $response = $this->mutate('/api/books/mutate', [['operation' => 'update', 'key' => 101, 'relations' => [$relation => $entry]]]);

        $response->assertOk();
        $response->assertExactJson(['operations' => [['operation' => 'update', 'key' => 101, 'relations' => [$relation => $entry]]]]);
        $this->assertSame($rows, self::rows($table));
    }

    /** @return array<string, array{string, array<string, mixed>|list<array<string, mixed>>, string, list<string>}> */
    public static function childDeletes(): array
    {
        $loans = ['Loan(Grace) -', 'Loan(Hidden) Book(Emma)'];
        $labels = ['Label(loose) - -'];

        return [
            'has one' => ['loan', ['operation' => 'delete', 'key' => 101], 'loans', $loans],
            'has one of many' => ['latestLoan', ['operation' => 'delete', 'key' => 101], 'loans', $loans],
            'morph one' => ['label', ['operation' => 'delete', 'key' => 101], 'labels', $labels],
            'morph one of many' => ['latestLabel', ['operation' => 'delete', 'key' => 101], 'labels', $labels],
            'morph many, one key' => ['labels', [['operation' => 'delete', 'key' => 101]], 'labels', $labels],
            'morph many, a key list' => ['labels', [['operation' => 'delete', 'key' => [101]]], 'labels', $labels],
        ];
    }

    #[Test]
    public function it_frees_a_relation_to_one_for_a_later_entry(): void
    {
        $response = $this->mutate('/api/books/mutate', [
            ['operation' => 'update', 'key' => 101, 'relations' => ['loan' => ['operation' => 'delete', 'key' => 101]]],
            ['operation' => 'update', 'key' => 101, 'relations' => ['loan' => ['operation' => 'attach', 'key' => 102]]],
        ]);

        $response->assertOk();
        $this->assertSame(['Loan(Grace) Book(Dune)', 'Loan(Hidden) Book(Emma)'], self::rows('loans'));
    }

    /** @param list<array<string, mixed>> $mutate */
    #[Test]
    #[DataProvider('refusedOperations')]
    public function it_refuses_a_delete_where_the_relation_does_not_take_it(string $uri, array $mutate, string $key): void
    {
        $this->app?->bind(BookResource::class, ChecksBookResource::class);

        $response = $this->mutate($uri, $mutate);

        $this->assertExactErrors($response, [$key => ["The selected {$key} is invalid."]]);
        $this->assertSame([], self::writes());
    }

    /** @return array<string, array{string, list<array<string, mixed>>, string}> */
    public static function refusedOperations(): array
    {
        return [
            'a relation that does not opt in' => ['/api/books/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['loan' => ['operation' => 'delete', 'key' => 101]]]], 'mutate.0.relations.loan.operation'],
            'a root entry' => ['/api/books/mutate', [['operation' => 'delete', 'key' => 101]], 'mutate.0.operation'],
            'a many-to-many relation' => ['/api/books/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['shelves' => [['operation' => 'delete', 'key' => [101]]]]]], 'mutate.0.relations.shelves.0.operation'],
            'a belongs to relation' => ['/api/loans/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['book' => ['operation' => 'delete', 'key' => 101]]]], 'mutate.0.relations.book.operation'],
        ];
    }

    #[Test]
    public function it_refuses_attributes_and_relations_in_a_delete_entry(): void
    {
        $response = $this->mutate('/api/teams/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [
            ['operation' => 'delete', 'key' => 102, 'attributes' => ['status' => 'closed']],
            ['operation' => 'delete', 'key' => 103, 'relations' => ['team' => ['operation' => 'attach', 'key' => 102]]],
        ]]]]);

        $this->assertExactErrors($response, [
            'mutate.0.relations.tickets.0.attributes' => ['The mutate.0.relations.tickets.0.attributes field is prohibited when mutate.0.relations.tickets.0.operation is delete.'],
            'mutate.0.relations.tickets.1.relations' => ['The mutate.0.relations.tickets.1.relations field is prohibited when mutate.0.relations.tickets.1.operation is delete.'],
        ]);
        $this->assertSame([], self::writes());
    }

    /** @param list<array<string, mixed>> $mutate */
    #[Test]
    #[DataProvider('unreachableChildren')]
    public function it_refuses_a_key_that_is_not_a_reachable_child_of_the_parent(string $uri, array $mutate, string $key): void
    {
        $response = $this->mutate($uri, $mutate);

        $this->assertExactErrors($response, [$key => ["The selected {$key} is invalid."]]);
        $this->assertSame([], self::writes());
        $this->assertSame(self::UNTOUCHED, self::tickets());
    }

    /** @return array<string, array{string, list<array<string, mixed>>, string}> */
    public static function unreachableChildren(): array
    {
        return [
            'a child of another parent' => ['/api/teams/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'delete', 'key' => [103, 104]]]]]], 'mutate.0.relations.tickets.0.key'],
            'a child outside the perimeter' => ['/api/teams/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'delete', 'key' => 105]]]]], 'mutate.0.relations.tickets.0.key'],
            'a row under a created parent' => ['/api/teams/mutate', [['operation' => 'create', 'attributes' => ['name' => 'Desk'], 'relations' => ['tickets' => [['operation' => 'delete', 'key' => 103]]]]], 'mutate.0.relations.tickets.0.key'],
            'a child of a relation to one outside the perimeter' => ['/api/books/mutate', [['operation' => 'update', 'key' => 102, 'relations' => ['loan' => ['operation' => 'delete', 'key' => 103]]]], 'mutate.0.relations.loan.key'],
            'a child that an earlier entry moves' => ['/api/teams/mutate', [
                ['operation' => 'update', 'key' => 102, 'relations' => ['tickets' => [['operation' => 'attach', 'key' => 103]]]],
                ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'delete', 'key' => 103]]]],
            ], 'mutate.1.relations.tickets.0.key'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $mutate
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('repeatedRows')]
    public function it_refuses_a_deleted_row_that_another_entry_names(array $mutate, array $errors): void
    {
        $response = $this->mutate('/api/teams/mutate', $mutate);

        $this->assertExactErrors($response, $errors);
        $this->assertSame([], self::writes());
    }

    /** @return array<string, array{list<array<string, mixed>>, array<string, list<string>>}> */
    public static function repeatedRows(): array
    {
        return [
            'updated, then deleted' => [[['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [
                ['operation' => 'update', 'key' => 103, 'attributes' => ['status' => 'closed']],
                ['operation' => 'delete', 'key' => [103]],
            ]]]], ['mutate.0.relations.tickets.1.key' => ['The mutate.0.relations.tickets.1.key field has a duplicate value.']]],
            'deleted, then updated' => [[
                ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'delete', 'key' => 103]]]],
                ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'update', 'key' => 103, 'attributes' => ['status' => 'closed']]]]],
            ], [
                'mutate.0.relations.tickets.0.key' => ['The mutate.0.relations.tickets.0.key field has a duplicate value.'],
                'mutate.1.relations.tickets.0.key' => ['The selected mutate.1.relations.tickets.0.key is invalid.'],
            ]],
            'deleted twice' => [[
                ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'delete', 'key' => 103]]]],
                ['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'delete', 'key' => 103]]]],
            ], [
                'mutate.0.relations.tickets.0.key' => ['The mutate.0.relations.tickets.0.key field has a duplicate value.'],
                'mutate.1.relations.tickets.0.key' => ['The selected mutate.1.relations.tickets.0.key is invalid.'],
            ]],
            'twice in one key list' => [
                [['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'delete', 'key' => [103, 103]]]]]],
                ['mutate.0.relations.tickets.0.key' => ['The selected mutate.0.relations.tickets.0.key is invalid.']],
            ],
        ];
    }

    #[Test]
    public function it_counts_each_deleted_key_as_an_entry_of_the_relation(): void
    {
        config(['rest.limits.sizes.relation_entries' => 2]);

        $response = $this->mutate('/api/teams/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [
            ['operation' => 'delete', 'key' => [102, 103]],
            ['operation' => 'update', 'key' => 101, 'attributes' => ['status' => 'closed']],
        ]]]]);

        $this->assertExactErrors($response, ['mutate.0.relations.tickets' => ['The mutate.0.relations.tickets field goes over the limit of 2 entries, each deleted key counting as one entry.']]);
        $this->assertSame([], self::writes());
    }

    #[Test]
    public function it_accepts_deleted_keys_up_to_the_limit_of_the_relation(): void
    {
        config(['rest.limits.sizes.relation_entries' => 2]);

        $response = $this->mutate('/api/teams/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'delete', 'key' => [102, 103]]]]]]);

        $response->assertOk();
        $this->assertSame(['Hidden open', 'Printer jam open', 'Slow network open'], self::tickets());
    }

    #[Test]
    public function it_refuses_a_child_that_its_delete_policy_refuses(): void
    {
        ChecksPolicy::$refused = ['delete Ticket(Broken screen)'];

        $response = $this->mutate('/api/teams/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [
            ['operation' => 'update', 'key' => 101, 'attributes' => ['status' => 'closed']],
            ['operation' => 'delete', 'key' => [102, 103]],
        ]]]]);

        $response->assertForbidden();
        $response->assertExactJson(['message' => 'Refused: delete Ticket(Broken screen).', 'denials' => [
            ['path' => 'mutate.0.relations.tickets.1', 'ability' => 'delete', 'key' => 103, 'message' => 'Refused: delete Ticket(Broken screen).'],
        ]]);
        $this->assertSame([], self::writes());
        $this->assertSame(self::UNTOUCHED, self::tickets());
    }

    /**
     * @param  list<array<string, mixed>>  $mutate
     * @param  list<array<string, mixed>>  $denials
     * @param  list<string>  $policies
     */
    #[Test]
    #[DataProvider('refusedLinks')]
    public function it_checks_the_link_of_a_deleted_child_on_the_parent(string $uri, array $mutate, string $refused, array $denials, array $policies): void
    {
        ChecksPolicy::$refused = [$refused];

        $response = $this->mutate($uri, $mutate);

        $response->assertForbidden();
        $response->assertExactJson(['message' => 'Refused: '.$refused.'.', 'denials' => $denials]);
        $this->assertSame($policies, self::policies());
        $this->assertSame([], self::writes());
    }

    /** @return array<string, array{string, list<array<string, mixed>>, string, list<array<string, mixed>>, list<string>}> */
    public static function refusedLinks(): array
    {
        return [
            'update on the parent' => [
                '/api/teams/mutate',
                [['operation' => 'update', 'key' => 101, 'relations' => ['tickets' => [['operation' => 'delete', 'key' => [103]]]]]],
                'update Team(Support) []',
                [
                    ['path' => 'mutate.0', 'ability' => 'update', 'key' => 101, 'message' => 'Refused: update Team(Support) [].'],
                    ['path' => 'mutate.0.relations.tickets.0', 'ability' => 'update', 'key' => 103, 'message' => 'Refused: update Team(Support) [].'],
                ],
                ['policy update Team(Support) []', 'policy delete Ticket(Broken screen)'],
            ],
            'the detach ability of the relation' => [
                '/api/books/mutate',
                [['operation' => 'update', 'key' => 101, 'relations' => ['labels' => [['operation' => 'delete', 'key' => 101]]]]],
                'detachLabel Book(Dune) Label(classic)',
                [['path' => 'mutate.0.relations.labels.0', 'ability' => 'detachLabel', 'key' => 101, 'message' => 'Refused: detachLabel Book(Dune) Label(classic).']],
                ['policy update Book(Dune) []', 'policy delete Label(classic)', 'policy detachLabel Book(Dune) Label(classic)'],
            ],
        ];
    }

    /** @return list<string> */
    private static function tickets(): array
    {
        return Ticket::query()
            ->orderBy('title')
            ->get()
            ->map(fn (Ticket $ticket): string => $ticket->getAttribute('title').' '.$ticket->getAttribute('status'))
            ->all();
    }

    /** @return list<string> */
    private static function policies(): array
    {
        return array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => str_starts_with($line, 'policy ')));
    }
}
