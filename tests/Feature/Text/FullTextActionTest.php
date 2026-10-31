<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use PDOException;
use PHPUnit\Framework\Attributes\Test;

class FullTextActionTest extends FullTextTestCase
{
    #[Test]
    public function it_acts_only_on_the_rows_that_match_a_full_text_search(): void
    {
        $this->createPost('Desk lamp', 'Brass shade', ['views' => 10]);
        $this->createPost('Glass vase', 'Hand blown glass', ['views' => 5]);
        $this->createPost('Floor lamp', 'Tall lamp', ['views' => 200]);
        $this->createPost('Lamp oil', 'Clear oil', ['views' => 5]);

        $response = $this->postJson('/full-text-posts/actions/publish-posts', [
            'search' => ['text' => ['value' => 'lamp'], 'filters' => [['field' => 'views', 'operator' => '<', 'value' => 100]]],
        ]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2]]);
        $this->assertSame([PostStatus::Published, PostStatus::Draft, PostStatus::Draft, PostStatus::Published], $this->statuses());
    }

    #[Test]
    public function it_keeps_an_action_with_text_inside_the_perimeter_of_the_resource(): void
    {
        $this->createPost('Desk lamp', 'Brass', ['featured' => true]);
        $this->createPost('Floor lamp', 'Tall', ['views' => 5]);
        $this->createPost('Glass vase', 'Blown', ['featured' => true]);
        $this->createPost('Lamp oil', 'Clear', ['views' => 150]);

        $response = $this->postJson('/perimeter-posts/actions/publish-posts', ['search' => ['text' => ['value' => 'lamp']]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2]]);
        $this->assertSame([PostStatus::Published, PostStatus::Draft, PostStatus::Draft, PostStatus::Published], $this->statuses());
    }

    #[Test]
    public function it_names_the_index_group_that_has_no_full_text_index_in_an_action(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Only MySQL and MariaDB need a FULLTEXT index for each group.');
        }

        $this->createPost('Desk lamp', 'Brass');
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::missingFullTextIndex('posts', [['summary']], new QueryException('mysql', '', [], new PDOException)));

        $this->postJson('/unindexed-full-text-posts/actions/publish-posts', ['search' => ['text' => ['value' => 'lamp']]]);
    }

    /** @return list<PostStatus> */
    private function statuses(): array
    {
        return Post::query()
            ->orderBy('id')
            ->pluck('status')
            ->all();
    }
}
