<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherKind;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

#[Group('scout')]
class EngineQueryContractTest extends EngineQueryContractTestCase
{
    #[Test]
    #[DataProvider('unsupportedComparisons')]
    public function it_refuses_an_operator_outside_the_comparisons_of_the_attribute(string $attribute, string $operator, mixed $value): void
    {
        $this->expectExceptionObject(InvalidEngineQueryException::unsupportedOperator($attribute, $operator));

        $this->engineQuery()->where($attribute, $operator, $value);
    }

    /** @return array<string, array{string, string, mixed}> */
    public static function unsupportedComparisons(): array
    {
        return [
            'a misspelled operator' => ['price', '=>', 0],
            'a pattern' => ['code', 'like', 'x'],
            'a range on a text' => ['code', '>', 'V-a'],
            'a range on a boolean' => ['active', '<', true],
            'a membership' => ['price', 'in', 1],
            'a nullity' => ['price', 'is null', null],
            'an upper case pattern' => ['code', 'LIKE', 'x'],
        ];
    }

    #[Test]
    public function it_refuses_a_membership_on_an_attribute_without_lists(): void
    {
        $this->expectExceptionObject(InvalidEngineQueryException::unsupportedOperator('active', 'in'));

        $this->engineQuery()->whereIn('active', [true]);
    }

    #[Test]
    #[DataProvider('invalidValues')]
    public function it_refuses_a_value_the_attribute_cannot_take(string $attribute, mixed $value): void
    {
        $this->expectExceptionObject(InvalidEngineQueryException::invalidValue($attribute, $value));

        $this->engineQuery()->where($attribute, '=', $value);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidValues(): array
    {
        return [
            'null' => ['price', null],
            'a list' => ['price', [1]],
            'an object' => ['code', new stdClass],
            'an infinite number' => ['price', INF],
            'not a number' => ['price', NAN],
            'an overflowing number' => ['price', '1e400'],
            'a fraction for an integer' => ['number', 3.5],
            'a word for an integer' => ['number', 'three'],
            'a word for a boolean' => ['active', 'yes'],
            'a boolean for a text' => ['code', true],
            'a float for a text' => ['code', 1.5],
            'a case of another enum' => ['kind', FilterOperator::Equal],
            'a value outside the enum' => ['kind', 'refund'],
        ];
    }

    #[Test]
    public function it_refuses_a_null_value_in_a_list(): void
    {
        $this->expectExceptionObject(InvalidEngineQueryException::invalidValue('number', null));

        $this->engineQuery()->whereIn('number', [1, null]);
    }

    #[Test]
    public function it_refuses_an_attribute_that_is_not_declared(): void
    {
        $this->expectExceptionObject(InvalidEngineQueryException::undeclaredAttribute('title'));

        $this->engineQuery()->whereNotIn('title', ['Budget']);
    }

    #[Test]
    public function it_hands_the_engine_the_typed_value_of_each_attribute(): void
    {
        $this->engineQuery()
            ->where('id', '=', '4')
            ->where('number', FilterOperator::NotEqual, '2')
            ->where('price', '>', '2.0')
            ->where('price', '<', '40.5')
            ->where('active', '=', 0)
            ->where('code', '!=', 7)
            ->where('kind', '=', VoucherKind::Credit)
            ->whereIn('kind', ['debit', VoucherKind::Credit])
            ->page(10, 1);

        $this->assertSame([
            ['field' => 'id', 'operator' => '=', 'value' => 4],
            ['field' => 'number', 'operator' => '!=', 'value' => 2],
            ['field' => 'price', 'operator' => '>', 'value' => 2],
            ['field' => 'price', 'operator' => '<', 'value' => 40.5],
            ['field' => 'active', 'operator' => '=', 'value' => false],
            ['field' => 'code', 'operator' => '!=', 'value' => '7'],
            ['field' => 'kind', 'operator' => '=', 'value' => 'credit'],
        ], $this->engine->searches[0]->wheres);
        $this->assertSame(['kind' => ['debit', 'credit']], $this->engine->searches[0]->whereIns);
    }

    #[Test]
    public function it_accepts_a_perimeter_attribute_that_clients_cannot_filter(): void
    {
        $engine = ScoutEngine::make()
            ->filterable(['price'])
            ->perimeter(fn (EngineQuery $query): EngineQuery => $query->where('team', '=', 'red'), [Text::make('team')]);
        $target = $this->target(VoucherResource::class);

        $engine->query(new TextQuery('report'), $target, $this->context()->request)->page(10, 1);

        $this->assertSame([['field' => 'team', 'operator' => '=', 'value' => 'red']], $this->engine->searches[0]->wheres);
        $this->assertSame(['price'], array_keys($engine->capabilities($target)->engine->filters ?? []));
    }
}
