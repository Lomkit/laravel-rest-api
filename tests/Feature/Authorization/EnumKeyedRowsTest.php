<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Illuminate\Foundation\Application;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Channel;
use Orchestra\Testbench\Attributes\DefineEnvironment;
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
            ->assertJsonPath('data.0.gates.authorized_to_update', true)
            ->assertJsonPath('data.1.gates.authorized_to_view', true);
        $this->postJson('/api/channels/mutate', ['mutate' => [['operation' => 'update', 'key' => 'phone', 'attributes' => ['name' => 'Hotline']]]])
            ->assertOk()
            ->assertJsonPath('updated', ['phone']);

        $this->assertSame(['viewAny', 'view email', 'update email', 'view phone', 'update phone', 'update phone'], $this->channels->calls);
        $this->assertDatabaseHas('channels', ['code' => 'phone', 'name' => 'Hotline']);
    }

    #[Test]
    #[DefineEnvironment('enableCache')]
    public function it_serves_the_decisions_on_enum_keyed_rows_from_the_cache(): void
    {
        $this->seedChannels();

        $this->postJson('/api/channels/search', self::GATED_SEARCH)->assertOk();
        $this->postJson('/api/channels/search', self::GATED_SEARCH)
            ->assertOk()
            ->assertJsonPath('data.1.gates.authorized_to_update', true);

        $this->assertSame(['viewAny', 'view email', 'update email', 'view phone', 'update phone'], $this->channels->calls);
    }

    protected function enableCache(Application $app): void
    {
        $app['config']->set('rest.authorizations.cache.enabled', true);
    }

    private function seedChannels(): void
    {
        Channel::create(['code' => 'phone', 'name' => 'Phone']);
        Channel::create(['code' => 'email', 'name' => 'Email']);
    }
}
