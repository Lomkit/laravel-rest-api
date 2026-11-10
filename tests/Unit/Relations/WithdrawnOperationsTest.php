<?php

namespace Lomkit\Rest\Tests\Unit\Relations;

use Lomkit\Rest\Exceptions\InvalidRelationException;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasOneOrManyThrough;
use Lomkit\Rest\Relations\HasOneThrough;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncedHasMany;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Test;

class WithdrawnOperationsTest extends PackageTestCase
{
    /**
     * @param  class-string<Relation>  $class
     * @param  class-string<Relation>  $parent
     * @param  class-string<Relation>  $base
     * @param  class-string<Resource>  $resource
     */
    #[Test]
    #[DataProviderExternal(RelationHierarchyTest::class, 'relations')]
    public function it_withdraws_operations_from_the_accepted_operations_of_each_relation(string $class, string $parent, string $base, string $resource, string $name): void
    {
        $relation = $class::make('rows', TicketResource::class);

        if ($base === HasOneOrManyThrough::class) {
            $this->expectException(InvalidRelationException::class);
            $this->expectExceptionMessage('The '.class_basename($class).' relation [rows] cannot withdraw the update operation: it accepts no operation.');
        }

        $relation->withoutOperations(MutationOperation::Update, MutationOperation::Attach);

        $expected = array_values(array_diff(array_column($relation->mutationOperations(), 'value'), ['update', 'attach']));

        $this->assertSame($expected, array_column($relation->acceptedOperations(), 'value'));
        $this->assertFalse($relation->syncRequiresWithoutDetaching());
    }

    /**
     * @param  class-string<Relation>  $class
     * @param  class-string<Relation>  $parent
     * @param  class-string<Relation>  $base
     * @param  class-string<Resource>  $resource
     */
    #[Test]
    #[DataProviderExternal(RelationHierarchyTest::class, 'relations')]
    public function it_withdraws_toggle_with_detach_from_a_many_to_many_relation_only(string $class, string $parent, string $base, string $resource, string $name): void
    {
        if ($base === HasOneOrManyThrough::class) {
            $this->expectNotToPerformAssertions();

            return;
        }

        $relation = $class::make('rows', TicketResource::class)->withoutOperations(MutationOperation::Detach);
        $many = $base === BelongsToMany::class;

        $this->assertSame($many ? ['create', 'update', 'attach', 'sync'] : ['create', 'update', 'attach'], array_column($relation->acceptedOperations(), 'value'));
        $this->assertSame($many, $relation->syncRequiresWithoutDetaching());
    }

    #[Test]
    public function it_keeps_toggle_on_a_relation_to_children_that_withdraws_detach(): void
    {
        $relation = SyncedHasMany::make('tickets', TicketResource::class)->withoutOperations(MutationOperation::Detach);

        $this->assertSame(['create', 'update', 'attach', 'sync', 'toggle'], array_column($relation->acceptedOperations(), 'value'));
        $this->assertFalse($relation->syncRequiresWithoutDetaching());
    }

    #[Test]
    public function it_keeps_delete_on_a_deletable_relation_that_withdraws_other_operations(): void
    {
        $relation = HasMany::make('tickets', TicketResource::class)
            ->deletable()
            ->withoutOperations(MutationOperation::Create, MutationOperation::Detach);

        $this->assertSame(['update', 'attach', 'delete'], array_column($relation->acceptedOperations(), 'value'));
    }

    #[Test]
    public function it_adds_up_the_operations_of_each_call(): void
    {
        $relation = BelongsToMany::make('tags', TicketResource::class)
            ->withoutOperations(MutationOperation::Create)
            ->withoutOperations(MutationOperation::Create, MutationOperation::Toggle);

        $this->assertSame(['update', 'attach', 'detach', 'sync'], array_column($relation->acceptedOperations(), 'value'));
        $this->assertFalse($relation->syncRequiresWithoutDetaching());
    }

    #[Test]
    public function it_refuses_to_withdraw_delete(): void
    {
        $this->expectException(InvalidRelationException::class);
        $this->expectExceptionMessage('The HasMany relation [tickets] cannot withdraw the delete operation: a relation accepts delete only through deletable(). Leave deletable() off instead.');

        HasMany::make('tickets', TicketResource::class)
            ->deletable()
            ->withoutOperations(MutationOperation::Delete);
    }

    #[Test]
    public function it_refuses_to_withdraw_an_operation_that_the_relation_does_not_accept(): void
    {
        $this->expectException(InvalidRelationException::class);
        $this->expectExceptionMessage('The HasMany relation [tickets] cannot withdraw the sync operation: it does not accept it. Withdraw only the operations it accepts [create, update, attach, detach].');

        HasMany::make('tickets', TicketResource::class)->withoutOperations(MutationOperation::Sync);
    }

    #[Test]
    public function it_refuses_to_withdraw_an_operation_from_a_through_relation(): void
    {
        $this->expectException(InvalidRelationException::class);
        $this->expectExceptionMessage('The HasOneThrough relation [leadSummary] cannot withdraw the detach operation: it accepts no operation.');

        HasOneThrough::make('leadSummary', TicketResource::class)->withoutOperations(MutationOperation::Detach);
    }
}
