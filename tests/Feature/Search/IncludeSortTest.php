<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Newsroom;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class IncludeSortTest extends PackageTestCase
{
    use SeedsNewsroomRows;

    private const array URGENT = ['relation' => 'comments', 'type' => 'count', 'alias' => 'urgent', 'filters' => [['field' => 'body', 'value' => 'urgent']]];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Newsroom];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedNewsroomRows();
    }

    #[Test]
    public function it_sorts_an_include_on_a_field_of_a_relation(): void
    {
        $response = $this->searchStories(['limit' => 10, 'sorts' => [['field' => 'reporter.name'], ['field' => 'id']]]);

        $response->assertOk()->assertJsonPath('data.*.stories.*.id', $this->byDriver([2, 1, 3, 4], [2, 1, 4, 3]));
    }

    /**
     * @param  array<string, int>  $limit
     * @param  list<int>  $nullsFirst
     * @param  list<int>  $nullsLast
     */
    #[Test]
    #[DataProvider('directions')]
    public function it_sorts_on_a_field_of_a_relation_within_the_limit_of_each_parent(array $limit, string $direction, array $nullsFirst, array $nullsLast): void
    {
        $response = $this->searchStories([...$limit, 'sorts' => [['field' => 'reporter.name', 'direction' => $direction]]]);

        $response->assertOk()->assertJsonPath('data.*.stories.*.id', $this->byDriver($nullsFirst, $nullsLast));
    }

    /** @return array<string, array{array<string, int>, string, list<int>, list<int>}> */
    public static function directions(): array
    {
        return [
            'ascending' => [['limit' => 1], 'asc', [2, 3], [2, 4]],
            'descending' => [['limit' => 1], 'desc', [1, 4], [1, 3]],
            'ascending within the default limit' => [[], 'asc', [2, 3], [2, 4]],
            'descending within the default limit' => [[], 'desc', [1, 4], [1, 3]],
        ];
    }

    /**
     * @param  array<string, int>  $limit
     * @param  list<array<string, mixed>>  $sorts
     * @param  list<list<array<string, int>>>  $stories
     */
    #[Test]
    #[DataProvider('aggregateSorts')]
    public function it_sorts_on_the_key_of_an_aggregate_within_the_limit_of_each_parent(array $limit, array $sorts, array $stories): void
    {
        $response = $this->searchStories([
            ...$limit,
            'aggregates' => [self::URGENT],
            'filters' => [['field' => 'headline', 'operator' => '!=', 'value' => 'Nothing']],
            'sorts' => $sorts,
        ]);

        $response->assertOk()->assertJsonPath('data.*.stories', $stories);
    }

    /** @return array<string, array{array<string, int>, list<array<string, mixed>>, list<list<array<string, int>>>}> */
    public static function aggregateSorts(): array
    {
        return [
            'the most urgent story' => [['limit' => 1], [['field' => 'urgent', 'direction' => 'desc'], ['field' => 'id']], [[['id' => 2, 'urgent' => 2]], [['id' => 3, 'urgent' => 0]], []]],
            'the least urgent story' => [['limit' => 1], [['field' => 'urgent'], ['field' => 'id', 'direction' => 'desc']], [[['id' => 1, 'urgent' => 1]], [['id' => 4, 'urgent' => 0]], []]],
            'the most urgent story within the default limit' => [[], [['field' => 'urgent', 'direction' => 'desc'], ['field' => 'id']], [[['id' => 2, 'urgent' => 2]], [['id' => 3, 'urgent' => 0]], []]],
            'the least urgent story within the default limit' => [[], [['field' => 'urgent'], ['field' => 'id', 'direction' => 'desc']], [[['id' => 1, 'urgent' => 1]], [['id' => 4, 'urgent' => 0]], []]],
        ];
    }

    /** @param array<string, mixed> $include */
    private function searchStories(array $include): TestResponse
    {
        return $this->postJson('/news-desks/search', ['search' => [
            'sorts' => [['field' => 'id']],
            'selects' => [['field' => 'id']],
            'includes' => [['relation' => 'stories', 'selects' => [['field' => 'id']], ...$include]],
        ]]);
    }

    /**
     * @param  list<int>  $nullsFirst
     * @param  list<int>  $nullsLast
     * @return list<int>
     */
    private function byDriver(array $nullsFirst, array $nullsLast): array
    {
        return DB::connection()->getDriverName() === 'pgsql' ? $nullsLast : $nullsFirst;
    }
}
