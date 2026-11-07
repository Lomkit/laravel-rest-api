<?php

namespace Lomkit\Rest\Tests\Unit\Mutations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Mutations\RowSet;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\UncachedTicketResource;
use Lomkit\Rest\Tests\PackageTestCase;
use Lomkit\Rest\Tests\Unit\Mutations\Stubs\StringKeyTagResource;
use Lomkit\Rest\Tests\Unit\Mutations\Stubs\SubclassingTagResource;
use Lomkit\Rest\Validation\KeysGrammar;
use PHPUnit\Framework\Attributes\Test;

class RowSetTest extends PackageTestCase
{
    use CapturesQueries;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Team::query()->insert(['id' => 1, 'name' => 'Support']);
        Ticket::query()->insert(['id' => 1, 'team_id' => 1, 'title' => 'Printer jam', 'status' => 'open']);
        Tag::query()->insert([
            ['id' => 1, 'name' => 'Hardware', 'deleted_at' => null],
            ['id' => 2, 'name' => 'Network', 'deleted_at' => null],
            ['id' => 3, 'name' => 'Archived', 'deleted_at' => '2026-01-01 00:00:00'],
        ]);
    }

    #[Test]
    public function it_gives_one_instance_of_a_row_that_two_resources_loaded(): void
    {
        $rows = new RowSet;
        $tickets = $this->resource(TicketResource::class);
        $uncached = $this->resource(UncachedTicketResource::class);
        $this->expect($rows, $tickets, 1);
        $this->expect($rows, $uncached, 1);

        $this->assertSame($rows->find($tickets, 1), $rows->find($uncached, 1));
    }

    #[Test]
    public function it_gives_a_row_with_no_recorded_change(): void
    {
        $rows = new RowSet;
        $tickets = $this->resource(TicketResource::class);
        $this->expect($rows, $tickets, 1);
        $ticket = $rows->find($tickets, 1);
        $ticket
            ->setAttribute('title', 'Paper jam')
            ->save();

        $this->assertTrue($ticket->wasChanged());
        $this->assertFalse($rows->find($tickets, 1)->wasChanged());
    }

    #[Test]
    public function it_refuses_a_key_that_its_lookups_did_not_load(): void
    {
        $rows = new RowSet;

        $this->expectException(ModelNotFoundException::class);

        $rows->find($this->resource(TicketResource::class), 1);
    }

    #[Test]
    public function it_gives_each_row_its_own_instance_when_its_key_is_cast_to_an_object(): void
    {
        $rows = new RowSet;
        $tags = $this->resource(StringKeyTagResource::class);
        $this->expect($rows, $tags, 1);
        $this->expect($rows, $tags, 2);

        $this->assertSame(['Hardware', 'Network'], [$rows->find($tags, 1)->getAttribute('name'), $rows->find($tags, 2)->getAttribute('name')]);
    }

    #[Test]
    public function it_gives_a_row_as_another_model_class_of_its_table_saved_it(): void
    {
        $rows = new RowSet;
        $tags = $this->resource(TagResource::class);
        $subclassing = $this->resource(SubclassingTagResource::class);

        foreach ([1, 2] as $key) {
            $this->expect($rows, $tags, $key);
            $this->expect($rows, $subclassing, $key);
        }

        $rows->find($subclassing, 1)->update(['name' => 'Printers']);
        $rows->find($subclassing, 2)->update(['name' => 'Wifi']);
        $found = $rows->find($tags, 1);

        $this->assertSame(['Printers', 'Printers', 'Wifi'], [$found->getAttribute('name'), $found->getOriginal('name'), $rows->find($tags, 2)->getAttribute('name')]);
    }

    #[Test]
    public function it_keeps_the_unsaved_attributes_of_a_row_that_another_model_class_saved(): void
    {
        $rows = new RowSet;
        $tags = $this->resource(TagResource::class);
        $subclassing = $this->resource(SubclassingTagResource::class);
        $this->expect($rows, $tags, 1);
        $this->expect($rows, $subclassing, 1);
        $tag = $rows->find($tags, 1)->setAttribute('name', 'Pending');

        $rows->find($subclassing, 1)->update(['name' => 'Printers']);

        $this->assertSame($tag, $rows->find($tags, 1));
        $this->assertSame(['Pending', 'Printers'], [$tag->getAttribute('name'), $tag->getOriginal('name')]);
    }

    /** @param class-string<Resource> $class */
    private function resource(string $class): Resource
    {
        return (new RestContext(MutateRequest::create('/api/tickets/mutate', 'POST'), $this->app))->resource($class);
    }

    private function expect(RowSet $rows, Resource $resource, int $key): void
    {
        $rows
            ->lookup($resource, fn (): Builder => $resource::newModel()->newQuery(), KeysGrammar::idOf($resource))
            ->expect($key);
    }
}
