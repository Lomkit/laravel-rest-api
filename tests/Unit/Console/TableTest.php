<?php

namespace Lomkit\Rest\Tests\Unit\Console;

use Lomkit\Rest\Console\Generation\Schema\Index;
use Lomkit\Rest\Console\Generation\Schema\Table;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TableTest extends TestCase
{
    #[Test]
    #[DataProvider('indexes')]
    public function it_finds_a_unique_index_of_the_column_alone(Index $index, bool $unique): void
    {
        $table = new Table('slots', [], [$index], []);

        $this->assertSame($unique, $table->isUniqueOn('slug'));
    }

    /** @return array<string, array{Index, bool}> */
    public static function indexes(): array
    {
        return [
            'a unique index of the column' => [new Index(['slug'], true, false), true],
            'a primary key of the column' => [new Index(['slug'], true, true), true],
            'a unique index of the column and another one' => [new Index(['desk', 'slug'], true, false), false],
            'a unique index of another column and the column' => [new Index(['slug', 'desk'], true, false), false],
            'an index of the column that is not unique' => [new Index(['slug'], false, false), false],
            'a unique index of another column' => [new Index(['desk'], true, false), false],
        ];
    }
}
