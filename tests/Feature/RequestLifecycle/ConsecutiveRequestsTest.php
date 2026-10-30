<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle;

use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ConsecutiveRequestsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Accounts, new Catalog];
    }

    #[Test]
    public function it_answers_each_request_with_its_own_search(): void
    {
        $deskLamp = Product::factory()->create(['name' => 'Desk lamp', 'price' => 39]);
        $floorLamp = Product::factory()->create(['name' => 'Floor lamp', 'price' => 129]);

        $cheapProducts = $this->postJson('/api/products/search', [
            'search' => ['filters' => [['field' => 'price', 'operator' => '<', 'value' => 100]]],
        ]);
        $expensiveProducts = $this->postJson('/api/products/search', [
            'search' => ['filters' => [['field' => 'price', 'operator' => '>', 'value' => 100]]],
        ]);

        $cheapProducts->assertOk()->assertJsonPath('data', [
            ['id' => $deskLamp->id, 'category_id' => $deskLamp->category_id, 'name' => 'Desk lamp', 'price' => 39],
        ]);
        $expensiveProducts->assertOk()->assertJsonPath('data', [
            ['id' => $floorLamp->id, 'category_id' => $floorLamp->category_id, 'name' => 'Floor lamp', 'price' => 129],
        ]);
    }

    #[Test]
    public function it_computes_the_fields_for_each_user(): void
    {
        $deskLamp = Product::factory()->create(['name' => 'Desk lamp', 'price' => 39]);

        $staffResponse = $this->actingAs(User::factory()->staff()->create())
            ->postJson('/api/staff-products/search', ['search' => ['limit' => 10]]);
        $customerResponse = $this->actingAs(User::factory()->create())
            ->postJson('/api/staff-products/search', ['search' => ['limit' => 10]]);

        $staffResponse->assertOk()->assertJsonPath('data', [
            ['id' => $deskLamp->id, 'name' => 'Desk lamp', 'price' => 39],
        ]);
        $customerResponse->assertOk()->assertJsonPath('data', [
            ['id' => $deskLamp->id, 'name' => 'Desk lamp'],
        ]);
    }
}
