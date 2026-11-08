<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ResponseBlockRulesTest extends PackageTestCase
{
    private Book $emma;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->emma = Book::query()->create(['title' => 'Emma']);
    }

    #[Test]
    #[DataProvider('refusedBlocks')]
    public function it_refuses_a_response_block_before_writing(mixed $block, string $key, string $message): void
    {
        $this->postJson('/api/books/mutate', ['mutate' => [['operation' => 'update', 'key' => $this->emma->id, 'attributes' => ['title' => 'Persuasion']]], 'response' => $block])
            ->assertUnprocessable()
            ->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);

        $this->assertSame('Emma', $this->emma->refresh()->title);
    }

    /** @return array<string, array{mixed, string, string}> */
    public static function refusedBlocks(): array
    {
        return [
            'a string' => ['all', 'response', 'The response field must be an array.'],
            'null' => [null, 'response', 'The response field must be an array.'],
            'filters' => [['filters' => [['field' => 'title', 'value' => 'Emma']]], 'response.filters', 'The response.filters field is prohibited.'],
            'sorts' => [['sorts' => [['field' => 'title']]], 'response.sorts', 'The response.sorts field is prohibited.'],
            'scopes' => [['scopes' => []], 'response.scopes', 'The response.scopes field is prohibited.'],
            'instructions' => [['instructions' => []], 'response.instructions', 'The response.instructions field is prohibited.'],
            'text' => [['text' => ['value' => 'Emma']], 'response.text', 'The response.text field is prohibited.'],
            'limit' => [['limit' => 10], 'response.limit', 'The response.limit field is prohibited.'],
            'page' => [['page' => 1], 'response.page', 'The response.page field is prohibited.'],
            'trashed next to an unknown key' => [['trashed' => 'with', 'select' => []], 'response.trashed', 'The response.trashed field is prohibited.'],
            'the create gate' => [['gates' => ['create']], 'response.gates.0', 'The response.gates.0 field is prohibited.'],
            'the create gate of an include' => [['includes' => [['relation' => 'labels', 'gates' => ['create']]]], 'response.includes.0.gates.0', 'The response.includes.0.gates.0 field is prohibited.'],
            'an unknown gate' => [['gates' => ['viewAny']], 'response.gates.0', 'The selected response.gates.0 is invalid.'],
            'an unknown relation' => [['includes' => [['relation' => 'author']]], 'response.includes.0.relation', 'The response.includes.0.relation field is not a valid or allowed relation for this resource.'],
            'an unknown field' => [['selects' => [['field' => 'isbn']]], 'response.selects.0.field', 'The selected response.selects.0.field is invalid.'],
        ];
    }

    #[Test]
    public function it_reads_a_response_block_that_holds_an_unknown_key_as_the_block_alone(): void
    {
        $mutate = [['operation' => 'update', 'key' => $this->emma->id, 'attributes' => ['title' => 'Persuasion']]];

        $expected = $this->postJson('/api/books/mutate', ['mutate' => $mutate, 'response' => ['selects' => [['field' => 'title']]]])->assertOk();
        $response = $this->postJson('/api/books/mutate', ['mutate' => $mutate, 'response' => ['selects' => [['field' => 'title', 'as' => 'name']], 'select' => [['field' => 'id']]]]);

        $response->assertOk()->assertExactJson($expected->json());
        $this->assertSame([['title' => 'Persuasion']], $response->json('records'));
    }
}
