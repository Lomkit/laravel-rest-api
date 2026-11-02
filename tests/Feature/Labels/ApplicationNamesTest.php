<?php

namespace Lomkit\Rest\Tests\Feature\Labels;

use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Labels\Stubs\LabelledPostController;
use Lomkit\Rest\Tests\Feature\Labels\Stubs\LabelledProductController;
use Lomkit\Rest\Tests\Feature\Labels\Stubs\NamedMutateRequest;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ApplicationNamesTest extends PackageTestCase
{
    use AssertsExactErrors;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog, new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        NamedMutateRequest::$names = [];
        NamedMutateRequest::$messages = [];
        $this->app->bind(MutateRequest::class, NamedMutateRequest::class);
        Category::factory()->create(['id' => 1, 'name' => 'Lighting']);
        Rest::resource('labelled-products', LabelledProductController::class)->register();
        Rest::resource('labelled-posts', LabelledPostController::class)->register();
    }

    #[Test]
    public function it_names_an_unlabelled_key_by_its_translation_line(): void
    {
        $this->app->make('translator')->load('*', 'validation', 'en');
        $this->app->make('translator')->addLines(['validation.attributes.mutate.*.attributes.category_id' => 'category'], 'en');

        $response = $this->postJson('/api/products/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['category_id' => 'x', 'name' => 'Floor lamp', 'price' => 10]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.attributes.category_id' => ['The category field must be an integer.']]);
    }

    #[Test]
    public function it_applies_a_custom_translation_line_to_an_action_field(): void
    {
        $this->app->make('translator')->load('*', 'validation', 'en');
        $this->app->make('translator')->addLines(['validation.custom.fields.status.required' => 'Choose a status.'], 'en');

        $response = $this->postJson('/api/posts/actions/change-status', ['fields' => []]);

        $this->assertExactErrors($response, ['fields.status' => ['Choose a status.']]);
    }

    #[Test]
    public function it_prefers_a_request_attribute_pattern_to_a_field_label(): void
    {
        NamedMutateRequest::$names = ['mutate.*.attributes.price' => 'price'];

        $this->assertExactErrors($this->twoProducts('x', 'y'), [
            'mutate.0.attributes.price' => ['The price field must be an integer.'],
            'mutate.1.attributes.price' => ['The price field must be an integer.'],
        ]);
    }

    #[Test]
    public function it_prefers_a_request_attribute_pattern_of_one_entry_to_a_field_label(): void
    {
        NamedMutateRequest::$names = ['mutate.0.attributes.*' => 'first value'];

        $this->assertExactErrors($this->twoProducts('x', 'y'), [
            'mutate.0.attributes.price' => ['The first value field must be an integer.'],
            'mutate.1.attributes.price' => ['The unit price field must be an integer.'],
        ]);
    }

    #[Test]
    public function it_prefers_a_request_attribute_name_of_one_key(): void
    {
        NamedMutateRequest::$names = ['mutate.1.attributes.price' => 'second price'];

        $this->assertExactErrors($this->twoProducts('x', 'y'), [
            'mutate.0.attributes.price' => ['The unit price field must be an integer.'],
            'mutate.1.attributes.price' => ['The second price field must be an integer.'],
        ]);
    }

    #[Test]
    public function it_prefers_the_request_message_of_one_key_to_the_field_message(): void
    {
        NamedMutateRequest::$messages = ['mutate.0.attributes.price.min' => 'Too cheap.'];

        $this->assertExactErrors($this->twoProducts(0, 0), [
            'mutate.0.attributes.price' => ['Too cheap.'],
            'mutate.1.attributes.price' => ['Give the unit price a positive value.'],
        ]);
    }

    #[Test]
    public function it_prefers_the_request_message_of_the_same_pattern_to_the_field_message(): void
    {
        NamedMutateRequest::$messages = ['mutate.*.attributes.price.min' => 'Too cheap.'];

        $this->assertExactErrors($this->twoProducts(0, 0), [
            'mutate.0.attributes.price' => ['Too cheap.'],
            'mutate.1.attributes.price' => ['Too cheap.'],
        ]);
    }

    #[Test]
    public function it_prefers_the_field_message_to_a_request_message_of_the_rule_alone(): void
    {
        NamedMutateRequest::$messages = ['min' => 'Too small.', 'integer' => 'Whole numbers only.'];

        $response = $this->postJson('/labelled-products/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['category_id' => 'x', 'name' => 'Floor lamp', 'price' => 0]],
        ]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.attributes.category_id' => ['Whole numbers only.'],
            'mutate.0.attributes.price' => ['Give the unit price a positive value.'],
        ]);
    }

    #[Test]
    public function it_prefers_a_request_message_of_the_key_alone_for_a_rule_object(): void
    {
        NamedMutateRequest::$messages = ['mutate.0.attributes.status' => 'Status not accepted.'];

        $response = $this->postJson('/labelled-posts/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['title' => 'Annual report', 'status' => 'archived', 'published_on' => '2026-02-01']],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.attributes.status' => ['Status not accepted.']]);
    }

    #[Test]
    public function it_prefers_the_field_message_to_a_custom_translation_line(): void
    {
        $this->app->make('translator')->load('*', 'validation', 'en');
        $this->app->make('translator')->addLines(['validation.custom.mutate.*.attributes.price.min' => 'Too low.'], 'en');

        $response = $this->postJson('/labelled-products/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Floor lamp', 'price' => 0]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.attributes.price' => ['Give the unit price a positive value.']]);
    }

    #[Test]
    public function it_names_a_field_by_its_label_before_a_translation_line(): void
    {
        $this->app->make('translator')->load('*', 'validation', 'en');
        $this->app->make('translator')->addLines(['validation.attributes.mutate.*.attributes.price' => 'tariff'], 'en');

        $response = $this->postJson('/labelled-products/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Floor lamp', 'price' => 'x']],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.attributes.price' => ['The unit price field must be an integer.']]);
    }

    private function twoProducts(mixed $first, mixed $second): TestResponse
    {
        return $this->postJson('/labelled-products/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Floor lamp', 'price' => $first]],
            ['operation' => 'create', 'attributes' => ['name' => 'Desk', 'price' => $second]],
        ]]);
    }
}
