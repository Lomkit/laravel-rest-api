<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Augmentations;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Extensions\Contexts\AugmentationContext;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstAugmentation;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MountedInstrumentController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MountedInstrumentResource;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MountedSensorController;
use Lomkit\Rest\Tests\Fixtures\Lab\Lab;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', [FirstAugmentation::class])]
class AugmentedPivotTest extends PackageTestCase
{
    use ResetsExtensionStubs;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Lab];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        FirstAugmentation::$augment = static function (AugmentationContext $context): void {
            if ($context->resource instanceof MountedInstrumentResource) {
                $context->with('firstMounting');
            }
        };
        Rest::resource('mounted-instruments', MountedInstrumentController::class)->register();
        Rest::resource('mounted-sensors', MountedSensorController::class)->register();
        DB::table('instruments')->insert(['id' => 1, 'name' => 'Scope']);
        DB::table('sensors')->insert(['id' => 2, 'name' => 'Probe']);
        DB::table('instrument_sensor')->insert(['instrument_id' => 1, 'sensor_id' => 2, 'wiring' => '{"pins":[1]}']);
    }

    #[Test]
    public function it_renders_no_pivot_model_that_an_augmentation_loads_on_a_row(): void
    {
        $response = $this->postJson('/mounted-instruments/search', ['search' => ['selects' => [['field' => 'id'], ['field' => 'name']]]]);

        $response->assertOk();
        $this->assertSame([['id' => 1, 'name' => 'Scope']], $response->json('data'));
    }

    #[Test]
    public function it_renders_only_the_pivot_of_the_relation_that_reaches_an_include(): void
    {
        $response = $this->postJson('/mounted-sensors/search', ['search' => [
            'selects' => [['field' => 'id']],
            'includes' => [['relation' => 'mountedInstruments', 'selects' => [['field' => 'id']]]],
        ]]);

        $response->assertOk();
        $this->assertSame([['id' => 2, 'mounted_instruments' => [['id' => 1, 'pivot' => ['wiring' => ['pins' => [1]]]]], '@more' => []]], $response->json('data'));
    }

    #[Test]
    public function it_renders_the_pivot_under_the_accessor_of_the_relation(): void
    {
        $response = $this->postJson('/mounted-sensors/search', ['search' => [
            'selects' => [['field' => 'id']],
            'includes' => [['relation' => 'wiredInstruments', 'selects' => [['field' => 'id']]]],
        ]]);

        $response->assertOk();
        $this->assertSame([['id' => 2, 'wired_instruments' => [['id' => 1, 'wired_mounting' => ['wiring' => ['pins' => [1]]]]], '@more' => []]], $response->json('data'));
    }
}
