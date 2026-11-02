<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RelationGroupRelationsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Team::create(['id' => 1, 'name' => 'Billing']);
        Team::create(['id' => 2, 'name' => 'Support']);
        $jam = Ticket::create(['id' => 1, 'team_id' => 1, 'title' => 'Printer jam', 'status' => 'open']);
        $toner = Ticket::create(['id' => 2, 'team_id' => 1, 'title' => 'Printer toner', 'status' => 'open']);
        $reset = Ticket::create(['id' => 3, 'team_id' => 2, 'title' => 'Password reset', 'status' => 'closed']);
        Ticket::create(['id' => 4, 'team_id' => 2, 'title' => 'New laptop', 'status' => 'open']);
        Tag::create(['id' => 1, 'name' => 'Hardware']);
        Tag::create(['id' => 2, 'name' => 'Network']);

        $jam->tags()->attach(1, ['position' => 1, 'weight' => 1]);
        $toner->tags()->attach(1, ['position' => 3, 'weight' => 1]);
        $reset->tags()->attach(2, ['position' => 2, 'weight' => 1]);
    }

    #[Test]
    public function it_filters_through_a_many_to_many_relation(): void
    {
        $response = $this->postJson('/api/tickets/search', ['search' => [
            'filters' => [
                ['relation' => 'tags', 'nested' => [['field' => 'name', 'value' => 'Hardware']]],
                ['type' => 'or', 'relation' => 'tags', 'exists' => false],
            ],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.id', [1, 2, 4]);
    }

    #[Test]
    public function it_reaches_a_pivot_field_through_a_relation_in_the_group(): void
    {
        $response = $this->postJson('/api/teams/search', ['search' => ['filters' => [
            ['relation' => 'tickets', 'nested' => [['field' => 'tags.pivot.position', 'operator' => '>=', 'value' => 3]]],
        ]]]);

        $response->assertOk()->assertJsonPath('data.*.name', ['Billing']);
    }

    #[Test]
    public function it_refuses_a_pivot_field_of_the_relation_of_the_group(): void
    {
        $response = $this->postJson('/api/tickets/search', ['search' => ['filters' => [
            ['relation' => 'tags', 'nested' => [['field' => 'pivot.position', 'value' => 1]]],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.filters.0.nested.0.field' => ['The selected search.filters.0.nested.0.field is invalid.'],
        ]);
    }

    #[Test]
    public function it_filters_the_includes_of_a_search_with_a_relation_group(): void
    {
        $response = $this->postJson('/api/teams/search', ['search' => [
            'sorts' => [['field' => 'id']],
            'includes' => [['relation' => 'tickets', 'filters' => [['relation' => 'tags', 'exists' => false]]]],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.tickets.*.title', ['New laptop']);
    }

    #[Test]
    public function it_filters_an_aggregate_with_a_relation_group(): void
    {
        $response = $this->postJson('/api/teams/search', ['search' => [
            'sorts' => [['field' => 'id']],
            'aggregates' => [['relation' => 'tickets', 'type' => 'count', 'filters' => [
                ['relation' => 'tags', 'nested' => [['field' => 'name', 'value' => 'Hardware']]],
            ]]],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.tickets_count', [2, 0]);
    }
}
