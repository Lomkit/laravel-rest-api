<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

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
