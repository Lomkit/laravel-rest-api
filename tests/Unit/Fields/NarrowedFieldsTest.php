<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\NarrowedFields;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Fields\WriteOperation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NarrowedFieldsTest extends TestCase
{
    #[Test]
    public function it_returns_the_declared_set_when_nothing_is_narrowed(): void
    {
        $declared = self::declared();

        $this->assertSame($declared, NarrowedFields::none()->apply($declared));
        $this->assertTrue(NarrowedFields::none()->isEmpty());
    }

    #[Test]
    public function it_drops_hidden_fields_in_declaration_order(): void
    {
        $narrowed = (new NarrowedFields(hidden: ['price', 'id']))->apply(self::declared());

        $this->assertSame(['category_id', 'name'], $narrowed->names());
    }

    #[Test]
    public function it_clones_a_narrowed_field_of_the_same_class(): void
    {
        $declared = self::declared();

        $narrowed = (new NarrowedFields(readOnly: ['price'], unfilterable: ['price'], unsortable: ['price']))->apply($declared);

        $field = $narrowed->get('price');
        $this->assertNotSame($declared->get('price'), $field);
        $this->assertSame(Integer::class, $field::class);
        $this->assertSame([false, false, false, false], [$field->isWritableOn(WriteOperation::Create), $field->isWritableOn(WriteOperation::Update), $field->isFilterable(), $field->isSortable()]);
        $original = $declared->get('price');
        $this->assertSame([true, true, true, true], [$original->isWritableOn(WriteOperation::Create), $original->isWritableOn(WriteOperation::Update), $original->isFilterable(), $original->isSortable()]);
    }

    #[Test]
    public function it_applies_each_reduction_alone(): void
    {
        $declared = self::declared();

        $readOnly = (new NarrowedFields(readOnly: ['price']))->apply($declared)->get('price');
        $unfilterable = (new NarrowedFields(unfilterable: ['price']))->apply($declared)->get('price');
        $unsortable = (new NarrowedFields(unsortable: ['price']))->apply($declared)->get('price');

        $this->assertSame([false, true, true], [$readOnly->isWritableOn(WriteOperation::Update), $readOnly->isFilterable(), $readOnly->isSortable()]);
        $this->assertSame([true, false, true], [$unfilterable->isWritableOn(WriteOperation::Update), $unfilterable->isFilterable(), $unfilterable->isSortable()]);
        $this->assertSame([true, true, false], [$unsortable->isWritableOn(WriteOperation::Update), $unsortable->isFilterable(), $unsortable->isSortable()]);
    }

    #[Test]
    public function it_keeps_untouched_fields_as_the_same_objects(): void
    {
        $declared = self::declared();

        $narrowed = (new NarrowedFields(hidden: ['price'], readOnly: ['name']))->apply($declared);

        $this->assertSame($declared->get('id'), $narrowed->get('id'));
        $this->assertSame($declared->get('category_id'), $narrowed->get('category_id'));
        $this->assertNotSame($declared->get('name'), $narrowed->get('name'));
    }

    #[Test]
    public function it_lists_hidden_names_among_unfilterable_and_unsortable_names(): void
    {
        $narrowed = new NarrowedFields(hidden: ['price'], unfilterable: ['name', 'price'], unsortable: ['id']);

        $this->assertSame(['price', 'name'], $narrowed->unfilterableNames());
        $this->assertSame(['price', 'id'], $narrowed->unsortableNames());
        $this->assertFalse($narrowed->isEmpty());
    }

    private static function declared(): FieldSet
    {
        return FieldSet::of([ID::make('id'), Integer::make('category_id'), Text::make('name'), Integer::make('price')]);
    }
}
