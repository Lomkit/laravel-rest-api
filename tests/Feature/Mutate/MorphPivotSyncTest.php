<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MorphPivotSyncTest extends PackageTestCase
{
    private const array ENDPOINTS = ['labels' => '/api/tickets/mutate', 'labelledTickets' => '/api/tags/mutate'];

    private const array COLUMNS = ['labels' => 'name', 'labelledTickets' => 'title'];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Team::query()->insert(['id' => 101, 'name' => 'Support']);
        Ticket::query()->insert([
            ['id' => 101, 'team_id' => 101, 'title' => 'Printer jam', 'status' => 'open'],
            ['id' => 102, 'team_id' => 101, 'title' => 'Password reset', 'status' => 'open'],
            ['id' => 103, 'team_id' => 101, 'title' => 'Laptop setup', 'status' => 'open'],
        ]);
        Tag::query()->insert([['id' => 101, 'name' => 'Hardware'], ['id' => 102, 'name' => 'Network'], ['id' => 103, 'name' => 'Monitor']]);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<int, int>  $attached
     * @param  list<int>  $renamed
     */
    #[Test]
    #[DataProvider('pivotEntries')]
    public function it_syncs_and_toggles_a_morph_pivot_relation(string $relation, array $entry, array $attached, array $renamed): void
    {
        self::parent($relation)
            ->{$relation}()
            ->attach(101, ['position' => 1]);
        $names = self::names($relation);

        $response = $this->postJson(self::ENDPOINTS[$relation], ['mutate' => [['operation' => 'update', 'key' => 101, 'relations' => [$relation => [$entry]]]]]);

        $response->assertOk();
        $this->assertSame($attached, self::attached($relation));
        $this->assertSame(array_replace($names, array_fill_keys($renamed, 'Reviewed')), self::names($relation));
    }

    /** @return iterable<string, array{string, array<string, mixed>, array<int, int>, list<int>}> */
    public static function pivotEntries(): iterable
    {
        foreach (['morph to many' => 'labels', 'morphed by many' => 'labelledTickets'] as $family => $relation) {
            yield $family.', sync replaces' => [$relation, ['operation' => 'sync', 'key' => [102], 'pivot' => ['position' => 2]], [102 => 2], []];
            yield $family.', sync with a key list' => [$relation, ['operation' => 'sync', 'key' => [102, 103], 'pivot' => ['position' => 2]], [102 => 2, 103 => 2], []];
            yield $family.', sync without detaching keeps' => [$relation, ['operation' => 'sync', 'key' => [102], 'pivot' => ['position' => 2], 'without_detaching' => true], [101 => 1, 102 => 2], []];
            yield $family.', toggle off and on with attributes and pivot' => [$relation, [
                'operation' => 'toggle',
                'key' => [101, 102],
                'attributes' => [self::COLUMNS[$relation] => 'Reviewed'],
                'pivot' => ['position' => 3],
            ], [102 => 3], [101, 102]];
        }
    }

    private static function parent(string $relation): Model
    {
        return $relation === 'labels' ? Ticket::query()->findOrFail(101) : Tag::query()->findOrFail(101);
    }

    /** @return array<int, mixed> */
    private static function attached(string $relation): array
    {
        return self::parent($relation)
            ->{$relation}()
            ->orderBy($relation === 'labels' ? 'tags.id' : 'tickets.id')
            ->get()
            ->mapWithKeys(fn (Model $related): array => [(int) $related->getKey() => $related->getRelation('pivot')->getAttribute('position')])
            ->all();
    }

    /** @return array<int, mixed> */
    private static function names(string $relation): array
    {
        $query = $relation === 'labels' ? Tag::query() : Ticket::query();

        return $query
            ->orderBy('id')
            ->pluck(self::COLUMNS[$relation], 'id')
            ->all();
    }
}
