<?php

namespace Lomkit\Rest\Tests\Unit\Console;

use Lomkit\Rest\Console\Generation\Schema\Column;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ColumnTest extends TestCase
{
    #[Test]
    #[DataProvider('types')]
    public function it_reads_the_length_of_a_string_column_from_its_type(string $type, ?int $length): void
    {
        $this->assertSame($length, (new Column('column', 'varchar', $type, false, false, false, false))->length());
    }

    /** @return array<string, array{string, int|null}> */
    public static function types(): array
    {
        return [
            'mysql varchar' => ['varchar(255)', 255],
            'mysql char' => ['char(36)', 36],
            'pgsql varchar' => ['character varying(120)', 120],
            'pgsql char' => ['character(2)', 2],
            'sqlite varchar' => ['varchar', null],
            'text' => ['text', null],
            'decimal' => ['decimal(8,2)', null],
        ];
    }

    #[Test]
    public function it_reads_a_column_reported_by_the_schema_builder(): void
    {
        $column = Column::fromSchema([
            'name' => 'title',
            'type_name' => 'VARCHAR',
            'type' => 'VARCHAR(80)',
            'collation' => null,
            'nullable' => false,
            'default' => null,
            'auto_increment' => false,
            'comment' => null,
            'generation' => ['type' => 'stored', 'expression' => 'upper(name)'],
        ]);

        $this->assertEquals(new Column('title', 'varchar', 'varchar(80)', false, false, false, true), $column);
        $this->assertSame(80, $column->length());
    }
}
