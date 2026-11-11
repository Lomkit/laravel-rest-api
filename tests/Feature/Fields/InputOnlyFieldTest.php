<?php

namespace Lomkit\Rest\Tests\Feature\Fields;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Fields\Stubs\AddressedOrder;
use Lomkit\Rest\Tests\Feature\Fields\Stubs\AddressedOrderController;
use Lomkit\Rest\Tests\Feature\Fields\Stubs\AddressedOrderPolicy;
use Lomkit\Rest\Tests\Feature\Fields\Stubs\AddressedOrderResource;
use Lomkit\Rest\Tests\Fixtures\Sales\Sales;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.debug', false)]
class InputOnlyFieldTest extends PackageTestCase
{
    private const array ORDER = ['team_id' => 1, 'reference' => 'A-1002', 'price' => 12.5, 'quantity' => 1, 'gift' => true, 'placed_at' => '2026-11-10 09:00:00'];

    private int $orderKey;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Sales];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        AddressedOrderResource::$updatable = true;
        AddressedOrderResource::$hooks = [];
        AddressedOrderPolicy::$checks = [];
        Gate::policy(AddressedOrder::class, AddressedOrderPolicy::class);
        Rest::resource('addressed-orders', AddressedOrderController::class)->register();
        $this->travelTo('2026-11-01 09:00:00');
        $order = AddressedOrder::query()->create([
            'team_id' => 1,
            'reference' => 'A-1001',
            'price' => '20.00',
            'quantity' => 2,
            'gift' => false,
            'placed_at' => '2026-11-01 09:00:00',
        ]);
        $this->orderKey = (int) $order->getKey();
        $this->travelTo('2026-11-11 10:00:00');
    }

    #[Test]
    public function it_hands_an_input_only_value_to_the_hooks_without_writing_it(): void
    {
        $response = $this->postJson('/addressed-orders/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => [...self::ORDER, 'delivery_address' => '12 Quay Street']],
            ['operation' => 'update', 'key' => $this->orderKey, 'attributes' => ['quantity' => 3, 'delivery_address' => '4 Mill Lane']],
        ]]);

        $response->assertOk()->assertExactJson(['operations' => [['operation' => 'create', 'key' => $this->orderKey + 1], ['operation' => 'update', 'key' => $this->orderKey]]]);
        $this->assertSame([
            'mutating create {"team_id":1,"reference":"A-1002","price":12.5,"quantity":1,"gift":true,"placed_at":"2026-11-10 09:00:00","delivery_address":"12 Quay Street"}',
            'mutated create {"team_id":1,"reference":"A-1002","price":12.5,"quantity":1,"gift":true,"placed_at":"2026-11-10 09:00:00","delivery_address":"12 Quay Street"}',
            'mutating update {"quantity":3,"delivery_address":"4 Mill Lane"}',
            'mutated update {"quantity":3,"delivery_address":"4 Mill Lane"}',
        ], AddressedOrderResource::$hooks);
        $this->assertSame([
            ['reference' => 'A-1001', 'quantity' => 3, 'gift' => false, 'updated_at' => '2026-11-11 10:00:00'],
            ['reference' => 'A-1002', 'quantity' => 1, 'gift' => true, 'updated_at' => '2026-11-11 10:00:00'],
        ], self::orders());
    }

    #[Test]
    public function it_validates_an_input_only_value(): void
    {
        $response = $this->postJson('/addressed-orders/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => [...self::ORDER, 'delivery_address' => str_repeat('a', 121)]],
        ]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes.delivery_address' => ['The mutate.0.attributes.delivery_address field must not be greater than 120 characters.'],
        ]);
        $this->assertSame([], AddressedOrderResource::$hooks);
    }

    #[Test]
    public function it_refuses_an_input_only_value_where_the_field_is_not_writable(): void
    {
        AddressedOrderResource::$updatable = false;

        $response = $this->postJson('/addressed-orders/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->orderKey, 'attributes' => ['delivery_address' => '4 Mill Lane']],
        ]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes' => ['The delivery_address key of the mutate.0.attributes field is not writable on update.'],
        ]);
    }

    #[Test]
    public function it_runs_the_hooks_of_an_entry_that_only_sends_input_only_values_and_writes_no_column(): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/addressed-orders/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $this->orderKey, 'attributes' => ['delivery_address' => '4 Mill Lane']],
        ]]);

        $response->assertOk()->assertExactJson(['operations' => [['operation' => 'update', 'key' => $this->orderKey]]]);
        $this->assertSame([], array_values(array_filter(
            array_column(DB::getQueryLog(), 'query'),
            fn (string $query): bool => str_starts_with($query, 'update') || str_starts_with($query, 'insert'),
        )));
        $this->assertSame([
            'mutating update {"delivery_address":"4 Mill Lane"}',
            'mutated update {"delivery_address":"4 Mill Lane"}',
        ], AddressedOrderResource::$hooks);
        $this->assertSame([['reference' => 'A-1001', 'quantity' => 2, 'gift' => false, 'updated_at' => '2026-11-01 09:00:00']], self::orders());
    }

    #[Test]
    public function it_shows_input_only_values_to_the_update_policy(): void
    {
        $response = $this->postJson('/addressed-orders/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => [...self::ORDER, 'delivery_address' => '12 Quay Street']],
            ['operation' => 'update', 'key' => $this->orderKey, 'attributes' => ['quantity' => 3, 'delivery_address' => '4 Mill Lane']],
        ]]);

        $response->assertOk();
        $this->assertSame([
            'create {"team_id":1,"reference":"A-1002","price":12.5,"quantity":1,"gift":true,"placed_at":"2026-11-10 09:00:00"}',
            'update ["quantity","delivery_address"]',
        ], AddressedOrderPolicy::$checks);
    }

    #[Test]
    public function it_never_returns_an_input_only_field(): void
    {
        $response = $this->postJson('/addressed-orders/search', ['search' => []]);

        $response->assertOk();
        $this->assertSame(
            ['id', 'team_id', 'reference', 'status', 'channel', 'customer', 'price', 'quantity', 'rating', 'gift', 'placed_at', 'delivered_on', 'summary'],
            array_keys($response->json('data.0')),
        );
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('reads')]
    public function it_refuses_to_read_an_input_only_field(string $uri, array $body, string $key): void
    {
        $response = $this->postJson($uri, $body);

        $response->assertUnprocessable()->assertJsonPath('errors', [$key => ["The selected {$key} is invalid."]]);
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function reads(): array
    {
        $field = ['field' => 'delivery_address'];

        return [
            'select' => ['/addressed-orders/search', ['search' => ['selects' => [$field]]], 'search.selects.0.field'],
            'sort' => ['/addressed-orders/search', ['search' => ['sorts' => [$field]]], 'search.sorts.0.field'],
            'filter' => ['/addressed-orders/search', ['search' => ['filters' => [[...$field, 'value' => '4 Mill Lane']]]], 'search.filters.0.field'],
            'aggregate' => ['/addressed-orders/search', ['search' => ['aggregates' => [['relation' => 'teamOrders', 'type' => 'min', ...$field]]]], 'search.aggregates.0.field'],
            'mutate response' => [
                '/addressed-orders/mutate',
                ['mutate' => [['operation' => 'create', 'attributes' => self::ORDER]], 'response' => ['selects' => [$field]]],
                'response.selects.0.field',
            ],
        ];
    }

    #[Test]
    public function it_describes_an_input_only_field_in_details(): void
    {
        $response = $this->getJson('/addressed-orders?with=fields.capabilities,fields.filter,fields.rules,fields.meta');

        $response->assertOk();
        $this->assertSame(
            '{"name":"delivery_address","label":null,"type":"text","nullable":false,"select":false,"sort":false,"filter":{"operators":[]},"create":true,"update":true,"rules":{"all":["string","max:120"],"create":[],"update":[]},"meta":{}}',
            json_encode(json_decode((string) $response->getContent())->data->fields[13]),
        );
    }

    /** @return list<array<string, mixed>> */
    private static function orders(): array
    {
        return AddressedOrder::query()
            ->orderBy('id')
            ->get()
            ->map(fn (AddressedOrder $order): array => [
                'reference' => $order->getAttribute('reference'),
                'quantity' => (int) $order->getAttribute('quantity'),
                'gift' => $order->getAttribute('gift'),
                'updated_at' => $order->getAttribute('updated_at')?->toDateTimeString(),
            ])
            ->all();
    }
}
