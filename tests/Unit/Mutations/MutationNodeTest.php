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
    public function it_gives_no_key_to_the_context_of_a_key_list(): void
    {
        $attach = new MutationNode(MutationOperation::Attach, [1, 2], null, null, null, null, 1);
        $update = new MutationNode(MutationOperation::Update, 5, null, null, null, null, 0);

        $this->assertEquals(new MutationContext(MutationOperation::Attach, null, 1), $attach->context());
        $this->assertEquals(new MutationContext(MutationOperation::Update, 5, 0), $update->context());
    }
}
