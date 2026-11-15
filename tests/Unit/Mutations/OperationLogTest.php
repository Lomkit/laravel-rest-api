<?php

namespace Lomkit\Rest\Tests\Unit\Mutations;

use Lomkit\Rest\Mutations\OperationLog;
use Lomkit\Rest\Mutations\WrittenRow;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OperationLogTest extends TestCase
{
    use BuildsMutationNodes;

    #[Test]
    public function it_nests_each_entry_under_the_entry_that_is_open(): void
    {
        $log = new OperationLog;
        $label = self::node('create');

        $log->begin(self::node('create', relations: ['labels' => [$label]]));
        $log->within(HasMany::make('labels', LabelResource::class));
        $log->begin($label);
        $log->wrote(new Label(['id' => 8]));
        $log->end();
        $log->wrote(new Book(['id' => 3]));
        $log->end();
        $log->begin(self::node('update', 1));
        $log->wrote(new Book(['id' => 1]));
        $log->end();

        $this->assertSame([
            ['operation' => 'create', 'key' => 3, 'relations' => ['labels' => [['operation' => 'create', 'key' => 8]]]],
            ['operation' => 'update', 'key' => 1],
        ], json_decode((string) json_encode($log->entries()), true));
    }

    #[Test]
    public function it_records_the_written_rows_only_when_asked(): void
    {
        $label = self::node('create', attributes: ['name' => 'Classic']);
        $book = self::node('create', attributes: ['title' => 'Dune'], relations: ['labels' => [$label]]);
        $update = self::node('update', 1, ['title' => 'Emma']);
        $books = new BookResource;
        $labels = new LabelResource;
        $logs = [new OperationLog(true), new OperationLog];

        foreach ($logs as $log) {
            $log->begin($book);
            $log->within(HasMany::make('labels', LabelResource::class));
            $log->begin($label);
            $log->wrote(new Label(['id' => 8]), $labels);
            $log->end();
            $log->wrote(new Book(['id' => 3]), $books);
            $log->end();
            $log->begin($update);
            $log->wrote(new Book(['id' => 1]), $books);
            $log->wrote(new Book(['id' => 2]));
            $log->end();
        }

        $this->assertSame(
            [[$labels, $label, 8], [$books, $book, 3], [$books, $update, 1]],
            array_map(fn (WrittenRow $row): array => [$row->resource, $row->entry, $row->model->getKey()], $logs[0]->written()),
        );
        $this->assertSame([], $logs[1]->written());
        $this->assertSame(json_encode($logs[0]->entries()), json_encode($logs[1]->entries()));
    }
}
