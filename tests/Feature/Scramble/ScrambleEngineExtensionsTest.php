<?php

namespace Lomkit\Rest\Tests\Feature\Scramble;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\ScrambleServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\DeclaredIndexExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EngineLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstIndexExtension;
use Lomkit\Rest\Tests\Fixtures\Ledger\Ledger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ScrambleEngineExtensionsTest extends ScrambleTestCase
{
    use ResetsExtensionStubs;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Ledger];
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [ScrambleServiceProvider::class, ScoutServiceProvider::class, RestServiceProvider::class];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        DeclaredIndexExtension::$declared = [FirstIndexExtension::class => [Integer::make('owner_marker')]];
    }

    #[Test]
    public function it_keeps_extension_attributes_out_of_the_openapi_document(): void
    {
        $without = json_encode(app(Generator::class)(), JSON_THROW_ON_ERROR);
        config(['rest.extensions' => [FirstIndexExtension::class]]);

        $with = json_encode(app(Generator::class)(), JSON_THROW_ON_ERROR);

        $this->assertContains('FirstIndexExtension', EngineLog::$calls);
        $this->assertStringNotContainsString('owner_marker', $with);
        $this->assertSame($without, $with);
    }
}
