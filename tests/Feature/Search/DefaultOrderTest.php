<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Stubs\KeyOrderedChannelController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Channel;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class DefaultOrderTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk];
    }

    #[Test]
    public function it_orders_the_rows_by_the_key_of_the_model_in_descending_order_without_sorts(): void
    {
        Rest::resource('key-ordered-channels', KeyOrderedChannelController::class)->register();
        Channel::create(['code' => 'email', 'name' => 'Email']);
        Channel::create(['code' => 'phone', 'name' => 'Phone']);

        $response = $this->postJson('/key-ordered-channels/search');

        $response->assertOk()
            ->assertJsonPath('data.*.code', ['phone', 'email'])
            ->assertJsonPath('data.*.name', ['Phone', 'Email']);
    }
}
