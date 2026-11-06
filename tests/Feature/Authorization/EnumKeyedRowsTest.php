<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Authorization\Stubs\CachedChannelController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Channel;
use PHPUnit\Framework\Attributes\Test;

class EnumKeyedRowsTest extends HelpdeskTestCase
{
    private const array GATED_SEARCH = ['search' => ['gates' => ['view', 'update']]];

    #[Test]
    public function it_checks_rows_whose_key_is_cast_to_an_enum(): void
    {
        $this->seedChannels();

        $this->postJson('/api/channels/search', self::GATED_SEARCH)
            ->assertOk()
            ->assertJsonPath('data.0.code', 'email')
            ->assertJsonPath('data.0.gates.authorized_to_update.allowed', true)
            ->assertJsonPath('data.1.gates.authorized_to_view.allowed', true);
        $this->postJson('/api/channels/mutate', ['mutate' => [['operation' => 'update', 'key' => 'phone', 'attributes' => ['name' => 'Hotline']]]])
            ->assertOk()
            ->assertJsonPath('operations', [['operation' => 'update', 'key' => 'phone']]);

        $this->assertSame(['viewAny', 'view email', 'update email', 'view phone', 'update phone', 'update phone'], $this->channels->calls);
        $this->assertDatabaseHas('channels', ['code' => 'phone', 'name' => 'Hotline']);
    }

    #[Test]
    public function it_serves_only_the_class_level_decisions_of_rows_without_timestamps(): void
    {
        Rest::resource('cached-channels', CachedChannelController::class)->register();
        $this->seedChannels();

        $this->postJson('/cached-channels/search', self::GATED_SEARCH)->assertOk();
        $this->postJson('/cached-channels/search', self::GATED_SEARCH)
            ->assertOk()
            ->assertJsonPath('data.1.gates.authorized_to_update.allowed', true);

        $this->assertSame(['viewAny', 'view email', 'update email', 'view phone', 'update phone', 'view email', 'update email', 'view phone', 'update phone'], $this->channels->calls);
    }

    private function seedChannels(): void
    {
        Channel::create(['code' => 'phone', 'name' => 'Phone']);
        Channel::create(['code' => 'email', 'name' => 'Email']);
    }
}
