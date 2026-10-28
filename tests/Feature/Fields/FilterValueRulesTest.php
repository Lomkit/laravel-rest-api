<?php

namespace Lomkit\Rest\Tests\Feature\Fields;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FilterValueRulesTest extends PackageTestCase
{
    /**
     * @param  Closure(): Field  $field
     * @param  array<string, mixed>  $input
     */
    #[Test]
    #[DataProvider('values')]
    public function it_validates_the_filter_value_for_the_operator_and_the_field_type(Closure $field, string $operator, array $input, bool $passes): void
    {
        $validator = Validator::make($input, $field()->filterValueRules($operator, 'value'));

        $this->assertSame($passes, $validator->passes());
    }

    /** @return iterable<string, array{Closure(): Field, string, array<string, mixed>, bool}> */
    public static function values(): iterable
    {
        $views = fn (): Field => Integer::make('views');
        $publishedOn = fn (): Field => Date::make('published_on');
        $title = fn (): Field => Text::make('title');
        $summary = fn (): Field => Text::make('summary')->nullable();
        $status = fn (): Field => Enum::make('status', PostStatus::class);
        $uuid = fn (): Field => ID::make('id')->keyType('uuid');

        yield 'in with a scalar' => [$views, 'in', ['value' => 10], false];
        yield 'in with an empty list' => [$views, 'in', ['value' => []], false];
        yield 'in with integers' => [$views, 'in', ['value' => [10, 250]], true];
        yield 'in with a non integer item' => [$views, 'in', ['value' => [10, 'many']], false];
        yield 'not in with a keyed array' => [$views, 'not in', ['value' => ['first' => 10]], false];
        yield 'between with one date' => [$publishedOn, 'between', ['value' => ['2026-01-01']], false];
        yield 'between with three dates' => [$publishedOn, 'between', ['value' => ['2026-01-01', '2026-02-01', '2026-03-01']], false];
        yield 'between with two dates' => [$publishedOn, 'between', ['value' => ['2026-01-01', '2026-03-01']], true];
        yield 'not between with a non date item' => [$publishedOn, 'not between', ['value' => ['2026-01-01', 'someday']], false];
        yield 'between with a scalar' => [fn (): Field => Number::make('rating'), 'between', ['value' => 2.5], false];
        yield 'like with an array' => [$title, 'like', ['value' => ['%a%']], false];
        yield 'like with a string' => [$title, 'like', ['value' => '%a%'], true];
        yield 'comparison with an empty string' => [$views, '<', ['value' => ''], false];
        yield 'comparison with blanks' => [$publishedOn, '>=', ['value' => '   '], false];
        yield 'in with an empty string item' => [$views, 'in', ['value' => [10, '']], false];
        yield 'between with empty strings' => [$publishedOn, 'between', ['value' => ['', '']], false];
        yield 'comparison with null on a type without base rules' => [fn (): Field => new class('code') extends Field
        {
            public function operators(): array
            {
                return ['='];
            }

            public function baseRules(): array
            {
                return [];
            }
        }, '=', ['value' => null], false];
        yield 'ilike with a string' => [$title, 'ilike', ['value' => 'BETA%'], true];
        yield 'not ilike with a number' => [$title, 'not ilike', ['value' => 12], false];
        yield 'is null with a value' => [$summary, 'is null', ['value' => 'First look'], false];
        yield 'is null without a value' => [$summary, 'is null', [], true];
        yield 'is null with a null value' => [$summary, 'is null', ['value' => null], true];
        yield 'is not null with a value' => [$summary, 'is not null', ['value' => true], false];
        yield 'equal to null on text' => [$title, '=', ['value' => null], false];
        yield 'equal to null on number' => [fn (): Field => Number::make('rating'), '=', ['value' => null], false];
        yield 'equal to null on enum' => [$status, '=', ['value' => null], false];
        yield 'equal to null on a nullable field' => [$summary, '=', ['value' => null], false];
        yield 'comparison without a value' => [$title, '!=', [], false];
        yield 'enum value outside the cases' => [$status, '=', ['value' => 'archived'], false];
        yield 'enum value among the cases' => [$status, '!=', ['value' => 'review'], true];
        yield 'enum item outside the cases' => [$status, 'in', ['value' => ['draft', 'archived']], false];
        yield 'enum class read from the model cast' => [fn (): Field => tap(Enum::make('status'), fn (Enum $field) => $field->bind(new Post)), '=', ['value' => 'archived'], false];
        yield 'uuid on a uuid key' => [$uuid, '=', ['value' => 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d'], true];
        yield 'non uuid on a uuid key' => [$uuid, '=', ['value' => '42'], false];
        yield 'uuid items on a uuid key' => [$uuid, 'not in', ['value' => ['a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d', '42']], false];
        yield 'integer on the default key' => [fn (): Field => ID::make('id'), '=', ['value' => 42], true];
        yield 'word on a boolean' => [fn (): Field => Boolean::make('featured'), '=', ['value' => 'yes'], false];
        yield 'integer greater than a decimal' => [$views, '>', ['value' => 2.5], false];
    }

    /**
     * @param  Closure(): Field  $field
     * @param  array<string, list<string>>  $expected
     */
    #[Test]
    #[DataProvider('ruleSets')]
    public function it_returns_the_rules_of_each_condition_family(Closure $field, FilterOperator|string $operator, array $expected): void
    {
        $this->assertSame($expected, $field()->filterValueRules($operator, 'filters.0.value'));
    }

    /** @return iterable<string, array{Closure(): Field, FilterOperator|string, array<string, list<string>>}> */
    public static function ruleSets(): iterable
    {
        $comparison = ['filters.0.value' => ['required', 'integer']];
        $pattern = ['filters.0.value' => ['required', 'string']];
        $membership = ['filters.0.value' => ['present', 'array', 'list', 'min:1'], 'filters.0.value.*' => ['required', 'ulid']];
        $range = ['filters.0.value' => ['present', 'array', 'list', 'size:2'], 'filters.0.value.*' => ['required', 'date']];
        $nullity = ['filters.0.value' => ['prohibited']];

        yield 'comparison' => [fn (): Field => Integer::make('views'), '>=', $comparison];
        yield 'pattern' => [fn (): Field => Text::make('title'), 'not like', $pattern];
        yield 'case insensitive pattern' => [fn (): Field => Text::make('title'), FilterOperator::ILike, $pattern];
        yield 'membership' => [fn (): Field => ID::make('id')->keyType('ulid'), 'NOT IN', $membership];
        yield 'range' => [fn (): Field => Date::make('published_on'), FilterOperator::Between, $range];
        yield 'nullity' => [fn (): Field => Text::make('summary')->nullable(), 'is not null', $nullity];
    }

    #[Test]
    public function it_uses_the_enum_rule_as_the_type_rule_of_an_enum(): void
    {
        $rules = Enum::make('status', PostStatus::class)->filterValueRules('in', 'value');

        $this->assertEquals([
            'value' => ['present', 'array', 'list', 'min:1'],
            'value.*' => ['required', Rule::enum(PostStatus::class)],
        ], $rules);
    }

    #[Test]
    public function it_rejects_an_unknown_operator(): void
    {
        $this->expectException(InvalidFieldException::class);
        $this->expectExceptionMessage('Unknown filter operator [contains].');

        Text::make('title')->filterValueRules('contains', 'value');
    }
}
