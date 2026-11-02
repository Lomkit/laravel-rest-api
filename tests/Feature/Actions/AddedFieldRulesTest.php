<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\RuledOperateRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class AddedFieldRulesTest extends PackageTestCase
{
    use AssertsExactErrors;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        RuledOperateRequest::$added = [];
        $this->app->bind(OperateRequest::class, RuledOperateRequest::class);
    }

    #[Test]
    public function it_meets_the_projected_field_names_with_a_fields_wildcard(): void
    {
        RuledOperateRequest::$added = ['fields.*' => ['array']];

        $response = $this->postJson('/api/posts/actions/publish-posts', ['fields' => [['name' => 'notify', 'value' => true]]]);

        $this->assertExactErrors($response, ['fields.notify' => ['The notify field must be an array.']]);
    }

    #[Test]
    public function it_keeps_the_rejection_of_an_unknown_entry_key_that_an_application_rule_names(): void
    {
        RuledOperateRequest::$added = ['fields.*.default' => ['boolean']];

        $response = $this->postJson('/api/posts/actions/publish-posts', ['fields' => [['name' => 'notify', 'value' => true, 'default' => false]]]);

        $this->assertExactErrors($response, ['fields.0.default' => ['The fields.0.default field is prohibited.']]);
    }

    #[Test]
    public function it_skips_a_size_rule_on_fields_that_the_payload_leaves_out(): void
    {
        RuledOperateRequest::$added = ['fields' => ['min:1']];

        $response = $this->postJson('/api/posts/actions/publish-posts', []);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0]]);
    }

    #[Test]
    public function it_fails_a_presence_rule_on_fields_that_the_payload_leaves_out(): void
    {
        RuledOperateRequest::$added = ['fields' => ['present']];

        $response = $this->postJson('/api/posts/actions/publish-posts', []);

        $this->assertExactErrors($response, ['fields' => ['The fields field must be present.']]);
    }

    #[Test]
    public function it_reports_the_entry_names_before_a_key_that_an_application_rule_adds(): void
    {
        RuledOperateRequest::$added = ['extra' => ['required']];
        $errors = ['fields.0.name' => ['The selected fields.0.name is invalid.'], 'extra' => ['The extra field is required.']];

        $response = $this->postJson('/api/posts/actions/publish-posts', ['fields' => [['name' => 'bogus', 'value' => 1]]]);

        $this->assertExactErrors($response, $errors);
        $response->assertJsonPath('errors', $errors);
    }
}
