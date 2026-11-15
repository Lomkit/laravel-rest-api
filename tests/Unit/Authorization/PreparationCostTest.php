<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Authorization\DecisionPreparation;
use Lomkit\Rest\Extensions\ExtensionRegistry;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\PreparingExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RowCheckLog;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Lomkit\Rest\Tests\Unit\Authorization\Stubs\CountingSettingsCast;
use Lomkit\Rest\Tests\Unit\Authorization\Stubs\WalkedDocument;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PreparationCostTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        RowCheckLog::reset();
        PreparingExtension::$resources = null;
        PreparingExtension::$received = [];
        PreparingExtension::$prepare = null;
        CountingSettingsCast::$writes = 0;
        WalkedDocument::$walks = 0;
    }

    #[\Override]
    protected function tearDown(): void
    {
        RowCheckLog::reset();
        PreparingExtension::$received = [];

        parent::tearDown();
    }

    #[Test]
    public function it_stores_a_cast_attribute_again_only_around_each_extension(): void
    {
        $row = $this->row();
        $row->getAttribute('settings');

        $this->prepare($row);

        $this->assertCount(1, PreparingExtension::$received);
        $this->assertSame(4, CountingSettingsCast::$writes);
    }

    #[Test]
    public function it_walks_each_model_once_to_record_the_relations_then_once_per_snapshot_and_comparison(): void
    {
        $row = $this->row();
        $row->setRelation('team', (new WalkedDocument)->newFromBuilder(['id' => 2]));

        $this->prepare($row);

        $this->assertCount(1, PreparingExtension::$received);
        $this->assertSame(6, WalkedDocument::$walks);
    }

    private function prepare(WalkedDocument $row): void
    {
        $registry = ExtensionRegistry::of([PreparingExtension::class], [], [], $this->app ?? $this->fail('No application.'));
        $preparation = new DecisionPreparation(null, static fn (): ExtensionRegistry => $registry);

        $preparation->prepare(['view|1' => new Check(new TicketResource, 'view', $row)], static fn (string $key): bool => false);
    }

    private function row(): WalkedDocument
    {
        return (new WalkedDocument)->newFromBuilder(['id' => 1, 'settings' => '{"a":1}']);
    }
}
