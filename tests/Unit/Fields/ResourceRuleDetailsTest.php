<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Closure;
use Lomkit\Rest\Fields\Details\ResourceRuleDetails;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\StartsWithCapital;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResourceRuleDetailsTest extends TestCase
{
    #[Test]
    public function it_describes_the_rules_of_each_attribute_as_strings(): void
    {
        $closure = function (string $attribute, mixed $value, Closure $fail): void {};
        $details = new ResourceRuleDetails(
            ['title' => 'max:100|filled'],
            ['price' => ['numeric', new StartsWithCapital, $closure], 'code' => new StartsWithCapital],
            [],
        );

        $this->assertSame(
            '{"all":{"title":["max:100","filled"]},"create":{"price":["numeric","starts_with_capital"],"code":["starts_with_capital"]},"update":{}}',
            json_encode($details),
        );
    }

    #[Test]
    public function it_encodes_empty_rules_as_objects(): void
    {
        $this->assertSame('{"all":{},"create":{},"update":{}}', json_encode(new ResourceRuleDetails([], [], [])));
    }
}
