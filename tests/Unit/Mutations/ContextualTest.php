<?php

namespace Lomkit\Rest\Tests\Unit\Mutations;

use LogicException;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Mutations\Contextual;
use Lomkit\Rest\Mutations\MutationContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ContextualTest extends TestCase
{
    #[Test]
    public function it_resolves_its_rules_for_a_mutation(): void
    {
        $rule = Contextual::rule(fn (MutationContext $mutation): string => 'required|max:'.$mutation->depth);

        $this->assertSame(['required', 'max:2'], $rule->resolve(new MutationContext(MutationOperation::Create, null, 2)));
    }

    #[Test]
    public function it_refuses_to_run_outside_a_mutation(): void
    {
        $this->expectExceptionObject(new LogicException('A contextual rule only applies to the attributes and the pivot of a mutation.'));

        Contextual::rule(fn (MutationContext $mutation): string => 'required')->validate('fields.note', 'x', fn () => null);
    }
}
