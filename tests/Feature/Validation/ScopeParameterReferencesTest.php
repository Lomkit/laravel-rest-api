<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\BoundedViewsPostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ScopeParameterReferencesTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('bounded-posts', BoundedViewsPostController::class)->register();
    }

    #[Test]
    public function it_compares_a_parameter_with_the_sibling_parameter_its_rule_names(): void
    {
        $response = $this->postJson('/bounded-posts/search', [
            'search' => ['scopes' => [['name' => 'viewedBetween', 'parameters' => [10, 5]]]],
        ]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.scopes.0.parameters.1 field must be greater than or equal to 10.',
            'errors' => [
                'search.scopes.0.parameters.1' => ['The search.scopes.0.parameters.1 field must be greater than or equal to 10.'],
            ],
        ]);
    }

    #[Test]
    public function it_ignores_a_root_key_with_the_name_of_the_sibling_parameter(): void
    {
        Post::factory()->create(['title' => 'Quiet post', 'views' => 3]);
        $read = Post::factory()->create(['title' => 'Read post', 'views' => 7]);
        Post::factory()->create(['title' => 'Viral post', 'views' => 5000]);

        $response = $this->postJson('/bounded-posts/search', [
            'search' => ['scopes' => [['name' => 'viewedBetween', 'parameters' => [5, 10]]]],
            'min' => 50,
        ]);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => $read->id, 'title' => 'Read post', 'views' => 7],
        ]);
    }

    #[Test]
    public function it_resolves_the_sibling_parameter_of_each_scope_entry(): void
    {
        $response = $this->postJson('/bounded-posts/search', [
            'search' => ['scopes' => [
                ['name' => 'viewedBetween', 'parameters' => [1, 100]],
                ['name' => 'viewedBetween', 'parameters' => [200, 150]],
            ]],
        ]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The search.scopes.1.parameters.1 field must be greater than or equal to 200.',
            'errors' => [
                'search.scopes.1.parameters.1' => ['The search.scopes.1.parameters.1 field must be greater than or equal to 200.'],
            ],
        ]);
    }
}
