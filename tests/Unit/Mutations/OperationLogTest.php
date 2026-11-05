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
}
