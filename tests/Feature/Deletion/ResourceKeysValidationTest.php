<?php

namespace Lomkit\Rest\Tests\Feature\Deletion;

use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ResourceKeysValidationTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    /** @param list<mixed> $resources */
    #[Test]
    #[DataProvider('invalidKeys')]
    public function it_rejects_keys_that_are_not_integers(string $method, string $uri, array $resources, string $message): void
    {
        $document = Document::factory()->create(['title' => 'Annual report']);
        $document->delete();

        $response = $this->json($method, $uri, ['resources' => $resources]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['resources.0' => [$message]]);
        $this->assertSoftDeleted($document);
    }

    /** @return array<string, array{string, string, list<mixed>, string}> */
    public static function invalidKeys(): array
    {
        $cases = [];

        foreach (self::endpoints() as $endpoint => [$method, $uri]) {
            $cases[$endpoint.' with a list as a key'] = [$method, $uri, [[1, 2]], 'The resources.0 field must be an integer.'];
            $cases[$endpoint.' with an object as a key'] = [$method, $uri, [['id' => 1]], 'The resources.0 field must be an integer.'];
            $cases[$endpoint.' with a word as a key'] = [$method, $uri, ['one'], 'The resources.0 field must be an integer.'];
            $cases[$endpoint.' with a null key'] = [$method, $uri, [null], 'The resources.0 field must be an integer.'];
        }

        return $cases;
    }

    #[Test]
    public function it_rejects_a_key_that_does_not_exist_on_destroy(): void
    {
        $document = Document::factory()->create(['title' => 'Annual report']);

        $response = $this->deleteJson('/api/documents', ['resources' => [999, $document->id]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'The selected resources.0 is invalid.',
            'errors' => ['resources.0' => ['The selected resources.0 is invalid.']],
        ]);
        $this->assertNotSoftDeleted($document);
    }

    #[Test]
    public function it_destroys_restores_and_force_destroys_by_key(): void
    {
        $document = Document::factory()->create(['title' => 'Annual report']);

        $this->deleteJson('/api/documents', ['resources' => [$document->id]])->assertOk();
        $this->assertSoftDeleted($document);

        $this->postJson('/api/documents/restore', ['resources' => [(string) $document->id]])->assertOk();
        $this->assertNotSoftDeleted($document);

        $this->deleteJson('/api/documents/force', ['resources' => [$document->id]])->assertOk();
        $this->assertModelMissing($document);
    }

    /** @return array<string, array{string, string}> */
    private static function endpoints(): array
    {
        return [
            'destroy' => ['DELETE', '/api/documents'],
            'restore' => ['POST', '/api/documents/restore'],
            'force destroy' => ['DELETE', '/api/documents/force'],
        ];
    }
}
