<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class GeneratorCommandsTest extends PackageTestCase
{
    #[Test]
    #[DataProvider('generators')]
    public function it_registers_each_generator_with_its_description_and_visibility(string $name, string $description, bool $hidden): void
    {
        $command = Artisan::all()[$name];

        $this->assertSame($name, $command->getName());
        $this->assertSame($description, $command->getDescription());
        $this->assertSame($hidden, $command->isHidden());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
        $this->assertSame('f', $command->getDefinition()->getOption('force')->getShortcut());
        $this->assertFalse($command->getDefinition()->getOption('force')->acceptValue());
    }

    /** @return array<string, array{string, string, bool}> */
    public static function generators(): array
    {
        return [
            'action' => ['rest:action', 'Create a new action class', false],
            'base controller' => ['rest:base-controller', 'Create a new base controller class', true],
            'base resource' => ['rest:base-resource', 'Create a new base resource class', true],
            'controller' => ['rest:controller', 'Create a new controller class', false],
            'instruction' => ['rest:instruction', 'Create a new instruction class', false],
            'policy' => ['rest:policy', 'Create a new read-only policy class', false],
            'resource' => ['rest:resource', 'Create a new resource class', false],
            'response' => ['rest:response', 'Create a new response class', false],
        ];
    }
}
