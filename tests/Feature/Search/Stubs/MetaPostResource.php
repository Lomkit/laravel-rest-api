<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Tests\Fixtures\Publishing\HighlightPosts;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class MetaPostResource extends PostResource
{
    /** @var array<string, mixed> */
    public static array $meta = [];

    /** @var list<string> */
    public static array $events = [];

    #[\Override]
    public function instructions(): array
    {
        return [Noted::make(), Popular::make(), HighlightPosts::make()];
    }

    #[\Override]
    public function searched(LengthAwarePaginator $page): void
    {
        self::$events[] = 'searched';
    }

    #[\Override]
    public function searchMeta(SearchContext $search, LengthAwarePaginator $page): array
    {
        self::$events[] = 'searchMeta';

        return [
            ...self::$meta,
            'views' => array_sum(array_map(fn (Post $post): int => $post->views, $page->items())),
            'total' => $page->total(),
            'titled' => $search->filtersOn('title'),
        ];
    }
}
