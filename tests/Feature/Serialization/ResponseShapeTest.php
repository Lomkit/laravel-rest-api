<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Search\Plan\QueryPlan;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ResponseShapeTest extends PackageTestCase
{
    use SeedsPublishingRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPublishingRows();
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('pages')]
    public function it_renders_a_page_of_rows(array $search, string $uri, string $json): void
    {
        $response = $this->postJson($uri, ['search' => $search]);

        $response->assertOk();
        $this->assertSame($json, $response->getContent());
    }

    /** @return array<string, array{array<string, mixed>, string, string}> */
    public static function pages(): array
    {
        return [
            'fields, an aggregate, gates and includes' => [
                [
                    'selects' => [['field' => 'id'], ['field' => 'first_name']],
                    'gates' => ['create', 'view'],
                    'aggregates' => [['relation' => 'posts', 'type' => 'count']],
                    'includes' => [
                        ['relation' => 'posts', 'selects' => [['field' => 'id'], ['field' => 'title']], 'sorts' => [['field' => 'id']]],
                        ['relation' => 'tags', 'selects' => [['field' => 'name']]],
                    ],
                    'sorts' => [['field' => 'first_name']],
                ],
                '/api/authors/search',
                '{"current_page":1,"data":[{"id":1,"first_name":"Ada","posts_count":2,"gates":{"authorized_to_view":true},"posts":[{"id":1,"title":"Engines"},{"id":2,"title":"Notes"}],"tags":[{"name":"Mathematics","pivot":[]}]},{"id":2,"first_name":"Grace","posts_count":0,"gates":{"authorized_to_view":true},"posts":[],"tags":[]}],"from":1,"last_page":1,"per_page":50,"to":2,"total":2,"meta":{"gates":{"authorized_to_create":false}}}',
            ],
            'a missing relation' => [
                [
                    'selects' => [['field' => 'id'], ['field' => 'title']],
                    'includes' => [['relation' => 'author', 'selects' => [['field' => 'last_name']]]],
                    'sorts' => [['field' => 'id']],
                ],
                '/api/posts/search',
                '{"current_page":1,"data":[{"id":1,"title":"Engines","author":{"last_name":"Lovelace"}},{"id":2,"title":"Notes","author":{"last_name":"Lovelace"}},{"id":3,"title":"Orphans","author":null}],"from":1,"last_page":1,"per_page":50,"to":3,"total":3,"meta":[]}',
            ],
        ];
    }

    #[Test]
    public function it_renders_the_rows_of_a_deletion(): void
    {
        $response = $this->deleteJson('/api/posts', ['resources' => [3]]);

        $response->assertOk();
        $this->assertSame(
            '{"data":[{"id":3,"title":"Orphans","summary":null,"status":"published","featured":false,"views":20,"rating":null,"published_on":"2026-01-01T00:00:00.000000Z","published_at":null,"metadata":null}],"meta":[]}',
            $response->getContent(),
        );
    }

    #[Test]
    public function it_renders_one_row(): void
    {
        $context = new RestContext(RestRequest::create('/api/posts', 'POST'), $this->app);
        $resource = $context->resource(PostResource::class);

        $response = PostResource::newResponse()
            ->plan(QueryPlan::bare($resource))
            ->responsable(Post::query()->findOrFail(1))
            ->toResponse($context->request);

        $this->assertSame(
            '{"data":{"id":1,"title":"Engines","summary":null,"status":"published","featured":false,"views":150,"rating":null,"published_on":"2026-01-01T00:00:00.000000Z","published_at":null,"metadata":null},"meta":[]}',
            json_encode($response),
        );
    }
}
