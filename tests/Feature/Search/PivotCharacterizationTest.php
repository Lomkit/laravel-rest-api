<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class PivotCharacterizationTest extends PackageTestCase
{
    use ComparesSearchSnapshots;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-01-02 00:00:00');

        Team::create(['id' => 1, 'name' => 'Billing']);
        Team::create(['id' => 2, 'name' => 'Support']);
        $jam = Ticket::create(['id' => 1, 'team_id' => 1, 'title' => 'Printer jam', 'status' => 'open']);
        $toner = Ticket::create(['id' => 2, 'team_id' => 1, 'title' => 'Printer toner', 'status' => 'open']);
        $reset = Ticket::create(['id' => 3, 'team_id' => 2, 'title' => 'Password reset', 'status' => 'closed']);
        Tag::create(['id' => 1, 'name' => 'Hardware', 'created_at' => '2026-01-01 09:00:00']);
        Tag::create(['id' => 2, 'name' => 'Network', 'created_at' => '2026-05-01 09:00:00']);

        $jam->tags()->attach(1, ['position' => 1, 'weight' => 1]);
        $toner->tags()->attach(1, ['position' => 3, 'weight' => 1]);
        $reset->tags()->attach(2, ['position' => 2, 'weight' => 1]);
        $jam->labels()->attach(2, ['position' => null, 'weight' => 1, 'created_at' => '2026-02-01 09:00:00']);
        $toner->labels()->attach(2, ['position' => 2, 'weight' => 1, 'created_at' => '2026-04-01 09:00:00']);
        $reset->labels()->attach(1, ['position' => 3, 'weight' => 1, 'created_at' => '2026-04-01 09:00:00']);
    }

    #[Test]
    public function it_keeps_the_results_and_queries_of_a_pivot_path_filter(): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/api/tickets/search', ['search' => ['filters' => [
            ['field' => 'tags.pivot.position', 'operator' => 'between', 'value' => [1, 2]],
        ]]]);

        $response->assertOk();
        $this->assertMatchesSnapshot('pivot-path', $response);
    }
}
