<?php

namespace Lomkit\Rest\Tests\Feature\Labels;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Labels\Stubs\LabelledPostController;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\LabelledOperateRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class OperateLabelsTest extends PackageTestCase
{
    use AssertsExactErrors;

    private const string LA = '/labelled-posts/actions/labelled-change-status';

    private const array PUBLISHED_SOON = ['search' => [], 'fields' => [['name' => 'status', 'value' => 'published'], ['name' => 'at', 'value' => 'soon']]];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('labelled-posts', LabelledPostController::class)->register();
    }

    #[Test]
    public function it_names_an_action_field_by_its_label(): void
    {
        $response = $this->postJson(self::LA, self::PUBLISHED_SOON);

        $this->assertExactErrors($response, ['fields.at' => ['The release date field must be a valid date.']]);
    }

    #[Test]
    public function it_words_an_action_field_by_its_message(): void
    {
        $response = $this->postJson(self::LA, ['search' => [], 'fields' => []]);

        $this->assertExactErrors($response, ['fields.status' => ['Choose a publication status.']]);
    }

    #[Test]
    public function it_names_the_other_field_of_a_rule_by_its_label(): void
    {
        $response = $this->postJson(self::LA, ['search' => [], 'fields' => [['name' => 'status', 'value' => 'published']]]);

        $this->assertExactErrors($response, ['fields.at' => ['The release date field is required when publication status is published.']]);
    }

    #[Test]
    public function it_prefers_the_request_messages_and_attribute_names(): void
    {
        $this->app->bind(OperateRequest::class, LabelledOperateRequest::class);

        $this->assertExactErrors($this->postJson(self::LA, ['search' => [], 'fields' => []]), ['fields.status' => ['Pick a status.']]);
        $this->assertExactErrors($this->postJson(self::LA, self::PUBLISHED_SOON), ['fields.at' => ['The publication date field must be a valid date.']]);
    }
}
