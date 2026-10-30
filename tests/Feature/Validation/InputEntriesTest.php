<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Validation\Validator;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\PayloadPostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class InputEntriesTest extends PackageTestCase
{
    private ReceivedFields $received;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        Rest::resource('payload-posts', PayloadPostController::class)->register();
    }

    #[Test]
    public function it_accepts_an_object_value_that_holds_a_name_key(): void
    {
        $response = $this->postJson('/payload-posts/actions/attach-payload', ['fields' => [
            ['name' => 'reason', 'value' => 'spam'],
            ['name' => 'payload', 'value' => ['name' => 'Report', 'size' => 2]],
        ]]);

        $response->assertOk();
        $this->assertSame([['handle', ['reason' => 'spam', 'payload' => ['name' => 'Report', 'size' => 2]]]], $this->received->calls());
    }

    #[Test]
    public function it_accepts_an_object_value_that_holds_the_name_of_another_input(): void
    {
        $response = $this->postJson('/payload-posts/actions/attach-payload', ['fields' => [
            ['name' => 'payload', 'value' => ['name' => 'reason']],
            ['name' => 'reason', 'value' => 'spam'],
        ]]);

        $response->assertOk();
        $this->assertSame([['handle', ['reason' => 'spam', 'payload' => ['name' => 'reason']]]], $this->received->calls());
    }

    #[Test]
    public function it_accepts_an_instruction_value_that_holds_a_name_key(): void
    {
        $response = $this->postJson('/payload-posts/search', ['search' => ['instructions' => [
            ['name' => 'with-payload', 'fields' => [['name' => 'payload', 'value' => ['name' => 'Report']]]],
        ]]]);

        $response->assertOk();
        $this->assertSame([['handle', ['payload' => ['name' => 'Report']]]], $this->received->calls());
    }

    #[Test]
    public function it_requires_the_action_fields_as_a_list(): void
    {
        $response = $this->postJson('/payload-posts/actions/attach-payload', ['fields' => [
            'reason' => ['name' => 'reason', 'value' => 'spam'],
        ]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The fields field must be a list.',
            'errors' => ['fields' => ['The fields field must be a list.']],
        ]);
        $this->assertSame([], $this->received->calls());
    }

    #[Test]
    public function it_requires_the_instruction_fields_as_a_list(): void
    {
        $response = $this->postJson('/payload-posts/search', ['search' => ['instructions' => [
            ['name' => 'with-payload', 'fields' => ['payload' => ['name' => 'payload', 'value' => ['size' => 2]]]],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.instructions.0.fields' => ['The search.instructions.0.fields field must be a list.'],
        ]);
    }

    #[Test]
    public function it_validates_the_input_names_on_explicit_keys(): void
    {
        $keys = [];
        $this->app['validator']->resolver(function (Translator $translator, array $data, array $rules, array $messages, array $attributes) use (&$keys): Validator {
            $keys = [...$keys, ...array_keys($rules)];

            return new Validator($translator, $data, $rules, $messages, $attributes);
        });

        $this->postJson('/payload-posts/actions/attach-payload', ['fields' => [['name' => 'reason', 'value' => 'spam']]])->assertOk();
        $this->postJson('/payload-posts/search', ['search' => ['instructions' => [
            ['name' => 'with-payload', 'fields' => [['name' => 'payload', 'value' => ['size' => 2]]]],
        ]]])->assertOk();

        $this->assertContains('fields.0.name', $keys);
        $this->assertContains('search.instructions.0.fields.0.name', $keys);
        $this->assertSame([], array_values(array_filter($keys, fn (string $key): bool => str_contains($key, '*'))));
    }
}
