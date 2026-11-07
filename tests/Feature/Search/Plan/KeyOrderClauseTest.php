<?php

namespace Lomkit\Rest\Tests\Feature\Search\Plan;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Search\Plan\KeyOrderClause;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class KeyOrderClauseTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    /** @param Closure(): Builder $query */
    #[Test]
    #[DataProvider('queries')]
    public function it_ends_an_order_with_the_key_once(Closure $query, string $sql): void
    {
        $builder = $query();

        (new KeyOrderClause)->apply($builder);

        $this->assertSame($sql, str_replace(['"', '`'], '', $builder->toRawSql()));
    }

    /** @return array<string, array{Closure(): Builder, string}> */
    public static function queries(): array
    {
        return [
            'no order' => [static fn (): Builder => Post::query(), 'select * from posts order by posts.id desc'],
            'a raw order' => [static fn (): Builder => Post::query()->orderByRaw('views + 1'), 'select * from posts order by views + 1, posts.id desc'],
            'an order on the unqualified key' => [static fn (): Builder => Post::query()->orderBy('id'), 'select * from posts order by id asc'],
            'a relation' => [static fn (): Builder => (new Author(['id' => 3]))->posts()->orderBy('views'), 'select * from posts where posts.author_id = 3 and posts.author_id is not null order by views asc, posts.id desc'],
        ];
    }
}
