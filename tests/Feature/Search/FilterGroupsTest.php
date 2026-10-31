<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\ListedAuthorController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FilterGroupsTest extends PackageTestCase
{
    /** @var list<Post> */
    private array $posts = [];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([['Annual report', 1], ['Spring launch', 2], ['Annual report', 3], ['Team offsite', 3]] as [$title, $views]) {
            $this->posts[] = Post::factory()->create(['title' => $title, 'views' => $views]);
        }
    }

    /** @return array<string, array{list<array<string, mixed>>, list<int>}> */
    public static function filterGroups(): array
    {
        return [
            'or' => [[
                ['field' => 'views', 'value' => 1],
                ['field' => 'views', 'value' => 3, 'type' => 'or'],
            ], [0, 2, 3]],
            'and' => [[
                ['field' => 'views', 'value' => 3],
                ['field' => 'title', 'value' => 'Annual report', 'type' => 'and'],
            ], [2]],
            'and by default' => [[
                ['field' => 'views', 'value' => 3],
                ['field' => 'title', 'value' => 'Team offsite'],
            ], [3]],
            'a group, then an or' => [[
                ['nested' => [['field' => 'views', 'value' => 1], ['field' => 'title', 'value' => 'Annual report']]],
                ['field' => 'views', 'value' => 2, 'type' => 'or'],
            ], [0, 1]],
            'a filter, then a group after an or' => [[
                ['field' => 'views', 'value' => 2],
                ['type' => 'or', 'nested' => [['field' => 'views', 'value' => 1], ['field' => 'title', 'value' => 'Annual report']]],
            ], [0, 1]],
            'an or that stays inside its group' => [[
                ['field' => 'title', 'value' => 'Annual report'],
                ['nested' => [['field' => 'views', 'value' => 1], ['field' => 'views', 'value' => 2, 'type' => 'or']]],
            ], [0]],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filters
     * @param  list<int>  $matches
     */
    #[Test]
    #[DataProvider('filterGroups')]
    public function it_combines_filters_with_their_type_and_their_groups(array $filters, array $matches): void
    {
        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => $filters, 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->ids($matches));
    }

    #[Test]
    public function it_keeps_an_or_filter_inside_the_perimeter_of_the_resource(): void
    {
        Rest::resource('listed-authors', ListedAuthorController::class)->register();
        $ada = Author::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        Author::factory()->create(['first_name' => 'Alan', 'last_name' => 'Hidden']);

        $response = $this->postJson('/listed-authors/search', ['search' => ['filters' => [
            ['field' => 'first_name', 'value' => 'Ada'],
            ['field' => 'first_name', 'value' => 'Alan', 'type' => 'or'],
        ]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [$ada->id]);
    }

    #[Test]
    public function it_nests_groups_up_to_the_configured_depth(): void
    {
        config(['rest.search.max_nesting_depth' => 2]);

        $response = $this->postJson('/api/posts/search', ['search' => ['sorts' => [['field' => 'id']], 'filters' => [['nested' => [
            ['nested' => [['field' => 'views', 'value' => 1], ['field' => 'title', 'value' => 'Annual report']]],
            ['type' => 'or', 'nested' => [['field' => 'views', 'value' => 3], ['field' => 'title', 'value' => 'Team offsite']]],
        ]]]]]);

        $response->assertOk()->assertJsonPath('data.*.id', $this->ids([0, 3]));
    }

    /** @return array<string, array{int, list<array<string, mixed>>, string}> */
    public static function tooDeepGroups(): array
    {
        $filter = ['field' => 'views', 'value' => 1];

        return [
            'a depth of 1' => [1, [['nested' => [['nested' => [$filter]]]]], 'search.filters.0.nested.0.nested'],
            'a depth of 2' => [2, [['nested' => [['nested' => [['nested' => [$filter]]]]]]], 'search.filters.0.nested.0.nested.0.nested'],
        ];
    }

    /** @param list<array<string, mixed>> $filters */
    #[Test]
    #[DataProvider('tooDeepGroups')]
    public function it_refuses_a_group_nested_deeper_than_the_configured_depth(int $depth, array $filters, string $key): void
    {
        config(['rest.search.max_nesting_depth' => $depth]);

        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => $filters]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [$key => ["The {$key} field is prohibited."]]);
    }

    #[Test]
    public function it_refuses_a_group_that_also_has_an_operator_and_a_value(): void
    {
        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [
            ['nested' => [['field' => 'views', 'value' => 1]], 'operator' => '=', 'value' => 99],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.filters.0.nested' => ['The search.filters.0.nested field prohibits search.filters.0.field / search.filters.0.operator / search.filters.0.value from being present.'],
        ]);
    }

    #[Test]
    public function it_refuses_a_type_other_than_and_or_or(): void
    {
        $response = $this->postJson('/api/posts/search', ['search' => ['filters' => [['field' => 'views', 'value' => 1, 'type' => 'xor']]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.filters.0.type' => ['The selected search.filters.0.type is invalid.'],
        ]);
    }

    /**
     * @param  list<int>  $positions
     * @return list<int>
     */
    private function ids(array $positions): array
    {
        return array_map(fn (int $position): int => $this->posts[$position]->id, $positions);
    }
}
