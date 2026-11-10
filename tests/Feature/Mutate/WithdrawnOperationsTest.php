<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\KeptTeamController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\KeptTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class WithdrawnOperationsTest extends HelpdeskTestCase
{
    use AssertsExactErrors;

    private const string KEPT = 'field must be true: this relation does not detach rows.';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(function (): void {
            Rest::resource('kept-tickets', KeptTicketController::class)->register();
            Rest::resource('kept-teams', KeptTeamController::class)->register();
        });

        Team::query()->insert([['id' => 101, 'name' => 'Support'], ['id' => 102, 'name' => 'Ops']]);
        Ticket::query()->insert([['id' => 101, 'team_id' => 101, 'title' => 'Printer jam', 'status' => 'open'], ['id' => 102, 'team_id' => 102, 'title' => 'Password reset', 'status' => 'open']]);
        Tag::query()->insert([['id' => 101, 'name' => 'Hardware'], ['id' => 102, 'name' => 'Network']]);
        DB::table('tag_ticket')->insert(['tag_id' => 101, 'ticket_id' => 101]);
    }

    /** @param array<string, mixed> $relations */
    #[Test]
    #[DataProvider('withdrawnOperations')]
    public function it_refuses_an_operation_that_the_relation_withdraws(string $uri, array $relations, string $path): void
    {
        $rows = self::rows();

        $response = $this->postJson($uri, ['mutate' => [['operation' => 'update', 'key' => 101, 'relations' => $relations]]]);

        $this->assertExactErrors($response, [$path => ["The selected {$path} is invalid."]]);
        $this->assertSame($rows, self::rows());
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function withdrawnOperations(): array
    {
        return [
            'detach on a belongs to' => ['/api/kept-tickets/mutate', ['team' => ['operation' => 'detach', 'key' => 101]], 'mutate.0.relations.team.operation'],
            'detach on a has many' => ['/api/kept-teams/mutate', ['tickets' => [['operation' => 'detach', 'key' => [101]]]], 'mutate.0.relations.tickets.0.operation'],
            'detach on a belongs to many' => ['/api/kept-tickets/mutate', ['tags' => [['operation' => 'detach', 'key' => [101]]]], 'mutate.0.relations.tags.0.operation'],
            'toggle on a belongs to many without detach' => ['/api/kept-tickets/mutate', ['tags' => [['operation' => 'toggle', 'key' => [101, 102]]]], 'mutate.0.relations.tags.0.operation'],
            'update on a morph to many' => ['/api/kept-tickets/mutate', ['labels' => [['operation' => 'update', 'key' => [101]]]], 'mutate.0.relations.labels.0.operation'],
        ];
    }

    /** @param array<string, mixed> $flag */
    #[Test]
    #[DataProvider('detachingSyncs')]
    public function it_refuses_a_sync_that_would_detach_rows_from_a_relation_without_detach(array $flag): void
    {
        $rows = self::rows();

        $response = $this->postJson('/api/kept-tickets/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 101,
            'relations' => ['tags' => [['operation' => 'sync', 'key' => [102], ...$flag]]],
        ]]]);

        $this->assertExactErrors($response, ['mutate.0.relations.tags.0.without_detaching' => ['The mutate.0.relations.tags.0.without_detaching '.self::KEPT]]);
        $this->assertSame($rows, self::rows());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function detachingSyncs(): array
    {
        return [
            'without the flag' => [[]],
            'with the flag false' => [['without_detaching' => false]],
        ];
    }

    #[Test]
    public function it_syncs_without_detaching_on_a_relation_without_detach(): void
    {
        $response = $this->postJson('/api/kept-tickets/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 101,
            'relations' => ['tags' => [['operation' => 'sync', 'key' => [102], 'without_detaching' => true]]],
        ]]]);

        $response->assertOk();
        $this->assertSame(['101 > 101', '102 > 101'], self::tags());
    }

    #[Test]
    public function it_keeps_the_other_operations_of_the_relation(): void
    {
        $response = $this->postJson('/api/kept-teams/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => 101,
            'relations' => ['tickets' => [['operation' => 'attach', 'key' => [102]]]],
        ]]]);

        $response->assertOk();
        $this->assertSame(['Password reset > 101', 'Printer jam > 101'], self::tickets());
    }

    /** @return array{list<string>, list<string>} */
    private static function rows(): array
    {
        return [self::tickets(), self::tags()];
    }

    /** @return list<string> */
    private static function tickets(): array
    {
        return DB::table('tickets')
            ->orderBy('title')
            ->get(['title', 'team_id'])
            ->map(fn (object $ticket): string => $ticket->title.' > '.$ticket->team_id)
            ->all();
    }

    /** @return list<string> */
    private static function tags(): array
    {
        return DB::table('tag_ticket')
            ->orderBy('tag_id')
            ->get(['tag_id', 'ticket_id'])
            ->map(fn (object $row): string => $row->tag_id.' > '.$row->ticket_id)
            ->all();
    }
}
