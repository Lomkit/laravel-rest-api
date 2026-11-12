<?php

namespace Lomkit\Rest\Tests\Unit\Relations;

use Lomkit\Rest\Exceptions\InvalidRelationException;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Details\DetailsSelection;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncedHasMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class SyncableTest extends PackageTestCase
{
    #[Test]
    public function it_adds_sync_after_the_selected_operations_and_before_delete(): void
    {
        $labels = MorphMany::make('labels', LabelResource::class)->syncable();
        $tickets = HasMany::make('tickets', TicketResource::class)
            ->syncable()
            ->withoutOperations(MutationOperation::Attach, MutationOperation::Detach)
            ->deletable();

        $this->assertSame(['create', 'update', 'attach', 'detach', 'sync'], array_column($labels->acceptedOperations(), 'value'));
        $this->assertSame(['create', 'update', 'sync', 'delete'], array_column($tickets->acceptedOperations(), 'value'));
        $this->assertSame(['create', 'update', 'sync', 'delete'], $tickets->toDetails(DetailsSelection::of(['relations.operations']))['operations']);
    }

    #[Test]
    public function it_keeps_the_relation_without_sync_until_syncable_is_called(): void
    {
        $relation = HasMany::make('tickets', TicketResource::class)->deletable();
        $withdrawn = HasMany::make('tickets', TicketResource::class)
            ->syncable()
            ->syncable(false);

        $this->assertSame(['create', 'update', 'attach', 'detach', 'delete'], array_column($relation->acceptedOperations(), 'value'));
        $this->assertFalse($relation->isSyncable());
        $this->assertFalse($relation->syncsChildren());
        $this->assertSame(['create', 'update', 'attach', 'detach'], array_column($withdrawn->acceptedOperations(), 'value'));
    }

    #[Test]
    public function it_refuses_to_keep_sync_through_only_operations(): void
    {
        $this->expectException(InvalidRelationException::class);
        $this->expectExceptionMessage('The HasMany relation [tickets] cannot keep the sync operation: a has-many relation accepts sync only through syncable(). Declare syncable() instead.');

        HasMany::make('tickets', TicketResource::class)->onlyOperations(MutationOperation::Create, MutationOperation::Sync);
    }

    #[Test]
    public function it_refuses_to_withdraw_sync_through_without_operations(): void
    {
        $this->expectException(InvalidRelationException::class);
        $this->expectExceptionMessage('The MorphMany relation [labels] cannot withdraw the sync operation: a has-many relation accepts sync only through syncable(). Leave syncable() off instead.');

        MorphMany::make('labels', LabelResource::class)
            ->syncable()
            ->withoutOperations(MutationOperation::Sync);
    }

    #[Test]
    public function it_keeps_the_sync_that_a_custom_relation_lists_as_a_link(): void
    {
        $relation = SyncedHasMany::make('tickets', TicketResource::class)->onlyOperations(MutationOperation::Sync);

        $this->assertSame(['sync'], array_column($relation->acceptedOperations(), 'value'));
        $this->assertFalse($relation->syncsChildren());
        $this->assertTrue(SyncedHasMany::make('tickets', TicketResource::class)->writesRelatedRows(MutationOperation::Sync));
    }

    #[Test]
    public function it_refuses_syncable_on_a_custom_relation_that_lists_sync_when_it_is_declared(): void
    {
        $this->expectException(InvalidRelationException::class);
        $this->expectExceptionMessage('The SyncedHasMany relation [tickets] cannot declare syncable(): its mutationOperations() already list sync, which links the rows it names and removes none. Leave sync out of mutationOperations(), or leave syncable() off.');

        SyncedHasMany::make('tickets', TicketResource::class)->syncable();
    }

    #[Test]
    public function it_writes_no_row_through_the_sync_entry_itself(): void
    {
        $relation = HasMany::make('tickets', TicketResource::class)->syncable();

        $this->assertTrue($relation->syncsChildren());
        $this->assertFalse($relation->writesRelatedRows(MutationOperation::Sync));
        $this->assertTrue($relation->writesRelatedRows(MutationOperation::Update));
    }

    #[Test]
    public function it_details_what_the_sync_of_each_relation_does(): void
    {
        $sync = fn (Relation $relation): mixed => $relation->toDetails(DetailsSelection::of(['relations.operations']))['sync'];

        $this->assertSame('check', $sync(HasMany::make('tickets', TicketResource::class)->syncable()));
        $this->assertSame('link', $sync(SyncedHasMany::make('tickets', TicketResource::class)));
        $this->assertSame('replace', $sync(BelongsToMany::make('tags', TagResource::class)));
        $this->assertSame('link', $sync(BelongsToMany::make('tags', TagResource::class)->withoutOperations(MutationOperation::Detach)));
        $this->assertNull($sync(HasMany::make('tickets', TicketResource::class)));
        $this->assertNull($sync(BelongsToMany::make('tags', TagResource::class)->withoutOperations(MutationOperation::Sync)));
    }
}
