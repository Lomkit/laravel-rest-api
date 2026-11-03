<?php

namespace Lomkit\Rest\Tests\Unit\Mutations;

use Lomkit\Rest\Mutations\OperationLog;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OperationLogTest extends TestCase
{
    #[Test]
    public function it_nests_each_entry_under_the_entry_that_is_open(): void
    {
        $log = new OperationLog;

        $log->begin(['operation' => 'create', 'relations' => ['labels' => [['operation' => 'create']]]]);
        $log->within(HasMany::make('labels', LabelResource::class));
        $log->begin(['operation' => 'create']);
        $log->wrote(new Label(['id' => 8]));
        $log->end();
        $log->wrote(new Book(['id' => 3]));
        $log->end();
        $log->begin(['operation' => 'update', 'key' => 1]);
        $log->wrote(new Book(['id' => 1]));
        $log->end();

        $this->assertSame([
            ['operation' => 'create', 'key' => 3, 'relations' => ['labels' => [['operation' => 'create', 'key' => 8]]]],
            ['operation' => 'update', 'key' => 1],
        ], json_decode((string) json_encode($log->entries()), true));
    }

    #[Test]
    public function it_starts_each_mutation_with_no_entry(): void
    {
        $log = new OperationLog;
        $log->begin(['operation' => 'update', 'key' => 1]);

        $log->start();

        $this->assertSame([], $log->entries());

        $log->begin(['operation' => 'update', 'key' => 2]);
        $log->wrote(new Book(['id' => 2]));
        $log->end();

        $this->assertSame([['operation' => 'update', 'key' => 2]], json_decode((string) json_encode($log->entries()), true));
    }
}
