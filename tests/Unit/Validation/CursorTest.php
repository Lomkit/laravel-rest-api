<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\Rejected;
use Lomkit\Rest\Validation\Violation;
use Lomkit\Rest\Validation\Violations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CursorTest extends TestCase
{
    #[Test]
    public function it_walks_to_a_key_by_its_escaped_path(): void
    {
        $cursor = Cursor::of(['a.b' => ['c' => 1]], new Violations)->at('a.b');

        $this->assertSame('a\.b.c', $cursor->at('c')->path);
        $this->assertSame(1, $cursor->at('c')->value);
        $this->assertTrue($cursor->at('c')->present);
        $this->assertFalse($cursor->at('d')->present);
        $this->assertNull($cursor->at('d')->value);
    }

    #[Test]
    public function it_reads_the_entries_of_a_list(): void
    {
        $items = Cursor::of(['ids' => [5, 6]], new Violations)
            ->at('ids')
            ->items(2);

        $this->assertNotNull($items);
        $this->assertSame(['ids.0', 'ids.1'], array_map(fn (Cursor $item): string => $item->path, $items));
        $this->assertSame([5, 6], array_map(fn (Cursor $item): mixed => $item->value, $items));
    }

    #[Test]
    public function it_reports_an_object_where_a_list_is_expected(): void
    {
        $violations = new Violations;

        $items = Cursor::of(['ids' => ['a' => 5]], $violations)
            ->at('ids')
            ->items(2);

        $this->assertNull($items);
        $this->assertEquals([new Violation('ids', 'validation.list', [])], $this->recorded($violations));
    }

    #[Test]
    public function it_reports_a_list_over_its_maximum(): void
    {
        $violations = new Violations;

        $items = Cursor::of(['ids' => [1, 2, 3]], $violations)
            ->at('ids')
            ->items(2);

        $this->assertNull($items);
        $this->assertEquals([new Violation('ids', 'validation.max.array', ['max' => '2'])], $this->recorded($violations));
    }

    #[Test]
    public function it_reads_no_entry_from_a_value_that_is_not_an_array(): void
    {
        $violations = new Violations;

        $items = Cursor::of(['ids' => 'x'], $violations)
            ->at('ids')
            ->items(2);

        $this->assertNull($items);
        $this->assertTrue($violations->isEmpty());
    }

    /** @return list<Violation> */
    private function recorded(Violations $violations): array
    {
        $recorded = [];

        foreach ($violations->applyTo([]) as $rules) {
            foreach ($rules as $rule) {
                $this->assertInstanceOf(Rejected::class, $rule);
                $recorded[] = $rule->violation;
            }
        }

        return $recorded;
    }
}
