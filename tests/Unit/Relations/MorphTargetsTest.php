<?php

namespace Lomkit\Rest\Tests\Unit\Relations;

use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Relations\MorphTargets;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
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
        $targets = MorphTo::make('labelable', BookResource::class)->targets();

        $this->assertSame([BookResource::class], $targets->resources());
        $this->assertSame([Book::class], $targets->modelClasses());
        $this->assertFalse($targets->isPolymorphic());
    }

    #[Test]
    public function it_names_each_target_type_as_the_morph_column_stores_it(): void
    {
        $this->assertSame([Book::class], MorphTargets::of(BookResource::class)->types());

        Relation::morphMap(['book' => Book::class], false);

        $this->assertSame(['book'], MorphTargets::of(BookResource::class)->types());
    }
}
