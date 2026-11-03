<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Lomkit\Rest\Authorization\Ability;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Http\Serialization\RowSerializer;
use Lomkit\Rest\Search\GateAbility;
use Lomkit\Rest\Search\Plan\LimitPolicy;
use Lomkit\Rest\Search\Plan\QueryPlan;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

class GateTableTest extends PackageTestCase
{
    use SeedsPublishingRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_gives_the_answers_of_a_row_and_of_the_meta_as_objects(): void
    {
        $this->seedPublishingRows();
        $posts = (new RestContext(RestRequest::create('/api/posts/search', 'POST'), $this->app))->resource(PostResource::class);
        $rows = RowSerializer::of(new QueryPlan($posts, [], [], [], LimitPolicy::none(), ['id'], [new GateAbility(Ability::View), new GateAbility(Ability::Create)]));
        $allowed = ['allowed' => true, 'message' => null];

        $row = $rows->row(Post::query()->findOrFail(1));

        $this->assertEquals(['id' => 1, 'gates' => (object) ['authorized_to_view' => $allowed]], $row);
        $this->assertInstanceOf(stdClass::class, $row['gates']);
        $this->assertEquals(['gates' => (object) ['authorized_to_create' => $allowed]], $rows->meta());
    }
}
