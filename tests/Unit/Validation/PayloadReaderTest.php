<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\NodeBudget;
use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\PayloadReader;
use Lomkit\Rest\Validation\Rejected;
use Lomkit\Rest\Validation\Violation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PayloadReaderTest extends TestCase
{
    private ParsedRules $rules;

    private NodeBudget $budget;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->rules = new ParsedRules;
        $this->budget = new NodeBudget;
    }

    #[Test]
    public function it_reads_an_object(): void
    {
        $this->assertTrue($this->reader()->object($this->cursor(['a' => 1])));
        $this->assertSame([], $this->rules->rules());
        $this->assertFalse($this->rules->isPartial());
    }

    #[Test]
    public function it_gives_the_array_rule_to_a_value_that_is_not_an_object(): void
    {
        $this->assertFalse($this->reader()->object($this->cursor('x')));
        $this->assertSame(['f' => ['array']], $this->rules->rules());
    }

    #[Test]
    public function it_rejects_a_blank_string_where_an_object_is_expected(): void
    {
        $this->assertFalse($this->reader()->object($this->cursor('  ')));
        $this->assertSame(['f'], array_keys($this->rules->rules()));
        $this->assertRejected(Violation::notArray('f'), $this->rules->rules()['f']);
        $this->assertSame('validation.array', Violation::notArray('f')->message);
    }

    #[Test]
    public function it_reads_the_entries_of_a_list(): void
    {
        $entries = $this->reader()->entries($this->cursor([1, 2]), 2);

        $this->assertSame(['f.0', 'f.1'], array_map(fn (Cursor $entry): string => $entry->path, $entries ?? []));
        $this->assertFalse($this->rules->isPartial());
    }

    #[Test]
    public function it_rejects_an_object_where_a_list_is_expected(): void
    {
        $this->assertNull($this->reader()->entries($this->cursor(['a' => 1]), 2));
        $this->assertRejected(Violation::notList('f'), $this->rules->rules()['f']);
        $this->assertTrue($this->budget->refused('f'));
        $this->assertTrue($this->rules->isPartial());
    }

    #[Test]
    public function it_rejects_a_blank_string_where_a_list_is_expected(): void
    {
        $this->assertNull($this->reader()->entries($this->cursor(''), 2));
        $this->assertRejected(Violation::notArray('f'), $this->rules->rules()['f']);
        $this->assertTrue($this->rules->isPartial());
    }

    #[Test]
    public function it_refuses_a_list_over_its_limit(): void
    {
        $this->assertNull($this->reader()->entries($this->cursor([1, 2, 3]), 2));
        $this->assertTrue($this->budget->refused('f'));
        $this->assertTrue($this->rules->isPartial());
    }

    #[Test]
    public function it_refuses_a_list_over_its_limit_before_reading_its_entries(): void
    {
        $list = $this->cursor(array_fill(0, 200000, 0));
        $reader = $this->reader();

        memory_reset_peak_usage();
        $before = memory_get_usage();
        $entries = $reader->entries($list, 2);

        $this->assertLessThan(1000000, memory_get_peak_usage() - $before);
        $this->assertNull($entries);
    }

    #[Test]
    public function it_tells_a_filled_value_from_an_empty_one(): void
    {
        $filled = array_map(fn (mixed $value): bool => $this->reader()->filled($this->cursor($value)), [null, [], '', '  ', 'a', 0, false, [1]]);

        $this->assertSame([false, false, false, false, true, true, true, true], $filled);
    }

    #[Test]
    public function it_admits_any_array_into_the_budget(): void
    {
        $this->assertTrue($this->reader()->admit($this->cursor(['a' => 1, 'b' => 2]), 2));
        $this->assertFalse($this->reader()->admit($this->cursor('x'), 2));
        $this->assertFalse($this->rules->isPartial());
    }

    #[Test]
    public function it_refuses_an_array_over_its_limit(): void
    {
        $reader = $this->reader();

        $this->assertFalse($reader->admit($this->cursor([1, 2, 3]), 2));
        $this->assertTrue($reader->refused($this->cursor([1, 2, 3])));
        $this->assertTrue($this->rules->isPartial());
    }

    #[Test]
    public function it_reads_the_items_of_a_list_only(): void
    {
        $reader = $this->reader();
        $paths = fn (array $items): array => array_map(fn (Cursor $item): string => $item->path, $items);

        $this->assertSame(['f.0', 'f.1'], $paths($reader->items($this->cursor([1, 2]))));
        $this->assertSame([], $reader->items($this->cursor(['a' => 1])));
        $this->assertSame([], $reader->items($this->cursor('x')));
        $this->assertSame([], $this->rules->rules());
    }

    #[Test]
    public function it_rejects_unknown_keys_in_payload_order(): void
    {
        $this->reader()->known($this->object(['b' => 1, 'x' => 2, 'a' => 3, 'y' => 4]), ['a' => true, 'b' => true]);

        $rules = $this->rules->rules();

        $this->assertSame(['o.x', 'o.y'], array_keys($rules));
        $this->assertRejected(Violation::unknown('o.x'), $rules['o.x']);
        $this->assertRejected(Violation::unknown('o.y'), $rules['o.y']);
        $this->assertSame([], $this->rules->labels());
    }

    #[Test]
    public function it_names_an_unknown_key_as_written(): void
    {
        $this->reader()->known($this->object(['sort_by' => 1]), ['field' => true]);

        $this->assertSame(['o.sort_by' => 'o.sort_by'], $this->rules->labels());
        $this->assertRejected(Violation::unknown('o.sort_by'), $this->rules->rules()['o.sort_by']);
    }

    #[Test]
    public function it_rejects_a_key_that_cannot_be_named_on_its_object(): void
    {
        $this->reader()->known($this->object(['field' => 'name', '*' => 1]), ['field' => true]);

        $this->assertSame(['o'], array_keys($this->rules->rules()));
        $this->assertRejected(new Violation('o', 'The :key key is not valid for the :attribute field.', ['key' => '*']), $this->rules->rules()['o']);
    }

    private function reader(): PayloadReader
    {
        return new PayloadReader($this->rules, $this->budget);
    }

    private function cursor(mixed $value): Cursor
    {
        return Cursor::of(['f' => $value], $this->rules->violations())->at('f');
    }

    /** @param array<string, mixed> $value */
    private function object(array $value): Cursor
    {
        return Cursor::of(['o' => $value], $this->rules->violations())->at('o');
    }

    /** @param list<mixed> $rules */
    private function assertRejected(Violation $violation, array $rules): void
    {
        $this->assertCount(1, $rules);
        $this->assertInstanceOf(Rejected::class, $rules[0]);
        $this->assertEquals($violation, $rules[0]->violation);
    }
}
