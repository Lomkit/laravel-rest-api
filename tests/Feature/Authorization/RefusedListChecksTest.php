<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use PHPUnit\Framework\Attributes\Test;

class RefusedListChecksTest extends HelpdeskTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->teams->answers['viewAny'] = Response::deny('Teams are not listed.');
    }

    #[Test]
    public function it_checks_no_relation_named_in_a_list_over_its_limit(): void
    {
        config(['rest.limits.includes' => 2]);
        $message = 'The search.includes field must not have more than 2 items.';
        $this->logQueries();

        $this->postJson('/api/tickets/search', ['search' => ['includes' => [
            ['relation' => 'team'],
            ['relation' => 'team'],
            ['relation' => 'team'],
        ]]])
            ->assertUnprocessable()
            ->assertExactJson(['message' => $message, 'errors' => ['search.includes' => [$message]]]);

        $this->assertSame([], $this->teams->calls);
        $this->assertNoQueries();
    }

    #[Test]
    public function it_checks_no_relation_below_a_group_nested_too_deep(): void
    {
        config(['rest.search.max_nesting_depth' => 1]);
        $message = 'The search.filters.0.nested.0.nested field is prohibited.';

        $this->postJson('/api/tickets/search', ['search' => ['filters' => [
            ['nested' => [['nested' => [['field' => 'team.name', 'value' => 'Billing']]]]],
        ]]])
            ->assertUnprocessable()
            ->assertExactJson(['message' => $message, 'errors' => ['search.filters.0.nested.0.nested' => [$message]]]);

        $this->assertSame([], $this->teams->calls);
    }

    #[Test]
    public function it_checks_the_relations_of_the_lists_that_are_read(): void
    {
        config(['rest.limits.selects' => 1]);

        $this->postJson('/api/tickets/search', ['search' => [
            'selects' => [['field' => 'id'], ['field' => 'title']],
            'includes' => [['relation' => 'team']],
        ]])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Teams are not listed.']);
    }
}
