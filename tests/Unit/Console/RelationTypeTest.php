<?php

namespace Lomkit\Rest\Tests\Unit\Console;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Console\Generation\RelationType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RelationTypeTest extends TestCase
{
    /** @param  class-string<Relation<*, *, *>>  $relation */
    #[Test]
    #[DataProvider('relations')]
    public function it_maps_each_eloquent_relation_to_a_relation_of_the_package(string $relation, ?RelationType $type): void
    {
        $this->assertSame($type, RelationType::of($this->createStub($relation)));
    }

    /** @return array<string, array{class-string<Relation<*, *, *>>, RelationType|null}> */
    public static function relations(): array
    {
        return [
            'belongs to' => [BelongsTo::class, RelationType::BelongsTo],
            'belongs to many' => [BelongsToMany::class, RelationType::BelongsToMany],
            'has many' => [HasMany::class, RelationType::HasMany],
            'has many through' => [HasManyThrough::class, RelationType::HasManyThrough],
            'has one' => [HasOne::class, RelationType::HasOne],
            'has one through' => [HasOneThrough::class, RelationType::HasOneThrough],
            'morph many' => [MorphMany::class, RelationType::MorphMany],
            'morph one' => [MorphOne::class, RelationType::MorphOne],
            'morph to' => [MorphTo::class, null],
            'another relation' => [Relation::class, null],
        ];
    }

    #[Test]
    public function it_tells_a_polymorphic_many_to_many_relation_from_its_inverse(): void
    {
        $relation = $this->createStub(MorphToMany::class);
        $inverse = $this->createStub(MorphToMany::class);
        $relation->method('getInverse')->willReturn(false);
        $inverse->method('getInverse')->willReturn(true);

        $this->assertSame(RelationType::MorphToMany, RelationType::of($relation));
        $this->assertSame(RelationType::MorphedByMany, RelationType::of($inverse));
    }
}
