<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.precognition.enabled', true)]
class PrecognitionOperateTest extends PackageTestCase
{
    use AssertsExactErrors;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_validates_the_action_fields_under_a_validate_only_name(): void
    {
        $response = $this->precognitiveOperation('change-status', ['fields' => [['name' => 'status', 'value' => 'archived']]], 'fields');

        $this->assertExactErrors($response, ['fields.status' => ['The selected status is invalid.']]);
    }

    #[Test]
    public function it_validates_one_action_field_by_its_name(): void
    {
        $response = $this->precognitiveOperation('change-status', ['fields' => [['name' => 'pinned', 'value' => true]]], 'fields.status');

        $this->assertExactErrors($response, ['fields.status' => ['The status field is required.']]);
    }

    #[Test]
    public function it_reports_fields_that_are_not_a_list_to_an_action_field_name(): void
    {
        $response = $this->precognitiveOperation('change-status', ['fields' => ['status' => 'archived']], 'fields.status');

        $this->assertExactErrors($response, ['fields' => ['The fields field must be a list.']]);
    }

    #[Test]
    public function it_reports_a_repeated_entry_name_to_its_action_field(): void
    {
        $response = $this->precognitiveOperation('change-status', ['fields' => [
            ['name' => 'status', 'value' => 'draft'],
            ['name' => 'status', 'value' => 'published'],
        ]], 'fields.status');

        $this->assertExactErrors($response, [
            'fields.0.name' => ['The fields.0.name field has a duplicate value.'],
            'fields.1.name' => ['The fields.1.name field has a duplicate value.'],
        ]);
    }

    #[Test]
    public function it_leaves_the_action_fields_out_of_another_validate_only_name(): void
    {
        $post = Post::factory()->create(['id' => 1, 'title' => 'Annual report', 'status' => PostStatus::Draft]);

        $response = $this->precognitiveOperation('change-status', [
            'search' => ['filters' => [['field' => 'title', 'value' => 'Annual report']]],
            'fields' => [['name' => 'status', 'value' => 'archived']],
        ], 'search');

        $response->assertNoContent()->assertHeader('Precognition-Success', 'true');
        $this->assertSame(PostStatus::Draft, $post->fresh()?->status);
    }

    #[Test]
    public function it_reports_a_page_in_the_search_of_an_action(): void
    {
        $response = $this->precognitiveOperation('publish-posts', ['search' => ['page' => 2]], 'search');

        $this->assertExactErrors($response, ['search.page' => ['The search.page field is prohibited.']]);
    }

    #[Test]
    public function it_requires_the_search_of_an_action_under_its_validate_only_name(): void
    {
        $response = $this->precognitiveOperation('publish-posts', ['fields' => [['name' => 'notify', 'value' => false]]], 'search');

        $this->assertExactErrors($response, ['search' => ['The search field is required.']]);
    }

    #[Test]
    public function it_requires_the_search_of_an_action_without_a_validate_only_name(): void
    {
        $response = $this->postJson('/api/posts/actions/publish-posts', ['fields' => [['name' => 'notify', 'value' => false]]], ['Precognition' => 'true']);

        $this->assertExactErrors($response, ['search' => ['The search field is required.']]);
    }

    #[Test]
    public function it_accepts_an_empty_search_under_its_validate_only_name(): void
    {
        $response = $this->precognitiveOperation('publish-posts', ['search' => (object) []], 'search');

        $response->assertNoContent()->assertHeader('Precognition-Success', 'true');
    }

    /** @param array<string, mixed> $body */
    private function precognitiveOperation(string $action, array $body, string $validateOnly): TestResponse
    {
        return $this->postJson("/api/posts/actions/{$action}", $body, [
            'Precognition' => 'true',
            'Precognition-Validate-Only' => $validateOnly,
        ]);
    }
}
