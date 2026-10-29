<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Carbon\CarbonImmutable;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InputValuesTest extends TestCase
{
    #[Test]
    public function it_binds_positions_to_the_fields_in_declared_order_and_casts_them(): void
    {
        $values = InputValues::fromPositional(FieldSet::of([Text::make('title'), Integer::make('min_views'), Boolean::make('featured')]), [42, '10', '1']);

        $this->assertSame(['title' => '42', 'min_views' => 10, 'featured' => true], $values->toArray());
        $this->assertSame(['42', 10, true], $values->arguments());
    }

    #[Test]
    public function it_casts_a_positional_date(): void
    {
        $values = InputValues::fromPositional(FieldSet::of([Date::make('since')]), ['2026-01-01']);

        $since = $values->arguments()[0];

        $this->assertInstanceOf(CarbonImmutable::class, $since);
        $this->assertSame('2026-01-01 00:00:00', $since->format('Y-m-d H:i:s'));
        $this->assertSame($since->format('Y-m-d H:i:s'), $values->toArray()['since']->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_fills_a_missing_position_with_its_cast_default(): void
    {
        $values = InputValues::fromPositional(FieldSet::of([Text::make('title'), Integer::make('min_views')->default('100')]), ['Hello']);

        $this->assertSame(['title' => 'Hello', 'min_views' => 100], $values->toArray());
        $this->assertSame(['Hello', 100], $values->arguments());
    }

    #[Test]
    public function it_prefers_a_given_null_over_the_default(): void
    {
        $values = InputValues::fromPositional(FieldSet::of([Integer::make('min_views')->nullable()->default(100)]), [null]);

        $this->assertSame(['min_views' => null], $values->toArray());
        $this->assertSame([null], $values->arguments());
    }

    #[Test]
    public function it_leaves_out_a_missing_position_without_default(): void
    {
        $values = InputValues::fromPositional(FieldSet::of([Text::make('title'), Integer::make('min_views')]), ['Hello']);

        $this->assertSame(['title' => 'Hello'], $values->toArray());
        $this->assertSame(['Hello'], $values->arguments());
    }

    #[Test]
    public function it_stops_the_arguments_at_the_first_missing_position_without_default(): void
    {
        $values = InputValues::fromPositional(
            FieldSet::of([Text::make('title'), Integer::make('min_views'), Boolean::make('featured')->default(true)]),
            ['Hello'],
        );

        $this->assertSame(['title' => 'Hello', 'featured' => true], $values->toArray());
        $this->assertSame(['Hello'], $values->arguments());
    }

    #[Test]
    public function it_ignores_positions_beyond_the_declared_fields(): void
    {
        $values = InputValues::fromPositional(FieldSet::of([Integer::make('min_views')]), ['10', '20']);

        $this->assertSame(['min_views' => 10], $values->toArray());
        $this->assertSame([10], $values->arguments());
    }

    #[Test]
    public function it_maps_named_values_in_declared_order_and_casts_them(): void
    {
        $values = InputValues::fromNamed(
            FieldSet::of([Boolean::make('featured'), Integer::make('min_views')]),
            [['name' => 'min_views', 'value' => '12'], ['name' => 'featured', 'value' => '0']],
        );

        $this->assertSame(['featured' => false, 'min_views' => 12], $values->toArray());
        $this->assertSame([false, 12], $values->arguments());
    }

    #[Test]
    public function it_fills_a_missing_named_value_with_its_default_and_leaves_out_the_others(): void
    {
        $values = InputValues::fromNamed(
            FieldSet::of([Boolean::make('featured')->default(true), Integer::make('min_views')->nullable(), Text::make('title')]),
            [['name' => 'title', 'value' => 'Hello']],
        );

        $this->assertSame(['featured' => true, 'title' => 'Hello'], $values->toArray());
    }

    #[Test]
    public function it_treats_an_entry_without_value_as_missing(): void
    {
        $values = InputValues::fromNamed(
            FieldSet::of([Boolean::make('featured')->default(true), Integer::make('min_views')]),
            [['name' => 'featured'], ['name' => 'min_views']],
        );

        $this->assertSame(['featured' => true], $values->toArray());
    }

    #[Test]
    public function it_ignores_named_values_of_undeclared_fields(): void
    {
        $values = InputValues::fromNamed(FieldSet::of([Integer::make('min_views')]), [['name' => 'unknown', 'value' => 'x'], ['name' => 'min_views', 'value' => 3]]);

        $this->assertSame(['min_views' => 3], $values->toArray());
    }

    #[Test]
    public function it_keeps_the_last_value_of_a_repeated_name(): void
    {
        $values = InputValues::fromNamed(FieldSet::of([Integer::make('min_views')]), [['name' => 'min_views', 'value' => 3], ['name' => 'min_views', 'value' => 5]]);

        $this->assertSame(['min_views' => 5], $values->toArray());
    }

    #[Test]
    public function it_has_no_values_without_fields(): void
    {
        $values = InputValues::fromPositional(FieldSet::of([]), ['ignored']);

        $this->assertSame([], $values->toArray());
        $this->assertSame([], $values->arguments());
        $this->assertSame([], InputValues::fromNamed(FieldSet::of([]), [['name' => 'ignored', 'value' => 1]])->toArray());
    }
}
