<?php

namespace Lomkit\Rest\Tests\Feature\Mutations;

use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.precognition.enabled', true)]
class PrecognitiveWritesTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $expected
     */
    #[Test]
    #[DataProvider('refusedWrites')]
    public function it_gives_the_same_errors_with_and_without_precognition(array $attributes, array $expected): void
    {
        $payload = ['mutate' => [['operation' => 'create', 'attributes' => $attributes]]];

        $precognitive = $this->postJson('/api/posts/mutate', $payload, ['Precognition' => 'true']);
        $plain = $this->postJson('/api/posts/mutate', $payload);

        $precognitive->assertUnprocessable()->assertExactJson($expected);
        $plain->assertUnprocessable()->assertExactJson($expected);
        $this->assertSame(0, Post::query()->count());
    }

    /** @return array<string, array{array<string, mixed>, array<string, mixed>}> */
    public static function refusedWrites(): array
    {
        return [
            'an attribute that is not writable' => [
                ['id' => 5, 'title' => 'Annual report', 'status' => 'draft', 'published_on' => '2026-01-01'],
                [
                    'message' => 'The id attribute is not writable on create.',
                    'errors' => ['mutate.0.attributes' => ['The id attribute is not writable on create.']],
                ],
            ],
            'a value refused by a field rule' => [
                ['title' => 'Annual report', 'status' => 'draft', 'published_on' => '2026-01-01', 'views' => -1],
                [
                    'message' => 'The mutate.0.attributes.views field must be at least 0.',
                    'errors' => ['mutate.0.attributes.views' => ['The mutate.0.attributes.views field must be at least 0.']],
                ],
            ],
        ];
    }

    #[Test]
    public function it_accepts_writable_attributes_without_writing_them(): void
    {
        $response = $this->postJson('/api/posts/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['title' => 'Annual report', 'status' => 'draft', 'published_on' => '2026-01-01'],
        ]]], ['Precognition' => 'true']);

        $response->assertNoContent()->assertHeader('Precognition-Success', 'true');
        $this->assertSame(0, Post::query()->count());
    }
}
