<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Feature\Text\Stubs\ContainsPostResource;
use Lomkit\Rest\Tests\Feature\Text\Stubs\MisindexedPostResource;
use Lomkit\Rest\Tests\Feature\Text\Stubs\StrictFullTextPostResource;
use Lomkit\Rest\Tests\Feature\Text\Stubs\UnsearchablePostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Text\Contains;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Text\TextTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TextDeclarationTest extends TextSearchTestCase
{
    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('searches')]
    public function it_refuses_a_full_text_search_without_fallback_on_a_database_without_full_text(array $search): void
    {
        if ($this->runsFullText()) {
            $this->markTestSkipped('The database runs full-text searches.');
        }

        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::unsupportedDriver(StrictFullTextPostResource::class, 'sqlite'));

        $this->search('strict-full-text-posts', $search);
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('searches')]
    public function it_refuses_a_full_text_index_on_a_field_that_is_not_searchable(array $search): void
    {
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::notSearchable(MisindexedPostResource::class, 'status'));

        $this->search('misindexed-posts', $search);
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('searches')]
    public function it_refuses_a_text_search_without_searchable_field(array $search): void
    {
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::noSearchableField(UnsearchablePostResource::class));

        $this->search('unsearchable-posts', $search);
    }

    #[Test]
    public function it_refuses_a_relevance_order_from_a_strategy_that_does_not_rank(): void
    {
        $target = new TextTarget(ContainsPostResource::class, FieldSet::of([Text::make('title')->searchable()]), new Post);

        $this->expectExceptionObject(InvalidTextSearchException::noRelevance(ContainsPostResource::class));

        Contains::make()->orderByRelevance(Post::query(), new TextQuery('lamp'), $target, SortDirection::Desc);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function searches(): array
    {
        return [
            'a text' => [['text' => ['value' => 'lamp']]],
            'no text' => [[]],
        ];
    }
}
