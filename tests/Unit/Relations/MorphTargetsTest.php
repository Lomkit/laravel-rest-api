<?php

namespace Lomkit\Rest\Tests\Unit\Relations;

use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Relations\MorphTarget;
use Lomkit\Rest\Relations\MorphTargets;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MorphTargetsTest extends TestCase
{
    private const array TARGETS = [BookResource::class, ShelfResource::class];

    #[\Override]
    protected function tearDown(): void
    {
        Relation::morphMap([], false);

        parent::tearDown();
    }

    #[Test]
    public function it_keys_each_target_by_the_morph_class_of_its_model(): void
    {
        $classes = MorphTargets::of(self::TARGETS, LabelResource::class, 'labelable');
        Relation::morphMap(['book' => Book::class, 'shelf' => Shelf::class], false);
        $aliases = MorphTargets::of(self::TARGETS, LabelResource::class, 'labelable');

        $this->assertSame([Book::class, Shelf::class], $classes->types());
        $this->assertSame(['book' => BookResource::class, 'shelf' => ShelfResource::class], $aliases->byType());
        $this->assertSame(self::TARGETS, $aliases->resources());
        $this->assertSame([Book::class, Shelf::class], $aliases->modelClasses());
        $this->assertSame(['book', 'shelf'], array_keys(iterator_to_array($aliases)));
        $this->assertTrue($aliases->isPolymorphic());
    }

    #[Test]
    #[DataProvider('clientTypes')]
    public function it_resolves_a_type_against_the_declared_targets_only(mixed $type, ?string $resolved): void
    {
        Relation::morphMap(['book' => Book::class, 'shelf' => Shelf::class], false);

        $this->assertSame($resolved, MorphTargets::of(self::TARGETS, LabelResource::class, 'labelable')->forType($type)?->type);
    }

    /** @return array<string, array{mixed, string|null}> */
    public static function clientTypes(): array
    {
        return [
            'a declared type' => ['shelf', 'shelf'],
            'no type with several targets' => [null, null],
            'the class of a mapped model' => [Book::class, null],
            'another type' => ['comment', null],
            'a type that differs by case' => ['Book', null],
            'a value that is not a string' => [1, null],
        ];
    }

    #[Test]
    public function it_resolves_the_one_target_of_a_relation_without_a_type(): void
    {
        $single = MorphTargets::of([BookResource::class], LabelResource::class, 'labelable');
        $several = MorphTargets::of(self::TARGETS, LabelResource::class, 'labelable');

        $this->assertEquals(new MorphTarget(Book::class, BookResource::class), $single->forType(null));
        $this->assertEquals(new MorphTarget(Book::class, BookResource::class), $single->single());
        $this->assertNull($several->single());
        $this->assertFalse($single->isPolymorphic());
    }

    #[Test]
    public function it_resolves_the_target_of_a_model_by_its_morph_class(): void
    {
        Relation::morphMap(['book' => Book::class, 'shelf' => Shelf::class], false);
        $targets = MorphTargets::of(self::TARGETS, LabelResource::class, 'labelable');

        $this->assertSame(['shelf', null], [$targets->forModel(new Shelf)?->type, $targets->forModel(new Loan)?->type]);
    }

    /** @param array<array-key, mixed> $resources */
    #[Test]
    #[DataProvider('invalidDeclarations')]
    public function it_refuses_a_declaration_that_does_not_name_each_target_once(array $map, array $resources, string $message): void
    {
        Relation::morphMap($map, false);

        $this->expectExceptionObject(new InvalidResourceException($message));

        MorphTargets::of($resources, LabelResource::class, 'labelable');
    }

    /** @return array<string, array{array<string, class-string>, array<array-key, mixed>, string}> */
    public static function invalidDeclarations(): array
    {
        $label = 'The MorphTo relation [labelable] of the resource ['.LabelResource::class.']';
        $list = $label.' declares its targets with a resource class or a list of resource classes.';

        return [
            'no target' => [[], [], $list],
            'a keyed list' => [[], ['book' => BookResource::class], $list],
            'a class that is not a resource' => [[], [Book::class], $list],
            'a model declared twice' => [[], [BookResource::class, BookResource::class], $label.' declares two targets of the morph type ['.Book::class.']. Declare each target model once.'],
            'a type with a star' => [['bo*k' => Book::class], [BookResource::class], $label.' declares a target of the morph type [bo*k], which contains * or ends with a backslash. Map the model to another alias in the morph map.'],
            'a type that ends with a backslash' => [['book\\' => Book::class], [BookResource::class], $label.' declares a target of the morph type [book\\], which contains * or ends with a backslash. Map the model to another alias in the morph map.'],
        ];
    }
}
