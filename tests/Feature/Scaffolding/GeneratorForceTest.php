<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class GeneratorForceTest extends ScaffoldingTestCase
{
    #[Test]
    #[DataProvider('generators')]
    public function it_keeps_an_existing_class_when_it_runs_again(string $command, string $name, string $path, string $type): void
    {
        $this->artisan($command, ['name' => $name, '--no-interaction' => true])->assertSuccessful();
        file_put_contents(app_path($path), '<?php // edited');

        $this->artisan($command, ['name' => $name, '--no-interaction' => true])
            ->expectsOutputToContain("{$type} already exists.")
            ->doesntExpectOutputToContain('created successfully')
            ->doesntExpectOutputToContain('was given or found')
            ->assertSuccessful();

        $this->assertSame('<?php // edited', file_get_contents(app_path($path)));
    }

    #[Test]
    #[DataProvider('generators')]
    public function it_replaces_an_existing_class_with_force(string $command, string $name, string $path, string $type): void
    {
        $this->artisan($command, ['name' => $name, '--no-interaction' => true])->assertSuccessful();
        file_put_contents(app_path($path), '<?php // edited');

        $this->artisan($command, ['name' => $name, '--force' => true, '--no-interaction' => true])
            ->expectsOutputToContain("{$type} [app/{$path}] created successfully.")
            ->assertSuccessful();

        $this->assertStringContainsString("class {$name} extends", file_get_contents(app_path($path)));
    }

    #[Test]
    #[DataProvider('generators')]
    public function it_replaces_an_existing_class_with_the_short_force_option(string $command, string $name, string $path, string $type): void
    {
        $this->artisan($command, ['name' => $name, '--no-interaction' => true])->assertSuccessful();
        file_put_contents(app_path($path), '<?php // edited');

        $this->artisan($command, ['name' => $name, '-f' => true, '--no-interaction' => true])->assertSuccessful();

        $this->assertStringContainsString("class {$name} extends", file_get_contents(app_path($path)));
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function generators(): array
    {
        return [
            'action' => ['rest:action', 'ForcedAction', 'Rest/Actions/ForcedAction.php', 'Action'],
            'base controller' => ['rest:base-controller', 'ForcedBaseController', 'Rest/Controllers/ForcedBaseController.php', 'Base Controller'],
            'base resource' => ['rest:base-resource', 'ForcedBaseResource', 'Rest/Resources/ForcedBaseResource.php', 'Base Resource'],
            'controller' => ['rest:controller', 'ForcedController', 'Rest/Controllers/ForcedController.php', 'Controller'],
            'instruction' => ['rest:instruction', 'ForcedInstruction', 'Rest/Instructions/ForcedInstruction.php', 'Instruction'],
            'resource' => ['rest:resource', 'ForcedResource', 'Rest/Resources/ForcedResource.php', 'Resource'],
            'response' => ['rest:response', 'ForcedResponse', 'Rest/Responses/ForcedResponse.php', 'Response'],
        ];
    }
}
