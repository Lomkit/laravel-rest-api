<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\LabelledOperateRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RequestMessagesTest extends PackageTestCase
{
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(OperateRequest::class, LabelledOperateRequest::class);
    }

    #[Test]
    public function it_applies_the_request_messages_to_the_action_fields(): void
    {
        $response = $this->postJson('/api/posts/actions/change-status', ['fields' => []]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'Pick a status.',
            'errors' => ['fields.status' => ['Pick a status.']],
        ]);
    }

    #[Test]
    public function it_applies_the_request_attribute_names_to_the_action_fields(): void
    {
        $response = $this->postJson('/api/posts/actions/change-status', [
            'fields' => [['name' => 'status', 'value' => 'published'], ['name' => 'at', 'value' => 'soon']],
        ]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The publication date field must be a valid date.',
            'errors' => ['fields.at' => ['The publication date field must be a valid date.']],
        ]);
    }

    #[Test]
    public function it_keeps_the_package_messages_next_to_the_request_messages(): void
    {
        $response = $this->postJson('/api/posts/actions/change-status', [
            'search' => ['text' => ['value' => 'report']],
            'fields' => [['name' => 'status', 'value' => 'draft']],
        ]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'Text search is not available for actions.',
            'errors' => ['search.text' => ['Text search is not available for actions.']],
        ]);
    }
}
