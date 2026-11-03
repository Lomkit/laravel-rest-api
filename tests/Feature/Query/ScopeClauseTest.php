<?php

namespace Lomkit\Rest\Tests\Feature\Query;

use BadMethodCallException;
use Carbon\CarbonImmutable;
use Lomkit\Rest\Exceptions\InvalidScopeException;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\Clauses\ScopeClause;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Search\ScopeNode;
use Lomkit\Rest\Tests\Feature\Search\Plan\ParsesSearchNodes;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ScopeClauseTest extends PackageTestCase
{
    use ParsesSearchNodes;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_passes_the_cast_parameters_to_the_model_scope(): void
    {
        $query = Post::query();

        ScopeClause::of($this->scope('popular', ['150']))->apply($query);

        $this->assertSame([150], $query->getBindings());
    }

    #[Test]
    public function it_passes_the_field_default_for_a_missing_position(): void
    {
        $query = Post::query();

        ScopeClause::of($this->scope('popular'))->apply($query);

        $this->assertSame([100], $query->getBindings());
    }

    #[Test]
    public function it_passes_a_date_position_as_an_immutable_start_of_day(): void
    {
        $query = Post::query();

        ScopeClause::of($this->scope('publishedSince', ['2026-01-01 15:30:00']))->apply($query);

        [$since] = $query->getBindings();
        $this->assertInstanceOf(CarbonImmutable::class, $since);
        $this->assertSame('2026-01-01 00:00:00', $since->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_only_runs_scopes_the_model_declares(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('scopeLatest()');

        ScopeClause::of(new ScopeNode(Scope::make('latest'), InputValues::fromPositional(FieldSet::of([]), [])))->apply(Post::query());
    }

    #[Test]
    public function it_builds_the_scopes_once_per_request(): void
    {
        $resource = $this->postResource();
        $request = $resource->context()->request;

        $this->assertSame($resource->getScopes($request), $resource->getScopes($request));
        $this->assertSame('popular', $resource->scope($request, 'popular')?->name);
        $this->assertNull($resource->scope($request, 'archived'));
    }

    #[Test]
    public function it_refuses_a_scope_declared_as_a_string(): void
    {
        $resource = new class extends Resource
        {
            public static string $model = Post::class;

            #[\Override]
            public function scopes(RestRequest $request): array
            {
                return ['published'];
            }
        };

        $this->expectException(InvalidScopeException::class);
        $this->expectExceptionMessage($resource::class."::scopes() returned a string at index 0 ('published'). Return Scope objects, for example Scope::make('published').");

        $resource->getScopes($resource->context()->request);
    }

    /** @param list<mixed> $parameters */
    private function scope(string $name, array $parameters = []): ScopeNode
    {
        $entry = $parameters === [] ? ['name' => $name] : ['name' => $name, 'parameters' => $parameters];

        return $this->parse($this->postResource(), ['scopes' => [$entry]])->scopes[0];
    }

    private function postResource(): Resource
    {
        return (new RestContext(RestRequest::create('/api/posts/search', 'POST'), $this->app))->resource(PostResource::class);
    }
}
