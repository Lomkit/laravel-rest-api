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

    /** @param array<string, mixed> $body */
    private function precognitiveOperation(string $action, array $body, string $validateOnly): TestResponse
    {
        return $this->postJson("/api/posts/actions/{$action}", $body, [
            'Precognition' => 'true',
            'Precognition-Validate-Only' => $validateOnly,
        ]);
    }
}
