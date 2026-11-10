<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Auth\Access\Response;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RelationshipChecksTest extends HelpdeskTestCase
{
    private Ticket $jam;

    /** @var array<string, Tag> */
    private array $labels;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->jam = $this->seedTickets()['Printer jam'];
        $this->labels = [];

        foreach (['Hardware', 'Urgent', 'Network', 'Monitor'] as $name) {
            $this->labels[$name] = Tag::create(['name' => $name]);
        }
    }

    #[Test]
    public function it_attaches_the_tags_the_parent_policy_allows(): void
    {
        $this->mutateTags([['operation' => 'attach', 'key' => [$this->tag('Hardware'), $this->tag('Network')]]])->assertOk();

        $this->assertSame([$this->tag('Hardware'), $this->tag('Network')], $this->attachedTags());
        $this->assertSame(['viewAny', ...$this->calls('update', $this->jam), $this->link('attachTag', 'Hardware'), $this->link('attachTag', 'Network')], $this->tickets->calls);
    }

    #[Test]
    public function it_refuses_an_attach_the_parent_policy_denies(): void
    {
        $this->mutateTags([['operation' => 'attach', 'key' => [$this->tag('Urgent')]]])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Urgent tickets are flagged by the escalation desk.');

        $this->assertSame([], $this->attachedTags());
    }

    #[Test]
    public function it_detaches_the_tags_the_parent_policy_allows_and_refuses_the_others(): void
    {
        $this->jam->tags()->attach([$this->tag('Hardware'), $this->tag('Urgent')]);

        $this->mutateTags([['operation' => 'detach', 'key' => [$this->tag('Hardware')]]])->assertOk();
        $this->mutateTags([['operation' => 'detach', 'key' => [$this->tag('Urgent')]]])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Urgent tickets are cleared by the escalation desk.');

        $this->assertSame([$this->tag('Urgent')], $this->attachedTags());
    }

    #[Test]
    public function it_checks_the_tags_a_sync_attaches_and_detaches(): void
    {
        $this->jam->tags()->attach([$this->tag('Urgent')]);

        $this->mutateTags([['operation' => 'sync', 'key' => [$this->tag('Hardware')]]])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Urgent tickets are cleared by the escalation desk.');

        $this->assertSame([$this->tag('Urgent')], $this->attachedTags());
        $this->assertSame(['viewAny', ...$this->calls('update', $this->jam), $this->link('attachTag', 'Hardware'), $this->link('detachTag', 'Urgent')], $this->tickets->calls);
    }

    #[Test]
    public function it_leaves_attached_a_tag_that_is_no_longer_found_while_syncing(): void
    {
        $this->jam->tags()->attach([$this->tag('Hardware'), $this->tag('Network')]);
        $this->labels['Hardware']->delete();

        $this->mutateTags([['operation' => 'sync', 'key' => [$this->tag('Network'), $this->tag('Monitor')]]])->assertOk();

        $this->assertSame([$this->tag('Hardware'), $this->tag('Network'), $this->tag('Monitor')], $this->attachedTags());
        $this->assertSame(['viewAny', ...$this->calls('update', $this->jam), $this->link('attachTag', 'Monitor')], $this->tickets->calls);
    }

    /** @param array<string, mixed> $entry */
    #[Test]
    #[DataProvider('refusedLinks')]
    public function it_refuses_a_link_that_the_parent_policy_denies(string $relation, array $entry): void
    {
        $this->mutateTags([$this->withUrgent($entry)], $relation)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Urgent tickets are flagged by the escalation desk.');

        $this->assertSame([], $this->attachedTags($relation));
    }

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function refusedLinks(): iterable
    {
        return self::forEachRelation([
            'attach' => ['operation' => 'attach', 'key' => ['Urgent']],
            'attach with pivot' => ['operation' => 'attach', 'key' => ['Urgent'], 'pivot' => ['position' => 2]],
            'create' => ['operation' => 'create', 'attributes' => ['name' => 'Urgent']],
            'update' => ['operation' => 'update', 'key' => 'Urgent', 'attributes' => ['name' => 'Urgent']],
            'update with pivot' => ['operation' => 'update', 'key' => 'Urgent', 'attributes' => ['name' => 'Urgent'], 'pivot' => ['position' => 2]],
            'toggle on' => ['operation' => 'toggle', 'key' => ['Urgent']],
        ]);
    }

    /** @param array<string, mixed> $entry */
    #[Test]
    #[DataProvider('refusedUnlinks')]
    public function it_refuses_an_unlink_that_the_parent_policy_denies(string $relation, array $entry): void
    {
        $this->jam->{$relation}()->attach([$this->tag('Urgent')]);

        $this->mutateTags([$this->withUrgent($entry)], $relation)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Urgent tickets are cleared by the escalation desk.');

        $this->assertSame([$this->tag('Urgent')], $this->attachedTags($relation));
    }

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function refusedUnlinks(): iterable
    {
        return self::forEachRelation([
            'detach' => ['operation' => 'detach', 'key' => ['Urgent']],
            'toggle off' => ['operation' => 'toggle', 'key' => ['Urgent']],
            'a sync that detaches' => ['operation' => 'sync', 'key' => []],
        ]);
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_syncs_an_attached_tag_without_checking_its_link(string $relation): void
    {
        $this->jam->{$relation}()->attach([$this->tag('Urgent')]);

        $this->mutateTags([['operation' => 'sync', 'key' => [$this->tag('Urgent')]]], $relation)->assertOk();

        $this->assertSame([$this->tag('Urgent')], $this->attachedTags($relation));
        $this->assertSame(['viewAny', ...$this->calls('update', $this->jam)], $this->tickets->calls);
    }

    #[Test]
    #[DataProvider('relations')]
    public function it_checks_the_link_of_an_attached_tag_whose_pivot_a_sync_writes(string $relation): void
    {
        $this->jam->{$relation}()->attach([$this->tag('Hardware') => ['position' => 1]]);

        $this->mutateTags([['operation' => 'sync', 'key' => [$this->tag('Hardware')], 'pivot' => ['position' => 2]]], $relation)->assertOk();

        $this->assertSame([2], $this->positions($relation));
        $this->assertSame(['viewAny', ...$this->calls('update', $this->jam), $this->link('attachTag', 'Hardware')], $this->tickets->calls);
    }

    /** @param array<string, mixed> $entry */
    #[Test]
    #[DataProvider('refusedPivotWrites')]
    public function it_refuses_a_sync_that_writes_the_pivot_of_a_link_the_parent_policy_denies(string $relation, array $entry): void
    {
        $this->jam->{$relation}()->attach([$this->tag('Urgent') => ['position' => 1]]);

        $this->mutateTags([$this->withUrgent($entry)], $relation)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Urgent tickets are flagged by the escalation desk.');

        $this->assertSame([1], $this->positions($relation));
    }

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function refusedPivotWrites(): iterable
    {
        return self::forEachRelation([
            'sync' => ['operation' => 'sync', 'key' => ['Urgent'], 'pivot' => ['position' => 2]],
            'sync without detaching' => ['operation' => 'sync', 'key' => ['Urgent'], 'without_detaching' => true, 'pivot' => ['position' => 2]],
        ]);
    }

    #[Test]
    public function it_checks_the_link_of_a_kept_tag_whose_pivot_a_sync_writes_under_an_attached_ticket(): void
    {
        $this->jam->tags()->attach([$this->tag('Urgent') => ['position' => 1]]);

        $this->postJson('/api/tags/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $this->tag('Hardware'),
            'relations' => ['labelledTickets' => [[
                'operation' => 'attach',
                'key' => [$this->jam->id],
                'relations' => ['tags' => [['operation' => 'sync', 'key' => [$this->tag('Urgent')], 'without_detaching' => true, 'pivot' => ['position' => 2]]]],
            ]]],
        ]]])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Urgent tickets are flagged by the escalation desk.');

        $this->assertSame([1], $this->positions('tags'));
        $this->assertSame([], $this->attachedTags('labels'));
    }

    #[Test]
    public function it_checks_update_on_the_parent_of_a_kept_row_whose_pivot_a_sync_writes_without_link_abilities(): void
    {
        $this->labels['Hardware']->labelledTickets()->attach([$this->jam->id => ['position' => 1]]);
        $this->tags->answers['update'] = Response::deny('Tags are frozen.');

        $this->postJson('/api/tickets/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $this->jam->id,
            'relations' => ['tags' => [[
                'operation' => 'attach',
                'key' => [$this->tag('Hardware')],
                'relations' => ['labelledTickets' => [['operation' => 'sync', 'key' => [$this->jam->id], 'pivot' => ['position' => 2]]]],
            ]]],
        ]]])
            ->assertForbidden()
            ->assertJsonPath('message', 'Tags are frozen.');

        $this->assertSame([1], $this->positions('labels'));
        $this->assertSame(['update '.$this->tag('Hardware')], array_values(array_filter($this->tags->calls, fn (string $call): bool => str_starts_with($call, 'update'))));
    }

    /** @return array<string, array{string}> */
    public static function relations(): array
    {
        return ['belongs to many' => ['tags'], 'morph to many' => ['labels']];
    }

    /** @param list<array<string, mixed>> $operations */
    private function mutateTags(array $operations, string $relation = 'tags'): TestResponse
    {
        return $this->postJson('/api/tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->jam->id, 'relations' => [$relation => $operations]],
        ]]);
    }

    private function tag(string $name): int
    {
        return $this->labels[$name]->id;
    }

    private function link(string $ability, string $tag): string
    {
        return $ability.' '.$this->jam->id.' '.$this->tag($tag);
    }

    /** @return list<int> */
    private function attachedTags(string $relation = 'tags'): array
    {
        return $this->jam
            ->{$relation}()
            ->withTrashed()
            ->orderBy('tags.id')
            ->pluck('tags.id')
            ->all();
    }

    /** @return list<mixed> */
    private function positions(string $relation): array
    {
        return $this->jam
            ->{$relation}()
            ->get()
            ->pluck('pivot.position')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function withUrgent(array $entry): array
    {
        return match (true) {
            ($entry['key'] ?? null) === 'Urgent' => [...$entry, 'key' => $this->tag('Urgent')],
            ($entry['key'] ?? null) === ['Urgent'] => [...$entry, 'key' => [$this->tag('Urgent')]],
            default => $entry,
        };
    }

    /**
     * @param  array<string, array<string, mixed>>  $entries
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    private static function forEachRelation(array $entries): iterable
    {
        foreach (self::relations() as $family => [$relation]) {
            foreach ($entries as $name => $entry) {
                yield $family.', '.$name => [$relation, $entry];
            }
        }
    }
}
