<?php

namespace Lomkit\Rest\Tests\Feature\Deletion;

use Illuminate\Database\Eloquent\Factories\Sequence;
use LogicException;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ResourceKeyChecksTest extends PackageTestCase
{
    use AssertsExactErrors;
    use CapturesQueries;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    /** @return array<string, array{string, string, ?string}> */
    public static function endpoints(): array
    {
        return [
            'destroy' => ['DELETE', '/api/documents', null],
            'restore' => ['POST', '/api/documents/restore', '2026-01-01 00:00:00'],
            'force destroy' => ['DELETE', '/api/documents/force', '2026-01-01 00:00:00'],
        ];
    }

    /** @return array<string, array{string, string, ?string, int}> */
    public static function endpointsAndCounts(): array
    {
        $cases = [];

        foreach (self::endpoints() as $endpoint => $row) {
            foreach ([1, 3, 40] as $count) {
                $cases[$endpoint.' with '.$count.' keys'] = [...$row, $count];
            }
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('endpointsAndCounts')]
    public function it_checks_the_keys_with_one_query(string $method, string $uri, ?string $deletedAt, int $count): void
    {
        $this->seedDocuments($count, $deletedAt);
        $this->captureQueries();

        $this->json($method, $uri, ['resources' => range(1, $count)])->assertOk();

        $this->assertSame([], $this->keyQueries('documents'));
        $this->assertCount(1, $this->readsOf('documents'));
        $this->assertStringStartsWith('select * from documents where ', $this->readsOf('documents')[0][0]);
        $this->assertSame([], $this->readsOf('documents')[0][1]);
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function it_refuses_repeated_keys(string $method, string $uri, ?string $deletedAt): void
    {
        $this->seedDocuments(1, $deletedAt);

        $response = $this->json($method, $uri, ['resources' => [1, '1']]);

        $this->assertExactErrors($response, [
            'resources.0' => ['The resources.0 field has a duplicate value.'],
            'resources.1' => ['The resources.1 field has a duplicate value.'],
        ]);
        $this->assertDatabaseHas('documents', ['id' => 1, 'deleted_at' => $deletedAt]);
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function it_refuses_an_object_of_keys(string $method, string $uri, ?string $deletedAt): void
    {
        $this->seedDocuments(1, $deletedAt);

        $response = $this->json($method, $uri, ['resources' => ['first' => 1]]);

        $this->assertExactErrors($response, ['resources' => ['The resources field must be a list.']]);
        $this->assertDatabaseHas('documents', ['id' => 1, 'deleted_at' => $deletedAt]);
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function it_refuses_more_keys_than_the_limit(string $method, string $uri, ?string $deletedAt): void
    {
        config(['rest.limits.sizes.resources' => 2]);
        $this->seedDocuments(3, $deletedAt);
        $this->captureQueries();

        $response = $this->json($method, $uri, ['resources' => [1, 2, 3]]);

        $this->assertExactErrors($response, ['resources' => ['The resources field must not have more than 2 items.']]);
        $this->assertSame([], $this->readsOf('documents'));
    }

    #[Test]
    public function it_gives_an_invalid_repeated_key_its_type_message_only(): void
    {
        $response = $this->deleteJson('/api/documents', ['resources' => ['one', 'one']]);

        $this->assertExactErrors($response, [
            'resources.0' => ['The resources.0 field must be an integer.'],
            'resources.1' => ['The resources.1 field must be an integer.'],
        ]);
    }

    #[Test]
    public function it_refuses_a_trashed_key_on_destroy(): void
    {
        $this->seedDocuments(1, '2026-01-01 00:00:00');

        $response = $this->deleteJson('/api/documents', ['resources' => [1]]);

        $this->assertExactErrors($response, ['resources.0' => ['The selected resources.0 is invalid.']]);
        $this->assertSame(1, Document::onlyTrashed()->count());
    }

    #[Test]
    public function it_refuses_to_read_the_keys_before_validation(): void
    {
        $this->expectExceptionObject(new LogicException('The resource keys were read before the request passed validation.'));

        (new DestroyRequest)->payload();
    }

    #[Test]
    public function it_refuses_to_read_parsed_keys_before_validation(): void
    {
        $this->seedDocuments(1, null);
        $request = DestroyRequest::create('/api/documents', 'DELETE', ['resources' => [1]])->setContainer($this->app);
        $route = $this->app['router']->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $request->rules();

        $this->expectExceptionObject(new LogicException('The resource keys were read before the request passed validation.'));

        $request->payload();
    }

    private function seedDocuments(int $count, ?string $deletedAt): void
    {
        Document::factory()
            ->count($count)
            ->sequence(fn (Sequence $sequence): array => ['id' => $sequence->index + 1])
            ->create(['title' => 'Annual report', 'deleted_at' => $deletedAt]);
    }
}
