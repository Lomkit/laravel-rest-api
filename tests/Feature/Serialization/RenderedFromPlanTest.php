<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\RenderEditingPostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class RenderedFromPlanTest extends PackageTestCase
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
    public function it_renders_the_last_entry_of_a_relation_included_twice(): void
    {
        $response = $this->postJson('/api/authors/search', ['search' => [
            'includes' => [
                ['relation' => 'posts', 'selects' => [['field' => 'id']]],
                ['relation' => 'posts', 'selects' => [['field' => 'title']]],
            ],
            'selects' => [['field' => 'id']],
            'sorts' => [['field' => 'first_name']],
        ]]);

        $response->assertOk();
        $this->assertSame(
            '{"current_page":1,"data":[{"id":1,"posts":[{"title":"Notes"},{"title":"Engines"}]},{"id":2,"posts":[]}],"from":1,"last_page":1,"per_page":50,"to":2,"total":2,"meta":{}}',
            $response->getContent(),
        );
    }

    #[Test]
    public function it_renders_the_validated_search_when_searching_edits_the_input(): void
    {
        Rest::resource('render-editing-posts', RenderEditingPostController::class)->register();

        $response = $this->postJson('/render-editing-posts/search', ['search' => [
            'selects' => [['field' => 'id'], ['field' => 'title']],
            'includes' => [['relation' => 'author', 'selects' => [['field' => 'first_name']]]],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk();
        $this->assertSame(
            '{"current_page":1,"data":[{"id":1,"title":"Engines","author":{"first_name":"Ada"}},{"id":2,"title":"Notes","author":{"first_name":"Ada"}},{"id":3,"title":"Orphans","author":null}],"from":1,"last_page":1,"per_page":50,"to":3,"total":3,"meta":{}}',
            $response->getContent(),
        );
    }
}
