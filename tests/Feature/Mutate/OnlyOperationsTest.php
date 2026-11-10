<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\OnlyTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class OnlyOperationsTest extends HelpdeskTestCase
{
    use AssertsExactErrors;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(fn () => Rest::resource('only-tickets', OnlyTicketController::class)->register());

        Team::query()->insert([['id' => 101, 'name' => 'Support'], ['id' => 102, 'name' => 'Ops']]);
        Ticket::query()->insert(['id' => 101, 'team_id' => 101, 'title' => 'Printer jam', 'status' => 'open']);
        Tag::query()->insert([['id' => 101, 'name' => 'Hardware'], ['id' => 102, 'name' => 'Network']]);
        DB::table('tag_ticket')->insert(['tag_id' => 101, 'ticket_id' => 101]);
    }

    /** @param array<string, mixed> $relations */
    #[Test]
    #[DataProvider('refusedOperations')]
    public function it_refuses_an_operation_that_the_relation_does_not_keep(array $relations, string $path, string $message): void
    {
        $rows = self::rows();

        $response = $this->postJson('/api/only-tickets/mutate', ['mutate' => [['operation' => 'update', 'key' => 101, 'relations' => $relations]]]);

        $this->assertExactErrors($response, [$path => [$message]]);
        $this->assertSame($rows, self::rows());
    }

    /** @return array<string, array{array<string, mixed>, string, string}> */
    public static function refusedOperations(): array
    {
        return [
            'update on a belongs to' => [['team' => ['operation' => 'update', 'key' => 101]], 'mutate.0.relations.team.operation', 'The selected mutate.0.relations.team.operation is invalid.'],
            'detach on a belongs to many' => [['tags' => [['operation' => 'detach', 'key' => [101]]]], 'mutate.0.relations.tags.0.operation', 'The selected mutate.0.relations.tags.0.operation is invalid.'],
            'toggle on a belongs to many without detach' => [['tags' => [['operation' => 'toggle', 'key' => [102]]]], 'mutate.0.relations.tags.0.operation', 'The selected mutate.0.relations.tags.0.operation is invalid.'],
            'a sync that would detach' => [['tags' => [['operation' => 'sync', 'key' => [102]]]], 'mutate.0.relations.tags.0.without_detaching', 'The mutate.0.relations.tags.0.without_detaching field must be true: this relation does not detach rows.'],
            'any entry on a read-only relation' => [['labels' => [['operation' => 'attach', 'key' => [102]]]], 'mutate.0.relations.labels', 'The mutate.0.relations.labels field is prohibited.'],
        ];
    }

    #[Test]
    public function it_runs_the_operations_that_the_relation_keeps(): void
    {
        $response = $this->postJson('/api/only-tickets/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 101,
            'relations' => [
                'team' => ['operation' => 'attach', 'key' => 102],
                'tags' => [['operation' => 'sync', 'key' => [102], 'without_detaching' => true]],
            ],
        ]]]);

        $response->assertOk();
        $this->assertSame([['Printer jam > 102'], ['101 > 101', '102 > 101']], self::rows());
    }

    /** @return array{list<string>, list<string>} */
    private static function rows(): array
    {
        return [
            DB::table('tickets')
                ->get(['title', 'team_id'])
                ->map(fn (object $ticket): string => $ticket->title.' > '.$ticket->team_id)
                ->all(),
            DB::table('tag_ticket')
                ->orderBy('tag_id')
                ->get(['tag_id', 'ticket_id'])
                ->map(fn (object $row): string => $row->tag_id.' > '.$row->ticket_id)
                ->all(),
        ];
    }
}
