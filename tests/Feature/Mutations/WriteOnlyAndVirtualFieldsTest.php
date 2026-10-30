<?php

namespace Lomkit\Rest\Tests\Feature\Mutations;

use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\OpenPublishingPolicy;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class WriteOnlyAndVirtualFieldsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(Author::class, OpenPublishingPolicy::class);
    }

    #[Test]
    public function it_writes_a_write_only_field_and_never_returns_it(): void
    {
        $response = $this->mutate([
            'operation' => 'create',
            'attributes' => ['first_name' => 'Grace', 'last_name' => 'Hopper', 'api_token' => 'grace-token'],
        ]);

        $grace = Author::query()->sole();
        $response->assertOk()->assertExactJson(['created' => [$grace->id], 'updated' => []]);
        $this->assertSame('grace-token', $grace->api_token);
        $this->postJson('/api/authors/search')->assertOk()->assertJsonPath('data', [
            ['id' => $grace->id, 'first_name' => 'Grace', 'last_name' => 'Hopper', 'settings' => null],
        ]);
    }

    #[Test]
    public function it_writes_a_virtual_field_through_its_fill_callback_on_create(): void
    {
        $response = $this->mutate([
            'operation' => 'create',
            'attributes' => ['full_name' => 'Grace Hopper', 'api_token' => 'grace-token'],
        ]);

        $grace = Author::query()->sole();
        $response->assertOk()->assertExactJson(['created' => [$grace->id], 'updated' => []]);
        $this->assertSame(['Grace', 'Hopper'], [$grace->first_name, $grace->last_name]);
    }

    #[Test]
    public function it_writes_a_virtual_field_through_its_fill_callback_on_update(): void
    {
        $ada = Author::factory()->create();

        $response = $this->mutate([
            'operation' => 'update',
            'key' => $ada->id,
            'attributes' => ['full_name' => 'Grace Hopper'],
        ]);

        $response->assertOk()->assertExactJson(['created' => [], 'updated' => [$ada->id]]);
        $this->assertSame(['Grace', 'Hopper'], [$ada->fresh()?->first_name, $ada->fresh()?->last_name]);
    }

    #[Test]
    public function it_refuses_a_virtual_field_without_a_fill_callback(): void
    {
        $response = $this->mutate([
            'operation' => 'create',
            'attributes' => ['first_name' => 'Grace', 'last_name' => 'Hopper', 'api_token' => 'grace-token', 'initials' => 'GH'],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes' => ['The initials attribute is not writable on create.'],
        ]);
        $this->assertSame(0, Author::query()->count());
    }

    /** @param array<string, mixed> $mutation */
    private function mutate(array $mutation): TestResponse
    {
        return $this->postJson('/api/authors/mutate', ['mutate' => [$mutation]]);
    }
}
