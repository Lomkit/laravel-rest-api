<?php

namespace Lomkit\Rest\Tests\Unit\Relations;

use Lomkit\Rest\Contracts\OwnerRelation;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Mutations\ChildWrites;
use Lomkit\Rest\Mutations\NoWrites;
use Lomkit\Rest\Mutations\OwnerWrites;
use Lomkit\Rest\Mutations\PivotWrites;
use Lomkit\Rest\Mutations\RelationWrites;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasManyThrough;
use Lomkit\Rest\Relations\HasOne;
use Lomkit\Rest\Relations\HasOneOfMany;
use Lomkit\Rest\Relations\HasOneOrMany;
use Lomkit\Rest\Relations\HasOneOrManyThrough;
use Lomkit\Rest\Relations\HasOneThrough;
use Lomkit\Rest\Relations\MorphedByMany;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Relations\MorphOne;
use Lomkit\Rest\Relations\MorphOneOfMany;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncedHasMany;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\SyncedMorphTo;
use Lomkit\Rest\Tests\Feature\Search\Stubs\NewsDeskResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RelationHierarchyTest extends PackageTestCase
{
    private const array DETAILS = ['resource', 'relation', 'constraints', 'name'];

    private const array WRITES = [
        BelongsTo::class => OwnerWrites::class,
        HasOneOrMany::class => ChildWrites::class,
        BelongsToMany::class => PivotWrites::class,
        HasOneOrManyThrough::class => NoWrites::class,
    ];

    /** @return array<string, array{class-string<Relation>, class-string<Relation>, class-string<Relation>, class-string<Resource>, string}> */
    public static function relations(): array
    {
        return [
            'belongs to' => [BelongsTo::class, Relation::class, BelongsTo::class, LoanResource::class, 'book'],
            'morph to' => [MorphTo::class, BelongsTo::class, BelongsTo::class, LabelResource::class, 'labelable'],
            'has one' => [HasOne::class, HasOneOrMany::class, HasOneOrMany::class, BookResource::class, 'loan'],
            'has one of many' => [HasOneOfMany::class, HasOne::class, HasOneOrMany::class, BookResource::class, 'latestLoan'],
            'has many' => [HasMany::class, HasOneOrMany::class, HasOneOrMany::class, TeamResource::class, 'tickets'],
            'morph one' => [MorphOne::class, HasOneOrMany::class, HasOneOrMany::class, BookResource::class, 'label'],
            'morph one of many' => [MorphOneOfMany::class, MorphOne::class, HasOneOrMany::class, BookResource::class, 'latestLabel'],
            'morph many' => [MorphMany::class, HasOneOrMany::class, HasOneOrMany::class, BookResource::class, 'labels'],
            'belongs to many' => [BelongsToMany::class, Relation::class, BelongsToMany::class, BookResource::class, 'shelves'],
            'morph to many' => [MorphToMany::class, BelongsToMany::class, BelongsToMany::class, TicketResource::class, 'labels'],
            'morphed by many' => [MorphedByMany::class, MorphToMany::class, BelongsToMany::class, TagResource::class, 'labelledTickets'],
            'has one through' => [HasOneThrough::class, HasOneOrManyThrough::class, HasOneOrManyThrough::class, NewsDeskResource::class, 'leadSummary'],
            'has many through' => [HasManyThrough::class, HasOneOrManyThrough::class, HasOneOrManyThrough::class, TeamResource::class, 'replies'],
        ];
    }

    /**
     * @param  class-string<Relation>  $class
     * @param  class-string<Relation>  $parent
     * @param  class-string<Relation>  $base
     * @param  class-string<Resource>  $resource
     */
    #[Test]
    #[DataProvider('relations')]
    public function it_declares_each_relation_class_under_its_parent_and_its_base(string $class, string $parent, string $base, string $resource, string $name): void
    {
        $this->assertSame($parent, get_parent_class($class));
        $this->assertSame(Relation::class, get_parent_class($base));
        $this->assertTrue(is_a($class, $base, true));
        $this->assertInstanceOf($class, $this->declared($resource, $name));
    }

    /**
     * @param  class-string<Relation>  $class
     * @param  class-string<Relation>  $parent
     * @param  class-string<Relation>  $base
     * @param  class-string<Resource>  $resource
     */
    #[Test]
    #[DataProvider('relations')]
    public function it_writes_the_relations_under_one_base_with_one_strategy(string $class, string $parent, string $base, string $resource, string $name): void
    {
        $writes = RelationWrites::of($resource::newModel(), $this->declared($resource, $name), []);

        $this->assertInstanceOf(self::WRITES[$base], $writes);
    }

    /**
     * @param  class-string<Relation>  $class
     * @param  class-string<Relation>  $parent
     * @param  class-string<Relation>  $base
     * @param  class-string<Resource>  $resource
     */
    #[Test]
    #[DataProvider('relations')]
    public function it_implements_the_contracts_of_its_base(string $class, string $parent, string $base, string $resource, string $name): void
    {
        $relation = $this->declared($resource, $name);

        $this->assertSame($base === BelongsTo::class, $relation instanceof OwnerRelation);
        $this->assertSame($base === BelongsToMany::class, $relation instanceof PivotRelation);
        $this->assertSame($class === MorphTo::class, $relation instanceof PolymorphicRelation);
    }

    /**
     * @param  class-string<Relation>  $class
     * @param  class-string<Relation>  $parent
     * @param  class-string<Relation>  $base
     * @param  class-string<Resource>  $resource
     */
    #[Test]
    #[DataProvider('relations')]
    public function it_lists_the_operations_and_the_entries_of_the_relation(string $class, string $parent, string $base, string $resource, string $name): void
    {
        $relation = $this->declared($resource, $name);
        $many = [HasMany::class, MorphMany::class, BelongsToMany::class, MorphToMany::class, MorphedByMany::class, HasManyThrough::class];
        $operations = $base === BelongsToMany::class
            ? [MutationOperation::Create, MutationOperation::Update, MutationOperation::Attach, MutationOperation::Detach, MutationOperation::Sync, MutationOperation::Toggle]
            : [MutationOperation::Create, MutationOperation::Update, MutationOperation::Attach, MutationOperation::Detach];

        $this->assertSame($operations, $relation->mutationOperations());
        $this->assertSame(in_array($class, $many, true), $relation->hasMultipleEntries());
    }

    /**
     * @param  class-string<Relation>  $class
     * @param  class-string<Relation>  $parent
     * @param  class-string<Relation>  $base
     * @param  class-string<Resource>  $resource
     */
    #[Test]
    #[DataProvider('relations')]
    public function it_accepts_delete_on_a_child_relation_that_opts_in_only(string $class, string $parent, string $base, string $resource, string $name): void
    {
        $relation = $this->declared($resource, $name);
        $operations = $relation->mutationOperations();

        $this->assertSame($operations, $relation->acceptedOperations());

        $deletable = $relation instanceof HasOneOrMany ? $relation->deletable() : $relation;

        $this->assertSame($base === HasOneOrMany::class ? [...$operations, MutationOperation::Delete] : $operations, $deletable->acceptedOperations());
    }

    #[Test]
    public function it_accepts_delete_through_deletable_only(): void
    {
        $operations = [MutationOperation::Create, MutationOperation::Update, MutationOperation::Attach, MutationOperation::Detach, MutationOperation::Sync, MutationOperation::Toggle];

        $this->assertSame($operations, SyncedHasMany::make('tickets', TicketResource::class)->acceptedOperations());
        $this->assertSame($operations, SyncedMorphTo::make('labelable', BookResource::class)->acceptedOperations());
        $this->assertSame([...$operations, MutationOperation::Delete], SyncedHasMany::make('tickets', TicketResource::class)->deletable()->acceptedOperations());
    }

    /**
     * @param  class-string<Relation>  $class
     * @param  class-string<Relation>  $parent
     * @param  class-string<Relation>  $base
     * @param  class-string<Resource>  $resource
     */
    #[Test]
    #[DataProvider('relations')]
    public function it_prohibits_the_entries_of_a_through_relation_only(string $class, string $parent, string $base, string $resource, string $name): void
    {
        $relation = $this->declared($resource, $name);
        $rules = $base === HasOneOrManyThrough::class ? ['mutate.0.relations.'.$name => 'prohibited'] : [];

        $this->assertSame($rules, $relation->rules($relation->parentResource(), 'mutate.0.relations.'.$name));
    }

    /**
     * @param  class-string<Relation>  $class
     * @param  class-string<Relation>  $parent
     * @param  class-string<Relation>  $base
     * @param  class-string<Resource>  $resource
     */
    #[Test]
    #[DataProvider('relations')]
    public function it_details_and_reserves_the_keys_of_the_relation(string $class, string $parent, string $base, string $resource, string $name): void
    {
        $relation = $this->declared($resource, $name);
        [$details, $reserved] = match (true) {
            $base === BelongsToMany::class => [[...self::DETAILS, 'pivot_fields'], ['pivot']],
            $class === MorphTo::class => [['relation', 'constraints', 'name', 'types', 'morph'], ['@type']],
            default => [self::DETAILS, []],
        };

        $this->assertSame($details, array_keys($relation->toDetails()));
        $this->assertSame($reserved, $relation->reservedKeys());
    }

    /** @param class-string<Resource> $resource */
    private function declared(string $resource, string $name): Relation
    {
        $relation = (new RestContext(new RestRequest, $this->app))
            ->resource($resource)
            ->definition()
            ->relation($name);

        $this->assertNotNull($relation);

        return $relation;
    }
}
