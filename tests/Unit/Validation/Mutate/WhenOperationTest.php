<?php

namespace Lomkit\Rest\Tests\Unit\Validation\Mutate;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\Mutate\EntryKeyRules;
use Lomkit\Rest\Validation\Mutate\MutationParser;
use Lomkit\Rest\Validation\Violations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WhenOperationTest extends TestCase
{
    #[Test]
    public function it_builds_the_rules_of_some_operations_from_the_mutation_parser_too(): void
    {
        $entry = Cursor::of(['mutate' => [['operation' => 'sync']]], new Violations)
            ->at('mutate')
            ->item(0);
        $applies = fn (MutationOperation $operation): bool => $operation->requiresKey() === false;

        $this->assertSame(['prohibited_if:mutate.0.operation,create'], EntryKeyRules::whenOperation('prohibited_if', $entry, $applies));
        $this->assertSame(EntryKeyRules::whenOperation('prohibited_if', $entry, $applies), MutationParser::whenOperation('prohibited_if', $entry, $applies));
    }
}
