<?php

namespace Lomkit\Rest\Tests\Feature\Fields;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class DeclaredCapabilitiesTest extends PackageTestCase
{
    #[Test]
    public function it_neither_sorts_nor_filters_a_write_only_field(): void
    {
        $field = Text::make('summary')->writeOnly();

        $this->assertFalse($field->isSortable());
        $this->assertSame([], $field->filterOperators());
    }

    #[Test]
    public function it_sorts_and_filters_a_write_only_field_through_its_callbacks(): void
    {
        $field = Text::make('summary')->writeOnly()
            ->sortUsing(fn (Builder $query, string $direction) => $query->orderBy('title', $direction))
            ->filterUsing(fn (Builder $query, string $operator, mixed $value) => $query->where('title', $operator, $value));

        $this->assertTrue($field->isSortable());
        $this->assertTrue($field->isFilterable());
    }

    /** @param Closure(): Field $field */
    #[Test]
    #[DataProvider('undeclaredOperators')]
    public function it_rejects_an_operator_the_field_does_not_declare(Closure $field, string $operator, string $message): void
    {
        $this->expectExceptionObject(new InvalidFieldException($message));

        $field()->filter(Post::query(), $operator, 'Annual report');
    }

    /** @return array<string, array{Closure(): Field, string, string}> */
    public static function undeclaredOperators(): array
    {
        return [
            'narrowed filter' => [fn (): Field => Text::make('title')->filterable(['=']), 'like', 'The field [title] does not support the operator [like].'],
            'filtering turned off' => [fn (): Field => Text::make('title')->filterable(false), '=', 'The field [title] does not support the operator [=].'],
            'write only field' => [fn (): Field => Text::make('summary')->writeOnly(), '=', 'The field [summary] does not support the operator [=].'],
        ];
    }

    /** @param Closure(): Field $field */
    #[Test]
    #[DataProvider('undeclaredOperators')]
    public function it_gives_no_value_rules_for_an_operator_the_field_does_not_declare(Closure $field, string $operator, string $message): void
    {
        $this->expectExceptionObject(new InvalidFieldException($message));

        $field()->filterValueRules($operator, 'search.filters.0.value');
    }

    #[Test]
    public function it_refuses_an_undeclared_operator_before_running_a_custom_filter(): void
    {
        $ran = false;
        $field = Text::make('full_name')->virtual()->filterable(['=', 'like'])
            ->filterUsing(function () use (&$ran): void {
                $ran = true;
            });

        try {
            $field->filter(Post::query(), 'in', ['Ada']);
            $this->fail('The operator was not refused.');
        } catch (InvalidFieldException $exception) {
            $this->assertSame('The field [full_name] does not support the operator [in].', $exception->getMessage());
        }

        $this->assertFalse($ran);
    }

    #[Test]
    public function it_keeps_apply_filter_available_on_a_field_that_is_not_filterable(): void
    {
        $query = Post::query();

        Text::make('title')->filterable(false)->applyFilter($query, '=', 'Annual report');

        $this->assertSame(
            [['type' => 'Basic', 'column' => 'posts.title', 'operator' => '=', 'value' => 'Annual report', 'boolean' => 'and']],
            $query->getQuery()->wheres,
        );
    }

    /** @param Closure(): Field $field */
    #[Test]
    #[DataProvider('unsortableFields')]
    public function it_rejects_sorting_a_field_that_is_not_sortable(Closure $field, string $message): void
    {
        $this->expectExceptionObject(new InvalidFieldException($message));

        $field()->sort(Post::query(), 'asc');
    }

    /** @return array<string, array{Closure(): Field, string}> */
    public static function unsortableFields(): array
    {
        return [
            'json' => [fn (): Field => Json::make('metadata'), 'The field [metadata] is not sortable.'],
            'sorting turned off' => [fn (): Field => Text::make('title')->sortable(false), 'The field [title] is not sortable.'],
            'write only field' => [fn (): Field => Text::make('summary')->writeOnly(), 'The field [summary] is not sortable.'],
        ];
    }
}
