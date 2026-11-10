<?php

namespace Lomkit\Rest\Tests\Unit\Relations;

use Lomkit\Rest\Exceptions\InvalidRelationException;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasOneOrManyThrough;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Lomkit\Rest\Tests\PackageTestCase;
use Lomkit\Rest\Tests\Unit\Relations\Stubs\AttachedAndSyncedTags;
use Lomkit\Rest\Tests\Unit\Relations\Stubs\UnconstructedHasMany;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Test;

class OnlyOperationsTest extends PackageTestCase
{
    /**
     * @param  class-string<Relation>  $class
     * @param  class-string<Relation>  $parent
     * @param  class-string<Relation>  $base
     * @param  class-string<Resource>  $resource
     */
    #[Test]
    #[DataProviderExternal(RelationHierarchyTest::class, 'relations')]
    public function it_keeps_only_the_listed_operations_of_each_relation(string $class, string $parent, string $base, string $resource, string $name): void
    {
        $relation = $class::make('rows', TicketResource::class);

        if ($base === HasOneOrManyThrough::class) {
            $this->expectException(InvalidRelationException::class);
            $this->expectExceptionMessage('The '.class_basename($class).' relation [rows] cannot keep the update operation: it does not accept it. Keep only the operations it accepts [].');
        }

        $relation->onlyOperations(MutationOperation::Update, MutationOperation::Attach);

        $this->assertSame(['update', 'attach'], array_column($relation->acceptedOperations(), 'value'));
        $this->assertSame($base === BelongsToMany::class, $relation->syncRequiresWithoutDetaching());
    }

    #[Test]
    public function it_combines_only_and_without_in_either_order(): void
    {
        $operations = [MutationOperation::Create, MutationOperation::Update, MutationOperation::Detach, MutationOperation::Sync];
        $first = BelongsToMany::make('tags', TicketResource::class)
            ->onlyOperations(...$operations)
            ->withoutOperations(MutationOperation::Update);
        $second = BelongsToMany::make('tags', TicketResource::class)
            ->withoutOperations(MutationOperation::Update)
            ->onlyOperations(...$operations);

        $this->assertSame(['create', 'detach', 'sync'], array_column($first->acceptedOperations(), 'value'));
        $this->assertSame(['create', 'detach', 'sync'], array_column($second->acceptedOperations(), 'value'));
        $this->assertFalse($first->syncRequiresWithoutDetaching());
    }

    #[Test]
    public function it_keeps_the_operations_that_every_only_call_lists(): void
    {
        $relation = HasMany::make('tickets', TicketResource::class)
            ->onlyOperations(MutationOperation::Create, MutationOperation::Update, MutationOperation::Attach)
            ->onlyOperations(MutationOperation::Update, MutationOperation::Attach, MutationOperation::Detach);

        $this->assertSame(['update', 'attach'], array_column($relation->acceptedOperations(), 'value'));
    }

    #[Test]
    public function it_accepts_no_operation_after_an_only_call_without_operations(): void
    {
        $this->assertSame([], HasMany::make('tickets', TicketResource::class)->onlyOperations()->acceptedOperations());
        $this->assertSame([MutationOperation::Delete], HasMany::make('tickets', TicketResource::class)->onlyOperations()->deletable()->acceptedOperations());
    }

    #[Test]
    public function it_applies_the_many_to_many_rule_when_only_leaves_detach_out(): void
    {
        $relation = BelongsToMany::make('tags', TicketResource::class)->onlyOperations(MutationOperation::Attach, MutationOperation::Sync, MutationOperation::Toggle);

        $this->assertSame(['attach', 'sync'], array_column($relation->acceptedOperations(), 'value'));
        $this->assertTrue($relation->syncRequiresWithoutDetaching());
    }

    #[Test]
    public function it_keeps_the_sync_of_a_many_to_many_relation_that_never_accepted_detach(): void
    {
        $relation = AttachedAndSyncedTags::make('tags', TicketResource::class)->onlyOperations(MutationOperation::Sync);

        $this->assertSame(['sync'], array_column($relation->acceptedOperations(), 'value'));
        $this->assertFalse($relation->syncRequiresWithoutDetaching());
    }

    #[Test]
    public function it_refuses_to_keep_delete(): void
    {
        $this->expectException(InvalidRelationException::class);
        $this->expectExceptionMessage('The HasMany relation [tickets] cannot keep the delete operation: a relation accepts delete only through deletable(). Declare deletable() instead.');

        HasMany::make('tickets', TicketResource::class)->onlyOperations(MutationOperation::Delete);
    }

    #[Test]
    public function it_refuses_to_keep_an_operation_that_the_relation_does_not_accept(): void
    {
        $this->expectException(InvalidRelationException::class);
        $this->expectExceptionMessage('The HasMany relation [tickets] cannot keep the toggle operation: it does not accept it. Keep only the operations it accepts [create, update, attach, detach].');

        HasMany::make('tickets', TicketResource::class)->onlyOperations(MutationOperation::Toggle);
    }

    #[Test]
    public function it_selects_the_operations_of_a_relation_whose_constructor_skips_the_parent_one(): void
    {
        $this->assertSame(['create', 'update', 'attach', 'detach'], array_column(UnconstructedHasMany::make('tickets', TicketResource::class)->acceptedOperations(), 'value'));
        $this->assertSame(['update'], array_column(UnconstructedHasMany::make('tickets', TicketResource::class)->withoutOperations(MutationOperation::Create)->onlyOperations(MutationOperation::Create, MutationOperation::Update)->acceptedOperations(), 'value'));
    }
}
