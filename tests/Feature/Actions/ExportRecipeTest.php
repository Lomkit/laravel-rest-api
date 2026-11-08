<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ExportTicketController;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

class ExportRecipeTest extends HelpdeskTestCase
{
    private const string EXPORT = '3e1f6a2b-7c4d-4e5f-9a0b-1c2d3e4f5a6b';

    private const string BATCH = '9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f';

    #[\Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('queue.batching.database', $app['config']->get('database.default'));
    }

    #[\Override]
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../../Support/Database/migrations/2023_08_16_095030_create_job_batches_table.php');
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('exported-tickets', ExportTicketController::class)
            ->withBatchProgress()
            ->register();
        Storage::fake('exports')->buildTemporaryUrlsUsing(
            fn (string $path, DateTimeInterface $expiration): string => "https://files.test/{$path}?expires={$expiration->getTimestamp()}",
        );
        $this->travelTo(CarbonImmutable::parse('2026-12-01 10:00:00'));
        Str::createUuidsUsingSequence([Uuid::fromString(self::EXPORT), Uuid::fromString(self::BATCH)]);
        Team::create(['id' => 1, 'name' => 'Support']);
        Team::create(['id' => 2, 'name' => 'Billing']);

        foreach ([
            8 => [1, 'Printer jam, floor 2', 'open'],
            9 => [1, 'Password reset', 'open'],
            10 => [1, 'Laptop setup', 'closed'],
            11 => [2, 'VPN access', 'open'],
            12 => [1, 'Monitor flicker', 'open'],
            13 => [1, 'Badge reader', 'open'],
            14 => [1, 'Desk phone', 'open'],
        ] as $id => [$team, $title, $status]) {
            Ticket::create(['id' => $id, 'team_id' => $team, 'title' => $title, 'status' => $status]);
        }
    }

    #[Test]
    public function it_exports_the_rows_of_the_search_in_the_picked_columns_to_one_file(): void
    {
        $response = $this->postJson('/exported-tickets/actions/export-tickets', [
            'fields' => [['name' => 'columns', 'value' => ['title', 'id']]],
            'search' => ['filters' => [['field' => 'status', 'value' => 'open']]],
        ]);

        $response->assertOk()->assertExactJson(['data' => [
            'impacted' => 5,
            'batch' => self::BATCH,
            'result' => ['type' => 'download', 'url' => 'https://files.test/'.self::EXPORT.'.csv?expires=1796205600', 'name' => 'tickets.csv'],
        ]]);
        $this->assertSame([self::EXPORT.'.csv'], Storage::disk('exports')->allFiles());
        $this->assertSame(
            "title,id\n\"Printer jam, floor 2\",8\n\"Password reset\",9\n\"Monitor flicker\",12\n\"Badge reader\",13\n\"Desk phone\",14\n",
            Storage::disk('exports')->get(self::EXPORT.'.csv'),
        );
    }

    #[Test]
    public function it_refuses_a_column_outside_the_allowed_list(): void
    {
        $response = $this->postJson('/exported-tickets/actions/export-tickets', [
            'fields' => [['name' => 'columns', 'value' => ['title', 'team_id']]],
        ]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected columns is invalid.',
            'errors' => ['fields.columns' => ['The selected columns is invalid.']],
        ]);
        $this->assertSame([], Storage::disk('exports')->allFiles());
    }

    #[Test]
    public function it_answers_a_message_when_no_row_matches(): void
    {
        $response = $this->postJson('/exported-tickets/actions/export-tickets', [
            'fields' => [['name' => 'columns', 'value' => ['id']]],
            'search' => ['filters' => [['field' => 'status', 'value' => 'archived']]],
        ]);

        $response->assertOk()->assertExactJson(['data' => [
            'impacted' => 0,
            'batch' => self::BATCH,
            'result' => ['type' => 'message', 'message' => 'No rows to export.'],
        ]]);
        $this->assertSame([], Storage::disk('exports')->allFiles());
    }
}
