<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Relations\Stubs\WeightedTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PivotPathFiltersTest extends PackageTestCase
{
    /** @var array<string, Model> */
    private array $records = [];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('weighted-tickets', WeightedTicketController::class)->register();

        $billing = $this->records['Billing'] = Team::create(['name' => 'Billing']);
        $support = $this->records['Support'] = Team::create(['name' => 'Support']);
        $jam = $this->records['Printer jam'] = Ticket::create(['team_id' => $billing->id, 'title' => 'Printer jam', 'status' => 'open']);
        $toner = $this->records['Printer toner'] = Ticket::create(['team_id' => $billing->id, 'title' => 'Printer toner', 'status' => 'open']);
        $reset = $this->records['Password reset'] = Ticket::create(['team_id' => $support->id, 'title' => 'Password reset', 'status' => 'closed']);
        $hardware = $this->records['Hardware'] = Tag::create(['name' => 'Hardware', 'created_at' => '2026-01-01 09:00:00']);
        $network = $this->records['Network'] = Tag::create(['name' => 'Network', 'created_at' => '2026-05-01 09:00:00']);

        $jam->tags()->attach($hardware, ['position' => 1, 'weight' => 1]);
        $toner->tags()->attach($hardware, ['position' => 3, 'weight' => 1]);
        $reset->tags()->attach($network, ['position' => 2, 'weight' => 1]);
        $jam->labels()->attach($network, ['position' => null, 'weight' => 1, 'created_at' => '2026-02-01 09:00:00']);
        $toner->labels()->attach($network, ['position' => 2, 'weight' => 1, 'created_at' => '2026-04-01 09:00:00']);
        $reset->labels()->attach($hardware, ['position' => 3, 'weight' => 1, 'created_at' => '2026-04-01 09:00:00']);
    }

    /** @return array<string, array{string, array<string, mixed>, list<string>}> */
    public static function pivotFilters(): array
    {
        return [
            'a belongs to many relation' => ['/api/tickets', ['field' => 'tags.pivot.position', 'operator' => 'between', 'value' => [1, 2]], ['Printer jam', 'Password reset']],
            'a morph to many relation' => ['/api/tickets', ['field' => 'labels.pivot.position', 'value' => 2], ['Printer toner']],
            'a nullable pivot field' => ['/api/tickets', ['field' => 'labels.pivot.position', 'operator' => 'is null'], ['Printer jam']],
            'a morphed by many relation' => ['/api/tags', ['field' => 'labelledTickets.pivot.position', 'operator' => 'not in', 'value' => [2]], ['Hardware']],
            'a nested relation' => ['/api/teams', ['field' => 'tickets.tags.pivot.position', 'operator' => '>=', 'value' => 3], ['Billing']],
            'a column the related table also has' => ['/weighted-tickets', ['field' => 'labels.pivot.created_at', 'operator' => '>=', 'value' => '2026-03-01 00:00:00'], ['Printer toner', 'Password reset']],
        ];
    }

    /**
     * @param  array<string, mixed>  $filter
     * @param  list<string>  $matches
     */
    #[Test]
    #[DataProvider('pivotFilters')]
    public function it_filters_on_a_pivot_field_through_the_pivot_table_of_its_relation(string $endpoint, array $filter, array $matches): void
    {
        $response = $this->postJson($endpoint.'/search', ['search' => ['filters' => [$filter], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', array_map(fn (string $name): mixed => $this->records[$name]->getKey(), $matches));
    }

    /** @return array<string, array{array<string, mixed>, array<string, list<string>>}> */
    public static function invalidPivotFilters(): array
    {
        return [
            'a write-only pivot field' => [['field' => 'tags.pivot.note', 'value' => 'Seen twice'], [
                'search.filters.0.field' => ['The selected search.filters.0.field is invalid.'],
            ]],
            'a pivot field equal to null' => [['field' => 'tags.pivot.position', 'value' => null], [
                'search.filters.0.value' => ['The search.filters.0.value field is required.'],
            ]],
            'is null on a pivot field that is not nullable' => [['field' => 'labels.pivot.weight', 'operator' => 'is null'], [
                'search.filters.0.operator' => ['The selected search.filters.0.operator is invalid.'],
            ]],
            'a pivot value of the wrong type' => [['field' => 'tags.pivot.weight', 'operator' => 'in', 'value' => [1, 'heavy']], [
                'search.filters.0.value.1' => ['The search.filters.0.value.1 field must be an integer.'],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $filter
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('invalidPivotFilters')]
    public function it_validates_a_pivot_filter_against_the_pivot_field(array $filter, array $errors): void
    {
        $response = $this->postJson('/weighted-tickets/search', ['search' => ['filters' => [$filter]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }
}
