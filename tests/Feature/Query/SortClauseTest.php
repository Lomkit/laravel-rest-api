<?php

namespace Lomkit\Rest\Tests\Feature\Query;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Query\Clauses\SortClause;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SortClauseTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_orders_by_the_qualified_column_of_the_field(): void
    {
        $query = Post::query();

        (new SortClause(Integer::make('views'), SortDirection::Desc))->apply($query);

        $this->assertSame('select * from posts order by posts.views desc', $this->sql($query));
    }

    #[Test]
    public function it_orders_in_ascending_order_by_default(): void
    {
        $query = Post::query();

        (new SortClause(Integer::make('views')))->apply($query);

        $this->assertSame('select * from posts order by posts.views asc', $this->sql($query));
    }

    #[Test]
    public function it_runs_the_custom_sort_of_the_field_instead_of_the_column_order(): void
    {
        $query = Post::query();
        $popularity = Integer::make('popularity')->virtual()->sortUsing(function (Builder $query, string $direction): void {
            $query->orderBy('featured', $direction)->orderBy('views', $direction);
        });

        (new SortClause($popularity, SortDirection::Desc))->apply($query);

        $this->assertSame('select * from posts order by featured desc, views desc', $this->sql($query));
    }

    #[Test]
    public function it_hands_the_custom_sort_the_eloquent_query_of_a_relation(): void
    {
        $query = Relation::noConstraints(fn (): HasMany => (new Author)->posts());
        $headline = Text::make('headline')->virtual()->sortUsing(function (EloquentBuilder $query, string $direction): void {
            $query->orderBy('title', $direction);
        });

        (new SortClause($headline, SortDirection::Desc))->apply($query);

        $this->assertSame('select * from posts order by title desc', $this->sql($query));
    }

    /** @param array{field: string, direction?: string|null} $sort */
    #[Test]
    #[DataProvider('sortEntries')]
    public function it_resolves_a_sort_entry_against_the_fields(array $sort, string $expected): void
    {
        $query = Post::query();
        $fields = FieldSet::of([Text::make('title'), Integer::make('views')]);

        SortClause::fromSort($fields, $sort)->apply($query);

        $this->assertSame($expected, $this->sql($query));
    }

    /** @return array<string, array{array{field: string, direction?: string|null}, string}> */
    public static function sortEntries(): array
    {
        return [
            'a direction' => [['field' => 'views', 'direction' => 'desc'], 'select * from posts order by posts.views desc'],
            'an upper case direction' => [['field' => 'views', 'direction' => 'DESC'], 'select * from posts order by posts.views desc'],
            'no direction' => [['field' => 'title'], 'select * from posts order by posts.title asc'],
            'a null direction' => [['field' => 'title', 'direction' => null], 'select * from posts order by posts.title asc'],
        ];
    }

    #[Test]
    public function it_refuses_a_sort_entry_on_an_undeclared_field(): void
    {
        $this->expectExceptionObject(new InvalidFieldException('Unknown field [nickname].'));

        SortClause::fromSort(FieldSet::of([Text::make('title')]), ['field' => 'nickname']);
    }

    #[Test]
    public function it_refuses_a_field_that_cannot_be_sorted(): void
    {
        $this->expectExceptionObject(new InvalidFieldException('The field [metadata] is not sortable.'));

        (new SortClause(Json::make('metadata')))->apply(Post::query());
    }

    private function sql(Builder $query): string
    {
        return str_replace(['"', '`'], '', $query->toSql());
    }
}
