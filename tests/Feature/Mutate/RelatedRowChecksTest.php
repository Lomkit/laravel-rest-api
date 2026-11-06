<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\ViewOnlyTagPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\ViewOnlyTicketPolicy;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RelatedRowChecksTest extends HelpdeskTestCase
{
    private const array COLUMNS = ['tags' => 'name', 'labels' => 'name', 'labelledTickets' => 'title'];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Team::query()->insert(['id' => 101, 'name' => 'Support']);
        Ticket::query()->insert([['id' => 101, 'team_id' => 101, 'title' => 'Printer jam', 'status' => 'open'], ['id' => 102, 'team_id' => 101, 'title' => 'Password reset', 'status' => 'open']]);
        Tag::query()->insert([['id' => 101, 'name' => 'Hardware'], ['id' => 102, 'name' => 'Network']]);
    }

    #[Test]
    #[DataProvider('attributeWrites')]
    public function it_refuses_attributes_on_a_related_row_without_update(string $relation, string $operation): void
    {
        $this->viewOnly($relation);

        $response = $this->mutate($relation, ['operation' => $operation, 'key' => $operation === 'update' ? 102 : [102], 'attributes' => [self::COLUMNS[$relation] => 'Reviewed']]);

        $response->assertForbidden();
        $this->assertSame([[], self::storedName($relation)], [self::attached($relation), self::related($relation)->getAttribute(self::COLUMNS[$relation])]);
    }

    /** @return iterable<string, array{string, string}> */
    public static function attributeWrites(): iterable
    {
        foreach (self::relations() as $family => [$relation]) {
            foreach (['sync', 'toggle', 'update'] as $operation) {
                yield $family.', '.$operation => [$relation, $operation];
            }
        }
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_writes_the_attributes_of_a_synced_row_with_update(string $relation): void
    {
        $response = $this->mutate($relation, ['operation' => 'sync', 'key' => [102], 'attributes' => [self::COLUMNS[$relation] => 'Reviewed']]);

        $response->assertOk();
        $this->assertSame([[102], 'Reviewed'], [self::attached($relation), self::related($relation)->getAttribute(self::COLUMNS[$relation])]);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  list<int>  $attached
     */
    #[Test]
    #[DataProvider('links')]
    public function it_links_a_related_row_with_view_only(string $relation, array $entry, array $attached): void
    {
        $this->viewOnly($relation);

        if ($entry['operation'] === 'detach') {
            self::parent($relation)
                ->{$relation}()
                ->attach(102);
        }

        $response = $this->mutate($relation, $entry);

        $response->assertOk();
        $this->assertSame([$attached, self::storedName($relation)], [self::attached($relation), self::related($relation)->getAttribute(self::COLUMNS[$relation])]);
    }

    /** @return iterable<string, array{string, array<string, mixed>, list<int>}> */
    public static function links(): iterable
    {
        foreach (self::relations() as $family => [$relation]) {
            yield $family.', sync' => [$relation, ['operation' => 'sync', 'key' => [102]], [102]];
            yield $family.', toggle' => [$relation, ['operation' => 'toggle', 'key' => [102]], [102]];
            yield $family.', attach' => [$relation, ['operation' => 'attach', 'key' => [102]], [102]];
            yield $family.', detach' => [$relation, ['operation' => 'detach', 'key' => [102]], []];
        }
    }

    /** @return array<string, array{string}> */
    public static function relations(): array
    {
        return ['belongs to many' => ['tags'], 'morph to many' => ['labels'], 'morphed by many' => ['labelledTickets']];
    }

    private function viewOnly(string $relation): void
    {
        if ($relation === 'labelledTickets') {
            Gate::policy(Ticket::class, ViewOnlyTicketPolicy::class);

            return;
        }

        Gate::policy(Tag::class, ViewOnlyTagPolicy::class);
    }

    /** @param array<string, mixed> $entry */
    private function mutate(string $relation, array $entry): TestResponse
    {
        return $this->postJson($relation === 'labelledTickets' ? '/api/tags/mutate' : '/api/tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'relations' => [$relation => [$entry]]],
        ]]);
    }

    private static function parent(string $relation): Model
    {
        return $relation === 'labelledTickets' ? Tag::query()->findOrFail(101) : Ticket::query()->findOrFail(101);
    }

    private static function related(string $relation): Model
    {
        return $relation === 'labelledTickets' ? Ticket::query()->findOrFail(102) : Tag::query()->findOrFail(102);
    }

    private static function storedName(string $relation): string
    {
        return $relation === 'labelledTickets' ? 'Password reset' : 'Network';
    }

    /** @return list<mixed> */
    private static function attached(string $relation): array
    {
        return self::parent($relation)
            ->{$relation}()
            ->pluck($relation === 'labelledTickets' ? 'tickets.id' : 'tags.id')
            ->all();
    }
}
