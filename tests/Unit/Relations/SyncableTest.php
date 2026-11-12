<?php

namespace Lomkit\Rest\Tests\Unit\Relations;

use Lomkit\Rest\Exceptions\InvalidRelationException;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Details\DetailsSelection;
use Lomkit\Rest\Relations\ChildRemoval;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncedHasMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class SyncableTest extends PackageTestCase
{
    #[Test]
    public function it_adds_sync_after_the_selected_operations_and_before_delete(): void
    {
        $detaching = MorphMany::make('labels', LabelResource::class)->syncable(ChildRemoval::Detach);
        $deleting = HasMany::make('tickets', TicketResource::class)
            ->syncable(ChildRemoval::Delete)
            ->withoutOperations(MutationOperation::Attach)
            ->deletable();

        $this->assertSame(['create', 'update', 'attach', 'detach', 'sync'], array_column($detaching->acceptedOperations(), 'value'));
        $this->assertSame(['create', 'update', 'detach', 'sync', 'delete'], array_column($deleting->acceptedOperations(), 'value'));
    }

    #[Test]
    public function it_keeps_the_relation_without_sync_until_syncable_is_called(): void
    {
        $relation = HasMany::make('tickets', TicketResource::class)->deletable();

        $this->assertSame(['create', 'update', 'attach', 'detach', 'delete'], array_column($relation->acceptedOperations(), 'value'));
        $this->assertNull($relation->syncRemoval());
        $this->assertFalse($relation->syncsChildren());
    }

    #[Test]
    public function it_details_what_a_sync_removes(): void
    {
        $selection = DetailsSelection::of(['relations.operations']);

        $this->assertSame('delete', HasMany::make('tickets', TicketResource::class)->deletable()->syncable(ChildRemoval::Delete)->toDetails($selection)['sync_removes']);
        $this->assertSame('detach', MorphMany::make('labels', LabelResource::class)->syncable(ChildRemoval::Detach)->toDetails($selection)['sync_removes']);
        $this->assertNull(HasMany::make('tickets', TicketResource::class)->toDetails($selection)['sync_removes']);
        $this->assertNull(SyncedHasMany::make('tickets', TicketResource::class)->toDetails($selection)['sync_removes']);
    }

    #[Test]
    public function it_refuses_a_sync_by_delete_on_a_relation_that_is_not_deletable(): void
    {
        $relation = HasMany::make('tickets', TicketResource::class)->syncable(ChildRemoval::Delete);

        $this->expectException(InvalidRelationException::class);
        $this->expectExceptionMessage('The HasMany relation [tickets] cannot sync by delete: it does not accept delete. Declare deletable() too, or sync by ChildRemoval::Detach.');

        $relation->acceptedOperations();
    }

    #[Test]
    public function it_refuses_a_sync_by_detach_on_a_relation_that_withdraws_detach(): void
    {
        $relation = MorphMany::make('labels', LabelResource::class)
            ->syncable(ChildRemoval::Detach)
            ->onlyOperations(MutationOperation::Create, MutationOperation::Update);

        $this->expectException(InvalidRelationException::class);
        $this->expectExceptionMessage('The MorphMany relation [labels] cannot sync by detach: it does not accept detach. Keep detach, or sync by ChildRemoval::Delete.');

        $relation->acceptedOperations();
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
            ->syncable(ChildRemoval::Detach)
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
    public function it_refuses_syncable_on_a_custom_relation_that_lists_sync(): void
    {
        $relation = SyncedHasMany::make('tickets', TicketResource::class)
            ->deletable()
            ->syncable(ChildRemoval::Delete);

        $this->expectException(InvalidRelationException::class);
        $this->expectExceptionMessage('The SyncedHasMany relation [tickets] cannot declare syncable(): its mutationOperations() already list sync, which links the rows it names and removes none. Leave sync out of mutationOperations(), or leave syncable() off.');

        $relation->acceptedOperations();
    }

    #[Test]
    public function it_writes_no_row_through_the_sync_entry_itself(): void
    {
        $relation = HasMany::make('tickets', TicketResource::class)
            ->deletable()
            ->syncable(ChildRemoval::Delete);

        $this->assertTrue($relation->syncsChildren());
        $this->assertFalse($relation->writesRelatedRows(MutationOperation::Sync));
        $this->assertTrue($relation->writesRelatedRows(MutationOperation::Update));
    }
}
