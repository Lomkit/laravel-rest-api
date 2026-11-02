<?php

namespace Lomkit\Rest\Tests\Unit\Mutations;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Mutations\MutationContext;
use Lomkit\Rest\Mutations\MutationNode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MutationNodeTest extends TestCase
{
    #[Test]
    public function it_rebuilds_the_entry_it_was_parsed_from(): void
    {
        $sync = new MutationNode(MutationOperation::Sync, [2], null, ['price' => 10], true, null, 1);
        $node = new MutationNode(MutationOperation::Update, 1, ['name' => 'Lamps'], null, null, ['featuredProducts' => [$sync]], 0);

        $this->assertSame([
            'operation' => 'update',
            'key' => 1,
            'attributes' => ['name' => 'Lamps'],
            'relations' => ['featuredProducts' => [[
                'operation' => 'sync',
                'key' => [2],
                'pivot' => ['price' => 10],
                'without_detaching' => true,
            ]]],
        ], $node->toArray());
    }

    #[Test]
    public function it_gives_no_key_to_the_context_of_a_key_list(): void
    {
        $attach = new MutationNode(MutationOperation::Attach, [1, 2], null, null, null, null, 1);
        $update = new MutationNode(MutationOperation::Update, 5, null, null, null, null, 0);

        $this->assertEquals(new MutationContext(MutationOperation::Attach, null, 1), $attach->context());
        $this->assertEquals(new MutationContext(MutationOperation::Update, 5, 0), $update->context());
    }
}
