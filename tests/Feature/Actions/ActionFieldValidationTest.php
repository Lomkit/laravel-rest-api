<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ActionFieldValidationTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('invalidFields')]
    public function it_reports_the_field_errors_under_their_keys(string $action, array $payload, array $errors): void
    {
        $post = Post::factory()->create(['status' => PostStatus::Draft]);

        $response = $this->postJson("/api/posts/actions/{$action}", $payload);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
        $this->assertSame(PostStatus::Draft, $post->fresh()?->status);
    }

    /** @return array<string, array{string, array<string, mixed>, array<string, list<string>>}> */
    public static function invalidFields(): array
    {
        return [
            'a value breaking the field type' => ['publish-posts', ['fields' => [['name' => 'at', 'value' => 'soon']]], [
                'fields.at' => ['The at field must be a valid date.'],
            ]],
            'an explicit null on a field that is not nullable' => ['publish-posts', ['fields' => [['name' => 'notify', 'value' => null]]], [
                'fields.notify' => ['The notify field must be true or false.'],
            ]],
            'an unknown field name' => ['publish-posts', ['fields' => [['name' => 'pinned', 'value' => true]]], [
                'fields.0.name' => ['The selected fields.0.name is invalid.'],
            ]],
            'a field name that is not a string' => ['publish-posts', ['fields' => [['name' => ['at'], 'value' => '2026-03-01']]], [
                'fields.0.name' => ['The fields.0.name field must be a string.', 'The selected fields.0.name is invalid.'],
            ]],
            'a missing required field' => ['change-status', ['fields' => []], [
                'fields.status' => ['The status field is required.'],
            ]],
            'a missing required field without the fields key' => ['change-status', [], [
                'fields.status' => ['The status field is required.'],
            ]],
            'an unknown field name next to a missing required field' => ['change-status', ['fields' => [['name' => 'bogus', 'value' => 1]]], [
                'fields.status' => ['The status field is required.'],
                'fields.0.name' => ['The selected fields.0.name is invalid.'],
            ]],
            'an entry without a name' => ['publish-posts', ['fields' => [['value' => true]]], [
                'fields.0.name' => ['The fields.0.name field is required.'],
            ]],
            'an entry that is not an object' => ['publish-posts', ['fields' => ['notify']], [
                'fields.0.name' => ['The fields.0.name field is required.'],
            ]],
            'a field name given twice' => ['publish-posts', ['fields' => [['name' => 'notify', 'value' => true], ['name' => 'notify', 'value' => false]]], [
                'fields.0.name' => ['The fields.0.name field has a duplicate value.'],
                'fields.1.name' => ['The fields.1.name field has a duplicate value.'],
            ]],
            'a search that is not an array' => ['publish-posts', ['search' => 'drafts'], [
                'search' => ['The search field must be an array.'],
            ]],
            'a required_if condition met by another field' => ['change-status', ['fields' => [['name' => 'status', 'value' => 'published']]], [
                'fields.at' => ['The at field is required when status is published.'],
            ]],
        ];
    }

    #[Test]
    public function it_reports_a_field_error_with_its_message(): void
    {
        Post::factory()->create();

        $response = $this->postJson('/api/posts/actions/publish-posts', ['fields' => [['name' => 'at', 'value' => 'soon']]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The at field must be a valid date.',
            'errors' => [
                'fields.at' => ['The at field must be a valid date.'],
            ],
        ]);
    }

    #[Test]
    public function it_accepts_a_required_if_condition_that_is_satisfied(): void
    {
        $post = Post::factory()->create(['status' => PostStatus::Draft]);

        $response = $this->postJson('/api/posts/actions/change-status', ['fields' => [
            ['name' => 'status', 'value' => 'published'],
            ['name' => 'at', 'value' => '2026-03-01 09:30:00'],
        ]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 1]]);
        $this->assertSame(PostStatus::Published, $post->fresh()?->status);
    }

    #[Test]
    public function it_does_not_require_a_field_whose_required_if_condition_is_not_met(): void
    {
        $post = Post::factory()->create(['status' => PostStatus::Draft]);

        $response = $this->postJson('/api/posts/actions/change-status', ['fields' => [['name' => 'status', 'value' => 'review']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 1]]);
        $this->assertSame(PostStatus::Review, $post->fresh()?->status);
    }
}
