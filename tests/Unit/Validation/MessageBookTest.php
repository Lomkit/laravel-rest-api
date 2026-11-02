<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\MessageBook;
use Lomkit\Rest\Validation\Violations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MessageBookTest extends TestCase
{
    private MessageBook $book;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->book = new MessageBook;
    }

    #[Test]
    public function it_stores_one_pattern_for_the_entries_of_one_field(): void
    {
        $this->price(Integer::make('price')->label('unit price'));

        $this->assertSame(['mutate.*.attributes.price' => 'unit price'], $this->book->attributeNames([], []));
    }

    #[Test]
    public function it_falls_back_to_the_keys_when_two_fields_meet_on_a_pattern(): void
    {
        $this->meet();

        $this->assertSame(
            ['search.instructions.1.fields.min_views' => 'minimum views', 'search.instructions.0.fields.min_views' => 'min views'],
            $this->book->attributeNames([], []),
        );
    }

    #[Test]
    public function it_reads_a_blank_label_as_no_label(): void
    {
        $value = Cursor::of(['fields' => []], new Violations)
            ->at('fields')
            ->at('min_views');

        $this->book->field($value, Integer::make('min_views')->label(''));
        $this->book->label('fields.min_views', 'min views');

        $this->assertSame(['fields.min_views' => 'min views'], $this->book->attributeNames([], []));
    }

    #[Test]
    public function it_keeps_the_names_of_the_application_first(): void
    {
        $custom = ['mutate.*.attributes.price' => 'price', 'mutate.0.attributes.name' => 'name'];
        $this->price(Integer::make('price')->label('unit price'));

        $this->assertSame($custom, $this->book->attributeNames($custom, []));
    }

    #[Test]
    public function it_leaves_out_the_explicit_labels_that_an_application_pattern_names(): void
    {
        $custom = ['search.instructions.*.fields.min_views' => 'views'];
        $this->meet();

        $this->assertSame($custom, $this->book->attributeNames($custom, []));
    }

    #[Test]
    public function it_leaves_out_a_label_of_one_key_that_an_application_pattern_names(): void
    {
        $value = Cursor::of(['fields' => []], new Violations)
            ->at('fields')
            ->at('status');
        $this->book->field($value, Integer::make('status')->label('publication status'));

        $this->assertSame(['fields.*' => 'input'], $this->book->attributeNames(['fields.*' => 'input'], []));
    }

    #[Test]
    public function it_names_a_field_by_its_label_before_a_translation(): void
    {
        $this->price(Integer::make('price')->label('unit price'));

        $this->assertSame(
            ['mutate.*.attributes.price' => 'unit price'],
            $this->book->attributeNames([], ['mutate.*.attributes.price' => 'tariff', 'mutate.0.attributes.price' => 'first tariff']),
        );
    }

    #[Test]
    public function it_gives_the_application_attribute_names_precedence_over_the_default_names(): void
    {
        $this->book->label('fields.min_views', 'min views');
        $this->book->label('mutate.0.relations.lineItems.0.operation', 'mutate.0.relations.lineItems.0.operation');
        $this->book->label('mutate.0.relations.lineItems.1.operation', 'mutate.0.relations.lineItems.1.operation');
        $this->book->label('fields.status', 'status');

        $this->assertSame(
            [
                'fields.min_views' => 'minimum views',
                'mutate.*.relations.lineItems.*.operation' => 'line operation',
                'fields.status' => 'status',
            ],
            $this->book->attributeNames(['fields.min_views' => 'minimum views', 'mutate.*.relations.lineItems.*.operation' => 'line operation'], []),
        );
    }

    #[Test]
    public function it_leaves_out_the_default_names_that_a_translation_names(): void
    {
        $this->book->label('items.0.unit_price', 'items.0.unit_price');
        $this->book->label('lineItems.0.unitPrice', 'lineItems.0.unitPrice');
        $this->book->label('fields.status', 'status');
        $this->book->label('fields.min_views', 'min views');

        $this->assertSame(
            ['fields.min_views' => 'minimum views', 'lineItems.0.unitPrice' => 'lineItems.0.unitPrice'],
            $this->book->attributeNames(['fields.min_views' => 'minimum views'], ['items.*.unit_price' => 'unit price', 'fields.status' => 'publication status']),
        );
    }

    #[Test]
    public function it_stores_one_message_per_rule_for_the_entries_of_one_field(): void
    {
        $this->price(Integer::make('price')->messages(['min' => 'Too low.', 'integer' => 'Whole numbers.']));

        $this->assertSame(['mutate.*.attributes.price.min' => 'Too low.', 'mutate.*.attributes.price.integer' => 'Whole numbers.'], $this->book->messages());
    }

    #[Test]
    public function it_falls_back_to_the_keys_for_a_message_that_one_field_gives(): void
    {
        [$first, $second] = $this->entries(['search' => ['scopes' => [[], []]]], 'search', 'scopes');

        $this->book->field($first->at('parameters')->at(0), Date::make('since')->messages(['date' => 'A date.']));
        $this->book->field($second->at('parameters')->at(0), Integer::make('min_views'));

        $this->assertSame(['search.scopes.0.parameters.0.date' => 'A date.'], $this->book->messages());
    }

    #[Test]
    public function it_registers_a_key_in_under_one_hundred_bytes(): void
    {
        $field = Integer::make('price')->label('unit price');
        $values = array_map(fn (Cursor $entry): Cursor => $entry->at('price'), Cursor::of(array_fill(0, 2000, []), new Violations)->items(2000) ?? []);
        gc_collect_cycles();
        $before = memory_get_usage();

        foreach ($values as $value) {
            $this->book->field($value, $field);
        }

        $this->assertLessThan(200000, memory_get_usage() - $before);
    }

    private function price(Field $field): void
    {
        foreach ($this->entries(['mutate' => [[], []]], 'mutate') as $entry) {
            $this->book->field($entry->at('attributes')->at('price'), $field);
        }
    }

    private function meet(): void
    {
        [$first, $second] = array_map(
            fn (Cursor $entry): Cursor => $entry->at('fields')->at('min_views'),
            $this->entries(['search' => ['instructions' => [[], []]]], 'search', 'instructions'),
        );

        $this->book->field($first, Integer::make('min_views'));
        $this->book->label($first->path, 'min views');
        $this->book->field($second, Integer::make('min_views')->label('minimum views'));
        $this->book->label($second->path, 'min views');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<Cursor>
     */
    private function entries(array $payload, string ...$path): array
    {
        $list = Cursor::of($payload, new Violations);

        foreach ($path as $key) {
            $list = $list->at($key);
        }

        return $list->items(5) ?? [];
    }
}
