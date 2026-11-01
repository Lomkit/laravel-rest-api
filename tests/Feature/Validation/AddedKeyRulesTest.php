<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\MinimumKeyDestroyRequest;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\MinimumKeyOperateRequest;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\Fixtures\Workspace\Workspace;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class AddedKeyRulesTest extends PackageTestCase
{
    use AssertsExactErrors;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive, new Workspace];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(DestroyRequest::class, MinimumKeyDestroyRequest::class);
        $this->app->bind(OperateRequest::class, MinimumKeyOperateRequest::class);
    }

    #[Test]
    public function it_applies_an_added_key_rule_to_the_keys_of_a_list_that_is_read(): void
    {
        Document::factory()->create(['id' => 1, 'title' => 'Annual report']);

        $response = $this->deleteJson('/api/documents', ['resources' => [1]]);

        $this->assertExactErrors($response, ['resources.0' => ['The key is too small.']]);
    }

    #[Test]
    public function it_applies_no_added_key_rule_under_a_list_over_its_limit(): void
    {
        config(['rest.limits.sizes.resources' => 1]);

        $response = $this->deleteJson('/api/documents', ['resources' => [1, 2]]);

        $this->assertExactErrors($response, ['resources' => ['The resources field must not have more than 1 items.']]);
    }

    #[Test]
    public function it_applies_no_added_key_rule_under_an_object_of_keys(): void
    {
        $response = $this->deleteJson('/api/documents', ['resources' => ['first' => 1]]);

        $this->assertExactErrors($response, ['resources' => ['The resources field must be a list.']]);
    }

    #[Test]
    public function it_applies_no_added_key_rule_under_the_keys_of_an_action_over_its_limit(): void
    {
        $response = $this->postJson('/api/team-notes/actions/pin-named-notes', ['resources' => [1, 2, 3, 4]]);

        $this->assertExactErrors($response, ['resources' => ['The resources field must not have more than 3 items.']]);
    }
}
