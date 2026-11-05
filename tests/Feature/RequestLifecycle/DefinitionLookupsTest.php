<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle;

use Illuminate\Support\ItemNotFoundException;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\ResourceDefinition;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Tests\Fixtures\Publishing\HighlightPosts;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\Publishing\PublishPosts;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class DefinitionLookupsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_reads_the_definition_of_the_resource_in_its_context(): void
    {
        $context = new RestContext(RestRequest::create('/api/posts/search', 'POST'), $this->app);
        $resource = $context->resource(PostResource::class);

        $this->assertSame($context->definition($resource), $resource->definition());
    }

    #[Test]
    public function it_finds_a_scope_an_instruction_and_an_action_by_name(): void
    {
        $definition = $this->definition();

        $this->assertSame('popular', $definition->scope('popular')?->name);
        $this->assertNull($definition->scope('archived'));
        $this->assertInstanceOf(HighlightPosts::class, $definition->instruction('highlight-posts'));
        $this->assertNull($definition->instruction('archive-posts'));
        $this->assertTrue($definition->actionExists('publish-posts'));
        $this->assertFalse($definition->actionExists('archive-posts'));
        $this->assertInstanceOf(PublishPosts::class, $definition->action('publish-posts'));
    }

    #[Test]
    public function it_refuses_an_action_that_the_resource_does_not_declare(): void
    {
        $this->expectException(ItemNotFoundException::class);

        $this->definition()->action('archive-posts');
    }

    #[Test]
    #[DataProvider('relationPaths')]
    public function it_finds_a_relation_by_a_dotted_name(string $path, ?string $found): void
    {
        $this->assertSame($found, $this->definition()->relation($path)?->relation);
    }

    /** @return array<string, array{string, ?string}> */
    public static function relationPaths(): array
    {
        return [
            'a relation' => ['author', 'author'],
            'a relation of a relation' => ['author.posts', 'posts'],
            'two levels down' => ['author.posts.author', 'author'],
            'an unknown relation' => ['editor', null],
            'past an unknown relation' => ['editor.posts', null],
            'an unknown relation of a relation' => ['author.editor', null],
        ];
    }

    private function definition(): ResourceDefinition
    {
        return (new RestContext(RestRequest::create('/api/posts/search', 'POST'), $this->app))
            ->resource(PostResource::class)
            ->definition();
    }
}
