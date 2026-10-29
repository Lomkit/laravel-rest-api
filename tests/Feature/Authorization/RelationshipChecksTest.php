<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\Test;

class RelationshipChecksTest extends HelpdeskTestCase
{
    private Ticket $jam;

    /** @var array<string, Tag> */
    private array $labels;

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
        $this->assertSame([...$this->calls('update', $this->jam), $this->link('attachTag', 'Hardware'), $this->link('attachTag', 'Network')], $this->tickets->calls);
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
        $this->assertSame([...$this->calls('update', $this->jam), $this->link('attachTag', 'Hardware'), $this->link('detachTag', 'Urgent')], $this->tickets->calls);
    }

    #[Test]
    public function it_syncs_past_an_attached_tag_that_is_no_longer_found(): void
    {
        $this->jam->tags()->attach([$this->tag('Hardware'), $this->tag('Network')]);
        $this->labels['Hardware']->delete();

        $this->mutateTags([['operation' => 'sync', 'key' => [$this->tag('Network'), $this->tag('Monitor')]]])->assertOk();

        $this->assertSame([$this->tag('Network'), $this->tag('Monitor')], $this->attachedTags());
        $this->assertSame([...$this->calls('update', $this->jam), $this->link('attachTag', 'Monitor')], $this->tickets->calls);
    }

    #[Test]
    public function it_checks_each_pair_of_rows_once(): void
    {
        $resource = $this->contextFor(null)->resource(TagResource::class);
        $hardware = $this->labels['Hardware'];

        $resource->authorizeToAttach($this->jam, $hardware);
        $resource->authorizeToAttach(Ticket::query()->findOrFail($this->jam->id), Tag::query()->findOrFail($hardware->id));
        $allowed = [$resource->authorizedToAttach($this->jam, $hardware), $resource->authorizedToAttach($this->jam, $this->labels['Urgent'])];

        $this->assertSame([true, false], $allowed);
        $this->assertSame([$this->link('attachTag', 'Hardware'), $this->link('attachTag', 'Urgent')], $this->tickets->calls);
    }

    #[Test]
    public function it_skips_the_check_when_the_parent_policy_has_no_method_for_the_relation(): void
    {
        $resource = $this->contextFor(null)->resource(TagResource::class);
        $team = $this->jam->team;

        $allowed = [$resource->authorizedToAttach($team, $this->labels['Urgent']), $resource->authorizedToDetach($team, $this->labels['Urgent'])];

        $this->assertSame([true, true], $allowed);
        $this->assertSame([[], []], [$this->teams->calls, $this->tickets->calls]);
    }

    /** @param list<array<string, mixed>> $operations */
    private function mutateTags(array $operations): TestResponse
    {
        return $this->postJson('/api/tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->jam->id, 'relations' => ['tags' => $operations]],
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
    private function attachedTags(): array
    {
        return $this->jam->tags()->withTrashed()->orderBy('tags.id')->pluck('tags.id')->all();
    }
}
