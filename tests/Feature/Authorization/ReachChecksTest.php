<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ReachChecksTest extends HelpdeskTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->renderDenials();
    }

    /** @return array<string, array{string, string, string}> */
    public static function dottedIncludeLevels(): array
    {
        return [
            'first level' => ['teams', 'Teams are not listed.', 'team.replies'],
            'second level' => ['replies', 'Replies are not listed.', 'team.replies'],
            'third level' => ['tags', 'Tags are not listed.', 'team.tickets.tags'],
        ];
    }

    #[Test]
    public function it_refuses_an_include_of_a_resource_the_user_cannot_list(): void
    {
        $this->teams->answers['viewAny'] = Response::deny('Teams are not listed.');
        $this->logQueries();

        $this->postJson('/api/tickets/search', ['search' => ['includes' => [['relation' => 'team']]]])
            ->assertForbidden()
            ->assertExactJson($this->denied('Teams are not listed.', 'search.includes.0'));

        $this->assertNoQueries();
        $this->assertSame([['viewAny'], ['viewAny']], [$this->tickets->calls, $this->teams->calls]);
    }

    #[Test]
    #[DataProvider('dottedIncludeLevels')]
    public function it_checks_each_level_of_a_dotted_include(string $policy, string $message, string $include): void
    {
        $this->{$policy}->answers['viewAny'] = Response::deny($message);
        $this->logQueries();

        $this->postJson('/api/tickets/search', ['search' => ['includes' => [['relation' => $include]]]])
            ->assertForbidden()
            ->assertExactJson($this->denied($message, 'search.includes.0'));

        $this->assertNoQueries();
        $this->assertSame(['viewAny'], $this->{$policy}->calls);
    }

    #[Test]
    public function it_refuses_an_aggregate_on_a_resource_the_user_cannot_list(): void
    {
        $this->tags->answers['viewAny'] = Response::deny('Tags are not listed.');
        $this->logQueries();

        $this->postJson('/api/tickets/search', ['search' => ['aggregates' => [['relation' => 'tags', 'type' => 'count']]]])
            ->assertForbidden()
            ->assertExactJson($this->denied('Tags are not listed.', 'search.aggregates.0'));

        $this->assertNoQueries();
    }

    #[Test]
    public function it_refuses_a_filter_on_a_resource_the_user_cannot_list(): void
    {
        $this->teams->answers['viewAny'] = Response::deny('Teams are not listed.');
        $this->logQueries();

        $this->postJson('/api/tickets/search', ['search' => ['filters' => [['field' => 'team.name', 'value' => 'Billing']]]])
            ->assertForbidden()
            ->assertExactJson($this->denied('Teams are not listed.', 'search.filters.0'));

        $this->assertNoQueries();
    }

    #[Test]
    public function it_refuses_an_undeclared_field_of_a_resource_the_user_cannot_list(): void
    {
        $this->teams->answers['viewAny'] = Response::deny('Teams are not listed.');
        $this->logQueries();

        $this->postJson('/api/tickets/search', ['search' => ['filters' => [['field' => 'team.budget', 'value' => 1]]]])
            ->assertForbidden()
            ->assertExactJson($this->denied('Teams are not listed.', 'search.filters.0'));

        $this->assertNoQueries();
    }

    #[Test]
    public function it_refuses_a_filter_on_a_pivot_column_of_a_resource_the_user_cannot_list(): void
    {
        $this->tags->answers['viewAny'] = Response::deny('Tags are not listed.');
        $this->logQueries();

        $this->postJson('/api/tickets/search', ['search' => ['filters' => [['field' => 'tags.pivot.position', 'value' => 1]]]])
            ->assertForbidden()
            ->assertExactJson($this->denied('Tags are not listed.', 'search.filters.0'));

        $this->assertNoQueries();
    }

    #[Test]
    public function it_refuses_a_nested_filter_on_a_resource_the_user_cannot_list(): void
    {
        $this->teams->answers['viewAny'] = Response::deny('Teams are not listed.');
        $this->logQueries();

        $this->postJson('/api/tickets/search', ['search' => ['filters' => [
            ['field' => 'status', 'value' => 'open'],
            ['nested' => [['field' => 'title', 'value' => 'Printer jam'], ['field' => 'team.name', 'value' => 'Billing']]],
        ]]])
            ->assertForbidden()
            ->assertExactJson($this->denied('Teams are not listed.', 'search.filters.1.nested.1'));

        $this->assertNoQueries();
    }

    #[Test]
    public function it_refuses_the_search_of_an_action_on_a_resource_the_user_cannot_list(): void
    {
        $this->teams->answers['viewAny'] = Response::deny('Teams are not listed.');
        $this->logQueries();

        $this->postJson('/api/tickets/actions/close-tickets', ['search' => ['filters' => [['field' => 'team.name', 'value' => 'Billing']]]])
            ->assertForbidden()
            ->assertExactJson($this->denied('Teams are not listed.', 'search.filters.0'));

        $this->assertNoQueries();
    }

    #[Test]
    public function it_lists_every_denial_of_the_request(): void
    {
        $this->teams->answers['viewAny'] = Response::deny('Teams are not listed.');
        $this->tags->answers['viewAny'] = Response::denyWithStatus(423, 'Tags are being merged.');

        $this->postJson('/api/tickets/search', ['search' => [
            'filters' => [['field' => 'tags.name', 'value' => 'Hardware']],
            'includes' => [['relation' => 'team']],
        ]])->assertStatus(423)->assertExactJson([
            'message' => 'Tags are being merged.',
            'denials' => [
                ['path' => 'search.filters.0', 'ability' => 'viewAny', 'message' => 'Tags are being merged.'],
                ['path' => 'search.includes.0', 'ability' => 'viewAny', 'message' => 'Teams are not listed.'],
            ],
        ]);
    }

    #[Test]
    public function it_checks_each_resource_once_however_often_the_request_reaches_it(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $jam->tags()->attach(Tag::create(['name' => 'Hardware']), ['position' => 1]);

        $this->postJson('/api/tickets/search', ['search' => [
            'filters' => [['field' => 'team.name', 'value' => 'Billing'], ['field' => 'tags.pivot.position', 'value' => 1]],
            'includes' => [['relation' => 'team'], ['relation' => 'tags'], ['relation' => 'team.tickets.tags']],
            'aggregates' => [['relation' => 'tags', 'type' => 'count']],
        ]])->assertOk()->assertJsonPath('data.0.id', $jam->id)->assertJsonPath('total', 1);

        $this->assertSame([['viewAny'], ['viewAny'], ['viewAny']], [$this->tickets->calls, $this->teams->calls, $this->tags->calls]);
    }

    /** @return array{message: string, denials: list<array{path: string, ability: string, message: string}>} */
    private function denied(string $message, string $path): array
    {
        return ['message' => $message, 'denials' => [['path' => $path, 'ability' => 'viewAny', 'message' => $message]]];
    }
}
