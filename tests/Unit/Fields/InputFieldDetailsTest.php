<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Closure;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\Details\InputFieldDetails;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\Priority;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\StartsWithCapital;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InputFieldDetailsTest extends TestCase
{
    #[Test]
    public function it_describes_a_plain_field(): void
    {
        $this->assertSame(
            ['name' => 'since', 'type' => 'date', 'nullable' => false, 'default' => null, 'rules' => ['date'], 'meta' => []],
            (new InputFieldDetails(Date::make('since')))->toArray(),
        );
    }

    #[Test]
    public function it_describes_nullable_default_rules_and_meta(): void
    {
        $field = Integer::make('min_views')->nullable()->default(100)->rules('min:0|max:1000')->meta(['label' => 'Minimum views']);

        $this->assertSame(
            [
                'name' => 'min_views',
                'type' => 'integer',
                'nullable' => true,
                'default' => 100,
                'rules' => ['nullable', 'integer', 'min:0', 'max:1000'],
                'meta' => ['label' => 'Minimum views'],
            ],
            (new InputFieldDetails($field))->toArray(),
        );
    }

    #[Test]
    public function it_adds_the_values_of_an_enum(): void
    {
        $this->assertSame(
            ['name' => 'status', 'type' => 'enum', 'nullable' => false, 'default' => 'draft', 'rules' => ['enum'], 'meta' => [], 'values' => ['draft', 'review', 'published']],
            (new InputFieldDetails(Enum::make('status', PostStatus::class)->default('draft')))->toArray(),
        );
        $this->assertSame([1, 2, 3], (new InputFieldDetails(Enum::make('priority', Priority::class)))->toArray()['values'] ?? null);
    }

    #[Test]
    public function it_describes_rule_objects_and_leaves_closures_and_write_rules_out(): void
    {
        $closure = function (string $attribute, mixed $value, Closure $fail): void {};
        $field = Text::make('title')->rules('required', new StartsWithCapital, $closure)->creationRules('unique:posts')->updateRules('sometimes');

        $this->assertSame(['string', 'required', 'starts_with_capital'], (new InputFieldDetails($field))->toArray()['rules']);
    }

    #[Test]
    public function it_describes_every_field_of_a_set_in_declared_order(): void
    {
        $fields = FieldSet::of([Boolean::make('featured')->default(true), Integer::make('min_views')->nullable()]);

        $this->assertSame(
            [
                ['name' => 'featured', 'type' => 'boolean', 'nullable' => false, 'default' => true, 'rules' => ['boolean'], 'meta' => []],
                ['name' => 'min_views', 'type' => 'integer', 'nullable' => true, 'default' => null, 'rules' => ['nullable', 'integer'], 'meta' => []],
            ],
            InputFieldDetails::forFields($fields),
        );
    }
}
