<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class IncludeAliasCopiesTest extends PackageTestCase
{
    use SeedsPublishingRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPublishingRows();
    }

    #[Test]
    public function it_loads_two_aliases_of_one_relation_with_their_own_filters_and_limits(): void
    {
        $this->assertAuthors([
            ['relation' => 'posts', 'alias' => 'popular', 'filters' => [['field' => 'views', 'operator' => '>', 'value' => 10]], 'limit' => 10, 'selects' => [['field' => 'id']]],
            ['relation' => 'posts', 'alias' => 'drafted', 'filters' => [['field' => 'status', 'value' => 'draft']], 'selects' => [['field' => 'id']]],
        ], '[{"id":1,"popular":[{"id":1}],"drafted":[{"id":2}],"@more":[]},{"id":2,"popular":[],"drafted":[],"@more":[]}]', 3);
    }

    #[Test]
    public function it_keeps_the_relation_of_the_same_name_next_to_its_alias(): void
    {
        $this->assertAuthors([
            ['relation' => 'posts', 'selects' => [['field' => 'id']]],
            ['relation' => 'posts', 'alias' => 'drafted', 'filters' => [['field' => 'status', 'value' => 'draft']], 'selects' => [['field' => 'id']]],
        ], '[{"id":1,"posts":[{"id":2},{"id":1}],"drafted":[{"id":2}],"@more":[]},{"id":2,"posts":[],"drafted":[],"@more":[]}]', 3);
    }

    #[Test]
    public function it_keeps_the_pivot_of_an_aliased_many_to_many_relation(): void
    {
        $this->assertAuthors([
            ['relation' => 'tags', 'alias' => 'topics', 'selects' => [['field' => 'name']]],
        ], '[{"id":1,"topics":[{"name":"Mathematics","pivot":{}}],"@more":[]},{"id":2,"topics":[],"@more":[]}]', 2);
    }

    #[Test]
    public function it_loads_aliases_at_every_depth(): void
    {
        $this->assertAuthors([
            [
                'relation' => 'posts',
                'alias' => 'drafted',
                'filters' => [['field' => 'status', 'value' => 'draft']],
                'selects' => [['field' => 'id']],
                'includes' => [[
                    'relation' => 'author',
                    'alias' => 'writer',
                    'selects' => [['field' => 'id']],
                    'includes' => [['relation' => 'posts', 'alias' => 'others', 'selects' => [['field' => 'id']], 'sorts' => [['field' => 'id']]]],
                ]],
            ],
        ], '[{"id":1,"drafted":[{"id":2,"writer":{"id":1,"others":[{"id":1},{"id":2}],"@more":[]}}],"@more":[]},{"id":2,"drafted":[],"@more":[]}]');
    }

    #[Test]
    public function it_fires_one_retrieved_event_per_row_the_query_returns(): void
    {
        $retrieved = 0;
        Event::listen('eloquent.retrieved: '.Author::class, function () use (&$retrieved): void {
            $retrieved++;
        });

        $this->postJson('/api/authors/search', ['search' => [
            'includes' => [['relation' => 'posts', 'alias' => 'drafted', 'filters' => [['field' => 'status', 'value' => 'draft']]]],
        ]])->assertOk();

        $this->assertSame(2, $retrieved);
    }

    /** @param list<array<string, mixed>> $includes */
    private function assertAuthors(array $includes, string $data, ?int $queries = null): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/api/authors/search', ['search' => [
            'includes' => $includes,
            'selects' => [['field' => 'id']],
            'sorts' => [['field' => 'first_name']],
        ]]);

        $response->assertOk();
        $this->assertSame(
            '{"current_page":1,"data":'.$data.',"from":1,"last_page":1,"per_page":50,"to":2,"total":2,"meta":{}}',
            $response->getContent(),
        );

        if ($queries !== null) {
            $this->assertCount($queries, DB::getQueryLog());
        }
    }
}
