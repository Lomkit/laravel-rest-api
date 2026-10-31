<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Fields\WriteOperation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldCapabilitiesTest extends TestCase
{
    #[Test]
    #[DataProvider('selectability')]
    public function it_is_selectable_only_when_selectable_not_write_only_and_not_virtual(bool $selectable, bool $writeOnly, bool $virtual, bool $expected): void
    {
        $field = Text::make('title')->selectable($selectable)->writeOnly($writeOnly)->virtual($virtual);

        $this->assertSame($expected, $field->isSelectable());
    }

    /** @return array<string, array{bool, bool, bool, bool}> */
    public static function selectability(): array
    {
        return [
            'selectable' => [true, false, false, true],
            'selectable and write only' => [true, true, false, false],
            'selectable and virtual' => [true, false, true, false],
            'selectable, write only and virtual' => [true, true, true, false],
            'not selectable' => [false, false, false, false],
            'not selectable and write only' => [false, true, false, false],
            'not selectable and virtual' => [false, false, true, false],
            'not selectable, write only and virtual' => [false, true, true, false],
        ];
    }

    #[Test]
    public function it_is_selectable_by_default(): void
    {
        $this->assertTrue(Text::make('title')->isSelectable());
        $this->assertTrue(Json::make('metadata')->isSelectable());
    }

    #[Test]
    #[DataProvider('sortability')]
    public function it_is_sortable_from_its_flag_its_custom_sort_and_its_storage(string $class, ?bool $sortable, bool $sortUsing, bool $virtual, bool $expected, bool $byColumn): void
    {
        $field = $class::make('column')->virtual($virtual);

        if ($sortable !== null) {
            $field->sortable($sortable);
        }

        if ($sortUsing) {
            $field->sortUsing(fn (Builder $query, string $direction) => $query->orderBy('column', $direction));
        }

        $this->assertSame($expected, $field->isSortable());
        $this->assertSame($byColumn, $field->isSortableByColumn());
    }

    /** @return array<string, array{class-string<Field>, bool|null, bool, bool, bool, bool}> */
    public static function sortability(): array
    {
        return [
            'text by default' => [Text::class, null, false, false, true, true],
            'text sortable' => [Text::class, true, false, false, true, true],
            'text not sortable' => [Text::class, false, false, false, false, false],
            'virtual text' => [Text::class, null, false, true, false, false],
            'virtual text marked sortable' => [Text::class, true, false, true, false, false],
            'text with a custom sort' => [Text::class, null, true, false, true, true],
            'text not sortable with a custom sort' => [Text::class, false, true, false, false, false],
            'virtual text with a custom sort' => [Text::class, null, true, true, true, false],
            'virtual text not sortable with a custom sort' => [Text::class, false, true, true, false, false],
            'json by default' => [Json::class, null, false, false, false, false],
            'json sortable' => [Json::class, true, false, false, true, true],
            'json with a custom sort' => [Json::class, null, true, false, true, false],
            'virtual json marked sortable' => [Json::class, true, false, true, false, false],
        ];
    }

    #[Test]
    #[DataProvider('writability')]
    public function it_is_writable_on_each_operation_from_its_flags(Field $field, bool $creatable, bool $updatable): void
    {
        $this->assertSame($creatable, $field->isWritableOn(WriteOperation::Create));
        $this->assertSame($updatable, $field->isWritableOn(WriteOperation::Update));
    }

    /** @return array<string, array{Field, bool, bool}> */
    public static function writability(): array
    {
        return [
            'text by default' => [Text::make('title'), true, true],
            'readonly text' => [Text::make('title')->readonly(), false, false],
            'id with readonly turned off' => [ID::make('id')->readonly(false), true, true],
            'text not creatable' => [Text::make('title')->creatable(false), false, true],
            'text not updatable' => [Text::make('title')->updatable(false), true, false],
            'readonly text made creatable' => [Text::make('title')->readonly()->creatable(), true, false],
            'readonly text made updatable' => [Text::make('title')->readonly()->updatable(), false, true],
            'creatable text made readonly' => [Text::make('title')->creatable()->readonly(), false, false],
            'write only text' => [Text::make('title')->writeOnly(), true, true],
            'virtual text' => [Text::make('title')->virtual(), false, false],
            'virtual text marked creatable and updatable' => [Text::make('title')->virtual()->creatable()->updatable(), false, false],
            'virtual text with a fill callback' => [Text::make('title')->virtual()->fillUsing(self::fillNothing(...)), true, true],
            'readonly virtual text with a fill callback' => [Text::make('title')->virtual()->fillUsing(self::fillNothing(...))->readonly(), false, false],
            'virtual text with a fill callback not updatable' => [Text::make('title')->virtual()->fillUsing(self::fillNothing(...))->updatable(false), true, false],
            'id by default' => [ID::make('id'), false, false],
            'creatable id' => [ID::make('id')->creatable(), true, false],
            'updatable id' => [ID::make('id')->updatable(), false, true],
            'creatable and updatable id' => [ID::make('id')->creatable()->updatable(), true, true],
            'virtual creatable id' => [ID::make('id')->virtual()->creatable(), false, false],
        ];
    }

    #[Test]
    public function it_filters_and_sorts_through_a_callback_only_when_one_is_declared(): void
    {
        $this->assertFalse(Text::make('title')->filtersThroughCallback());
        $this->assertFalse(Text::make('title')->sortsThroughCallback());
        $this->assertTrue(Text::make('title')->filterUsing(fn (Builder $query): Builder => $query)->filtersThroughCallback());
        $this->assertFalse(Text::make('title')->filterUsing(fn (Builder $query): Builder => $query)->sortsThroughCallback());
        $this->assertTrue(Text::make('title')->sortUsing(fn (Builder $query): Builder => $query)->sortsThroughCallback());
        $this->assertFalse(Text::make('title')->sortUsing(fn (Builder $query): Builder => $query)->filtersThroughCallback());
    }

    #[Test]
    public function it_is_write_only_only_when_asked(): void
    {
        $this->assertFalse(Text::make('password')->isWriteOnly());
        $this->assertTrue(Text::make('password')->writeOnly()->isWriteOnly());
        $this->assertFalse(Text::make('password')->writeOnly()->writeOnly(false)->isWriteOnly());
    }

    #[Test]
    public function it_stores_the_columns_it_requires(): void
    {
        $this->assertSame([], Text::make('full_name')->getRequires());
        $this->assertSame(['first_name', 'last_name'], Text::make('full_name')->virtual()->requires(['first_name', 'last_name'])->getRequires());
    }

    #[Test]
    public function it_merges_meta_with_later_keys_winning(): void
    {
        $field = Text::make('title')
            ->meta(['label' => 'Title', 'group' => 'content'])
            ->meta(['group' => 'headline', 'hint' => 'Shown in lists']);

        $this->assertSame(['label' => 'Title', 'group' => 'headline', 'hint' => 'Shown in lists'], $field->getMeta());
    }

    #[Test]
    public function it_has_no_meta_by_default(): void
    {
        $this->assertSame([], Text::make('title')->getMeta());
    }

    #[Test]
    #[DataProvider('configurators')]
    public function it_returns_the_same_field_from_every_configurator(Closure $configure): void
    {
        $field = Text::make('title');

        $this->assertSame($field, $configure($field));
    }

    /** @return array<string, array{Closure(Field): Field}> */
    public static function configurators(): array
    {
        return [
            'selectable' => [fn (Field $field): Field => $field->selectable(false)],
            'virtual' => [fn (Field $field): Field => $field->virtual()],
            'requires' => [fn (Field $field): Field => $field->requires(['summary'])],
            'sortable' => [fn (Field $field): Field => $field->sortable(false)],
            'sortUsing' => [fn (Field $field): Field => $field->sortUsing(fn (Builder $query, string $direction) => $query)],
            'readonly' => [fn (Field $field): Field => $field->readonly()],
            'creatable' => [fn (Field $field): Field => $field->creatable(false)],
            'updatable' => [fn (Field $field): Field => $field->updatable(false)],
            'writeOnly' => [fn (Field $field): Field => $field->writeOnly()],
            'fillUsing' => [fn (Field $field): Field => $field->fillUsing(self::fillNothing(...))],
            'filterable' => [fn (Field $field): Field => $field->filterable(['='])],
            'filterUsing' => [fn (Field $field): Field => $field->filterUsing(fn (Builder $query, string $operator, mixed $value) => $query)],
            'nullable' => [fn (Field $field): Field => $field->nullable()],
            'rules' => [fn (Field $field): Field => $field->rules('max:255')],
            'creationRules' => [fn (Field $field): Field => $field->creationRules('required')],
            'updateRules' => [fn (Field $field): Field => $field->updateRules('sometimes')],
            'meta' => [fn (Field $field): Field => $field->meta(['label' => 'Title'])],
        ];
    }

    private static function fillNothing(Model $model, mixed $value): void {}
}
