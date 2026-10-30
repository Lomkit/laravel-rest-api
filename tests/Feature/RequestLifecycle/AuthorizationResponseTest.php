<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle;

use Illuminate\Auth\Access\Response;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class AuthorizationResponseTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[Test]
    public function it_forbids_a_request_whose_authorize_returns_a_denial(): void
    {
        $this->app->bind(SearchRequest::class, fn (): SearchRequest => new class extends SearchRequest
        {
            #[\Override]
            public function authorize(): bool|Response
            {
                return Response::deny('The archive is closed.');
            }
        });

        $this->postJson('/api/documents/search')
            ->assertForbidden()
            ->assertJsonPath('message', 'The archive is closed.');
    }

    #[Test]
    public function it_serves_a_request_whose_authorize_returns_an_approval(): void
    {
        $document = Document::factory()->create(['title' => 'Annual report']);
        $this->app->bind(SearchRequest::class, fn (): SearchRequest => new class extends SearchRequest
        {
            #[\Override]
            public function authorize(): bool|Response
            {
                return Response::allow();
            }
        });

        $this->postJson('/api/documents/search')->assertOk()->assertExactJson([
            'current_page' => 1,
            'data' => [
                ['id' => $document->id, 'title' => 'Annual report', 'archived' => false],
            ],
            'from' => 1,
            'last_page' => 1,
            'per_page' => 50,
            'to' => 1,
            'total' => 1,
            'meta' => [],
        ]);
    }
}
