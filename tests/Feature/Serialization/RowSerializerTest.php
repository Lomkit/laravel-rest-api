<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Illuminate\Database\Eloquent\Collection;
use LogicException;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Http\Serialization\RowSerializer;
use Lomkit\Rest\Search\Plan\LimitPolicy;
use Lomkit\Rest\Search\Plan\QueryPlan;
use Lomkit\Rest\Tests\Feature\Query\Stubs\MinimumViewsClause;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RowSerializerTest extends PackageTestCase
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

    #[Test]
    public function it_refuses_a_clause_that_is_not_an_include(): void
    {
        $this->expectExceptionObject(new LogicException(MinimumViewsClause::class.' is not an include of a plan.'));

        RowSerializer::of(new QueryPlan($this->posts(), [], [new MinimumViewsClause], [], LimitPolicy::none()));
    }

    #[Test]
    public function it_refuses_a_loaded_relation_that_the_resource_does_not_declare(): void
    {
        $post = Post::query()->findOrFail(1);
        $post->setRelation('reviews', new Collection);

        $this->expectExceptionObject(new LogicException('The reviews relation of '.Post::class.' is not declared by '.PostResource::class.'.'));

        RowSerializer::of(QueryPlan::bare($this->posts()))->row($post);
    }

    #[Test]
    public function it_refuses_a_relation_that_holds_neither_a_model_nor_a_collection(): void
    {
        $post = Post::query()->findOrFail(1);
        $post->setRelation('author', 'Ada');

        $this->expectExceptionObject(new LogicException('The author relation of '.Post::class.' holds neither a model nor a collection.'));

        RowSerializer::of(QueryPlan::bare($this->posts()))->row($post);
    }

    private function posts(): Resource
    {
        return (new RestContext(RestRequest::create('/api/posts/search', 'POST'), $this->app))->resource(PostResource::class);
    }
}
