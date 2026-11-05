<?php

namespace Lomkit\Rest\Tests\Unit\Relations;

use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Relations\MorphTargets;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MorphTargetsTest extends TestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        Relation::morphMap([], false);

        parent::tearDown();
    }

    #[Test]
    public function it_holds_the_target_declared_on_a_morph_to_relation(): void
    {
        $targets = MorphTargets::of([BookResource::class], LabelResource::class, 'labelable');

        $this->assertSame([BookResource::class], $targets->resources());
        $this->assertSame([Book::class], $targets->modelClasses());
        $this->assertFalse($targets->isPolymorphic());
    }

    #[Test]
    public function it_names_each_target_type_as_the_morph_column_stores_it(): void
    {
        $this->assertSame([Book::class], MorphTargets::of([BookResource::class], LabelResource::class, 'labelable')->types());

        Relation::morphMap(['book' => Book::class], false);

        $this->assertSame(['book'], MorphTargets::of([BookResource::class], LabelResource::class, 'labelable')->types());
    }

    #[Test]
    public function it_maps_each_target_type_to_its_resource(): void
    {
        $this->assertSame([Book::class => BookResource::class], MorphTargets::of([BookResource::class], LabelResource::class, 'labelable')->byType());

        Relation::morphMap(['book' => Book::class], false);

        $this->assertSame(['book' => BookResource::class], MorphTargets::of([BookResource::class], LabelResource::class, 'labelable')->byType());
    }
}
