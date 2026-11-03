<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FieldResolverTest extends PackageTestCase
{
    /** @param Closure(): Field $field */
    #[Test]
    #[DataProvider('resolvers')]
    public function it_makes_a_field_selectable_through_its_resolver(Closure $field, bool $selectable): void
    {
        $this->assertSame($selectable, $field()->isSelectable());
    }

    /** @return iterable<string, array{Closure(): Field, bool}> */
    public static function resolvers(): iterable
    {
        yield 'a virtual field' => [
            static fn (): Field => Text::make('label')->virtual(),
            false,
        ];
        yield 'a virtual field with a resolver' => [
            static fn (): Field => Text::make('label')
                ->virtual()
                ->resolveUsing(self::identify(...)),
            true,
        ];
        yield 'a resolver declared before virtual()' => [
            static fn (): Field => Text::make('label')
                ->resolveUsing(self::identify(...))
                ->virtual(),
            true,
        ];
        yield 'a resolver on a field declared selectable(false)' => [
            static fn (): Field => Text::make('label')
                ->virtual()
                ->resolveUsing(self::identify(...))
                ->selectable(false),
            false,
        ];
        yield 'a resolver on a write only field' => [
            static fn (): Field => Text::make('label')
                ->writeOnly()
                ->resolveUsing(self::identify(...)),
            false,
        ];
        yield 'a resolver on a column' => [
            static fn (): Field => Text::make('label')->resolveUsing(self::identify(...)),
            true,
        ];
    }

    #[Test]
    public function it_resolves_the_value_from_the_model_of_the_row(): void
    {
        $field = Text::make('label')
            ->virtual()
            ->resolveUsing(self::identify(...));

        $this->assertTrue($field->resolvesThroughCallback());
        $this->assertSame(Post::class.'#7', $field->resolve(new Post(['id' => 7])));
    }

    #[Test]
    public function it_refuses_to_resolve_a_field_without_a_resolver(): void
    {
        $this->expectExceptionObject(InvalidFieldException::unresolved('label'));

        Text::make('label')->resolve(new Post);
    }

    #[Test]
    public function it_refuses_a_resolver_on_a_pivot_field(): void
    {
        $this->expectExceptionObject(InvalidFieldException::resolvedPivotField('Tag::pivotFields()', 'note'));

        FieldSet::forPivot([Text::make('note')->resolveUsing(self::identify(...))], 'Tag::pivotFields()');
    }

    private static function identify(Model $model): string
    {
        return $model::class.'#'.$model->getKey();
    }
}
