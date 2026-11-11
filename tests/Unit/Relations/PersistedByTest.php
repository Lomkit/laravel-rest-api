<?php

namespace Lomkit\Rest\Tests\Unit\Relations;

use Lomkit\Rest\Exceptions\InvalidRelationException;
use Lomkit\Rest\Mutations\EloquentPivots;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\MorphedByMany;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\NotAPersister;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\RecordingPivots;
use Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\RefusingPivots;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PersistedByTest extends PackageTestCase
{
    /** @return array<string, array{class-string<BelongsToMany>, string, string}> */
    public static function refusals(): array
    {
        return [
            'a class that is not a persister' => [BelongsToMany::class, 'BelongsToMany', NotAPersister::class],
            'a missing class' => [MorphToMany::class, 'MorphToMany', 'App\Pivots\MissingPivots'],
            'a morphed by many relation' => [MorphedByMany::class, 'MorphedByMany', NotAPersister::class],
        ];
    }

    /** @param class-string<BelongsToMany> $type */
    #[Test]
    #[DataProvider('refusals')]
    public function it_refuses_a_class_that_is_not_a_pivot_persister(string $type, string $name, string $persister): void
    {
        $this->expectExceptionObject(InvalidRelationException::notAPivotPersister($name, 'tags', $persister));

        $type::make('tags', TagResource::class)->persistedBy($persister);
    }

    #[Test]
    public function it_persists_with_eloquent_pivots_by_default(): void
    {
        $this->assertInstanceOf(EloquentPivots::class, BelongsToMany::make('tags', TagResource::class)->getPivotPersister());
    }

    #[Test]
    public function it_resolves_the_persister_through_the_container(): void
    {
        $relation = BelongsToMany::make('tags', TagResource::class)->persistedBy(RecordingPivots::class);
        $first = $relation->getPivotPersister();
        $second = $relation->getPivotPersister();
        $this->app->bind(RecordingPivots::class, RefusingPivots::class);

        $this->assertSame([RecordingPivots::class, RecordingPivots::class, RefusingPivots::class], [$first::class, $second::class, $relation->getPivotPersister()::class]);
        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function it_refuses_a_binding_that_is_not_a_pivot_persister(): void
    {
        $relation = BelongsToMany::make('tags', TagResource::class)->persistedBy(RecordingPivots::class);
        $this->app->bind(RecordingPivots::class, NotAPersister::class);

        $this->expectExceptionObject(InvalidRelationException::notAPivotPersister('BelongsToMany', 'tags', RecordingPivots::class));

        $relation->getPivotPersister();
    }
}
